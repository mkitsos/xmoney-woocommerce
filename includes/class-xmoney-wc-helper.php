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
	 * @param string $secret_key Secret key from xMoney.
	 * @return string Base64 encoded checksum.
	 */
	public static function get_base64_checksum( array $order_data, string $secret_key ): string {
		$json        = json_encode( $order_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$hmac_sha512 = hash_hmac( 'sha512', $json, $secret_key, true );
		return base64_encode( $hmac_sha512 );
	}

	/**
	 * Get plugin configuration.
	 *
	 * @return array Configuration array with site_id, public_key, secret_key, and is_live.
	 */
	public static function get_configuration(): array {
		$is_live = 'yes' === get_option( 'xmoney_wc_live_mode', 'no' );

		return array(
			'is_live'    => $is_live,
			'site_id'    => $is_live ? get_option( 'xmoney_wc_live_site_id', '' ) : get_option( 'xmoney_wc_test_site_id', '' ),
			'public_key' => $is_live ? get_option( 'xmoney_wc_live_public_key', '' ) : get_option( 'xmoney_wc_test_public_key', '' ),
			'secret_key' => $is_live ? get_option( 'xmoney_wc_live_secret_key', '' ) : get_option( 'xmoney_wc_test_secret_key', '' ),
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

