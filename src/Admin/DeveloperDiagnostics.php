<?php
/**
 * Developer Diagnostics helper service.
 *
 * @package SCFW\Admin
 */

declare(strict_types=1);

namespace SCFW\Admin;

use SCFW\Core\Field\FieldDestination;

/**
 * Provides metadata generation, lifecycle mappings, and diagnostic representations
 * for developer mode inspections.
 *
 * @since 0.0.1
 */
final class DeveloperDiagnostics {

	/**
	 * Order meta key prefix for saved checkout field values.
	 *
	 * Matches OrderFieldPresentation and ClassicSaver.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	public const META_PREFIX = '_scfw_';

	/**
	 * Compute the canonical order meta key for a field ID.
	 *
	 * @since 0.0.1
	 *
	 * @param string $field_id Field identifier.
	 *
	 * @return string Canonical order meta key.
	 */
	public static function metaKey( string $field_id ): string {
		return self::META_PREFIX . sanitize_key( $field_id );
	}

	/**
	 * Get human-readable destination labels.
	 *
	 * @since 0.0.1
	 *
	 * @return array<string, string> Map of destination string to localized label.
	 */
	public static function destinationLabels(): array {
		return array(
			FieldDestination::ADMIN_ORDER->value    => __( 'Admin Order', 'felmivo-checkout-builder' ),
			FieldDestination::THANK_YOU->value      => __( 'Thank You', 'felmivo-checkout-builder' ),
			FieldDestination::MY_ACCOUNT->value     => __( 'My Account', 'felmivo-checkout-builder' ),
			FieldDestination::CUSTOMER_EMAIL->value => __( 'Customer Email', 'felmivo-checkout-builder' ),
			FieldDestination::ADMIN_EMAIL->value    => __( 'Admin Email', 'felmivo-checkout-builder' ),
		);
	}

	/**
	 * Check if a field has an active destination.
	 *
	 * @since 0.0.1
	 *
	 * @param array<string, mixed> $field       Field definition array.
	 * @param string               $destination Destination enum value.
	 *
	 * @return bool True if destination is active for this field.
	 */
	public static function isDestinationActive( array $field, string $destination ): bool {
		$destinations = isset( $field['destinations'] ) && is_array( $field['destinations'] )
			? $field['destinations']
			: FieldDestination::all();

		return in_array( $destination, $destinations, true );
	}

	/**
	 * Get formatted condition description for display.
	 *
	 * @since 0.0.1
	 *
	 * @param array<string, mixed> $field Field definition array.
	 *
	 * @return string Human-readable condition status or "None".
	 */
	public static function conditionSummary( array $field ): string {
		$conditions = isset( $field['conditions'] ) && is_array( $field['conditions'] ) ? $field['conditions'] : null;

		if ( empty( $conditions ) || empty( $conditions['field'] ) ) {
			return __( 'None', 'felmivo-checkout-builder' );
		}

		$parent   = (string) $conditions['field'];
		$operator = (string) ( $conditions['operator'] ?? 'equals' );
		$value    = (string) ( $conditions['value'] ?? '' );

		return sprintf( '%s %s %s', $parent, $operator, '' !== $value ? $value : "''" );
	}
}
