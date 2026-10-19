<?php
/**
 * Field Template Repository.
 *
 * @package SCFW\Core\Field
 */

declare(strict_types=1);

namespace SCFW\Core\Field;

/**
 * Single source of truth for built-in field use-case template packs.
 *
 * Provides pre-configured, duplicate-safe field definitions for common store
 * scenarios (Local Delivery, Gifting, B2B / Wholesale, Direct Contact).
 *
 * @since 0.0.1
 */
final class FieldTemplateRepository {

	/**
	 * Template pack identifier constants.
	 */
	public const LOCAL_DELIVERY = 'local_delivery';
	public const GIFTING        = 'gifting';
	public const B2B_WHOLESALE  = 'b2b_wholesale';
	public const DIRECT_CONTACT = 'direct_contact';

	/**
	 * Get all registered template packs.
	 *
	 * @since 0.0.1
	 *
	 * @return array<string, array{id: string, title: string, description: string, fields: array<int, array<string, mixed>>}>
	 */
	public static function all(): array {
		return array(
			self::LOCAL_DELIVERY => self::getLocalDeliveryTemplate(),
			self::GIFTING        => self::getGiftingTemplate(),
			self::B2B_WHOLESALE  => self::getB2bWholesaleTemplate(),
			self::DIRECT_CONTACT => self::getDirectContactTemplate(),
		);
	}

	/**
	 * Get a specific template pack by id.
	 *
	 * @since 0.0.1
	 *
	 * @param string $template_id Template identifier.
	 *
	 * @return array{id: string, title: string, description: string, fields: array<int, array<string, mixed>>}|null Template data or null if not found.
	 */
	public static function get( string $template_id ): ?array {
		$all = self::all();
		return $all[ $template_id ] ?? null;
	}

	/**
	 * Generate duplicate-safe field definitions ready to append to an existing field collection.
	 *
	 * Inspects existing field IDs and generates unique IDs where collisions exist.
	 * When a controlling field's ID is modified, internal condition references within the
	 * template pack are automatically rewritten to match the new unique ID.
	 *
	 * @since 0.0.1
	 *
	 * @param string               $template_id Template pack ID.
	 * @param array<string, mixed> $existing    Existing configured field keys/array.
	 *
	 * @return array<int, array<string, mixed>> List of normalized, unique field definition arrays.
	 */
	public static function generateFields( string $template_id, array $existing ): array {
		$template = self::get( $template_id );
		if ( null === $template ) {
			return array();
		}

		$existing_ids = array();
		foreach ( $existing as $key => $val ) {
			if ( is_string( $key ) ) {
				$existing_ids[ sanitize_key( $key ) ] = true;
			}
			if ( is_array( $val ) && isset( $val['id'] ) && is_scalar( $val['id'] ) ) {
				$existing_ids[ sanitize_key( (string) $val['id'] ) ] = true;
			}
		}

		$id_map          = array();
		$prepared_fields = array();

		// Step 1: Assign unique IDs and build rename mapping.
		foreach ( $template['fields'] as $field_def ) {
			$orig_id = (string) $field_def['id'];
			$label   = (string) $field_def['label'];

			if ( isset( $existing_ids[ $orig_id ] ) ) {
				$base   = $orig_id;
				$suffix = 2;
				$new_id = $base . '_' . $suffix;
				while ( isset( $existing_ids[ $new_id ] ) ) {
					++$suffix;
					$new_id = $base . '_' . $suffix;
				}
			} else {
				$new_id = $orig_id;
			}

			$existing_ids[ $new_id ] = true;
			$id_map[ $orig_id ]      = $new_id;

			$field_copy       = $field_def;
			$field_copy['id'] = $new_id;
			$prepared_fields[] = $field_copy;
		}

		// Step 2: Rewrite internal condition references if any controlling field was renamed.
		foreach ( $prepared_fields as &$field ) {
			if ( ! empty( $field['conditions'] ) && is_array( $field['conditions'] ) && ! empty( $field['conditions']['field'] ) ) {
				$target_controller = (string) $field['conditions']['field'];
				if ( isset( $id_map[ $target_controller ] ) ) {
					$field['conditions']['field'] = $id_map[ $target_controller ];
				}
			}
		}
		unset( $field );

		return $prepared_fields;
	}

	/**
	 * Apply a template pack to the saved configuration option.
	 *
	 * Appends new fields while strictly preserving existing fields, destinations,
	 * conditions, and ordering.
	 *
	 * @since 0.0.1
	 *
	 * @param string $template_id Template pack ID.
	 *
	 * @return bool True on success, false on failure or invalid template.
	 */
	public static function applyTemplate( string $template_id ): bool {
		$template = self::get( $template_id );
		if ( null === $template ) {
			return false;
		}

		$current_options = get_option( 'scfw_options', array() );
		$current_options = is_array( $current_options ) ? $current_options : array();

		$current_fields = isset( $current_options['fields'] ) && is_array( $current_options['fields'] )
			? $current_options['fields']
			: FieldRepository::config();

		$new_fields = self::generateFields( $template_id, $current_fields );
		if ( empty( $new_fields ) ) {
			return false;
		}

		$merged_fields = $current_fields;
		foreach ( $new_fields as $field_def ) {
			$normalized = FieldRepository::normalizeFieldEntry( $field_def, true );
			if ( null !== $normalized ) {
				$merged_fields[ $normalized['id'] ] = $normalized;
			}
		}

		$current_options['fields'] = $merged_fields;
		if ( ! isset( $current_options['enabled'] ) ) {
			$current_options['enabled'] = 1;
		}

		return (bool) update_option( 'scfw_options', $current_options );
	}

	/**
	 * Handle admin_post_scfw_apply_template action from admin settings.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function handleApply(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to modify checkout fields.', 'felmivo-checkout-builder' ) );
		}

		check_admin_referer( 'scfw_apply_template' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$template_id = isset( $_POST['template_id'] ) && is_scalar( $_POST['template_id'] ) ? sanitize_key( (string) $_POST['template_id'] ) : '';

		if ( '' === $template_id || ! self::applyTemplate( $template_id ) ) {
			add_settings_error( 'scfw', 'template_apply_failed', __( 'Could not apply template pack.', 'felmivo-checkout-builder' ), 'error' );
		} else {
			$template = self::get( $template_id );
			$title    = $template['title'] ?? __( 'Template', 'felmivo-checkout-builder' );
			add_settings_error(
				'scfw',
				'template_applied',
				sprintf(
					/* translators: %s is template name */
					__( 'Applied "%s" template pack successfully.', 'felmivo-checkout-builder' ),
					$title
				),
				'success'
			);
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=scfw-fields&settings-updated=true' ) );
		exit;
	}

	/**
	 * Template A: Local Delivery.
	 *
	 * @return array{id: string, title: string, description: string, fields: array<int, array<string, mixed>>}
	 */
	private static function getLocalDeliveryTemplate(): array {
		return array(
			'id'          => self::LOCAL_DELIVERY,
			'title'       => __( 'Local Delivery', 'felmivo-checkout-builder' ),
			'description' => __( 'Collect delivery date, preferred time slot, and special drop-off instructions for local courier dispatch.', 'felmivo-checkout-builder' ),
			'fields'      => array(
				array(
					'id'           => 'preferred_date',
					'label'        => __( 'Preferred Delivery Date', 'felmivo-checkout-builder' ),
					'type'         => FieldType::DATE->value,
					'required'     => false,
					'enabled'      => true,
					'placeholder'  => 'YYYY-MM-DD',
					'description'  => __( 'Select the best date to receive your order.', 'felmivo-checkout-builder' ),
					'default'      => '',
					'options'      => array(),
					'destinations' => FieldDestination::all(),
					'conditions'   => null,
					'rules'        => array(),
				),
				array(
					'id'           => 'delivery_time_slot',
					'label'        => __( 'Delivery Time Slot', 'felmivo-checkout-builder' ),
					'type'         => FieldType::SELECT->value,
					'required'     => false,
					'enabled'      => true,
					'placeholder'  => __( 'Select a time slot', 'felmivo-checkout-builder' ),
					'description'  => __( 'Choose your preferred delivery window.', 'felmivo-checkout-builder' ),
					'default'      => '',
					'options'      => array(
						__( 'Morning', 'felmivo-checkout-builder' ),
						__( 'Afternoon', 'felmivo-checkout-builder' ),
						__( 'Evening', 'felmivo-checkout-builder' ),
					),
					'destinations' => FieldDestination::all(),
					'conditions'   => null,
					'rules'        => array(),
				),
				array(
					'id'           => 'delivery_instructions',
					'label'        => __( 'Delivery Instructions', 'felmivo-checkout-builder' ),
					'type'         => FieldType::TEXTAREA->value,
					'required'     => false,
					'enabled'      => true,
					'placeholder'  => __( 'Gate code, safe drop location, or special notes...', 'felmivo-checkout-builder' ),
					'description'  => __( 'Provide special notes for the courier.', 'felmivo-checkout-builder' ),
					'default'      => '',
					'options'      => array(),
					'destinations' => FieldDestination::all(),
					'conditions'   => null,
					'rules'        => array(),
				),
			),
		);
	}

	/**
	 * Template B: Gifting.
	 *
	 * @return array{id: string, title: string, description: string, fields: array<int, array<string, mixed>>}
	 */
	private static function getGiftingTemplate(): array {
		return array(
			'id'          => self::GIFTING,
			'title'       => __( 'Gifting', 'felmivo-checkout-builder' ),
			'description' => __( 'Allow buyers to request gift wrapping, write a personalized gift message, and specify the recipient name.', 'felmivo-checkout-builder' ),
			'fields'      => array(
				array(
					'id'           => 'gift_wrapping',
					'label'        => __( 'Gift Wrapping', 'felmivo-checkout-builder' ),
					'type'         => FieldType::CHECKBOX->value,
					'required'     => false,
					'enabled'      => true,
					'placeholder'  => '',
					'description'  => __( 'Check if this order is a gift requiring decorative packaging.', 'felmivo-checkout-builder' ),
					'default'      => '0',
					'options'      => array(),
					'destinations' => FieldDestination::all(),
					'conditions'   => null,
					'rules'        => array(),
				),
				array(
					'id'           => 'gift_message',
					'label'        => __( 'Gift Message', 'felmivo-checkout-builder' ),
					'type'         => FieldType::TEXTAREA->value,
					'required'     => false,
					'enabled'      => true,
					'placeholder'  => __( 'Write a personalized note for the recipient...', 'felmivo-checkout-builder' ),
					'description'  => __( 'Included in a gift card with the shipment.', 'felmivo-checkout-builder' ),
					'default'      => '',
					'options'      => array(),
					'destinations' => FieldDestination::all(),
					'conditions'   => array(
						'field'    => 'gift_wrapping',
						'operator' => 'equals',
						'value'    => 'yes',
					),
					'rules'        => array(),
				),
				array(
					'id'           => 'recipient_name',
					'label'        => __( 'Recipient Name', 'felmivo-checkout-builder' ),
					'type'         => FieldType::TEXT->value,
					'required'     => false,
					'enabled'      => true,
					'placeholder'  => __( 'Recipient Full Name', 'felmivo-checkout-builder' ),
					'description'  => __( 'Name of the person receiving the gift.', 'felmivo-checkout-builder' ),
					'default'      => '',
					'options'      => array(),
					'destinations' => FieldDestination::all(),
					'conditions'   => null,
					'rules'        => array(),
				),
			),
		);
	}

	/**
	 * Template C: B2B / Wholesale.
	 *
	 * @return array{id: string, title: string, description: string, fields: array<int, array<string, mixed>>}
	 */
	private static function getB2bWholesaleTemplate(): array {
		return array(
			'id'          => self::B2B_WHOLESALE,
			'title'       => __( 'B2B / Wholesale', 'felmivo-checkout-builder' ),
			'description' => __( 'Capture company name, tax registration/VAT number, and internal purchase order references for business buyers.', 'felmivo-checkout-builder' ),
			'fields'      => array(
				array(
					'id'           => 'company_name',
					'label'        => __( 'Company Name', 'felmivo-checkout-builder' ),
					'type'         => FieldType::TEXT->value,
					'required'     => false,
					'enabled'      => true,
					'placeholder'  => __( 'e.g. Acme Corp Ltd.', 'felmivo-checkout-builder' ),
					'description'  => __( 'Registered business entity name.', 'felmivo-checkout-builder' ),
					'default'      => '',
					'options'      => array(),
					'destinations' => FieldDestination::all(),
					'conditions'   => null,
					'rules'        => array(),
				),
				array(
					'id'           => 'tax_vat_number',
					'label'        => __( 'Tax / VAT Number', 'felmivo-checkout-builder' ),
					'type'         => FieldType::TEXT->value,
					'required'     => false,
					'enabled'      => true,
					'placeholder'  => __( 'e.g. GB123456789 or Tax ID', 'felmivo-checkout-builder' ),
					'description'  => __( 'Business tax identification number for invoice issuance.', 'felmivo-checkout-builder' ),
					'default'      => '',
					'options'      => array(),
					'destinations' => FieldDestination::all(),
					'conditions'   => null,
					'rules'        => array(),
				),
				array(
					'id'           => 'po_reference',
					'label'        => __( 'Purchase Order Reference', 'felmivo-checkout-builder' ),
					'type'         => FieldType::TEXT->value,
					'required'     => false,
					'enabled'      => true,
					'placeholder'  => __( 'e.g. PO-98421', 'felmivo-checkout-builder' ),
					'description'  => __( 'Internal reference for procurement matching.', 'felmivo-checkout-builder' ),
					'default'      => '',
					'options'      => array(),
					'destinations' => FieldDestination::all(),
					'conditions'   => null,
					'rules'        => array(),
				),
			),
		);
	}

	/**
	 * Template D: Direct Contact.
	 *
	 * @return array{id: string, title: string, description: string, fields: array<int, array<string, mixed>>}
	 */
	private static function getDirectContactTemplate(): array {
		return array(
			'id'          => self::DIRECT_CONTACT,
			'title'       => __( 'Direct Contact', 'felmivo-checkout-builder' ),
			'description' => __( 'Provide an alternative phone line and ask customers for their preferred communication channel.', 'felmivo-checkout-builder' ),
			'fields'      => array(
				array(
					'id'           => 'alt_phone',
					'label'        => __( 'Alternative Phone', 'felmivo-checkout-builder' ),
					'type'         => FieldType::TEL->value,
					'required'     => false,
					'enabled'      => true,
					'placeholder'  => __( '+1 (555) 000-0000', 'felmivo-checkout-builder' ),
					'description'  => __( 'Secondary phone number if primary recipient is unreachable.', 'felmivo-checkout-builder' ),
					'default'      => '',
					'options'      => array(),
					'destinations' => FieldDestination::all(),
					'conditions'   => null,
					'rules'        => array(),
				),
				array(
					'id'           => 'contact_channel',
					'label'        => __( 'Preferred Contact Method', 'felmivo-checkout-builder' ),
					'type'         => FieldType::SELECT->value,
					'required'     => false,
					'enabled'      => true,
					'placeholder'  => __( 'Choose contact method', 'felmivo-checkout-builder' ),
					'description'  => __( 'How should we reach out regarding your shipment?', 'felmivo-checkout-builder' ),
					'default'      => 'WhatsApp',
					'options'      => array(
						__( 'WhatsApp', 'felmivo-checkout-builder' ),
						__( 'SMS', 'felmivo-checkout-builder' ),
						__( 'Email', 'felmivo-checkout-builder' ),
					),
					'destinations' => FieldDestination::all(),
					'conditions'   => null,
					'rules'        => array(),
				),
			),
		);
	}
}
