<?php
/**
 * Block checkout coordinator.
 *
 * @package SCFW\Frontend
 */

declare(strict_types=1);

namespace SCFW\Frontend;

use SCFW\Core\Field\Field;
use SCFW\Core\Field\FieldRepository;
use SCFW\Core\Field\FieldType;
use SCFW\Services\FieldVisibility;

/**
 * Coordinates the WooCommerce Block Checkout integration.
 *
 * The coordinator registers the Core field definitions with the official
 * WooCommerce Blocks additional checkout field API and mirrors the submitted
 * values onto the `_scfw_` order meta convention.
 *
 * Rendering, collection and lifecycle handling are provided by WooCommerce
 * itself once a field is registered. This class does not duplicate the
 * Classic Checkout pipeline and never writes legacy post meta directly.
 *
 * @since 0.0.1
 */
final class BlockCheckout {

	/**
	 * Namespace prefix prepended to Core field ids for the Blocks API.
	 *
	 * WooCommerce requires additional checkout field ids of the form
	 * namespace/name. The Core field id is used as the name and the plugin
	 * namespace is prepended here only.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	private const FIELD_NAMESPACE = 'scfw';

	/**
	 * Checkout location the registered field should be rendered in.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	private const LOCATION = 'order';

	/**
	 * Validator adapter.
	 *
	 * @since 0.0.1
	 *
	 * @var ClassicValidator
	 */
	private ClassicValidator $validator;

	/**
	 * Create a block checkout coordinator.
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		$this->validator = new ClassicValidator();
	}

	/**
	 * Register all Core field definitions with the Blocks API.
	 *
	 * Hooked to woocommerce_init.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function registerField(): void {
		$config = FieldRepository::config();

		foreach ( FieldRepository::get() as $field ) {
			if ( ! FieldVisibility::isVisible( $field->getId(), $config[ $field->getId() ] ?? array() ) ) {
				continue;
			}

			woocommerce_register_additional_checkout_field( $this->settings( $field ) );
		}
	}

	/**
	 * Enqueue the Block Checkout date field enhancement script.
	 *
	 * Hooked to wp_enqueue_scripts. The Blocks Additional Checkout Fields API
	 * supports text, select and checkbox only, so a Core DATE field renders
	 * as a text input with a `data-scfw-type="date"` marker. This script
	 * upgrades each marked input to a native date input, which the browser
	 * renders as a real calendar picker, and reapplies the upgrade whenever
	 * the Checkout block mounts a field again. It is idempotent and only
	 * acts when the configured fields actually render on the page, so the
	 * Classic Checkout and every other page stay untouched.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function enqueueBlockAssets(): void {
		if ( is_admin() || ! is_checkout() ) {
			return;
		}

		$script_version = (string) filemtime( SCFW_PATH . 'assets/frontend/block-checkout-date.js' );
		$style_version  = (string) filemtime( SCFW_PATH . 'assets/frontend/block-checkout-date.css' );

		wp_enqueue_style(
			'scfw-block-checkout-date',
			SCFW_URL . 'assets/frontend/block-checkout-date.css',
			array(),
			$style_version
		);

		wp_enqueue_script(
			'scfw-block-checkout-date',
			SCFW_URL . 'assets/frontend/block-checkout-date.js',
			array(),
			$script_version,
			true
		);

		// Expose condition configuration to Block frontend.
		$condition_map = array();
		foreach ( FieldRepository::config() as $id => $cfg ) {
			if ( ! empty( $cfg['conditions'] ) && is_array( $cfg['conditions'] ) ) {
				$condition_map[ $id ] = $cfg['conditions'];
			}
		}

		wp_localize_script(
			'scfw-block-checkout-date',
			'scfwBlockConditions',
			$condition_map
		);
	}



	/**
	 * Sanitize a submitted block checkout field value.
	 *
	 * Used as the official sanitize_callback. The registered field options
	 * are mapped back to the Core field before the shared sanitization runs.
	 *
	 * @since 0.0.1
	 *
	 * @param mixed $value Submitted value.
	 * @param array<string, mixed> $field Registered field options.
	 *
	 * @return string|bool Sanitized value.
	 */
	public function sanitizeField( mixed $value, array $field ): string|bool {
		$core = $this->coreField( $field );

		if ( null === $core ) {
			return '';
		}

		return $this->validator->sanitize( $core, $value );
	}

	/**
	 * Validate a field value with the shared Core Validator.
	 *
	 * Used as the official validate_callback.
	 *
	 * @since 0.0.1
	 *
	 * @param mixed $value Submitted value.
	 * @param array<string, mixed> $field Registered field options.
	 *
	 * @return bool|\WP_Error True when valid, an error otherwise.
	 */
	public function validateField( mixed $value, array $field ): bool|\WP_Error {
		$core = $this->coreField( $field );

		if ( null === $core ) {
			return true;
		}

		if ( $core->hasConditions() && ( '' === $value || null === $value ) ) {
			return true;
		}

		$result = $this->validator->validate( $core, $value );

		if ( $result->isValid() ) {
			return true;
		}

		$errors = new \WP_Error();

		foreach ( $result->getErrors() as $error ) {
			$errors->add( 'scfw_' . $core->getId(), $error->getMessage() );
		}

		return $errors;
	}

	/**
	 * Validate conditional required fields in the context of all submitted location fields.
	 *
	 * Hooked to woocommerce_blocks_validate_location_order_fields.
	 *
	 * @since 0.0.2
	 *
	 * @param \WP_Error            $errors Error collection.
	 * @param array<string, mixed> $fields All submitted fields in this location.
	 * @param string               $group  Location group name.
	 *
	 * @return void
	 */
	public function validateConditionalFields( \WP_Error $errors, array $fields, string $group ): void {
		$config = FieldRepository::config();

		$field_values = array();
		foreach ( $fields as $key => $val ) {
			$raw_key                  = str_starts_with( $key, self::FIELD_NAMESPACE . '/' )
				? substr( $key, strlen( self::FIELD_NAMESPACE . '/' ) )
				: $key;
			$field_values[ $raw_key ] = $val;
		}

		foreach ( FieldRepository::get() as $field ) {
			if ( ! $field->hasConditions() || ! $field->getRequired() ) {
				continue;
			}

			if ( ! FieldVisibility::isVisible( $field->getId(), $config[ $field->getId() ] ?? array(), $field_values ) ) {
				continue;
			}

			$raw_value = $field_values[ $field->getId() ] ?? '';
			$sanitized = $this->validator->sanitize( $field, $raw_value );
			$result    = $this->validator->validate( $field, $sanitized );

			if ( ! $result->isValid() ) {
				foreach ( $result->getErrors() as $error ) {
					$errors->add(
						'woocommerce_required_checkout_field',
						$error->getMessage(),
						array( 'key' => $this->blockId( $field ) )
					);
				}
			}
		}
	}

	/**
	 * Persist the block checkout field values to the SCFW order meta keys.
	 *
	 * Hooked to woocommerce_store_api_checkout_update_order_from_request. The
	 * official Blocks API does not use the `_scfw_` convention, so the
	 * submitted values already validated on the route are mirrored onto the
	 * SCFW keys here. The order receives the meta through the standard
	 * WC_Data update flow, which is HPOS-compatible.
	 *
	 * @since 0.0.1
	 *
	 * @param \WC_Order          $order   Order being updated.
	 * @param \WP_REST_Request   $request Checkout request.
	 *
	 * @return void
	 */
	public function saveFields( \WC_Order $order, \WP_REST_Request $request ): void {
		$additional = isset( $request['additional_fields'] ) ? (array) $request['additional_fields'] : array();
		$config     = FieldRepository::config();

		// Map additional fields with raw field ids for ConditionEvaluator.
		$field_values = array();
		foreach ( $additional as $key => $val ) {
			$raw_key                  = str_starts_with( $key, self::FIELD_NAMESPACE . '/' ) ? substr( $key, strlen( self::FIELD_NAMESPACE . '/' ) ) : $key;
			$field_values[ $raw_key ] = $val;
		}

		foreach ( FieldRepository::get() as $field ) {
			if ( ! FieldVisibility::isVisible( $field->getId(), $config[ $field->getId() ] ?? array(), $field_values ) ) {
				continue;
			}

			$id = $this->blockId( $field );

			if ( ! isset( $additional[ $id ] ) ) {
				continue;
			}

			$value  = $this->validator->sanitize( $field, $additional[ $id ] );
			$result = $this->validator->validate( $field, $value );

			if ( ! $result->isValid() ) {
				continue;
			}

			$order->update_meta_data( '_scfw_' . $field->getId(), $this->toScalar( $value ) );
		}
	}


	/**
	 * Build the Blocks API field settings from a Core field definition.
	 *
	 * Native options are attached when the field renders as a select on the
	 * block. That covers Core SELECT fields and Core RADIO fields, which are
	 * presented as a native select because the Blocks Additional Checkout
	 * Fields API supports text, select and checkbox only (final Block radio
	 * presentation limitation). The Core type and options stay untouched.
	 *
	 * A Core DATE field is registered as text with a `data-scfw-type="date"`
	 * marker attribute because the Blocks API has no date type. The marker
	 * survives registration (data attributes are allowed) and reaches the
	 * rendered input, where the small block checkout script upgrades it to a
	 * native date input and its calendar picker. The stored value format is
	 * unchanged.
	 *
	 * @since 0.0.1
	 *
	 * @param Field $field Core field definition.
	 *
	 * @return array<string, mixed> Blocks API field options.
	 */
	private function settings( Field $field ): array {
		$settings = array(
			'id'                => $this->blockId( $field ),
			'label'             => $field->getLabel(),
			'optionalLabel'     => $field->getLabel(),
			'location'          => self::LOCATION,
			'type'              => $this->blockType( $field ),
			'required'          => ! $field->hasConditions() && $field->getRequired(),
			'sanitize_callback' => array( $this, 'sanitizeField' ),
			'validate_callback' => array( $this, 'validateField' ),
		);

		if ( FieldType::SELECT === $field->getType() || FieldType::RADIO === $field->getType() ) {
			$settings['options'] = $this->blockOptions( $field );
		}

		$attributes = array();

		if ( FieldType::DATE === $field->getType() ) {
			$attributes['data-scfw-type'] = 'date';
		}

		if ( ! empty( $attributes ) ) {
			$settings['attributes'] = $attributes;
		}

		return $settings;
	}



	/**
	 * Map the Core choice options to the Blocks select options format.
	 *
	 * Used for Core SELECT fields and for Core RADIO fields, which are
	 * presented as a native select on the block. Core options are single
	 * strings that act as both the submitted value and the displayed label.
	 * The Blocks API requires each select option to be an array with value
	 * and label properties, so the same string is used for both.
	 *
	 * @since 0.0.1
	 *
	 * @param Field $field Core field definition.
	 *
	 * @return array<int, array{value: string, label: string}> Blocks select options.
	 */
	private function blockOptions( Field $field ): array {
		$options = array();

		foreach ( $field->getOptions() as $option ) {
			$options[] = array(
				'value' => $option,
				'label' => $option,
			);
		}

		return $options;
	}

	/**
	 * Map a Core field type to a type the Blocks API supports.
	 *
	 * The Blocks Additional Checkout Fields API supports text, select and
	 * checkbox only, so a native radio is impossible (final Block radio
	 * presentation limitation). Core RADIO fields are presented as a native
	 * select using the configured options; the submitted value stays the
	 * selected option string. TEXTAREA maps to text. This is an
	 * adapter-level presentation mapping and never changes the Core FieldType
	 * or the shared Validator.
	 *
	 * @since 0.0.1
	 *
	 * @param Field $field Core field definition.
	 *
	 * @return string Supported Blocks field type.
	 */
	private function blockType( Field $field ): string {
		return match ( $field->getType() ) {
			FieldType::SELECT   => 'select',
			FieldType::RADIO    => 'select',
			FieldType::CHECKBOX => 'checkbox',
			default             => 'text',
		};
	}

	/**
	 * Build the namespaced block id for a Core field.
	 *
	 * @since 0.0.1
	 *
	 * @param Field $field Core field definition.
	 *
	 * @return string Namespaced field id.
	 */
	private function blockId( Field $field ): string {
		return self::FIELD_NAMESPACE . '/' . $field->getId();
	}

	/**
	 * Resolve the Core field definition for registered field options.
	 *
	 * @since 0.0.1
	 *
	 * @param array<string, mixed> $field Registered field options.
	 *
	 * @return Field|null Matching Core field, or null when unknown.
	 */
	private function coreField( array $field ): ?Field {
		$id = $field['id'] ?? '';

		foreach ( FieldRepository::get() as $core ) {
			if ( $this->blockId( $core ) === $id ) {
				return $core;
			}
		}

		return null;
	}

	/**
	 * Normalize a value into a scalar string for storage.
	 *
	 * @since 0.0.1
	 *
	 * @param mixed $value Validated value.
	 *
	 * @return string Scalar representation.
	 */
	private function toScalar( mixed $value ): string {
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return '';
	}
}
