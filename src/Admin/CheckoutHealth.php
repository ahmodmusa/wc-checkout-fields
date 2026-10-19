<?php
/**
 * Checkout health diagnostic service.
 *
 * @package SCFW\Admin
 */

declare(strict_types=1);

namespace SCFW\Admin;

use SCFW\Core\Field\FieldRepository;
use SCFW\Core\Field\FieldType;
use SCFW\Services\ConditionEvaluator;

/**
 * Evaluates the configuration health of checkout fields.
 *
 * Inspects the current normalized field configuration for potential configuration
 * errors and risks (missing labels, empty options on choice fields, broken condition
 * targets, self-referencing conditions, or malformed conditions).
 *
 * @since 0.0.1
 */
final class CheckoutHealth {

	public const STATUS_HEALTHY = 'healthy';
	public const STATUS_WARNING = 'warning';

	public const CODE_MISSING_LABEL      = 'missing_label';
	public const CODE_EMPTY_CHOICE       = 'empty_choice_options';
	public const CODE_BROKEN_CONDITION   = 'broken_condition_reference';
	public const CODE_SELF_CONDITION     = 'self_referencing_condition';
	public const CODE_INVALID_CONDITION  = 'invalid_condition';

	/**
	 * Run health audit on a field configuration array or current saved config.
	 *
	 * @since 0.0.1
	 *
	 * @param array<string, array<string, mixed>>|null $config Optional fields config to evaluate. Defaults to FieldRepository::config().
	 *
	 * @return array{status: string, warnings: array<int, array{code: string, severity: string, field: string, field_label: string, message: string, suggestion: string}>}
	 */
	public static function audit( ?array $config = null ): array {
		if ( null === $config ) {
			$config = FieldRepository::config();
		}

		$warnings = array();
		$existing_ids = array();

		// Index all field IDs first to validate dependencies.
		foreach ( $config as $key => $field ) {
			$id = isset( $field['id'] ) && is_scalar( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : ( is_string( $key ) ? sanitize_key( $key ) : '' );
			if ( '' !== $id ) {
				$existing_ids[ $id ] = true;
			}
		}

		foreach ( $config as $key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$id      = isset( $field['id'] ) && is_scalar( $field['id'] ) ? sanitize_key( (string) $field['id'] ) : ( is_string( $key ) ? sanitize_key( $key ) : '' );
			$label   = isset( $field['label'] ) && is_scalar( $field['label'] ) ? trim( (string) $field['label'] ) : '';
			$type    = isset( $field['type'] ) && is_scalar( $field['type'] ) ? (string) $field['type'] : '';
			$enabled = ! empty( $field['enabled'] );

			$display_label = '' !== $label ? $label : ( '' !== $id ? $id : __( 'Untitled Field', 'felmivo-checkout-builder' ) );

			// Rule 1: Missing Label (for enabled fields).
			if ( $enabled && '' === $label ) {
				$warnings[] = array(
					'code'        => self::CODE_MISSING_LABEL,
					'severity'    => 'warning',
					'field'       => $id,
					'field_label' => $display_label,
					'message'     => sprintf(
						/* translators: %s is field identifier */
						__( 'Field "%s" has an empty label.', 'felmivo-checkout-builder' ),
						$display_label
					),
					'suggestion'  => __( 'Provide a clear label so customers know what to enter during checkout.', 'felmivo-checkout-builder' ),
				);
			}

			// Rule 2: Empty Choice Field (Select & Radio must have at least one valid option).
			if ( FieldType::SELECT->value === $type || FieldType::RADIO->value === $type ) {
				$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
				$valid_options = array_filter(
					$options,
					static function ( $opt ) {
						return is_scalar( $opt ) && '' !== trim( (string) $opt );
					}
				);

				if ( empty( $valid_options ) ) {
					$warnings[] = array(
						'code'        => self::CODE_EMPTY_CHOICE,
						'severity'    => 'warning',
						'field'       => $id,
						'field_label' => $display_label,
						'message'     => sprintf(
							/* translators: 1: field label, 2: field type */
							__( 'This %2$s field "%1$s" has no options configured.', 'felmivo-checkout-builder' ),
							$display_label,
							$type
						),
						'suggestion'  => __( 'Add at least one choice option so customers can select a value.', 'felmivo-checkout-builder' ),
					);
				}
			}

			// Rules 4, 5, 6: Condition Evaluation & Reference Checks.
			if ( isset( $field['conditions'] ) && null !== $field['conditions'] ) {
				$conditions = $field['conditions'];

				if ( ! is_array( $conditions ) ) {
					$warnings[] = array(
						'code'        => self::CODE_INVALID_CONDITION,
						'severity'    => 'warning',
						'field'       => $id,
						'field_label' => $display_label,
						'message'     => sprintf(
							/* translators: %s is field label */
							__( 'Condition on field "%s" is malformed.', 'felmivo-checkout-builder' ),
							$display_label
						),
						'suggestion'  => __( 'Re-save the condition settings or clear the condition.', 'felmivo-checkout-builder' ),
					);
					continue;
				}

				$controller = isset( $conditions['field'] ) && is_scalar( $conditions['field'] ) ? sanitize_key( (string) $conditions['field'] ) : '';
				$operator   = isset( $conditions['operator'] ) && is_scalar( $conditions['operator'] ) ? sanitize_key( (string) $conditions['operator'] ) : '';

				if ( '' === $controller ) {
					// Empty controller without condition is skipped (unconditional).
					continue;
				}

				// Rule 5: Self-reference.
				if ( $controller === $id ) {
					$warnings[] = array(
						'code'        => self::CODE_SELF_CONDITION,
						'severity'    => 'warning',
						'field'       => $id,
						'field_label' => $display_label,
						'message'     => sprintf(
							/* translators: %s is field label */
							__( 'Field "%s" has a condition referencing itself.', 'felmivo-checkout-builder' ),
							$display_label
						),
						'suggestion'  => __( 'Select a different controlling field or remove the condition.', 'felmivo-checkout-builder' ),
					);
					continue;
				}

				// Rule 4: Broken condition reference.
				if ( ! isset( $existing_ids[ $controller ] ) ) {
					$warnings[] = array(
						'code'        => self::CODE_BROKEN_CONDITION,
						'severity'    => 'warning',
						'field'       => $id,
						'field_label' => $display_label,
						'message'     => sprintf(
							/* translators: 1: field label, 2: missing controller field id */
							__( 'Condition on "%1$s" references missing field "%2$s".', 'felmivo-checkout-builder' ),
							$display_label,
							$controller
						),
						'suggestion'  => __( 'Update the controlling field or remove the condition.', 'felmivo-checkout-builder' ),
					);
					continue;
				}

				// Rule 6: Invalid operator.
				if ( ! in_array( $operator, ConditionEvaluator::supportedOperators(), true ) ) {
					$warnings[] = array(
						'code'        => self::CODE_INVALID_CONDITION,
						'severity'    => 'warning',
						'field'       => $id,
						'field_label' => $display_label,
						'message'     => sprintf(
							/* translators: 1: field label, 2: invalid operator */
							__( 'Condition on "%1$s" uses unsupported operator "%2$s".', 'felmivo-checkout-builder' ),
							$display_label,
							$operator
						),
						'suggestion'  => __( 'Select a valid operator such as "equals" or "not_equals".', 'felmivo-checkout-builder' ),
					);
				}
			}
		}

		return array(
			'status'   => empty( $warnings ) ? self::STATUS_HEALTHY : self::STATUS_WARNING,
			'warnings' => $warnings,
		);
	}
}
