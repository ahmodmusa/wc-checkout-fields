<?php
/**
 * Block checkout bootstrap.
 *
 * @package SCFW\Frontend
 */

declare(strict_types=1);

namespace SCFW\Frontend;

/**
 * Registers every WooCommerce Block Checkout hook.
 *
 * The bootstrap owns no business logic, rendering, or validation. It only
 * wires the WooCommerce Blocks additional checkout fields lifecycle in to the
 * BlockCheckout coordinator.
 *
 * @since 0.0.1
 */
final class BlockCheckoutBootstrap {

	/**
	 * Register the WooCommerce Block Checkout hooks.
	 *
	 * Fields are registered through the official additional checkout fields
	 * API so the Checkout block renders, collects and validates them natively.
	 * The order update callback mirrors the additional fields onto the
	 * `_scfw_` order meta convention. The date enhancement script loads only
	 * on checkout pages that actually render a visible DATE field.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function register(): void {
		$block = new BlockCheckout();

		add_action( 'woocommerce_init', array( $block, 'registerField' ) );
		add_action( 'wp_enqueue_scripts', array( $block, 'enqueueBlockAssets' ) );
		add_action( 'woocommerce_blocks_validate_location_order_fields', array( $block, 'validateConditionalFields' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $block, 'saveFields' ), 10, 2 );
	}
}
