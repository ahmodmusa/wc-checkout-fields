<?php
/**
 * WooCommerce dependency and notice tests.
 *
 * @package SCFW\Tests
 */

declare(strict_types=1);

namespace SCFW\Tests;

use PHPUnit\Framework\TestCase;
use SCFW\Core\HookRegistrar;
use SCFW\Services\WooCommerceChecker;

/**
 * Verifies WooCommerce dependency detection and notice rendering.
 */
final class WooCommerceDependencyTest extends TestCase {

	public function testWooCommerceCheckerClassExists(): void {
		$this->assertTrue( class_exists( WooCommerceChecker::class ) );
	}

	public function testWooCommerceAvailabilityDetection(): void {
		// When WooCommerce class is not loaded in unit tests, isAvailable returns false
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->assertFalse( WooCommerceChecker::isAvailable() );
		} else {
			$this->assertTrue( WooCommerceChecker::isAvailable() );
		}
	}

	public function testMissingNoticeOutput(): void {
		$GLOBALS['scfw_test_current_user_can'] = true;

		ob_start();
		WooCommerceChecker::renderMissingNotice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'Felmivo Checkout Builder for WooCommerce', $output );
		$this->assertStringContainsString( 'requires WooCommerce to be installed and active', $output );
	}

	public function testMissingNoticeHiddenForNonAdmin(): void {
		$GLOBALS['scfw_test_current_user_can'] = false;

		ob_start();
		WooCommerceChecker::renderMissingNotice();
		$output = ob_get_clean();

		$this->assertEmpty( $output );

		// Reset stub
		$GLOBALS['scfw_test_current_user_can'] = true;
	}
}
