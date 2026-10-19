<?php
/**
 * Hook registrar.
 *
 * @package SCFW\Core
 */

declare(strict_types=1);

namespace SCFW\Core;

use SCFW\Admin\AdminMenu;
use SCFW\Admin\FieldTransfer;
use SCFW\Admin\OrderDisplay;
use SCFW\Admin\Settings;
use SCFW\Frontend\BlockCheckoutBootstrap;
use SCFW\Frontend\CheckoutBootstrap;
use SCFW\Frontend\NativeCheckoutFieldSuppression;
use SCFW\Frontend\OrderEmail;
use SCFW\Frontend\OrderMyAccount;
use SCFW\Frontend\OrderThankYou;
use SCFW\REST\OrderResponse;
use SCFW\Services\WooCommerceChecker;

/**
 * Central registrar for WordPress hooks.
 *
 * @since 0.0.1
 */
final class HookRegistrar {

	/**
	 * Register all plugin hooks.
	 *
	 * Admin hooks remain registered regardless of the WooCommerce
	 * availability. WooCommerce-dependent checkout hooks are registered only
	 * when WooCommerce is active.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( WooCommerceChecker::isAvailable() ) {
			add_action( 'admin_menu', array( AdminMenu::class, 'register' ) );
			add_action( 'admin_menu', array( AdminMenu::class, 'reorderSubmenu' ), 999 );
			add_action( 'admin_init', array( Settings::class, 'register' ) );
			add_action( 'admin_enqueue_scripts', array( AdminMenu::class, 'enqueueAssets' ) );
			add_action( 'admin_post_scfw_export', array( FieldTransfer::class, 'export' ) );
			add_action( 'admin_post_scfw_import', array( FieldTransfer::class, 'import' ) );
			add_action( 'admin_post_scfw_apply_template', array( \SCFW\Core\Field\FieldTemplateRepository::class, 'handleApply' ) );

			CheckoutBootstrap::register();
			BlockCheckoutBootstrap::register();
			NativeCheckoutFieldSuppression::register();
			OrderDisplay::register();
			OrderEmail::register();
			OrderMyAccount::register();
			OrderThankYou::register();
			OrderResponse::register();
		} else {
			add_action( 'admin_notices', array( WooCommerceChecker::class, 'renderMissingNotice' ) );
		}
	}
}
