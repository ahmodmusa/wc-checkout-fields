# Felmivo Checkout Builder for WooCommerce

Add custom checkout fields to WooCommerce Classic and Block Checkout.

Status: currently in the WordPress.org review queue.

## Description

**Felmivo Checkout Builder for WooCommerce** gives store owners and developers an intuitive, reliable way to manage WooCommerce checkout fields across both Classic Checkout and modern WooCommerce Block Checkout.

Create checkout fields, control when they appear, decide where their data goes, and keep your store configuration healthy without writing code.

## Features

- **Classic & Block Checkout Support**: Free checkout field customization for WooCommerce Classic Checkout AND modern WooCommerce Checkout Blocks.
- **Visual Field Builder**: Easily create, edit, disable, enable, reorder, and remove custom checkout fields.
- **Multiple Field Types**: Supports text, textarea, email, telephone, number, date, checkbox, select dropdowns, and radio choices.
- **Basic Conditional Visibility**: Dynamically show or hide dependent fields based on customer selections at checkout (e.g., Gift Wrapping → Gift Message) with zero page reloads.
- **Reusable Field Templates**: 1-click apply pre-configured field packs for common store scenarios (Local Delivery, Gifting, B2B / Wholesale, and Direct Contact).
- **Live Field Preview**: Real-time visual preview of field styling, labels, placeholders, options, and required indicators directly in the field editor modal.
- **Import / Export**: Safely export and import field configurations as structured JSON to backup or migrate between stores.
- **Checkout Health Diagnostics**: Continuous static analysis of your checkout field configuration to alert you to broken rules, empty choices, or missing dependencies.
- **Order Data Lifecycle & Destinations**: Field values are stored through official WooCommerce CRUD order APIs with canonical `_scfw_` metadata keys, appearing in configured destinations: Admin Order Details, Customer Thank You page, My Account Order View, Customer Invoice Emails, and Admin New Order Emails.
- **HPOS Compatible**: Compatible with High-Performance Order Storage (HPOS); tested with real orders on both Classic and Blocks with HPOS enabled.

## Requirements

- **WordPress:** 6.7 or higher
- **WooCommerce:** 9.0 or higher
- **PHP:** 8.2 or higher

## Installation

1. Upload the `felmivo-checkout-builder` folder to the `/wp-content/plugins/` directory, or install the plugin directly through the WordPress plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **WooCommerce > Felmivo Checkout** in your WordPress admin dashboard to start creating and customizing your checkout fields.

## FAQ

### Does this plugin support WooCommerce Block Checkout?
Yes. Felmivo Checkout Builder for WooCommerce natively registers custom fields with the modern WooCommerce Block Checkout, provides smooth conditional visibility bridges, and validates conditional required fields during checkout.

### Does this plugin support High-Performance Order Storage (HPOS)?
Yes. All field data lifecycle handlers and order presentations are built using official WooCommerce CRUD methods and are 100% HPOS compatible.

### Where does the custom field data appear after checkout?
You can customize each field's destinations in the Field Settings modal to show on the WooCommerce Admin Order page, Customer Thank You page, My Account order details, Customer confirmation emails, and Admin notification emails.

### How do conditional required fields work on WooCommerce Block Checkout?
When a field is configured with conditional visibility rules and is marked required, Felmivo evaluates the condition on both the client side and the server side. If the condition is met and the field is visible, it is required and validated. If the condition is not met and the field is hidden, it will not block checkout submission.

## Changelog

### 0.0.2
- Renamed plugin to Felmivo Checkout Builder for WooCommerce (slug and text domain: `felmivo-checkout-builder`) following WordPress.org review feedback.
- Added `Requires Plugins: woocommerce` plugin header for WordPress.org review compliance.
- Fixed: the nonce value is now sanitized with sanitize_text_field() before verification on the order admin screen.
- Shortened WooCommerce submenu label to "Felmivo Checkout".
- Fixed HPOS compatibility declaration with standalone autoloader fallback.
- Added conditional required validation support for WooCommerce Block Checkout.
- Added administrator notice when WooCommerce is inactive.

### 0.0.1
- Initial release featuring Field Builder, 9 supported field types, Conditional Visibility, Data Destinations, Use-Case Templates, Checkout Health Engine, Live Field Preview, and Developer Diagnostics.

## License

This project is licensed under the GNU General Public License v2.0 or later (GPL-2.0-or-later). See the [LICENSE](LICENSE) file for the full license text.
