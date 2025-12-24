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
}

