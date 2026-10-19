# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Field Engine Foundation: immutable Field value object, FieldType enum, validating FieldFactory, and default fields provider.
- Validation Foundation: WordPress-independent Validator with per-type rules and ValidationResult/ValidationError value objects.
- Rendering Foundation: Renderer contract shared by Classic and Block Checkout, RenderContext/RenderResult value objects, and renderer factory.
- WooCommerce feature compatibility declaration (custom order tables / HPOS) via the FeaturesUtil API.
- Classic Checkout integration: render, validate, and save hooks wired into the WooCommerce Classic Checkout lifecycle.
- First Classic Checkout field: the Delivery Instructions textarea, persisted to the `_scfw_delivery_instructions` order meta key.
- Validation through the shared Core Validator, with no duplicated validation logic.
- HPOS-compatible order meta storage via the WooCommerce order object (`_scfw_` meta prefix).
- WooCommerce Admin Order Display: saved checkout field values rendered on the order edit screen.
- Live verification completed on WordPress 7.0.2 + WooCommerce 11.0.0.

### Added (Block Checkout)
- Block Checkout integration through the official WooCommerce Blocks additional checkout field API (`woocommerce_register_additional_checkout_field`).
- Delivery Instructions registered on the Block Checkout `order` location with the Core TEXTAREA type mapped to the supported Blocks `text` type.
- Submitted Block Checkout values persisted to the `_scfw_delivery_instructions` order meta key through the Store API order update flow, HPOS-compatible.
- Shared Core Validator reused by both Classic and Block Checkout; no duplicated validation logic.
- Documented the adapter-level type mapping decision in the architecture doc.

### Added (Phase 5 — Order Integration)
- Reusable `OrderFieldPresentation` service for ordered, enabled, non-empty SCFW order values.
- Customer and administrative WooCommerce order email presentation with escaped output.
- Thank You / order-received presentation with canonical SCFW output and namespace-specific native-field suppression.
- Authorized My Account order presentation with namespace-specific native Additional Checkout Fields suppression.
- Additive `scfw_fields` property on the existing WooCommerce `/wc/v3/orders/<id>` response for administrator and shop-manager REST consumers.
- HPOS-safe order presentation through WooCommerce order APIs without new metadata or storage.

### Changed
- Configured PHPCS to match the frozen PSR-4/PSR-12 architecture rather than forcing legacy WordPress naming conventions.
- Updated README and CHANGELOG to describe the implemented foundations.

## [0.0.2] - 2026-10-19

### Changed
- Renamed plugin to Felmivo Checkout Builder for WooCommerce (slug and text domain: `felmivo-checkout-builder`) following WordPress.org review feedback.
- Added `Requires Plugins: woocommerce` plugin header for WordPress.org review compliance.
- Shortened WooCommerce submenu label to "Felmivo Checkout".
- Improved HPOS compatibility declaration with standalone autoloader fallback.
- Added conditional required validation support for WooCommerce Block Checkout.
- Added administrator notice when WooCommerce is inactive.

### Security
- Sanitized order admin nonce value with `sanitize_text_field()` prior to verification.

## [0.0.1] - 2026-08-04

### Added
- Repository foundation scaffold for WordPress.org production readiness.
- WordPress plugin bootstrap entry file with defensive autoload loading.
- Composer, PHPCS, and PHPStan configuration.
- WordPress.org plugin readme and GitHub README foundation.
- WooCommerce dependency guard service.
- Central hook registrar and plugin kernel wiring.
- Admin menu framework and WordPress Settings API settings foundation.

### Changed
- Moved the WooCommerce availability check from plugin-include time into the WordPress plugin lifecycle.
- Tightened repository metadata and package configuration without altering the project identity.

### Security
- Hardened the bootstrap so a missing Composer autoloader cannot white-screen the site.
- Added a sanitize callback for the registered settings option.
- Kept the bootstrap file minimal and ABSPATH protected.
