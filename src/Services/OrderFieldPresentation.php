<?php
/**
 * Order field presentation service.
 *
 * @package SCFW\Services
 */

declare(strict_types=1);

namespace SCFW\Services;

use SCFW\Core\Field\FieldDestination;
use SCFW\Core\Field\FieldRepository;
use SCFW\Core\Field\FieldType;
use WC_Order;

/**
 * Resolves saved checkout-field values for order presentation surfaces.
 *
 * The service reads only through the WooCommerce order object, so the same
 * code works with HPOS and the legacy order storage without touching
 * wp_postmeta or custom tables. Values are resolved for the currently
 * configured enabled fields in FieldRepository order, and empty values are
 * omitted.
 *
 * @since 0.0.1
 */
final class OrderFieldPresentation {

	/**
	 * Order meta key prefix for saved checkout field values.
	 *
	 * Mirrors the prefix used by the checkout savers so this service never
	 * re-implements storage rules; it only maps a configured field to its
	 * already-saved order meta key.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	private const META_PREFIX = '_scfw_';

	/**
	 * Resolve the presentable field rows for an order.
	 *
	 * @since 0.0.1
	 *
	 * @param WC_Order               $order       WooCommerce order.
	 * @param FieldDestination|null  $destination Target presentation destination, or null for all.
	 *
	 * @return array<int, array{id: string, label: string, value: string}> Presentable rows in configured order.
	 */
	public static function rowsFor( WC_Order $order, ?FieldDestination $destination = null ): array {
		$rows = array();

		foreach ( FieldRepository::get() as $field ) {
			if ( null !== $destination && ! $field->hasDestination( $destination ) ) {
				continue;
			}

			$value = $order->get_meta( self::META_PREFIX . $field->getId(), true );

			if ( ! is_scalar( $value ) || '' === (string) $value ) {
				continue;
			}

			$formatted = (string) $value;

			if ( FieldType::CHECKBOX === $field->getType() ) {
				if ( '1' === $formatted || 1 === $value || true === $value ) {
					$formatted = __( 'Yes', 'felmivo-checkout-builder' );
				} elseif ( '0' === $formatted || 0 === $value || false === $value ) {
					$formatted = __( 'No', 'felmivo-checkout-builder' );
				}
			}

			$rows[] = array(
				'id'    => $field->getId(),
				'label' => $field->getLabel(),
				'value' => $formatted,
			);
		}

		return $rows;
	}
}
