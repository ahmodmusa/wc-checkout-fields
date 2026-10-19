<?php
/**
 * Settings.
 *
 * @package SCFW\Admin
 */

declare(strict_types=1);

namespace SCFW\Admin;

use SCFW\Core\Field\FieldRepository;
use SCFW\Core\Field\FieldType;

/**
 * Registers the plugin settings.
 *
 * @since 0.0.1
 */
final class Settings {

	/**
	 * Settings option name.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'scfw_options';

	/**
	 * Register the WordPress settings API.
	 *
	 * Only users with the manage_options capability may change settings.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		register_setting(
			'scfw',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'show_in_rest'      => false,
				'default'           => array(),
				'autoload'          => true,
				'sanitize_callback' => array( self::class, 'sanitize' ),
			)
		);

		add_filter(
			'wp_redirect',
			array( self::class, 'filterRedirect' ),
			10,
			1
		);

		add_settings_section(
			'scfw_general',
			__( 'General Settings', 'felmivo-checkout-builder' ),
			'',
			'scfw-settings'
		);

		add_settings_field(
			'enabled',
			'',
			array( self::class, 'renderEnabledField' ),
			'scfw-settings',
			'scfw_general'
		);

		add_settings_field(
			'developer_diagnostics',
			'',
			array( self::class, 'renderDeveloperDiagnosticsField' ),
			'scfw-settings',
			'scfw_general'
		);

		add_settings_section(
			'scfw_fields',
			'',
			'',
			'scfw-fields'
		);

		add_settings_field(
			'fields',
			'',
			array( FieldBuilder::class, 'render' ),
			'scfw-fields',
			'scfw_fields'
		);
	}

	/**
	 * Determine whether Developer Diagnostics is enabled.
	 *
	 * Defaults to false (OFF) unless explicitly enabled in settings.
	 *
	 * @since 0.0.1
	 *
	 * @return bool True if developer diagnostics is enabled.
	 */
	public static function isDeveloperDiagnosticsEnabled(): bool {
		$options = get_option( self::OPTION_NAME, array() );

		return is_array( $options ) && ! empty( $options['developer_diagnostics'] );
	}

	/**
	 * Sanitize the plugin settings.
	 *
	 * Preserves the existing `enabled` behavior and normalizes the submitted
	 * checkout field configuration (edits, add, remove and move actions) into
	 * the canonical field configuration stored under `scfw_options.fields`.
	 *
	 * @since 0.0.1
	 *
	 * @param mixed $value Submitted settings value.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public static function sanitize( $value ): array {
		$options = is_array( $value ) ? $value : array();
		$current = get_option( self::OPTION_NAME, array() );
		$current = is_array( $current ) ? $current : array();

		$clean = array(
			'enabled'               => isset( $options['enabled'] ) ? absint( $options['enabled'] ) : ( isset( $current['enabled'] ) ? absint( $current['enabled'] ) : 0 ),
			'developer_diagnostics' => isset( $options['developer_diagnostics'] ) ? absint( $options['developer_diagnostics'] ) : ( isset( $current['developer_diagnostics'] ) ? absint( $current['developer_diagnostics'] ) : 0 ),
		);

		if ( isset( $options['fields'] ) && is_array( $options['fields'] ) ) {
			$fields = self::normalizeFormFields( $options['fields'], $options );

			if ( null === $fields ) {
				add_settings_error(
					'scfw',
					'fields_invalid',
					__( 'The checkout field configuration was invalid and was not saved.', 'felmivo-checkout-builder' ),
					'error'
				);
				$clean['fields'] = isset( $current['fields'] ) && is_array( $current['fields'] ) ? $current['fields'] : array();
			} else {
				$clean['fields'] = $fields;
			}
		} elseif ( isset( $current['fields'] ) && is_array( $current['fields'] ) ) {
			$clean['fields'] = $current['fields'];
		}

		return $clean;
	}

	/**
	 * Filter redirection target after saving settings via options.php.
	 *
	 * Ensures deterministic redirection back to the SCFW admin screen
	 * (preserving the active tab if present) instead of landing on options.php.
	 *
	 * @since 0.0.1
	 *
	 * @param string $location The original redirect URL.
	 * @return string Filtered redirect URL.
	 */
	public static function filterRedirect( string $location ): string {
		// Only intercept when SCFW option page was submitted (nonce already verified by options.php).
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['option_page'] ) || 'scfw' !== $_POST['option_page'] ) {
			return $location;
		}

		$referer = isset( $_POST['_wp_http_referer'] ) ? sanitize_text_field( wp_unslash( $_POST['_wp_http_referer'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! empty( $referer ) && strpos( $referer, 'page=scfw-fields' ) !== false ) {
			if ( preg_match( '#^https?://#i', $referer ) ) {
				return add_query_arg( 'settings-updated', 'true', $referer );
			}
			$clean_path = preg_replace( '#^/?wp-admin/#', '', $referer );
			return add_query_arg( 'settings-updated', 'true', admin_url( $clean_path ) );
		}

		return admin_url( 'admin.php?page=scfw-fields&settings-updated=true' );
	}

	/**
	 * Render the enabled checkbox field.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function renderEnabledField(): void {
		$options = get_option( self::OPTION_NAME, array() );
		$options = is_array( $options ) ? $options : array();
		?>
		<label class="scfw-switch" for="scfw_enabled">
			<input type="hidden" name="scfw_options[enabled]" value="0" />
			<input type="checkbox" class="scfw-switch__input" id="scfw_enabled" name="scfw_options[enabled]" value="1" <?php checked( ! empty( $options['enabled'] ), true ); ?> />
			<span class="scfw-switch__track" aria-hidden="true"><span class="scfw-switch__thumb"></span></span>
			<span class="scfw-switch__title"><?php esc_html_e( 'Enable Checkout Fields', 'felmivo-checkout-builder' ); ?></span>
		</label>
		<p class="description scfw-switch-copy">
			<?php esc_html_e( 'When enabled, custom fields appear during checkout. When disabled, custom fields are not shown at checkout but the configuration is preserved.', 'felmivo-checkout-builder' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the developer diagnostics checkbox field in settings.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function renderDeveloperDiagnosticsField(): void {
		$options = get_option( self::OPTION_NAME, array() );
		$options = is_array( $options ) ? $options : array();
		?>
		<label class="scfw-switch" for="scfw_developer_diagnostics">
			<input type="hidden" name="scfw_options[developer_diagnostics]" value="0" />
			<input type="checkbox" class="scfw-switch__input" id="scfw_developer_diagnostics" name="scfw_options[developer_diagnostics]" value="1" <?php checked( ! empty( $options['developer_diagnostics'] ), true ); ?> />
			<span class="scfw-switch__track" aria-hidden="true"><span class="scfw-switch__thumb"></span></span>
			<span class="scfw-switch__title"><?php esc_html_e( 'Developer Diagnostics', 'felmivo-checkout-builder' ); ?></span>
		</label>
		<p class="description scfw-switch-copy">
			<?php esc_html_e( 'Show technical field identifiers, storage keys, and lifecycle details in the Field Builder.', 'felmivo-checkout-builder' ); ?>
		</p>
		<?php
	}

	/**
	 * Normalize the submitted field builder rows.
	 *
	 * Applies the move, remove and add actions signalled by the native form row
	 * buttons, then delegates the schema normalization to the Core field
	 * repository. The resulting canonical config preserves the submitted row
	 * order, which is the configured checkout order.
	 *
	 * @since 0.0.1
	 *
	 * @param array<array-key, mixed> $options  Submitted form fields.
	 * @param array<array-key, mixed> $settings Whole submitted settings value.
	 *
	 * @return array<string, array{id: string, label: string, type: string, required: bool, enabled: bool, placeholder: string, description: string, default: string, options: array<int, string>, rules: array<int|string, mixed>}>|null Canonical field configuration, or null when invalid.
	 */
	private static function normalizeFormFields( array $options, array $settings ): ?array {
		$rows = self::applyMove( $options );

		self::preserveStoredProperties( $rows );

		foreach ( $rows as $id => $row ) {
			if ( is_array( $row ) && ! empty( $row['remove'] ) ) {
				unset( $rows[ $id ] );
			}
		}

		if ( ! self::appendNewField( $rows, $settings ) ) {
			return null;
		}

		return FieldRepository::validateFields( array_values( $rows ) );
	}

	/**
	 * Carry over stored configuration for rows that do not submit a value,
	 * keeping only the properties that stay valid for the row's type.
	 *
	 * The Free field builder has no controls for placeholder, description,
	 * default or options yet, and it never submits rules, so a saved field
	 * would otherwise strip an existing value created by an import or by the
	 * Pro extension. Free never evaluates rules; it only preserves data it
	 * does not own so an ordinary settings save cannot corrupt it.
	 *
	 * A type conversion must not drag incompatible properties along: an option
	 * list that a checkbox cannot carry, or a default that no longer matches
	 * the new type, would reject the whole configuration save. Options and the
	 * default are therefore copied only when they validate against the row's
	 * submitted type; incompatible values are cleared before the schema runs.
	 * Placeholder and description are type-neutral and always preserved.
	 *
	 * @since 0.0.1
	 *
	 * @param array<int, mixed> $rows Rows being normalized.
	 *
	 * @return void
	 */
	private static function preserveStoredProperties( array &$rows ): void {
		$current = get_option( self::OPTION_NAME, array() );
		$current = is_array( $current ) && isset( $current['fields'] ) && is_array( $current['fields'] ) ? $current['fields'] : array();

		foreach ( $rows as &$row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id = isset( $row['id'] ) && is_scalar( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';

			if ( '' === $id || ! isset( $current[ $id ] ) || ! is_array( $current[ $id ] ) ) {
				continue;
			}

			$type = null;

			if ( isset( $row['type'] ) && is_scalar( $row['type'] ) ) {
				$type = FieldType::tryFrom( (string) $row['type'] );
			}

			if ( null === $type ) {
				continue;
			}

			$stored     = $current[ $id ];
			$storedType = isset( $stored['type'] ) && is_scalar( $stored['type'] ) ? (string) $stored['type'] : '';
			$conversion = $storedType !== $type->value;

			foreach ( array( 'placeholder', 'description' ) as $key ) {
				if ( ! array_key_exists( $key, $row ) && array_key_exists( $key, $stored ) ) {
					$row[ $key ] = $stored[ $key ];
				}
			}

			if ( ! array_key_exists( 'options', $row ) && array_key_exists( 'options', $stored ) && ( FieldType::RADIO === $type || FieldType::SELECT === $type ) ) {
				$options   = is_array( $stored['options'] ) ? $stored['options'] : array();
				$cleared   = $conversion && array() === $options;

				if ( ! $cleared ) {
					$row['options'] = $options;
				}
			}

			if ( ! array_key_exists( 'default', $row ) && array_key_exists( 'default', $stored ) ) {
				$row['default'] = self::compatibleDefault( $type, $stored['default'], isset( $row['options'] ) && is_array( $row['options'] ) ? $row['options'] : array() );
			}

			if ( ! array_key_exists( 'destinations', $row ) && array_key_exists( 'destinations', $stored ) ) {
				$row['destinations'] = $stored['destinations'];
			}

			if ( ! array_key_exists( 'conditions', $row ) && array_key_exists( 'conditions', $stored ) ) {
				$row['conditions'] = $stored['conditions'];
			}

			if ( ! array_key_exists( 'rules', $row ) && array_key_exists( 'rules', $stored ) ) {
				$row['rules'] = $stored['rules'];
			}
		}
		unset( $row );
	}

	/**
	 * Resolve a preserved default that stays valid for a field type.
	 *
	 * A default carried across a type conversion must validate against the new
	 * type and its options; otherwise the strict schema would reject the whole
	 * configuration save. Incompatible defaults normalize to the empty string
	 * so the converted row then validates normally.
	 *
	 * @since 0.0.1
	 *
	 * @param FieldType          $type    New field type.
	 * @param mixed              $default Stored default value.
	 * @param array<mixed>       $options Effective choice options.
	 *
	 * @return string Compatible default.
	 */
	private static function compatibleDefault( FieldType $type, mixed $default, array $options ): string {
		if ( ! is_scalar( $default ) ) {
			return '';
		}

		$value = (string) $default;

		if ( '' === $value ) {
			return '';
		}

		if ( FieldType::CHECKBOX === $type ) {
			return in_array( $value, array( '1', '0' ), true ) ? $value : '';
		}

		if ( FieldType::RADIO === $type || FieldType::SELECT === $type ) {
			return in_array( $value, $options, true ) ? $value : '';
		}

		return $value;
	}

	/**
	 * Apply the move action of a single row button.
	 *
	 * Only the clicked row carries a move marker. The row is swapped with its
	 * neighbor in the submitted order, which after a drag-and-drop reorder is
	 * the currently displayed order.
	 *
	 * @since 0.0.1
	 *
	 * @param array<array-key, mixed> $options Submitted form fields.
	 *
	 * @return array<int, mixed> Rows with the move applied.
	 */
	private static function applyMove( array $options ): array {
		$rows   = array_values( $options );
		$count  = count( $rows );
		$target = null;
		$swap   = null;

		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) || empty( $row['move'] ) ) {
				continue;
			}

			$swap   = $index;
			$target = 'up' === $row['move'] ? $index - 1 : $index + 1;
			break;
		}

		if ( null !== $swap && null !== $target && $target >= 0 && $target < $count ) {
			$tmp        = $rows[ $swap ];
			$rows[ $swap ]   = $rows[ $target ];
			$rows[ $target ] = $tmp;
		}

		return $rows;
	}

	/**
	 * Append a new field entry when the add action was submitted.
	 *
	 * A unique id is derived from the submitted label and checked against the
	 * existing row ids so the new `_scfw_` meta key can never collide.
	 *
	 * @since 0.0.1
	 *
	 * @param array<int, mixed>     $rows     Rows being normalized.
	 * @param array<array-key, mixed> $settings Whole submitted settings value.
	 *
	 * @return bool True when no add request was made or the new field is valid.
	 */
	private static function appendNewField( array &$rows, array $settings ): bool {
		if ( empty( $settings['add'] ) || ! is_array( $settings['new_field'] ?? null ) ) {
			return empty( $settings['add'] );
		}

		$label = '';

		if ( isset( $settings['new_field']['label'] ) && is_scalar( $settings['new_field']['label'] ) ) {
			$label = sanitize_text_field( (string) $settings['new_field']['label'] );
		}

		$type = isset( $settings['new_field']['type'] ) && is_scalar( $settings['new_field']['type'] ) ? (string) $settings['new_field']['type'] : '';

		if ( '' === $label || null === FieldType::tryFrom( $type ) ) {
			return false;
		}

		$existing = array();

		foreach ( $rows as $row ) {
			if ( is_array( $row ) && isset( $row['id'] ) && is_scalar( $row['id'] ) ) {
				$existing[ sanitize_key( (string) $row['id'] ) ] = true;
			}
		}

		$rows[] = array(
			'id'       => FieldRepository::generateId( $label, $existing ),
			'label'    => $label,
			'type'     => $type,
			'required' => false,
			'enabled'  => true,
		);

		return true;
	}
}
