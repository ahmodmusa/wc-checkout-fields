<?php
/**
 * Checkout field builder.
 *
 * @package SCFW\Admin
 */

declare(strict_types=1);

namespace SCFW\Admin;

use SCFW\Core\Field\FieldDestination;
use SCFW\Core\Field\FieldRepository;
use SCFW\Core\Field\FieldType;


/**
 * Renders the field list and focused field editor workspace.
 *
 * The list contains display-only field metadata. Each field's authoritative
 * Settings API controls live once in its editor panel, which lets the panels
 * switch visibility without producing duplicate POST values.
 *
 * @since 0.0.1
 */
final class FieldBuilder {

	/**
	 * Render the field workspace and add-field controls.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function render(): void {
		$config        = array_values( FieldRepository::config() );
		$typeCases     = FieldType::cases();
		$fieldCount    = count( $config );
		$requiredCount = 0;
		$devMode       = Settings::isDeveloperDiagnosticsEnabled();

		foreach ( $config as $field ) {
			if ( ! empty( $field['required'] ) ) {
				$requiredCount++;
			}
		}
		?>
		<div class="scfw-fields-view <?php echo $devMode ? 'scfw-dev-mode-active' : ''; ?>" id="scfw-fields-view">
			<!-- Main Field Table Card Matching Reference Design -->
			<div class="scfw-table-card">
				<table class="scfw-fields-table" aria-label="<?php esc_attr_e( 'Checkout Fields List', 'felmivo-checkout-builder' ); ?>">
					<thead>
						<tr>
							<th class="scfw-col-handle" scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Order', 'felmivo-checkout-builder' ); ?></span></th>
							<th class="scfw-col-label" scope="col"><?php esc_html_e( 'Label', 'felmivo-checkout-builder' ); ?></th>
							<th class="scfw-col-type" scope="col"><?php esc_html_e( 'Type', 'felmivo-checkout-builder' ); ?></th>
							<th class="scfw-col-location" scope="col"><?php esc_html_e( 'Location', 'felmivo-checkout-builder' ); ?></th>
							<th class="scfw-col-required" scope="col"><?php esc_html_e( 'Required', 'felmivo-checkout-builder' ); ?></th>
							<th class="scfw-col-enabled" scope="col"><?php esc_html_e( 'Enabled', 'felmivo-checkout-builder' ); ?></th>
							<th class="scfw-col-actions" scope="col"><?php esc_html_e( 'Actions', 'felmivo-checkout-builder' ); ?></th>
						</tr>
					</thead>
					<tbody id="scfw-fields-tbody">
						<?php if ( 0 === $fieldCount ) : ?>
							<tr class="scfw-empty-row">
								<td colspan="7" class="scfw-empty-cell">
									<p><?php esc_html_e( 'No custom checkout fields configured yet. Click "Add New Field" above to create one.', 'felmivo-checkout-builder' ); ?></p>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $config as $index => $field ) : ?>
								<?php self::renderTableRow( $index, $field, $devMode ); ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Page Summary Section Matching Reference (Bottom) -->
			<div class="scfw-summary-bar" role="status" aria-label="<?php esc_attr_e( 'Fields Summary', 'felmivo-checkout-builder' ); ?>">
				<div class="scfw-summary-item">
					<span class="scfw-summary-icon scfw-summary-icon--list" aria-hidden="true">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
					</span>
					<span class="scfw-summary-text"><?php echo esc_html( sprintf( /* translators: %d is field count */ _n( '%d Field', '%d Fields', $fieldCount, 'felmivo-checkout-builder' ), $fieldCount ) ); ?></span>
				</div>
				<div class="scfw-summary-item">
					<span class="scfw-summary-icon scfw-summary-icon--check" aria-hidden="true">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
					</span>
					<span class="scfw-summary-text"><?php echo esc_html( sprintf( /* translators: %d is count */ __( '%d Required', 'felmivo-checkout-builder' ), $requiredCount ) ); ?></span>
				</div>
				<div class="scfw-summary-item">
					<span class="scfw-summary-icon scfw-summary-icon--location" aria-hidden="true">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
					</span>
					<span class="scfw-summary-text"><?php esc_html_e( 'Location: Checkout', 'felmivo-checkout-builder' ); ?></span>
				</div>
				<?php
				$health = CheckoutHealth::audit( $config );
				$is_healthy = CheckoutHealth::STATUS_HEALTHY === $health['status'];
				$warning_count = count( $health['warnings'] );
				?>
				<div class="scfw-summary-item scfw-summary-item--health">
					<?php if ( $is_healthy ) : ?>
						<span class="scfw-health-badge scfw-health-badge--healthy">
							<span class="scfw-health-icon" aria-hidden="true">✓</span>
							<span class="scfw-health-text"><?php esc_html_e( 'All systems healthy', 'felmivo-checkout-builder' ); ?></span>
						</span>
					<?php else : ?>
						<button type="button" class="scfw-health-badge scfw-health-badge--warning scfw-trigger-health-modal" id="scfw-health-btn" aria-haspopup="dialog" aria-expanded="false" aria-label="<?php echo esc_attr( sprintf( /* translators: %d is count */ _n( '%d configuration warning. Click to view details.', '%d configuration warnings. Click to view details.', $warning_count, 'felmivo-checkout-builder' ), $warning_count ) ); ?>">
							<span class="scfw-health-icon" aria-hidden="true">⚠</span>
							<span class="scfw-health-text"><?php echo esc_html( sprintf( /* translators: %d is count */ _n( '%d configuration warning', '%d configuration warnings', $warning_count, 'felmivo-checkout-builder' ), $warning_count ) ); ?></span>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<!-- Editor Modal/Drawer Workspace for Full Field Controls -->
			<div class="scfw-modal-backdrop" id="scfw-editor-backdrop" hidden>
				<div class="scfw-modal-container" role="dialog" aria-modal="true" aria-labelledby="scfw-modal-title">
					<div class="scfw-modal-header">
						<h2 id="scfw-modal-title"><?php esc_html_e( 'Edit Field Settings', 'felmivo-checkout-builder' ); ?></h2>
						<button type="button" class="scfw-modal-close" id="scfw-modal-close" aria-label="<?php esc_attr_e( 'Close', 'felmivo-checkout-builder' ); ?>">
							<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
						</button>
					</div>
					<div class="scfw-modal-body" id="scfw-modal-body">
						<?php foreach ( $config as $index => $field ) : ?>
							<?php self::renderFieldEditor( $index, $field, $typeCases, $fieldCount, false, $config, $devMode ); ?>
						<?php endforeach; ?>
						<?php self::renderAddFieldPanel( $typeCases ); ?>
					</div>

					<div class="scfw-modal-footer">
						<button type="button" class="button button-secondary scfw-modal-cancel"><?php esc_html_e( 'Done', 'felmivo-checkout-builder' ); ?></button>
						<button type="submit" class="button button-primary scfw-btn-primary" name="submit" value="1"><?php esc_html_e( 'Save All Changes', 'felmivo-checkout-builder' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Hidden persistent fields container to ensure all settings inputs are submitted safely -->
			<div class="scfw-hidden-inputs-host" aria-hidden="true" style="display:none;">
				<?php foreach ( $config as $index => $field ) : ?>
					<input type="hidden" class="scfw-field-order-input" name="scfw_options[fields][<?php echo esc_attr( (string) $index ); ?>][order]" value="<?php echo esc_attr( (string) $index ); ?>" />
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render auxiliary modals (Templates, Health) outside the settings form.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function renderModals(): void {
		$config = array_values( FieldRepository::config() );
		$health = CheckoutHealth::audit( $config );

		self::renderTemplatesModal();
		self::renderHealthModal( $health );
	}

	/**
	 * Render one table row matching reference columns and styling.
	 *
	 * @since 0.0.1
	 *
	 * @param int                  $index   Field index.
	 * @param array<string, mixed> $field   Field configuration.
	 * @param bool                 $devMode Whether Developer Diagnostics is enabled.
	 *
	 * @return void
	 */
	private static function renderTableRow( int $index, array $field, bool $devMode = false ): void {
		$id       = (string) ( $field['id'] ?? '' );
		$type     = (string) ( $field['type'] ?? 'text' );
		$label    = (string) ( $field['label'] ?? '' );
		$enabled  = ! empty( $field['enabled'] );
		$required = ! empty( $field['required'] );
		$typeName = ucfirst( $type );
		$metaKey  = DeveloperDiagnostics::metaKey( $id );
		$destMap  = DeveloperDiagnostics::destinationLabels();
		?>
		<tr class="scfw-field-row <?php echo $enabled ? '' : 'scfw-field-row--disabled'; ?>" data-row="<?php echo esc_attr( (string) $index ); ?>" data-type="<?php echo esc_attr( $type ); ?>">
			<!-- Drag Handle -->
			<td class="scfw-col-handle">
				<span class="scfw-drag-handle" draggable="true" title="<?php esc_attr_e( 'Drag to reorder', 'felmivo-checkout-builder' ); ?>" aria-label="<?php esc_attr_e( 'Drag to reorder', 'felmivo-checkout-builder' ); ?>" aria-hidden="true">
					<svg width="14" height="18" viewBox="0 0 14 18" fill="none" xmlns="http://www.w3.org/2000/svg">
						<circle cx="3.5" cy="3.5" r="1.5" fill="#a0aec0"/>
						<circle cx="10.5" cy="3.5" r="1.5" fill="#a0aec0"/>
						<circle cx="3.5" cy="9" r="1.5" fill="#a0aec0"/>
						<circle cx="10.5" cy="9" r="1.5" fill="#a0aec0"/>
						<circle cx="3.5" cy="14.5" r="1.5" fill="#a0aec0"/>
						<circle cx="10.5" cy="14.5" r="1.5" fill="#a0aec0"/>
					</svg>
				</span>
			</td>

			<!-- Label -->
			<td class="scfw-col-label">
				<span class="scfw-row-label-text"><?php echo esc_html( $label ); ?></span>
			</td>

			<!-- Type -->
			<td class="scfw-col-type">
				<span class="scfw-row-type-text"><?php echo esc_html( $typeName ); ?></span>
			</td>

			<!-- Location -->
			<td class="scfw-col-location">
				<span class="scfw-row-location-text"><?php esc_html_e( 'Checkout', 'felmivo-checkout-builder' ); ?></span>
			</td>

			<!-- Required -->
			<td class="scfw-col-required">
				<?php if ( $required ) : ?>
					<span class="scfw-icon-check" title="<?php esc_attr_e( 'Required', 'felmivo-checkout-builder' ); ?>" aria-label="<?php esc_attr_e( 'Required', 'felmivo-checkout-builder' ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5925dc" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
					</span>
				<?php else : ?>
					<span class="scfw-icon-dash" aria-hidden="true">—</span>
				<?php endif; ?>
			</td>

			<!-- Enabled Toggle (Interactive) -->
			<td class="scfw-col-enabled">
				<label class="scfw-toggle-switch">
					<input type="hidden" name="scfw_options[fields][<?php echo esc_attr( (string) $index ); ?>][enabled]" value="0" />
					<input type="checkbox" class="scfw-toggle-input" name="scfw_options[fields][<?php echo esc_attr( (string) $index ); ?>][enabled]" value="1" <?php checked( $enabled, true ); ?> aria-label="<?php echo esc_attr( sprintf( /* translators: %s is field label */ __( 'Enable %s field', 'felmivo-checkout-builder' ), $label ) ); ?>" />
					<span class="scfw-toggle-track"><span class="scfw-toggle-thumb"></span></span>
				</label>
			</td>

			<!-- Actions: Edit & Delete Icons -->
			<td class="scfw-col-actions">
				<div class="scfw-actions-wrap">
					<button type="button" class="scfw-action-btn scfw-action-btn--edit scfw-trigger-edit-field" data-row="<?php echo esc_attr( (string) $index ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s is field label */ __( 'Edit %s', 'felmivo-checkout-builder' ), $label ) ); ?>" title="<?php esc_attr_e( 'Edit Field', 'felmivo-checkout-builder' ); ?>">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5925dc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
					</button>
					<button type="submit" class="scfw-action-btn scfw-action-btn--delete scfw-remove" name="scfw_options[fields][<?php echo esc_attr( (string) $index ); ?>][remove]" value="1" data-confirm="<?php esc_attr_e( 'Are you sure you want to delete this field?', 'felmivo-checkout-builder' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s is field label */ __( 'Delete %s', 'felmivo-checkout-builder' ), $label ) ); ?>" title="<?php esc_attr_e( 'Delete Field', 'felmivo-checkout-builder' ); ?>">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
					</button>
				</div>
			</td>
		</tr>
		<?php if ( $devMode ) : ?>
			<tr class="scfw-dev-row" data-row="<?php echo esc_attr( (string) $index ); ?>">
				<td colspan="7" class="scfw-dev-cell">
					<div class="scfw-dev-bar">
						<span class="scfw-dev-chip">
							<strong class="scfw-dev-chip-label"><?php esc_html_e( 'ID:', 'felmivo-checkout-builder' ); ?></strong>
							<code><?php echo esc_html( $id ); ?></code>
						</span>
						<span class="scfw-dev-chip">
							<strong class="scfw-dev-chip-label"><?php esc_html_e( 'Meta Key:', 'felmivo-checkout-builder' ); ?></strong>
							<code><?php echo esc_html( $metaKey ); ?></code>
							<button type="button" class="button button-small scfw-copy-key-btn" data-key="<?php echo esc_attr( $metaKey ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s is meta key */ __( 'Copy meta key %s', 'felmivo-checkout-builder' ), $metaKey ) ); ?>">
								<?php esc_html_e( 'Copy Key', 'felmivo-checkout-builder' ); ?>
							</button>
						</span>
						<span class="scfw-dev-chip scfw-dev-chip--destinations">
							<strong class="scfw-dev-chip-label"><?php esc_html_e( 'Destinations:', 'felmivo-checkout-builder' ); ?></strong>
							<span class="scfw-dev-dest-list">
								<?php
								$activeDests = array();
								foreach ( $destMap as $k => $destLabel ) {
									if ( DeveloperDiagnostics::isDestinationActive( $field, $k ) ) {
										$activeDests[] = $destLabel;
									}
								}
								echo esc_html( ! empty( $activeDests ) ? implode( ' · ', $activeDests ) : __( 'None', 'felmivo-checkout-builder' ) );
								?>
							</span>
						</span>
					</div>
				</td>
			</tr>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render one authoritative editor panel.
	 *
	 * @since 0.0.1
	 *
	 * @param int                         $index     Field index.
	 * @param array<string, mixed>        $field     Field configuration.
	 * @param array<int, FieldType>       $typeCases Supported field types.
	 * @param int                         $total     Total field count.
	 * @param bool                        $selected  Whether this is initially selected.
	 * @param array<int, mixed>           $allFields All configured fields.
	 * @param bool                        $devMode   Whether Developer Diagnostics is enabled.
	 *
	 * @return void
	 */
	private static function renderFieldEditor( int $index, array $field, array $typeCases, int $total, bool $selected, array $allFields, bool $devMode = false ): void {
		$type = (string) $field['type'];
		?>
		<div class="scfw-field-details scfw-field-editor" id="scfw-field-editor-<?php echo esc_attr( (string) $index ); ?>" data-row="<?php echo esc_attr( (string) $index ); ?>" <?php echo $selected ? '' : 'hidden'; ?>>
			<div class="scfw-editor-header">
				<div>
					<span class="scfw-section-eyebrow"><?php esc_html_e( 'Field settings', 'felmivo-checkout-builder' ); ?></span>
					<h2 class="scfw-editor-field-heading"><?php echo esc_html( (string) $field['label'] ); ?></h2>
					<span class="scfw-editor-field-type"><?php echo esc_html( $type ); ?></span>

				</div>
				<span class="scfw-editor-position"><?php echo esc_html( sprintf( /* translators: %1$d is the current field number, %2$d is the total count. */ __( 'Field %1$d of %2$d', 'felmivo-checkout-builder' ), $index + 1, $total ) ); ?></span>
			</div>

			<section class="scfw-editor-section">
				<h3><?php esc_html_e( 'General', 'felmivo-checkout-builder' ); ?></h3>
				<div class="scfw-editor-grid">
					<label class="scfw-editor-field scfw-editor-field--wide">
						<span class="scfw-editor-label"><?php esc_html_e( 'Label', 'felmivo-checkout-builder' ); ?></span>
						<input type="text" class="scfw-field-label" name="scfw_options[fields][<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( (string) $field['label'] ); ?>" />
					</label>
					<label class="scfw-editor-field">
						<span class="scfw-editor-label"><?php esc_html_e( 'Field ID', 'felmivo-checkout-builder' ); ?></span>
						<input type="text" class="scfw-field-id" name="scfw_options[fields][<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( (string) $field['id'] ); ?>" />
					</label>
					<label class="scfw-editor-field">
						<span class="scfw-editor-label"><?php esc_html_e( 'Field type', 'felmivo-checkout-builder' ); ?></span>
						<select class="scfw-field-type scfw-type--<?php echo esc_attr( $type ); ?>" name="scfw_options[fields][<?php echo esc_attr( (string) $index ); ?>][type]">
							<?php foreach ( $typeCases as $typeCase ) : ?>
								<option value="<?php echo esc_attr( $typeCase->value ); ?>" <?php selected( $type, $typeCase->value, true ); ?>><?php echo esc_html( $typeCase->value ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</div>

				<div class="scfw-editor-toggle-grid">
					<label class="scfw-editor-switch scfw-required-control">
						<input type="hidden" name="scfw_options[fields][<?php echo esc_attr( (string) $index ); ?>][required]" value="0" />
						<input type="checkbox" class="scfw-mini-switch__input" name="scfw_options[fields][<?php echo esc_attr( (string) $index ); ?>][required]" value="1" <?php checked( (bool) $field['required'], true ); ?> />
						<span class="scfw-editor-switch__box" aria-hidden="true"></span>
						<span><strong><?php esc_html_e( 'Required', 'felmivo-checkout-builder' ); ?></strong><small><?php esc_html_e( 'This field must be completed at checkout.', 'felmivo-checkout-builder' ); ?></small></span>
					</label>
					<label class="scfw-editor-switch scfw-status-control">
						<input type="hidden" name="scfw_options[fields][<?php echo esc_attr( (string) $index ); ?>][enabled]" value="0" />
						<input type="checkbox" class="scfw-mini-switch__input" name="scfw_options[fields][<?php echo esc_attr( (string) $index ); ?>][enabled]" value="1" <?php checked( (bool) $field['enabled'], true ); ?> />
						<span class="scfw-editor-switch__box" aria-hidden="true"></span>
						<span><strong><?php esc_html_e( 'Enabled', 'felmivo-checkout-builder' ); ?></strong><small><?php esc_html_e( 'Show this field during checkout.', 'felmivo-checkout-builder' ); ?></small></span>
					</label>
				</div>
			</section>

			<?php self::renderTypeSettings( $index, $type, $field ); ?>

			<?php self::renderConditionsSettings( $index, $field, $allFields ); ?>

			<?php self::renderDestinationsSettings( $index, $field ); ?>

			<section class="scfw-editor-section scfw-editor-preview-section">
				<h3><?php esc_html_e( 'Live Preview', 'felmivo-checkout-builder' ); ?></h3>
				<div class="scfw-field-preview-container">
					<?php echo FieldPreviewRenderer::render( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</section>

			<?php if ( $devMode ) : ?>
				<?php self::renderDeveloperDiagnosticsSection( $field ); ?>
			<?php endif; ?>

			<div class="scfw-editor-actions">
				<button type="submit" class="button scfw-remove" name="scfw_options[fields][<?php echo esc_attr( (string) $index ); ?>][remove]" value="1" data-confirm="<?php esc_attr_e( 'Are you sure you want to delete this field?', 'felmivo-checkout-builder' ); ?>">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					<?php esc_html_e( 'Delete Field', 'felmivo-checkout-builder' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render conditional visibility controls for a field editor.
	 *
	 * @since 0.0.1
	 *
	 * @param int                  $index     Field index.
	 * @param array<string, mixed> $field     Field configuration.
	 * @param array<int, mixed>    $allFields All configured fields for parent selection.
	 *
	 * @return void
	 */
	private static function renderConditionsSettings( int $index, array $field, array $allFields ): void {
		$conditions = isset( $field['conditions'] ) && is_array( $field['conditions'] ) ? $field['conditions'] : null;
		$hasCondition = null !== $conditions && ! empty( $conditions['field'] );
		$currentId  = (string) ( $field['id'] ?? '' );
		$prefix     = 'scfw_options[fields][' . $index . '][conditions]';

		// Available parent fields (excluding self).
		$otherFields = array();
		foreach ( $allFields as $f ) {
			if ( is_array( $f ) && isset( $f['id'] ) && $f['id'] !== $currentId ) {
				$otherFields[] = $f;
			}
		}
		?>
		<section class="scfw-editor-section scfw-conditions-settings">
			<h3><?php esc_html_e( 'Conditional Visibility', 'felmivo-checkout-builder' ); ?></h3>
			<p class="description" style="margin:0 0 12px; color:#646970;">
				<?php esc_html_e( 'Show or hide this field based on the customer’s selection in another checkout field.', 'felmivo-checkout-builder' ); ?>
			</p>

			<?php if ( empty( $otherFields ) ) : ?>
				<div style="background:#f3f4f6; border-left:4px solid #9ca3af; padding:10px 14px; border-radius:4px; font-size:13px; color:#4b5563;">
					<?php esc_html_e( 'Conditional visibility requires at least one other field to reference.', 'felmivo-checkout-builder' ); ?>
				</div>
			<?php else : ?>
				<div class="scfw-condition-controls" style="background:#f9fafb; padding:12px 14px; border-radius:6px; border:1px solid #e5e7eb;">
					<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; align-items:end;">
						<label class="scfw-editor-field" style="margin:0;">
							<span class="scfw-editor-label" style="font-size:12px;"><?php esc_html_e( 'Controlling Field', 'felmivo-checkout-builder' ); ?></span>
							<select name="<?php echo esc_attr( $prefix . '[field]' ); ?>" style="width:100%;">
								<option value=""><?php esc_html_e( '— Always Visible (No Condition) —', 'felmivo-checkout-builder' ); ?></option>
								<?php foreach ( $otherFields as $candidate ) : ?>
									<option value="<?php echo esc_attr( (string) $candidate['id'] ); ?>" <?php selected( (string) ( $conditions['field'] ?? '' ), (string) $candidate['id'], true ); ?>>
										<?php echo esc_html( (string) $candidate['label'] ); ?> (<?php echo esc_html( (string) $candidate['id'] ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</label>

						<label class="scfw-editor-field" style="margin:0;">
							<span class="scfw-editor-label" style="font-size:12px;"><?php esc_html_e( 'Operator', 'felmivo-checkout-builder' ); ?></span>
							<select name="<?php echo esc_attr( $prefix . '[operator]' ); ?>" style="width:100%;">
								<option value="equals" <?php selected( (string) ( $conditions['operator'] ?? 'equals' ), 'equals', true ); ?>><?php esc_html_e( 'equals', 'felmivo-checkout-builder' ); ?></option>
								<option value="not_equals" <?php selected( (string) ( $conditions['operator'] ?? '' ), 'not_equals', true ); ?>><?php esc_html_e( 'not equals', 'felmivo-checkout-builder' ); ?></option>
							</select>
						</label>

						<label class="scfw-editor-field" style="margin:0;">
							<span class="scfw-editor-label" style="font-size:12px;"><?php esc_html_e( 'Expected Value', 'felmivo-checkout-builder' ); ?></span>
							<input type="text" name="<?php echo esc_attr( $prefix . '[value]' ); ?>" value="<?php echo esc_attr( (string) ( $conditions['value'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Yes or Option Value', 'felmivo-checkout-builder' ); ?>" style="width:100%;" />
						</label>
					</div>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}


	/**
	 * Render presentation destination controls for a field editor.
	 *
	 * @since 0.0.1
	 *
	 * @param int                  $index Field index.
	 * @param array<string, mixed> $field Field configuration.
	 *
	 * @return void
	 */
	private static function renderDestinationsSettings( int $index, array $field ): void {
		$destinations = isset( $field['destinations'] ) && is_array( $field['destinations'] ) ? $field['destinations'] : FieldDestination::all();
		$prefix       = 'scfw_options[fields][' . $index . '][destinations][]';

		$map = array(
			FieldDestination::ADMIN_ORDER->value    => array(
				'label' => __( 'Admin Order', 'felmivo-checkout-builder' ),
				'desc'  => __( 'Display in WooCommerce admin order details.', 'felmivo-checkout-builder' ),
			),
			FieldDestination::THANK_YOU->value      => array(
				'label' => __( 'Thank You Page', 'felmivo-checkout-builder' ),
				'desc'  => __( 'Display on the customer order-received page.', 'felmivo-checkout-builder' ),
			),
			FieldDestination::MY_ACCOUNT->value     => array(
				'label' => __( 'My Account', 'felmivo-checkout-builder' ),
				'desc'  => __( 'Display in customer order details under My Account.', 'felmivo-checkout-builder' ),
			),
			FieldDestination::CUSTOMER_EMAIL->value => array(
				'label' => __( 'Customer Email', 'felmivo-checkout-builder' ),
				'desc'  => __( 'Include in order emails sent to the customer.', 'felmivo-checkout-builder' ),
			),
			FieldDestination::ADMIN_EMAIL->value    => array(
				'label' => __( 'Admin Email', 'felmivo-checkout-builder' ),
				'desc'  => __( 'Include in new-order notification emails sent to store admin.', 'felmivo-checkout-builder' ),
			),
		);
		?>
		<section class="scfw-editor-section scfw-destinations-settings">
			<h3><?php esc_html_e( 'Show Data In', 'felmivo-checkout-builder' ); ?></h3>
			<p class="description" style="margin:0 0 12px; color:#646970;">
				<?php esc_html_e( 'Select the touchpoints where this field’s value should be displayed once an order is placed.', 'felmivo-checkout-builder' ); ?>
			</p>
			<div class="scfw-editor-toggle-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:10px;">
				<?php foreach ( $map as $key => $item ) : ?>
					<?php $checked = in_array( $key, $destinations, true ); ?>
					<label class="scfw-editor-switch" style="display:flex; align-items:flex-start; gap:8px; background:#f9fafb; padding:10px 12px; border-radius:6px; border:1px solid #e5e7eb; cursor:pointer;">
						<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $checked, true ); ?> style="margin-top:3px;" />
						<span>
							<strong style="display:block; font-size:13px; color:#1f2937;"><?php echo esc_html( $item['label'] ); ?></strong>
							<small style="display:block; font-size:11px; color:#6b7280; line-height:1.3; margin-top:2px;"><?php echo esc_html( $item['desc'] ); ?></small>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}


	/**
	 * Render type-specific controls for an editor.
	 *
	 * @since 0.0.1
	 *
	 * @param int                  $index Field index.
	 * @param string               $type Field type.
	 * @param array<string, mixed> $field Field configuration.
	 *
	 * @return void
	 */
	private static function renderTypeSettings( int $index, string $type, array $field ): void {
		$isChoice   = in_array( $type, array( 'radio', 'select' ), true );
		$isText     = in_array( $type, array( 'text', 'textarea' ), true );
		$isTextarea = 'textarea' === $type;
		$hasDefault = $isChoice || $isText;
		$options    = is_array( $field['options'] ?? null ) ? array_values( $field['options'] ) : array();
		$prefix     = 'scfw_options[fields][' . $index . ']';

		if ( 0 === count( $options ) ) {
			$options = array( '' );
		}
		?>
		<section class="scfw-editor-section scfw-type-settings scfw-field-details-inner" data-type-settings="<?php echo esc_attr( $type ); ?>">
			<h3><?php esc_html_e( 'Field-specific settings', 'felmivo-checkout-builder' ); ?></h3>
			<div class="scfw-options-editor" <?php echo $isChoice ? '' : 'hidden'; ?>>
				<div class="scfw-options-header">
					<h4><?php esc_html_e( 'Options', 'felmivo-checkout-builder' ); ?></h4>
					<button type="button" class="button scfw-option-add" <?php disabled( ! $isChoice ); ?>><?php esc_html_e( 'Add Option', 'felmivo-checkout-builder' ); ?></button>
				</div>
				<div class="scfw-options-list">
					<?php foreach ( $options as $option ) : ?>
						<?php self::renderOptionRow( $prefix, (string) $option, $isChoice ); ?>
					<?php endforeach; ?>
				</div>
				<template class="scfw-option-template">
					<?php self::renderOptionRow( $prefix, '', true ); ?>
				</template>
			</div>

			<div class="scfw-detail-grid">
				<div class="scfw-placeholder-field" <?php echo $isText ? '' : 'hidden'; ?>>
					<label class="scfw-editor-field">
						<span class="scfw-editor-label"><?php esc_html_e( 'Placeholder', 'felmivo-checkout-builder' ); ?></span>
						<input type="text" name="<?php echo esc_attr( $prefix . '[placeholder]' ); ?>" value="<?php echo esc_attr( (string) ( $field['placeholder'] ?? '' ) ); ?>" <?php disabled( ! $isText ); ?> />
					</label>
				</div>
				<div class="scfw-default-field" <?php echo $hasDefault ? '' : 'hidden'; ?>>
					<label class="scfw-editor-field">
						<span class="scfw-editor-label"><?php esc_html_e( 'Default Value', 'felmivo-checkout-builder' ); ?></span>
						<input type="text" name="<?php echo esc_attr( $prefix . '[default]' ); ?>" value="<?php echo esc_attr( (string) ( $field['default'] ?? '' ) ); ?>" <?php disabled( ! $hasDefault ); ?> />
					</label>
				</div>
				<div class="scfw-description-field" <?php echo $isTextarea ? '' : 'hidden'; ?>>
					<label class="scfw-editor-field scfw-editor-field--wide">
						<span class="scfw-editor-label"><?php esc_html_e( 'Description', 'felmivo-checkout-builder' ); ?></span>
						<input type="text" name="<?php echo esc_attr( $prefix . '[description]' ); ?>" value="<?php echo esc_attr( (string) ( $field['description'] ?? '' ) ); ?>" <?php disabled( ! $isTextarea ); ?> />
					</label>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * Render the inline add-field panel.
	 *
	 * @since 0.0.1
	 *
	 * @param array<int, FieldType> $typeCases Supported field types.
	 *
	 * @return void
	 */
	private static function renderAddFieldPanel( array $typeCases ): void {
		?>
		<section class="scfw-add-field" id="scfw-add-field" aria-labelledby="scfw-add-field-title">
			<div class="scfw-add-field-heading">
				<div>
					<span class="scfw-section-eyebrow"><?php esc_html_e( 'Creation', 'felmivo-checkout-builder' ); ?></span>
					<h2 id="scfw-add-field-title"><?php esc_html_e( 'Add New Field', 'felmivo-checkout-builder' ); ?></h2>
				</div>
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
			</div>
			<p><?php esc_html_e( 'Create a custom field for additional checkout information.', 'felmivo-checkout-builder' ); ?></p>
			<div class="scfw-add-controls">
				<label>
					<span class="scfw-editor-label"><?php esc_html_e( 'Field type', 'felmivo-checkout-builder' ); ?></span>
					<select name="scfw_options[new_field][type]">
						<?php foreach ( $typeCases as $type ) : ?>
							<option value="<?php echo esc_attr( $type->value ); ?>"><?php echo esc_html( $type->value ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span class="scfw-editor-label"><?php esc_html_e( 'Field label', 'felmivo-checkout-builder' ); ?></span>
					<input type="text" name="scfw_options[new_field][label]" value="" placeholder="<?php esc_attr_e( 'e.g. Delivery Instructions', 'felmivo-checkout-builder' ); ?>" />
				</label>
				<?php submit_button( __( 'Add Field', 'felmivo-checkout-builder' ), 'primary', 'scfw_options[add]', false, array( 'id' => 'scfw-add-submit' ) ); ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Render one option control row.
	 *
	 * @since 0.0.1
	 *
	 * @param string $prefix  Form field prefix.
	 * @param string $value   Option value.
	 * @param bool   $enabled Whether the option is applicable.
	 *
	 * @return void
	 */
	private static function renderOptionRow( string $prefix, string $value, bool $enabled ): void {
		?>
		<div class="scfw-option-row">
			<input type="text" name="<?php echo esc_attr( $prefix . '[options][]' ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php disabled( ! $enabled ); ?> aria-label="<?php esc_attr_e( 'Option value', 'felmivo-checkout-builder' ); ?>" />
			<button type="button" class="button-link scfw-option-up" aria-label="<?php esc_attr_e( 'Move option up', 'felmivo-checkout-builder' ); ?>" <?php disabled( ! $enabled ); ?>>&#8593;</button>
			<button type="button" class="button-link scfw-option-down" aria-label="<?php esc_attr_e( 'Move option down', 'felmivo-checkout-builder' ); ?>" <?php disabled( ! $enabled ); ?>>&#8595;</button>
			<button type="button" class="button-link scfw-option-remove" aria-label="<?php esc_attr_e( 'Remove option', 'felmivo-checkout-builder' ); ?>" <?php disabled( ! $enabled ); ?>><?php esc_html_e( 'Remove', 'felmivo-checkout-builder' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Render the Use-Case Templates / Field Packs modal.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	private static function renderTemplatesModal(): void {
		$templates = \SCFW\Core\Field\FieldTemplateRepository::all();
		?>
		<div class="scfw-modal-backdrop scfw-templates-backdrop" id="scfw-templates-backdrop" hidden>
			<div class="scfw-modal-container scfw-templates-modal-container" role="dialog" aria-modal="true" aria-labelledby="scfw-templates-modal-title">
				<div class="scfw-modal-header">
					<div>
						<h2 id="scfw-templates-modal-title" style="margin:0 0 4px;"><?php esc_html_e( 'Use-Case Templates', 'felmivo-checkout-builder' ); ?></h2>
						<p class="description" style="margin:0; font-size:13px; color:#6b7280;">
							<?php esc_html_e( 'Choose a ready-to-use pack of checkout fields. Applying a pack safely appends fields without overwriting your existing configuration.', 'felmivo-checkout-builder' ); ?>
						</p>
					</div>
					<button type="button" class="scfw-modal-close" id="scfw-templates-modal-close" aria-label="<?php esc_attr_e( 'Close', 'felmivo-checkout-builder' ); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					</button>
				</div>
				<div class="scfw-modal-body scfw-templates-modal-body" style="padding:20px; max-height:70vh; overflow-y:auto;">
					<div class="scfw-templates-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">
						<?php foreach ( $templates as $pack_id => $pack ) : ?>
							<div class="scfw-template-card" style="background:#ffffff; border:1px solid #e5e7eb; border-radius:8px; padding:18px; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 1px 3px rgba(0,0,0,0.04); transition:all 0.15s ease;">
								<div>
									<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
										<h3 style="margin:0; font-size:16px; font-weight:600; color:#111827;"><?php echo esc_html( $pack['title'] ); ?></h3>
										<span class="scfw-badge" style="background:#f3f4f6; color:#4b5563; font-size:11px; font-weight:600; padding:2px 8px; border-radius:12px;">
											<?php echo esc_html( sprintf( /* translators: %d is count */ _n( '%d Field', '%d Fields', count( $pack['fields'] ), 'felmivo-checkout-builder' ), count( $pack['fields'] ) ) ); ?>
										</span>
									</div>
									<p style="font-size:13px; color:#6b7280; margin:0 0 14px; line-height:1.4;">
										<?php echo esc_html( $pack['description'] ); ?>
									</p>

									<div class="scfw-template-fields-preview" style="background:#f9fafb; border-radius:6px; padding:10px 12px; margin-bottom:16px; border:1px solid #f3f4f6;">
										<span style="font-size:11px; font-weight:700; text-transform:uppercase; color:#9ca3af; letter-spacing:0.05em; display:block; margin-bottom:6px;">
											<?php esc_html_e( 'Included Fields:', 'felmivo-checkout-builder' ); ?>
										</span>
										<ul style="margin:0; padding:0 0 0 16px; font-size:12px; color:#374151;">
											<?php foreach ( $pack['fields'] as $f ) : ?>
												<li style="margin-bottom:3px;">
													<strong><?php echo esc_html( $f['label'] ); ?></strong>
													<span style="color:#9ca3af; font-size:11px;">(<?php echo esc_html( $f['type'] ); ?>)</span>
												</li>
											<?php endforeach; ?>
										</ul>
										<?php if ( 'gifting' === $pack_id ) : ?>
											<div style="margin-top:8px; padding-top:6px; border-top:1px dashed #e5e7eb; font-size:11px; color:#6366f1;">
												<span class="dashicons dashicons-visibility" style="font-size:14px; width:14px; height:14px; vertical-align:middle;" aria-hidden="true"></span>
												<?php esc_html_e( 'Gift Message appears when Gift Wrapping is enabled.', 'felmivo-checkout-builder' ); ?>
											</div>
										<?php endif; ?>
									</div>
								</div>

								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
									<?php wp_nonce_field( 'scfw_apply_template' ); ?>
									<input type="hidden" name="action" value="scfw_apply_template" />
									<input type="hidden" name="template_id" value="<?php echo esc_attr( $pack_id ); ?>" />
									<button type="submit" class="button button-secondary scfw-apply-template-btn" style="width:100%; justify-content:center; text-align:center; font-weight:600;">
										<?php echo esc_html( sprintf( /* translators: %s is pack name */ __( 'Apply %s', 'felmivo-checkout-builder' ), $pack['title'] ) ); ?>
									</button>
								</form>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="scfw-modal-footer" style="padding:14px 20px; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end;">
					<button type="button" class="button button-secondary scfw-templates-modal-cancel"><?php esc_html_e( 'Close', 'felmivo-checkout-builder' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Configuration Health Diagnostic modal.
	 *
	 * @since 0.0.1
	 *
	 * @param array{status: string, warnings: array<int, array{code: string, severity: string, field: string, field_label: string, message: string, suggestion: string}>} $health Health audit results.
	 *
	 * @return void
	 */
	private static function renderHealthModal( array $health ): void {
		$warnings = $health['warnings'] ?? array();
		?>
		<div class="scfw-modal-backdrop scfw-health-backdrop" id="scfw-health-backdrop" hidden>
			<div class="scfw-modal-container scfw-health-modal-container" role="dialog" aria-modal="true" aria-labelledby="scfw-health-modal-title">
				<div class="scfw-modal-header">
					<div>
						<h2 id="scfw-health-modal-title" style="margin:0 0 4px; display:flex; align-items:center; gap:8px;">
							<span style="color:#d97706;" aria-hidden="true">⚠</span>
							<?php esc_html_e( 'Configuration Health Diagnostics', 'felmivo-checkout-builder' ); ?>
						</h2>
						<p class="description" style="margin:0; font-size:13px; color:#6b7280;">
							<?php esc_html_e( 'Review detected checkout configuration issues and suggested fixes below.', 'felmivo-checkout-builder' ); ?>
						</p>
					</div>
					<button type="button" class="scfw-modal-close" id="scfw-health-modal-close" aria-label="<?php esc_attr_e( 'Close', 'felmivo-checkout-builder' ); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					</button>
				</div>
				<div class="scfw-modal-body scfw-health-modal-body" style="padding:20px; max-height:70vh; overflow-y:auto;">
					<?php if ( empty( $warnings ) ) : ?>
						<div style="text-align:center; padding:30px 20px; color:#059669;">
							<span style="font-size:32px; display:block; margin-bottom:8px;" aria-hidden="true">✓</span>
							<h3 style="margin:0 0 4px; font-size:16px; color:#065f46;"><?php esc_html_e( 'All systems healthy', 'felmivo-checkout-builder' ); ?></h3>
							<p style="margin:0; font-size:13px; color:#047857;"><?php esc_html_e( 'No configuration warnings detected.', 'felmivo-checkout-builder' ); ?></p>
						</div>
					<?php else : ?>
						<div class="scfw-health-warnings-list" style="display:flex; flex-direction:column; gap:12px;">
							<?php foreach ( $warnings as $w ) : ?>
								<div class="scfw-health-warning-card" style="background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:16px;">
									<div style="display:flex; align-items:flex-start; gap:10px;">
										<span style="color:#d97706; font-size:16px; line-height:1.4;" aria-hidden="true">⚠</span>
										<div style="flex:1;">
											<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
												<strong style="color:#92400e; font-size:14px;"><?php echo esc_html( $w['field_label'] ); ?></strong>
												<span style="font-size:11px; color:#b45309; font-weight:600; background:#fef3c7; padding:2px 8px; border-radius:4px;"><?php echo esc_html( $w['code'] ); ?></span>
											</div>
											<p style="margin:0 0 8px; font-size:13px; color:#78350f; line-height:1.4;">
												<?php echo esc_html( $w['message'] ); ?>
											</p>
											<div style="font-size:12px; color:#92400e; background:#ffffff; border:1px solid #fef3c7; border-radius:6px; padding:8px 12px;">
												<strong><?php esc_html_e( 'Suggested Action:', 'felmivo-checkout-builder' ); ?></strong>
												<?php echo esc_html( $w['suggestion'] ); ?>
											</div>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="scfw-modal-footer" style="padding:14px 20px; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end;">
					<button type="button" class="button button-secondary scfw-health-modal-cancel"><?php esc_html_e( 'Close', 'felmivo-checkout-builder' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Developer Diagnostics section inside the field editor modal.
	 *
	 * @since 0.0.1
	 *
	 * @param array<string, mixed> $field Field configuration array.
	 *
	 * @return void
	 */
	private static function renderDeveloperDiagnosticsSection( array $field ): void {
		$id        = (string) ( $field['id'] ?? '' );
		$type      = (string) ( $field['type'] ?? 'text' );
		$metaKey   = DeveloperDiagnostics::metaKey( $id );
		$destMap   = DeveloperDiagnostics::destinationLabels();
		$condition = DeveloperDiagnostics::conditionSummary( $field );
		?>
		<section class="scfw-editor-section scfw-editor-dev-diagnostics">
			<div class="scfw-dev-section-header">
				<div class="scfw-dev-badge"><?php esc_html_e( 'Developer Diagnostics', 'felmivo-checkout-builder' ); ?></div>
				<p class="description" style="margin:4px 0 0; font-size:12px; color:#6b7280;">
					<?php esc_html_e( 'Read-only technical storage schema, internal keys, and lifecycle details.', 'felmivo-checkout-builder' ); ?>
				</p>
			</div>

			<div class="scfw-dev-grid">
				<div class="scfw-dev-field">
					<span class="scfw-dev-label"><?php esc_html_e( 'Field ID', 'felmivo-checkout-builder' ); ?></span>
					<code class="scfw-dev-code"><?php echo esc_html( $id ); ?></code>
				</div>
				<div class="scfw-dev-field">
					<span class="scfw-dev-label"><?php esc_html_e( 'Field Type', 'felmivo-checkout-builder' ); ?></span>
					<code class="scfw-dev-code"><?php echo esc_html( $type ); ?></code>
				</div>
				<div class="scfw-dev-field scfw-dev-field--meta">
					<span class="scfw-dev-label"><?php esc_html_e( 'Order Meta Key', 'felmivo-checkout-builder' ); ?></span>
					<div class="scfw-dev-key-row">
						<code class="scfw-dev-code scfw-dev-code--key"><?php echo esc_html( $metaKey ); ?></code>
						<button type="button" class="button button-small scfw-copy-key-btn" data-key="<?php echo esc_attr( $metaKey ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s is meta key */ __( 'Copy meta key %s', 'felmivo-checkout-builder' ), $metaKey ) ); ?>">
							<?php esc_html_e( 'Copy Key', 'felmivo-checkout-builder' ); ?>
						</button>
					</div>
				</div>
				<div class="scfw-dev-field">
					<span class="scfw-dev-label"><?php esc_html_e( 'Condition Status', 'felmivo-checkout-builder' ); ?></span>
					<code class="scfw-dev-code"><?php echo esc_html( $condition ); ?></code>
				</div>
			</div>

			<div class="scfw-dev-lifecycle-wrap">
				<span class="scfw-dev-label"><?php esc_html_e( 'Order Lifecycle Presentation Matrix', 'felmivo-checkout-builder' ); ?></span>
				<table class="scfw-dev-lifecycle-table" aria-label="<?php esc_attr_e( 'Lifecycle Matrix', 'felmivo-checkout-builder' ); ?>">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Surface', 'felmivo-checkout-builder' ); ?></th>
							<th scope="col" style="width:70px; text-align:center;"><?php esc_html_e( 'Active', 'felmivo-checkout-builder' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $destMap as $k => $destLabel ) : ?>
							<?php $isActive = DeveloperDiagnostics::isDestinationActive( $field, $k ); ?>
							<tr class="<?php echo $isActive ? 'scfw-dev-lifecycle--active' : 'scfw-dev-lifecycle--inactive'; ?>">
								<td><?php echo esc_html( $destLabel ); ?></td>
								<td style="text-align:center;">
									<?php if ( $isActive ) : ?>
										<span class="scfw-dev-check" title="<?php esc_attr_e( 'Active', 'felmivo-checkout-builder' ); ?>" aria-label="<?php esc_attr_e( 'Active', 'felmivo-checkout-builder' ); ?>">✓</span>
									<?php else : ?>
										<span class="scfw-dev-dash" title="<?php esc_attr_e( 'Inactive', 'felmivo-checkout-builder' ); ?>" aria-label="<?php esc_attr_e( 'Inactive', 'felmivo-checkout-builder' ); ?>">—</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
		<?php
	}
}
