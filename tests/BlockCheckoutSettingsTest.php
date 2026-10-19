<?php
/**
 * Block checkout settings tests.
 *
 * @package SCFW\Tests
 */

declare(strict_types=1);

namespace SCFW\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SCFW\Core\Field\Field;
use SCFW\Core\Field\FieldFactory;
use SCFW\Core\Field\FieldType;
use SCFW\Frontend\BlockCheckout;

/**
 * Verifies the Blocks API field settings built from Core field definitions,
 * in particular the native select options mapping.
 */
final class BlockCheckoutSettingsTest extends TestCase {

	/**
	 * Invoke the private settings builder for a field.
	 *
	 * @param Field $field Core field definition.
	 *
	 * @return array<string, mixed> Blocks API field options.
	 */
	private function settings( Field $field ): array {
		$method = new ReflectionMethod( BlockCheckout::class, 'settings' );

		return $method->invoke( new BlockCheckout(), $field );
	}

	public function testSelectSettingsIncludeNativeOptions(): void {
		$field = FieldFactory::create(
			'delivery',
			'Delivery Slot',
			FieldType::SELECT,
			true,
			'',
			'',
			array( 'Morning', 'Evening' )
		);

		$settings = $this->settings( $field );

		$this->assertSame( 'scfw/delivery', $settings['id'] );
		$this->assertSame( 'select', $settings['type'] );
		$this->assertTrue( $settings['required'] );
		$this->assertSame(
			array(
				array( 'value' => 'Morning', 'label' => 'Morning' ),
				array( 'value' => 'Evening', 'label' => 'Evening' ),
			),
			$settings['options']
		);
	}

	public function testTextSettingsDoNotIncludeOptions(): void {
		$settings = $this->settings(
			FieldFactory::create( 'notes', 'Notes', FieldType::TEXT )
		);

		$this->assertSame( 'text', $settings['type'] );
		$this->assertArrayNotHasKey( 'options', $settings );
	}

	public function testCheckboxSettingsDoNotIncludeOptions(): void {
		$settings = $this->settings(
			FieldFactory::create( 'agree', 'Agree', FieldType::CHECKBOX )
		);

		$this->assertSame( 'checkbox', $settings['type'] );
		$this->assertArrayNotHasKey( 'options', $settings );
	}

	public function testDateSettingsRegisterAsTextWithNativeDateMarker(): void {
		$settings = $this->settings(
			FieldFactory::create( 'born', 'Date of Birth', FieldType::DATE )
		);

		$this->assertSame( 'text', $settings['type'] );
		$this->assertSame( array( 'data-scfw-type' => 'date' ), $settings['attributes'] );
		$this->assertArrayNotHasKey( 'options', $settings );
	}

	public function testNonDateSettingsDoNotCarryAttributes(): void {
		$text = $this->settings(
			FieldFactory::create( 'notes', 'Notes', FieldType::TEXT )
		);
		$box  = $this->settings(
			FieldFactory::create( 'agree', 'Agree', FieldType::CHECKBOX )
		);

		$this->assertArrayNotHasKey( 'attributes', $text );
		$this->assertArrayNotHasKey( 'attributes', $box );
	}

	public function testRadioMapsToNativeSelectWithOptions(): void {
		$settings = $this->settings(
			FieldFactory::create( 'color', 'Color', FieldType::RADIO, true, '', '', array( 'Red', 'Blue' ) )
		);

		$this->assertSame( 'scfw/color', $settings['id'] );
		$this->assertSame( 'select', $settings['type'] );
		$this->assertTrue( $settings['required'] );
		$this->assertSame(
			array(
				array( 'value' => 'Red', 'label' => 'Red' ),
				array( 'value' => 'Blue', 'label' => 'Blue' ),
			),
			$settings['options']
		);
	}

	public function testConditionalRequiredFieldRegistersAsNonRequiredForBlocksApi(): void {
		$field = FieldFactory::create(
			'gift_msg',
			'Gift Message',
			FieldType::TEXT,
			true,
			'',
			'',
			array(),
			'',
			array(),
			array(
				'field'    => 'is_gift',
				'operator' => 'equals',
				'value'    => '1',
			)
		);

		$settings = $this->settings( $field );
		$this->assertSame( 'scfw/gift_msg', $settings['id'] );
		$this->assertFalse( $settings['required'], 'Conditional required fields must register as false at the Blocks API level to allow conditional location evaluation.' );
	}

	public function testUnconditionalRequiredFieldRegistersAsRequired(): void {
		$field = FieldFactory::create(
			'instructions',
			'Instructions',
			FieldType::TEXT,
			true
		);

		$settings = $this->settings( $field );
		$this->assertTrue( $settings['required'] );
	}

	public function testValidateFieldBypassesEmptyCheckWhenFieldIsConditional(): void {
		$block = new BlockCheckout();
		$field = array( 'id' => 'scfw/test_field' );

		// Configure test field with condition and required in FieldRepository options.
		$GLOBALS['scfw_test_options'] = array(
			'scfw_options' => array(
				'enabled' => 1,
				'fields'  => array(
					array(
						'id'         => 'test_field',
						'label'      => 'Test Field',
						'type'       => 'text',
						'required'   => true,
						'enabled'    => true,
						'conditions' => array(
							'field'    => 'trigger',
							'operator' => 'equals',
							'value'    => '1',
						),
					),
				),
			),
		);

		$result = $block->validateField( '', $field );
		$this->assertTrue( $result, 'Empty value must pass validateField for conditional fields so validateConditionalFields can evaluate in location context.' );
	}

	public function testValidateConditionalFieldsEnforcesRequiredWhenConditionIsMet(): void {
		$block = new BlockCheckout();

		$GLOBALS['scfw_test_options'] = array(
			'scfw_options' => array(
				'enabled' => 1,
				'fields'  => array(
					array(
						'id'         => 'trigger',
						'label'      => 'Trigger',
						'type'       => 'checkbox',
						'required'   => false,
						'enabled'    => true,
					),
					array(
						'id'         => 'gift_msg',
						'label'      => 'Gift Message',
						'type'       => 'text',
						'required'   => true,
						'enabled'    => true,
						'conditions' => array(
							'field'    => 'trigger',
							'operator' => 'equals',
							'value'    => '1',
						),
					),
				),
			),
		);

		// Case A: Trigger is 1, gift_msg is empty -> Error expected
		$errors_a = new \WP_Error();
		$fields_a = array(
			'scfw/trigger'  => '1',
			'scfw/gift_msg' => '',
		);
		$block->validateConditionalFields( $errors_a, $fields_a, 'other' );
		$this->assertContains( 'woocommerce_required_checkout_field', $errors_a->get_error_codes() );

		// Case B: Trigger is 0, gift_msg is empty -> No error expected
		$errors_b = new \WP_Error();
		$fields_b = array(
			'scfw/trigger'  => '0',
			'scfw/gift_msg' => '',
		);
		$block->validateConditionalFields( $errors_b, $fields_b, 'other' );
		$this->assertEmpty( $errors_b->get_error_codes() );

		// Case C: Trigger is 1, gift_msg is filled -> No error expected
		$errors_c = new \WP_Error();
		$fields_c = array(
			'scfw/trigger'  => '1',
			'scfw/gift_msg' => 'Special surprise!',
		);
		$block->validateConditionalFields( $errors_c, $fields_c, 'other' );
		$this->assertEmpty( $errors_c->get_error_codes() );
	}

	public function testOptionalRadioMapsToNativeSelectWithOptions(): void {
		$settings = $this->settings(
			FieldFactory::create( 'shade', 'Shade', FieldType::RADIO, false, '', '', array( 'Light', 'Dark' ) )
		);

		$this->assertSame( 'select', $settings['type'] );
		$this->assertFalse( $settings['required'] );
		$this->assertSame(
			array(
				array( 'value' => 'Light', 'label' => 'Light' ),
				array( 'value' => 'Dark', 'label' => 'Dark' ),
			),
			$settings['options']
		);
	}
}
