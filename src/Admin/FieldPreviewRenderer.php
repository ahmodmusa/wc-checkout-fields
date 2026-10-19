<?php
/**
 * Standalone Field Preview Renderer for Admin Builder.
 *
 * @package SCFW\Admin
 */

declare(strict_types=1);

namespace SCFW\Admin;

use SCFW\Core\Field\FieldType;

/**
 * Generates initial and fallback HTML preview for checkout fields inside the
 * Field Builder modal, following Classic Checkout markup conventions safely.
 *
 * @since 0.0.1
 */
final class FieldPreviewRenderer {

	/**
	 * Render the preview markup for a field configuration.
	 *
	 * @since 0.0.1
	 *
	 * @param array<string, mixed> $field Field configuration array.
	 *
	 * @return string Safe HTML preview string.
	 */
	public static function render( array $field ): string {
		$id          = (string) ( $field['id'] ?? 'preview_field' );
		$label       = (string) ( $field['label'] ?? __( 'Untitled Field', 'felmivo-checkout-builder' ) );
		$type        = (string) ( $field['type'] ?? 'text' );
		$required    = ! empty( $field['required'] );
		$enabled     = ! isset( $field['enabled'] ) || ! empty( $field['enabled'] );
		$placeholder = (string) ( $field['placeholder'] ?? '' );
		$default     = (string) ( $field['default'] ?? '' );
		$description = (string) ( $field['description'] ?? '' );
		$options     = is_array( $field['options'] ?? null ) ? array_values( $field['options'] ) : array();
		$conditions  = $field['conditions'] ?? null;

		$preview_id = 'scfw_preview_input_' . \sanitize_key( $id );
		$is_choice  = in_array( $type, array( 'radio', 'select' ), true );

		ob_start();
		?>
		<div class="scfw-live-preview-box<?php echo $enabled ? '' : ' scfw-preview--disabled'; ?>" data-preview-for="<?php echo \esc_attr( $id ); ?>">
			<div class="scfw-preview-header">
				<div class="scfw-preview-meta-left">
					<span class="scfw-preview-badge"><?php esc_html_e( 'Customer Checkout Preview', 'felmivo-checkout-builder' ); ?></span>
					<?php if ( ! $enabled ) : ?>
						<span class="scfw-preview-status-badge scfw-preview-status--disabled"><?php esc_html_e( 'Field Disabled', 'felmivo-checkout-builder' ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $conditions ) && is_array( $conditions ) ) : ?>
					<div class="scfw-preview-condition-tag" title="<?php echo \esc_attr( sprintf( /* translators: %s condition */ __( 'Condition: %s', 'felmivo-checkout-builder' ), DeveloperDiagnostics::conditionSummary( $field ) ) ); ?>">
						<span class="dashicons dashicons-randomize" aria-hidden="true" style="font-size:13px; width:13px; height:13px; line-height:1.4;"></span>
						<span><?php echo \esc_html( sprintf( /* translators: %s is condition summary */ __( 'Conditional: %s', 'felmivo-checkout-builder' ), DeveloperDiagnostics::conditionSummary( $field ) ) ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<div class="scfw-preview-surface">
				<div class="scfw-preview-form-row form-row form-row-wide scfw-preview-type--<?php echo \esc_attr( $type ); ?>">
					<?php if ( 'checkbox' === $type ) : ?>
						<label class="checkbox scfw-preview-checkbox-label">
							<input type="checkbox" id="<?php echo \esc_attr( $preview_id ); ?>" class="input-checkbox" disabled <?php checked( ! empty( $default ), true ); ?> />
							<span class="scfw-preview-label-text"><?php echo \esc_html( $label ); ?></span>
							<?php if ( $required ) : ?>
								<abbr class="required" title="<?php esc_attr_e( 'required', 'felmivo-checkout-builder' ); ?>" style="color:#e11d48; margin-left:4px;">*</abbr>
							<?php endif; ?>
						</label>
					<?php else : ?>
						<label for="<?php echo \esc_attr( $preview_id ); ?>" class="scfw-preview-label">
							<span class="scfw-preview-label-text"><?php echo \esc_html( $label ); ?></span>
							<?php if ( $required ) : ?>
								<abbr class="required" title="<?php esc_attr_e( 'required', 'felmivo-checkout-builder' ); ?>" style="color:#e11d48; margin-left:4px;">*</abbr>
							<?php endif; ?>
						</label>

						<?php if ( 'textarea' === $type ) : ?>
							<textarea id="<?php echo \esc_attr( $preview_id ); ?>" class="input-text scfw-preview-textarea" placeholder="<?php echo \esc_attr( $placeholder ); ?>" readonly rows="3"><?php echo \esc_textarea( $default ); ?></textarea>
						<?php elseif ( 'select' === $type ) : ?>
							<select id="<?php echo \esc_attr( $preview_id ); ?>" class="select scfw-preview-select" disabled>
								<?php if ( ! empty( $placeholder ) ) : ?>
									<option value=""><?php echo \esc_html( $placeholder ); ?></option>
								<?php endif; ?>
								<?php if ( empty( $options ) ) : ?>
									<option value=""><?php esc_html_e( 'No options configured yet.', 'felmivo-checkout-builder' ); ?></option>
								<?php else : ?>
									<?php foreach ( $options as $opt ) : ?>
										<?php $optStr = (string) $opt; ?>
										<option value="<?php echo \esc_attr( $optStr ); ?>" <?php selected( $default, $optStr, true ); ?>><?php echo \esc_html( $optStr ); ?></option>
									<?php endforeach; ?>
								<?php endif; ?>
							</select>
						<?php elseif ( 'radio' === $type ) : ?>
							<div class="scfw-preview-radio-group">
								<?php if ( empty( $options ) ) : ?>
									<p class="scfw-preview-empty-notice"><?php esc_html_e( 'No options configured yet.', 'felmivo-checkout-builder' ); ?></p>
								<?php else : ?>
									<?php foreach ( $options as $rIdx => $opt ) : ?>
										<?php $optStr = (string) $opt; ?>
										<label class="scfw-preview-radio-option">
											<input type="radio" name="<?php echo \esc_attr( $preview_id ); ?>" value="<?php echo \esc_attr( $optStr ); ?>" disabled <?php checked( $default, $optStr, true ); ?> />
											<span><?php echo \esc_html( $optStr ); ?></span>
										</label>
									<?php endforeach; ?>
								<?php endif; ?>
							</div>
						<?php elseif ( 'number' === $type ) : ?>
							<input type="number" id="<?php echo \esc_attr( $preview_id ); ?>" class="input-text" placeholder="<?php echo \esc_attr( $placeholder ); ?>" value="<?php echo \esc_attr( $default ); ?>" readonly />
						<?php elseif ( 'date' === $type ) : ?>
							<input type="date" id="<?php echo \esc_attr( $preview_id ); ?>" class="input-text" value="<?php echo \esc_attr( $default ); ?>" readonly />
						<?php elseif ( 'email' === $type ) : ?>
							<input type="email" id="<?php echo \esc_attr( $preview_id ); ?>" class="input-text" placeholder="<?php echo \esc_attr( $placeholder ); ?>" value="<?php echo \esc_attr( $default ); ?>" readonly />
						<?php elseif ( 'tel' === $type ) : ?>
							<input type="tel" id="<?php echo \esc_attr( $preview_id ); ?>" class="input-text" placeholder="<?php echo \esc_attr( $placeholder ); ?>" value="<?php echo \esc_attr( $default ); ?>" readonly />
						<?php else : ?>
							<input type="text" id="<?php echo \esc_attr( $preview_id ); ?>" class="input-text" placeholder="<?php echo \esc_attr( $placeholder ); ?>" value="<?php echo \esc_attr( $default ); ?>" readonly />
						<?php endif; ?>

						<?php if ( ! empty( $description ) && 'textarea' === $type ) : ?>
							<span class="description scfw-preview-description"><?php echo \esc_html( $description ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
