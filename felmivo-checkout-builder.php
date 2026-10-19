<?php
/**
 * Plugin Name: Felmivo Checkout Builder for WooCommerce
 * Plugin URI: https://github.com/ahmodmusa/wc-checkout-fields
 * Description: Add custom checkout fields to WooCommerce Classic and Block Checkout.
 * Version: 0.0.2
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * Author: Ahmod Musa
 * Author URI: https://ahmodmusa.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: felmivo-checkout-builder
 * Domain Path: /languages
 * WC requires at least: 9
 * WC tested up to: 9
 *
 * @package SCFW
 */

declare(strict_types=1);

use SCFW\Plugin;
use SCFW\Services\WooCommerceChecker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

define( 'SCFW_VERSION', '0.0.2' );
define( 'SCFW_FILE', __FILE__ );
define( 'SCFW_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCFW_URL', plugin_dir_url( __FILE__ ) );

if ( class_exists( Plugin::class ) ) {
	add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
}

if ( class_exists( WooCommerceChecker::class ) ) {
	add_action( 'before_woocommerce_init', array( WooCommerceChecker::class, 'declareCompatibility' ) );
}
