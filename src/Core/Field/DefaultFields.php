<?php
/**
 * Default fields provider.
 *
 * @package SCFW\Core\Field
 */

declare(strict_types=1);

namespace SCFW\Core\Field;

/**
 * Provides the default checkout field definitions.
 *
 * @since 0.0.1
 */
final class DefaultFields {

	/**
	 * Get the default field definitions.
	 *
	 * The first hardcoded demonstration field is the delivery instructions
	 * textarea saved to the _scfw_delivery_instructions order meta key.
	 *
	 * @since 0.0.1
	 *
	 * @return array<int, Field> Default field definitions.
	 */
	public static function get(): array {
		return array(
			FieldFactory::create(
				'delivery_instructions',
				__( 'Delivery Instructions', 'felmivo-checkout-builder' ),
				FieldType::TEXTAREA
			),
		);
	}
}