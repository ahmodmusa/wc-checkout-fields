<?php
/**
 * Admin order display.
 *
 * @package SCFW\Admin
 */

declare(strict_types=1);

namespace SCFW\Admin;

use SCFW\Core\Field\Field;
use SCFW\Core\Field\FieldDestination;
use SCFW\Core\Field\FieldRepository;
use SCFW\Core\Field\FieldType;
use SCFW\Core\Validation\Validator;
use SCFW\Services\OrderFieldPresentation;
use WC_Order;


/**
 * Displays and allows inline editing of saved checkout field values on the WooCommerce admin order screen.
 *
 * The saver writes every checkout field value to WooCommerce order meta under
 * the _scfw_ prefix. This class renders those values on the order edit screen
 * and provides inline editing with field-type appropriate controls.
 *
 * It reads and persists only through the WooCommerce order object (get_meta / update_meta_data / delete_meta_data)
 * so the same code works with HPOS and the legacy post storage without direct SQL.
 *
 * @since 0.0.1
 */
final class OrderDisplay {

	/**
	 * Nonce action for saving order fields.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'scfw_save_order_fields';

	/**
	 * Nonce name in POST request.
	 *
	 * @var string
	 */
	public const NONCE_NAME = '_scfw_order_fields_nonce';

	/**
	 * Order meta key prefix for saved checkout field values.
	 *
	 * @var string
	 */
	private const META_PREFIX = '_scfw_';

	/**
	 * Register the admin order display and save hooks.
	 *
	 * The display hook fires inside the WooCommerce order data meta box after the columns
	 * on the order edit screen. The process hook saves meta on order update.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( self::class, 'render' ), 10, 1 );
		add_action( 'woocommerce_process_shop_order_meta', array( self::class, 'save' ), 10, 2 );
	}

	/**
	 * Render the saved checkout field values and inline edit controls on the admin order screen.
	 *
	 * The section only renders when the current user can manage orders. Every
	 * dynamic value is escaped before output.
	 *
	 * @since 0.0.1
	 *
	 * @param WC_Order $order WooCommerce order.
	 *
	 * @return void
	 */
	public static function render( WC_Order $order ): void {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$rows = OrderFieldPresentation::rowsFor( $order, FieldDestination::ADMIN_ORDER );

		// If no fields have saved values, check if any configured fields are assigned to ADMIN_ORDER.
		// If nothing is configured or saved, omit section.
		$editable_fields = self::editableFieldsFor( $order );

		if ( empty( $rows ) && empty( $editable_fields ) ) {
			return;
		}

		self::output( $order, $rows, $editable_fields );
	}

	/**
	 * Save updated checkout field values from the order admin screen.
	 *
	 * Hooked to `woocommerce_process_shop_order_meta`.
	 *
	 * @since 0.0.1
	 *
	 * @param int            $order_id Order ID.
	 * @param WC_Order|mixed $order    Order or post object.
	 *
	 * @return void
	 */
	public static function save( int $order_id, mixed $order = null ): void {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		if ( empty( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field type in loop.
		$post_data = isset( $_POST['scfw_fields'] ) && is_array( $_POST['scfw_fields'] ) ? wp_unslash( $_POST['scfw_fields'] ) : array();
		$validator = new Validator();

		foreach ( FieldRepository::get() as $field ) {
			$field_id = $field->getId();
			$meta_key = self::META_PREFIX . $field_id;

			// Check if field was submitted in the form.
			if ( ! array_key_exists( $field_id, $post_data ) ) {
				// For checkbox, missing from POST means unchecked.
				if ( FieldType::CHECKBOX === $field->getType() ) {
					$order->delete_meta_data( $meta_key );
				}
				continue;
			}

			$raw_value = $post_data[ $field_id ];

			// Handle checkbox.
			if ( FieldType::CHECKBOX === $field->getType() ) {
				if ( '1' === (string) $raw_value || 'yes' === (string) $raw_value || 'on' === (string) $raw_value || true === $raw_value ) {
					$order->update_meta_data( $meta_key, '1' );
				} else {
					$order->delete_meta_data( $meta_key );
				}
				continue;
			}

			// Sanitize based on field type.
			$scalar    = is_scalar( $raw_value ) ? (string) $raw_value : '';
			$sanitized = match ( $field->getType() ) {
				FieldType::TEXTAREA => sanitize_textarea_field( $scalar ),
				FieldType::EMAIL    => sanitize_email( $scalar ),
				default             => sanitize_text_field( $scalar ),
			};

			// If empty, delete or clear meta.
			if ( '' === trim( $sanitized ) ) {
				$order->delete_meta_data( $meta_key );
				continue;
			}

			// Validate choice options and specialized formats.
			$validation_result = $validator->validate( $field, $sanitized );
			if ( ! $validation_result->isValid() ) {
				// Invalid choice option or format; reject and do not update.
				continue;
			}

			$order->update_meta_data( $meta_key, $sanitized );
		}

		$order->save();
	}

	/**
	 * Get all configured fields eligible for editing on this order.
	 *
	 * @since 0.0.1
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array<int, array{field: Field, raw_value: mixed}>
	 */
	public static function editableFieldsFor( WC_Order $order ): array {
		$editable = array();

		foreach ( FieldRepository::get() as $field ) {
			if ( ! $field->hasDestination( FieldDestination::ADMIN_ORDER ) ) {
				continue;
			}

			$raw_value = $order->get_meta( self::META_PREFIX . $field->getId(), true );

			$editable[] = array(
				'field'     => $field,
				'raw_value' => is_scalar( $raw_value ) ? (string) $raw_value : '',
			);
		}

		return $editable;
	}

	/**
	 * Print the checkout fields section (view & edit modes).
	 *
	 * Hooked to `woocommerce_admin_order_data_after_shipping_address`, which fires
	 * inside `.order_data_column_shipping` within `.order_data_column_container`.
	 *
	 * By closing the shipping column and the column container prior to output,
	 * `.scfw-admin-order-fields` renders as a direct child of `#order_data` and a
	 * direct sibling of `.order_data_column_container`, spanning the full width of
	 * the Order Data meta box above the bottom of the container.
	 *
	 * @since 0.0.1
	 *
	 * @param WC_Order                                                  $order           WooCommerce order.
	 * @param array<int, array{id: string, label: string, value: string}> $rows            Field display rows.
	 * @param array<int, array{field: Field, raw_value: mixed}>         $editable_fields Editable fields list.
	 *
	 * @return void
	 */
	private static function output( WC_Order $order, array $rows, array $editable_fields ): void {
		?>
					</div><!-- /.order_data_column_shipping -->
				</div><!-- /.order_data_column_container -->
				<div class="scfw-admin-order-fields" style="clear:both; width:100%; box-sizing:border-box; margin:24px 0 0 0; padding:20px 16px 0 16px; border-top:1px solid #e5e5e5; display:block;">
					<div class="scfw-admin-order-fields-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
						<h3 style="margin:0; font-size:14px; font-weight:600; color:#1d2327; display:flex; align-items:center; gap:6px;">
							<span class="dashicons dashicons-clipboard" style="font-size:18px; width:18px; height:18px; color:#5925dc;" aria-hidden="true"></span>
							<?php esc_html_e( 'Checkout Fields', 'felmivo-checkout-builder' ); ?>
						</h3>
						<div class="scfw-admin-order-fields-actions">
							<a href="#" class="scfw-edit-order-fields-btn" role="button" style="text-decoration:none; font-weight:600; font-size:13px; color:#2271b1;">
								<?php esc_html_e( 'Edit', 'felmivo-checkout-builder' ); ?>
							</a>
							<a href="#" class="scfw-cancel-order-fields-btn" role="button" style="text-decoration:none; font-weight:600; font-size:13px; color:#646970; display:none;">
								<?php esc_html_e( 'Cancel', 'felmivo-checkout-builder' ); ?>
							</a>
						</div>
					</div>

					<!-- VIEW MODE -->
					<div class="scfw-admin-order-fields-view scfw-admin-order-fields-card" style="background:#ffffff; border:1px solid #ccd0d4; border-radius:6px; overflow:hidden; box-shadow:0 1px 1px rgba(0,0,0,0.04);">
						<?php if ( ! empty( $rows ) ) : ?>
							<table style="width:100%; border-collapse:collapse; text-align:left; font-size:13px; margin:0;">
								<tbody>
									<?php foreach ( $rows as $index => $row ) : ?>
										<tr style="border-bottom:<?php echo $index < count( $rows ) - 1 ? '1px solid #f0f0f1' : 'none'; ?>;">
											<th scope="row" style="padding:10px 16px; width:30%; min-width:180px; font-weight:600; color:#50575e; background:#fafafa; border:none; vertical-align:top;">
												<?php echo esc_html( $row['label'] ); ?>
											</th>
											<td style="padding:10px 16px; color:#1d2327; background:#ffffff; border:none; vertical-align:top; word-break:break-word;">
												<?php echo esc_html( $row['value'] ); ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php else : ?>
							<p style="padding:12px 16px; margin:0; color:#646970; font-style:italic;">
								<?php esc_html_e( 'No checkout field values recorded for this order.', 'felmivo-checkout-builder' ); ?>
							</p>
						<?php endif; ?>
					</div>

					<!-- EDIT MODE -->
					<div class="scfw-admin-order-fields-edit scfw-admin-order-fields-card" style="display:none; background:#ffffff; border:1px solid #ccd0d4; border-radius:6px; padding:16px 20px; box-shadow:0 1px 1px rgba(0,0,0,0.04);">
						<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
						<div class="scfw-order-fields-grid" style="display:grid; grid-template-columns:1fr; gap:16px;">
							<?php foreach ( $editable_fields as $entry ) : ?>
								<?php
								$field     = $entry['field'];
								$raw_value = $entry['raw_value'];
								$input_id  = 'scfw_order_field_' . sanitize_key( $field->getId() );
								$name_attr = 'scfw_fields[' . esc_attr( $field->getId() ) . ']';
								?>
								<div class="scfw-order-field-row form-field form-field-wide" style="margin:0;">
									<?php if ( FieldType::CHECKBOX === $field->getType() ) : ?>
										<label for="<?php echo esc_attr( $input_id ); ?>" style="display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-weight:600; color:#1d2327;">
											<input type="checkbox"
												name="<?php echo esc_attr( $name_attr ); ?>"
												id="<?php echo esc_attr( $input_id ); ?>"
												value="1"
												<?php checked( in_array( $raw_value, array( '1', 'yes', 'on', true, 1 ), true ), true ); ?>
												style="margin:0;" />
											<?php echo esc_html( $field->getLabel() ); ?>
										</label>
									<?php else : ?>
										<label for="<?php echo esc_attr( $input_id ); ?>" style="display:block; font-weight:600; margin-bottom:6px; color:#1d2327;">
											<?php echo esc_html( $field->getLabel() ); ?>
										</label>

										<?php if ( FieldType::TEXTAREA === $field->getType() ) : ?>
											<textarea
												name="<?php echo esc_attr( $name_attr ); ?>"
												id="<?php echo esc_attr( $input_id ); ?>"
												rows="3"
												style="width:100%; box-sizing:border-box;"
												placeholder="<?php echo esc_attr( $field->getPlaceholder() ); ?>"><?php echo esc_textarea( $raw_value ); ?></textarea>

										<?php elseif ( FieldType::SELECT === $field->getType() ) : ?>
											<select
												name="<?php echo esc_attr( $name_attr ); ?>"
												id="<?php echo esc_attr( $input_id ); ?>"
												style="width:100%; max-width:400px; box-sizing:border-box;">
												<option value=""><?php esc_html_e( '-- Select an option --', 'felmivo-checkout-builder' ); ?></option>
												<?php foreach ( $field->getOptions() as $opt ) : ?>
													<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $raw_value, $opt ); ?>>
														<?php echo esc_html( $opt ); ?>
													</option>
												<?php endforeach; ?>
											</select>

										<?php elseif ( FieldType::RADIO === $field->getType() ) : ?>
											<div class="scfw-radio-group" style="display:flex; flex-direction:column; gap:6px;">
												<?php foreach ( $field->getOptions() as $opt_idx => $opt ) : ?>
													<?php $radio_id = $input_id . '_' . $opt_idx; ?>
													<label for="<?php echo esc_attr( $radio_id ); ?>" style="display:inline-flex; align-items:center; gap:6px; font-weight:normal; cursor:pointer;">
														<input type="radio"
															name="<?php echo esc_attr( $name_attr ); ?>"
															id="<?php echo esc_attr( $radio_id ); ?>"
															value="<?php echo esc_attr( $opt ); ?>"
															<?php checked( $raw_value, $opt ); ?> />
														<?php echo esc_html( $opt ); ?>
													</label>
												<?php endforeach; ?>
											</div>

										<?php else : ?>
											<?php
											$html_type = match ( $field->getType() ) {
												FieldType::EMAIL  => 'email',
												FieldType::TEL    => 'tel',
												FieldType::NUMBER => 'number',
												FieldType::DATE   => 'date',
												default           => 'text',
											};
	?>
											<input
												type="<?php echo esc_attr( $html_type ); ?>"
												name="<?php echo esc_attr( $name_attr ); ?>"
												id="<?php echo esc_attr( $input_id ); ?>"
												value="<?php echo esc_attr( $raw_value ); ?>"
												placeholder="<?php echo esc_attr( $field->getPlaceholder() ); ?>"
												style="width:100%; max-width:400px; box-sizing:border-box;" />
										<?php endif; ?>
									<?php endif; ?>

									<?php if ( '' !== $field->getDescription() ) : ?>
										<span class="description" style="display:block; margin-top:4px; font-size:12px; color:#646970;">
											<?php echo esc_html( $field->getDescription() ); ?>
										</span>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<div class="order_data_column_container" style="display:none;">
					<div class="order_data_column order_data_column_shipping">
		<?php
	}
}
