<?php
/**
 * XMoney WooCommerce Helper Class
 *
 * Provides utility functions for the plugin.
 *
 * @package XMoney_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class for xMoney WooCommerce plugin.
 */
class XMoney_WC_Helper {

	/**
	 * Get base64 encoded JSON request.
	 *
	 * @param array $order_data Order data array.
	 * @return string Base64 encoded JSON.
	 */
	public static function get_base64_json_request( array $order_data ): string {
		$json = json_encode( $order_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return base64_encode( $json );
	}

	/**
	 * Get base64 encoded checksum (HMAC-SHA512).
	 *
	 * @param array  $order_data Order data array.
	 * @param string $secret_key Secret key from xMoney (with sk_test_ or sk_live_ prefix).
	 * @return string Base64 encoded checksum.
	 */
	public static function get_base64_checksum( array $order_data, string $secret_key ): string {
		// Extract the actual secret key value (remove sk_test_ or sk_live_ prefix).
		$secret_key_value = self::get_secret_key_value( $secret_key );

		$json        = json_encode( $order_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$hmac_sha512 = hash_hmac( 'sha512', $json, $secret_key_value, true );
		return base64_encode( $hmac_sha512 );
	}

	/**
	 * Get plugin configuration.
	 *
	 * @return array Configuration array with public_key, secret_key, and is_live.
	 */
	public static function get_configuration(): array {
		$public_key = get_option( 'xmoney_wc_public_key', '' );
		$secret_key = get_option( 'xmoney_wc_secret_key', '' );

		// Auto-detect environment from public key prefix.
		$is_live = self::is_live_mode( $public_key );

		return array(
			'is_live'    => $is_live,
			'public_key' => $public_key,
			'secret_key' => $secret_key,
		);
	}

	/**
	 * Validate public key format.
	 *
	 * @param string $public_key The public key to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_public_key( string $public_key ): bool {
		if ( empty( $public_key ) ) {
			return false;
		}

		return strpos( $public_key, 'pk_live_' ) === 0 || strpos( $public_key, 'pk_test_' ) === 0;
	}

	/**
	 * Validate secret key format.
	 *
	 * @param string $secret_key The secret key to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_secret_key( string $secret_key ): bool {
		if ( empty( $secret_key ) ) {
			return false;
		}

		return strpos( $secret_key, 'sk_live_' ) === 0 || strpos( $secret_key, 'sk_test_' ) === 0;
	}

	/**
	 * Extract the actual secret key value by removing the prefix.
	 *
	 * @param string $secret_key The full secret key (sk_test_xxx or sk_live_xxx).
	 * @return string The secret key without the prefix.
	 */
	public static function get_secret_key_value( string $secret_key ): string {
		if ( strpos( $secret_key, 'sk_live_' ) === 0 ) {
			return substr( $secret_key, 8 ); // Remove 'sk_live_' (8 chars).
		}

		if ( strpos( $secret_key, 'sk_test_' ) === 0 ) {
			return substr( $secret_key, 8 ); // Remove 'sk_test_' (8 chars).
		}

		// Return as-is if no recognized prefix.
		return $secret_key;
	}

	/**
	 * Detect if we're in live mode based on public key prefix.
	 *
	 * @param string $public_key The public key to check.
	 * @return bool True if live mode, false if test mode.
	 */
	public static function is_live_mode( string $public_key ): bool {
		if ( empty( $public_key ) ) {
			return false;
		}

		// Check for live key prefix.
		if ( strpos( $public_key, 'pk_live_' ) === 0 ) {
			return true;
		}

		// Default to test mode (pk_test_ or any other prefix).
		return false;
	}

	/**
	 * Get environment label based on public key.
	 *
	 * @param string $public_key The public key to check.
	 * @return string 'live', 'test', or 'unknown'.
	 */
	public static function get_environment( string $public_key ): string {
		if ( empty( $public_key ) ) {
			return 'unknown';
		}

		if ( strpos( $public_key, 'pk_live_' ) === 0 ) {
			return 'live';
		}

		if ( strpos( $public_key, 'pk_test_' ) === 0 ) {
			return 'test';
		}

		return 'unknown';
	}

	/**
	 * Get list of EEA (European Economic Area) country codes.
	 *
	 * Includes all EU member states plus Iceland, Liechtenstein, and Norway.
	 *
	 * @return array Array of ISO 3166-1 alpha-2 country codes.
	 */
	public static function get_eea_countries(): array {
		return array(
			// EU Member States (27)
			'AT', // Austria
			'BE', // Belgium
			'BG', // Bulgaria
			'HR', // Croatia
			'CY', // Cyprus
			'CZ', // Czech Republic
			'DK', // Denmark
			'EE', // Estonia
			'FI', // Finland
			'FR', // France
			'DE', // Germany
			'GR', // Greece
			'HU', // Hungary
			'IE', // Ireland
			'IT', // Italy
			'LV', // Latvia
			'LT', // Lithuania
			'LU', // Luxembourg
			'MT', // Malta
			'NL', // Netherlands
			'PL', // Poland
			'PT', // Portugal
			'RO', // Romania
			'SK', // Slovakia
			'SI', // Slovenia
			'ES', // Spain
			'SE', // Sweden
			// EEA EFTA States (3)
			'IS', // Iceland
			'LI', // Liechtenstein
			'NO', // Norway
		);
	}

	/**
	 * Check if a country is in the EEA.
	 *
	 * @param string $country_code ISO 3166-1 alpha-2 country code.
	 * @return bool True if country is in EEA, false otherwise.
	 */
	public static function is_eea_country( string $country_code ): bool {
		return in_array( strtoupper( $country_code ), self::get_eea_countries(), true );
	}

	/**
	 * Check if the store's base country is in the EEA.
	 *
	 * @return bool True if store is in EEA, false otherwise.
	 */
	public static function is_store_in_eea(): bool {
		$base_location = wc_get_base_location();
		$country_code  = $base_location['country'] ?? '';

		return self::is_eea_country( $country_code );
	}

	/**
	 * Get appearance configuration for the payment form.
	 *
	 * @return array Appearance configuration.
	 */
	public static function get_appearance_config(): array {
		$gateway_settings = get_option( 'woocommerce_xmoney_wc_settings', array() );
		
		$theme_mode = $gateway_settings['theme_mode'] ?? 'light';
		
		// If not custom mode, return just the theme
		if ( 'custom' !== $theme_mode ) {
			return array(
				'theme' => $theme_mode,
			);
		}
		
		// Build custom variables
		$variables = array();
		
		$color_map = array(
			'color_primary'          => 'colorPrimary',
			'color_danger'           => 'colorDanger',
			'color_background'       => 'colorBackground',
			'color_background_focus' => 'colorBackgroundFocus',
			'color_text'             => 'colorText',
			'color_text_secondary'   => 'colorTextSecondary',
			'color_text_placeholder' => 'colorTextPlaceholder',
			'color_border'           => 'colorBorder',
			'color_border_focus'     => 'colorBorderFocus',
			'border_radius'          => 'borderRadius',
		);
		
		foreach ( $color_map as $setting_key => $sdk_key ) {
			$value = $gateway_settings[ $setting_key ] ?? '';
			if ( ! empty( $value ) ) {
				$variables[ $sdk_key ] = $value;
			}
		}
		
		if ( empty( $variables ) ) {
			return array(
				'theme' => 'light',
			);
		}
		
		return array(
			'theme'     => 'custom',
			'variables' => $variables,
		);
	}

	/**
	 * Format phone number.
	 *
	 * @param string $phone Phone number.
	 * @return string Formatted phone number.
	 */
	public static function format_phone( string $phone ): string {
		if ( empty( $phone ) ) {
			return '';
		}

		// Remove all non-digit characters except +
		$phone = preg_replace( '/[^0-9+]/', '', $phone );

		// Add + prefix if not present
		if ( '+' !== substr( $phone, 0, 1 ) ) {
			$phone = '+' . $phone;
		}

		return $phone;
	}

	/**
	 * Get current language code.
	 *
	 * @return string Language code (e.g., 'en', 'el', 'ro').
	 */
	public static function get_current_language(): string {
		$locale = get_locale();
		return explode( '_', $locale )[0];
	}

	/**
	 * Convert WooCommerce locale to xMoney locale format.
	 *
	 * @return string Locale in format 'en-US', 'el-GR', 'ro-RO'.
	 */
	public static function get_xmoney_locale(): string {
		$locale = get_locale();
		$parts  = explode( '_', $locale );

		$lang = strtolower( $parts[0] ?? 'en' );
		$country = strtoupper( $parts[1] ?? 'US' );

		// Map common languages to xMoney supported locales.
		$locale_map = array(
			'en' => 'en-US',
			'el' => 'el-GR',
			'ro' => 'ro-RO',
		);

		if ( isset( $locale_map[ $lang ] ) ) {
			return $locale_map[ $lang ];
		}

		// Default fallback.
		return 'en-US';
	}

	/**
	 * Get xMoney API base URL based on environment.
	 *
	 * @param bool $is_live Whether in live mode.
	 * @return string API base URL.
	 */
	public static function get_api_base_url( bool $is_live ): string {
		return $is_live
			? 'https://api.xmoney.com'
			: 'https://api-stage.xmoney.com';
	}

	/**
	 * Verify payment status via xMoney API.
	 *
	 * Makes a server-to-server call to xMoney to verify the payment status.
	 * This is the secure way to confirm a payment - never trust frontend data.
	 *
	 * @param string $external_order_id The external order ID sent to xMoney.
	 * @return array|WP_Error Array with 'success' boolean and 'data' on success, WP_Error on failure.
	 */
	public static function verify_payment_status( string $external_order_id ) {
		$configuration = self::get_configuration();
		$secret_key    = $configuration['secret_key'];
		$is_live       = $configuration['is_live'];

		if ( empty( $secret_key ) ) {
			return new \WP_Error( 'missing_secret_key', __( 'Secret key not configured.', 'xmoney-woocommerce' ) );
		}

		// Get the actual secret key value (without prefix) for API authentication.
		$api_key = self::get_secret_key_value( $secret_key );

		// Build API URL.
		$api_base = self::get_api_base_url( $is_live );
		$api_url  = add_query_arg( 'externalOrderId', $external_order_id, $api_base . '/order' );

		// Make API request.
		$response = wp_remote_get(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		// Check for WP errors.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Check HTTP status.
		$http_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'xMoney API returned HTTP %d', 'xmoney-woocommerce' ),
					$http_code
				)
			);
		}

		// Parse response.
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data ) {
			return new \WP_Error( 'invalid_response', __( 'Invalid JSON response from xMoney API.', 'xmoney-woocommerce' ) );
		}

		// Check API response code.
		if ( ! isset( $data['code'] ) || 200 !== $data['code'] ) {
			return new \WP_Error(
				'api_error',
				$data['message'] ?? __( 'Unknown API error.', 'xmoney-woocommerce' )
			);
		}

		// Check if order exists in response.
		if ( empty( $data['data'] ) || ! is_array( $data['data'] ) || 0 === count( $data['data'] ) ) {
			return new \WP_Error( 'order_not_found', __( 'Order not found in xMoney.', 'xmoney-woocommerce' ) );
		}

		// Get the first (and should be only) order.
		$order_data = $data['data'][0];

		// Return structured result.
		return array(
			'success'      => true,
			'order_id'     => $order_data['id'] ?? 0,
			'order_status' => $order_data['orderStatus'] ?? '',
			'amount'       => $order_data['amount'] ?? '0.00',
			'currency'     => $order_data['currency'] ?? '',
			'customer_id'  => $order_data['customerId'] ?? 0,
			'raw_data'     => $order_data,
		);
	}

	/**
	 * Check if an order status indicates successful payment.
	 *
	 * @param string $order_status The order status from xMoney API.
	 * @return bool True if payment was successful.
	 */
	public static function is_successful_payment_status( string $order_status ): bool {
		$success_statuses = array( 'complete-ok', 'in-progress', 'open-ok' );
		return in_array( strtolower( $order_status ), $success_statuses, true );
	}

	/**
	 * Prepare order data for xMoney API.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return array Order data array.
	 */
	public static function prepare_order_data( WC_Order $order ): array {
		$configuration = self::get_configuration();
		$order_data    = $order->get_data();

		// Prepare customer data.
		$customer = array(
			'identifier' => 0 === $order_data['customer_id'] ? (string) $order->get_id() : (string) $order_data['customer_id'],
			'firstName'  => $order_data['billing']['first_name'] ?? '',
			'lastName'   => $order_data['billing']['last_name'] ?? '',
			'country'    => $order_data['billing']['country'] ?? '',
			'city'       => $order_data['billing']['city'] ?? ( $order_data['shipping']['city'] ?? '' ),
			'email'      => $order_data['billing']['email'] ?? '',
		);

		// Build order data structure for xMoney Embedded Checkout API.
		// Note: Embedded Checkout uses 'publicKey', Hosted Checkout uses 'siteId'.
		return array(
			'publicKey' => $configuration['public_key'],
			'customer'  => $customer,
			'order'     => array(
				'orderId'     => (string) $order->get_id(),
				'description' => sprintf(
					/* translators: %s: Order number */
					__( 'Order #%s', 'xmoney-woocommerce' ),
					$order->get_order_number()
				),
				'type'        => 'purchase',
				'amount'      => $order->get_total(), // Amount in actual currency (e.g., 18.99).
				'currency'    => $order->get_currency(),
			),
			'cardTransactionMode' => 'authAndCapture',
			'backUrl'             => wc_get_checkout_url(),
		);
	}

	/**
	 * Get test mode notice text.
	 *
	 * @return array Array with 'title' and 'text' keys.
	 */
	public static function get_test_mode_notice(): array {
		return array(
			'title' => __( "Test mode.", 'xmoney-woocommerce' ),
			'text'  => __( 'Payments will be simulated and no real charges will occur.', 'xmoney-woocommerce' ),
		);
	}
}

