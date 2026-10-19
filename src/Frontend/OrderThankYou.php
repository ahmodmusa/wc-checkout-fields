<?php
/**
 * Order thank-you adapter.
 *
 * @package SCFW\Frontend
 */

declare(strict_types=1);

namespace SCFW\Frontend;

use SCFW\Core\Field\FieldDestination;
use SCFW\Services\OrderFieldPresentation;
use WC_Order;


/**
 * Displays saved checkout field values on the WooCommerce Thank You page.
 *
 * Hooks the native `woocommerce_thankyou` action so the values appear inside
 * the order-received area that both the Classic Checkout shortcode and the
 * Block Checkout Order Confirmation block render. The adapter is a thin
 * WooCommerce layer: it loads the order through the WooCommerce order API and
 * pulls the rows from the shared presentation service, only escaping and
 * printing them.
 *
 * WooCommerce fires the hook only after it has verified the order key and the
 * viewer's permission, so this surface never discloses order data based on an
 * order ID alone.
 *
 * Nothing is printed when no configured field has a saved value, so Thank You
 * pages without checkout-field data remain unchanged.
 *
 * @since 0.0.1
 */
final class OrderThankYou {

	/**
	 * Register the Thank You page hook.
	 *
	 * Runs after the default order details table so the section appears below
	 * the native order summary.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'woocommerce_thankyou', array( self::class, 'render' ), 20, 1 );
	}

	/**
	 * Render saved checkout field values on the Thank You page.
	 *
	 * The order is loaded only through the WooCommerce order API so the same
	 * code works with HPOS and the legacy order storage. Rendering never
	 * mutates the order.
	 *
	 * @since 0.0.1
	 *
	 * @param mixed $order_id WooCommerce order id.
	 *
	 * @return void
	 */
	public static function render( $order_id ): void {
		if ( ! is_int( $order_id ) && ! is_string( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$rows = OrderFieldPresentation::rowsFor( $order, FieldDestination::THANK_YOU );

		if ( empty( $rows ) ) {
			return;
		}

		self::output( $rows );
	}

	/**
	 * Print the checkout fields section.
	 *
	 * Uses semantic markup with an accessible definition list structured in a
	 * card container that inherits the theme's styling while remaining polished
	 * and clear across both desktop and mobile viewports.
	 *
	 * @since 0.0.1
	 *
	 * @param array<int, array{id: string, label: string, value: string}> $rows Field rows.
	 *
	 * @return void
	 */
	private static function output( array $rows ): void {
		?>
		<section class="woocommerce-order-details scfw-thankyou-section">
			<h2 class="woocommerce-order-details__title scfw-thankyou-title"><?php esc_html_e( 'Additional Details', 'felmivo-checkout-builder' ); ?></h2>
			<div class="scfw-order-fields-card">
				<table class="woocommerce-table scfw-order-fields-table">
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr class="scfw-order-field-row scfw-order-field--<?php echo esc_attr( $row['id'] ); ?>">
								<th scope="row" class="scfw-order-field-label"><?php echo esc_html( $row['label'] ); ?></th>
								<td class="scfw-order-field-value"><?php echo esc_html( $row['value'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
		<?php
	}
}
