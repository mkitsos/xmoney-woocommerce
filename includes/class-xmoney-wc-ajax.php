<?php

/**
 * XMoney WooCommerce AJAX Handler
 *
 * Handles AJAX requests for payment intent creation.
 *
 * @package XMoney_WooCommerce
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * AJAX Handler Class
 */
class XMoney_WC_Ajax
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		add_action('wp_ajax_xmoney_wc_create_payment_intent', array($this, 'create_payment_intent'));
		add_action('wp_ajax_nopriv_xmoney_wc_create_payment_intent', array($this, 'create_payment_intent'));
		add_action('wp_ajax_xmoney_wc_create_payment_intent_from_cart', array($this, 'create_payment_intent_from_cart'));
		add_action('wp_ajax_nopriv_xmoney_wc_create_payment_intent_from_cart', array($this, 'create_payment_intent_from_cart'));
		add_action('wp_ajax_xmoney_wc_handle_payment_complete', array($this, 'handle_payment_complete'));
		add_action('wp_ajax_nopriv_xmoney_wc_handle_payment_complete', array($this, 'handle_payment_complete'));
		add_action('wp_ajax_xmoney_dismiss_ssl_notice', array($this, 'dismiss_ssl_notice'));
	}

	/**
	 * Dismiss SSL notice.
	 */
	public function dismiss_ssl_notice()
	{
		check_ajax_referer('xmoney_dismiss_ssl', 'nonce');

		if (! current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => 'Unauthorized'));
		}

		update_option('xmoney_wc_ssl_notice_dismissed', 'yes');
		wp_send_json_success();
	}

	/**
	 * Create payment intent (payload + checksum).
	 */
	public function create_payment_intent()
	{
		// Verify nonce.
		if (! check_ajax_referer('xmoney_wc_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => XMoney_WC_Helper::get_error('invalid_token')));
			return;
		}

		// Get order ID from request.
		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;

		if (! $order_id) {
			wp_send_json_error(array('message' => XMoney_WC_Helper::get_error('order_id_required')));
			return;
		}

		$order = wc_get_order($order_id);

		if (! $order) {
			wp_send_json_error(array('message' => XMoney_WC_Helper::get_error('order_not_found')));
			return;
		}

		// Verify order key.
		$order_key = isset($_POST['order_key']) ? sanitize_text_field(wp_unslash($_POST['order_key'])) : '';
		if ($order->get_order_key() !== $order_key) {
			wp_send_json_error(array('message' => XMoney_WC_Helper::get_error('invalid_order_key')));
			return;
		}

		// Get configuration.
		$configuration = XMoney_WC_Helper::get_configuration();

		if (empty($configuration['public_key']) || empty($configuration['secret_key'])) {
			wp_send_json_error(array('message' => XMoney_WC_Helper::get_error('not_configured')));
			return;
		}

		// Prepare order data.
		$order_data = XMoney_WC_Helper::prepare_order_data($order);

		// Generate payload and checksum.
		$payload  = XMoney_WC_Helper::get_base64_json_request($order_data);
		$checksum = XMoney_WC_Helper::get_base64_checksum($order_data, $configuration['secret_key']);

		// Return payment intent data.
		wp_send_json_success(
			array(
				'publicKey'   => $configuration['public_key'],
				'payload'     => $payload,
				'checksum'    => $checksum,
				'appearance'  => XMoney_WC_Helper::get_appearance_config(),
			)
		);
	}

	/**
	 * Create payment intent from cart data (before order creation).
	 */
	public function create_payment_intent_from_cart()
	{
		// Verify nonce.
		if (! check_ajax_referer('xmoney_wc_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => XMoney_WC_Helper::get_error('invalid_token')));
			return;
		}

		// Check if cart exists.
		if (! WC()->cart || WC()->cart->is_empty()) {
			wp_send_json_error(array('message' => XMoney_WC_Helper::get_error('cart_empty')));
			return;
		}

		// Get configuration.
		$configuration = XMoney_WC_Helper::get_configuration();

		if (empty($configuration['public_key']) || empty($configuration['secret_key'])) {
			wp_send_json_error(array('message' => XMoney_WC_Helper::get_error('not_configured')));
			return;
		}

		// Get customer data from checkout fields or session.
		$customer_id = get_current_user_id();

		// Try to get billing data from posted checkout data, fallback to customer session.
		$billing_data = array();
		if (WC()->checkout()->get_posted_data()) {
			$billing_data = WC()->checkout()->get_posted_data();
		} else {
			// Fallback to customer session data.
			$billing_data = array(
				'billing_first_name' => WC()->customer->get_billing_first_name(),
				'billing_last_name'  => WC()->customer->get_billing_last_name(),
				'billing_country'    => WC()->customer->get_billing_country(),
				'billing_city'       => WC()->customer->get_billing_city(),
				'billing_email'      => WC()->customer->get_billing_email(),
			);
		}

		// Prepare customer data.
		$customer = array(
			'identifier' => $customer_id ? (string) $customer_id : 'guest-' . time(),
			'firstName'  => $billing_data['billing_first_name'] ?? '',
			'lastName'   => $billing_data['billing_last_name'] ?? '',
			'country'    => $billing_data['billing_country'] ?? WC()->customer->get_billing_country() ?? '',
			'city'       => $billing_data['billing_city'] ?? WC()->customer->get_billing_city() ?? '',
			'email'      => $billing_data['billing_email'] ?? WC()->customer->get_billing_email() ?? '',
		);

		// Get cart total (xMoney expects amount in actual currency, not cents).
		$total = WC()->cart->total;

		// Generate temporary order ID.
		$temp_order_id = 'temp-' . time() . '-' . wp_generate_password(8, false);

		// Build order data structure for xMoney Embedded Checkout API.
		// Note: Embedded Checkout uses 'publicKey', Hosted Checkout uses 'siteId'.
		$order_data = array(
			'publicKey' => $configuration['public_key'],
			'customer'  => $customer,
			'order'     => array(
				'orderId'     => $temp_order_id,
				'description' => sprintf(
					/* translators: %s: Site name */
					__('Order from %s', 'xmoney-woocommerce'),
					get_bloginfo('name')
				),
				'type'        => 'purchase',
				'amount'      => $total, // Amount in actual currency (e.g., 18.99).
				'currency'    => get_woocommerce_currency(),
			),
			'cardTransactionMode' => 'authAndCapture',
			'backUrl'             => wc_get_checkout_url(),
		);

		// Generate payload and checksum.
		$payload  = XMoney_WC_Helper::get_base64_json_request($order_data);
		$checksum = XMoney_WC_Helper::get_base64_checksum($order_data, $configuration['secret_key']);

		// Return payment intent data.
		wp_send_json_success(
			array(
				'publicKey'      => $configuration['public_key'],
				'payload'        => $payload,
				'checksum'       => $checksum,
				'temp_order_id'  => $temp_order_id,
				'appearance'     => XMoney_WC_Helper::get_appearance_config(),
			)
		);
	}

	/**
	 * Handle payment completion callback from frontend.
	 *
	 * SECURITY: This method does NOT trust frontend data for payment status.
	 * Instead, it makes a server-to-server call to xMoney API to verify the payment.
	 */
	public function handle_payment_complete()
	{
		// Verify nonce.
		if (! check_ajax_referer('xmoney_wc_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => XMoney_WC_Helper::get_error('invalid_token')));
			return;
		}

		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
		// The externalOrderId sent to xMoney (can be temp ID or WC order ID).
		$external_order_id = isset($_POST['external_order_id']) ? sanitize_text_field(wp_unslash($_POST['external_order_id'])) : '';

		if (! $order_id) {
			wp_send_json_error(array('message' => XMoney_WC_Helper::get_error('order_id_missing')));
			return;
		}

		if (empty($external_order_id)) {
			wp_send_json_error(array('message' => XMoney_WC_Helper::get_error('external_order_id_missing')));
			return;
		}

		$order = wc_get_order($order_id);

		if (! $order) {
			wp_send_json_error(array('message' => XMoney_WC_Helper::get_error('order_not_found')));
			return;
		}

		// SECURITY: Verify payment status via xMoney API (server-to-server).
		// Never trust frontend-submitted payment status.
		$verification = XMoney_WC_Helper::verify_payment_status($external_order_id);

		if (is_wp_error($verification)) {
			// API call failed - set order to pending for manual review.
			$order->update_status(
				'pending',
				sprintf(
					XMoney_WC_Helper::get_order_note('verification_error'),
					$verification->get_error_message()
				)
			);

			wp_send_json_error(
				array(
					'message'  => XMoney_WC_Helper::get_error('verification_failed'),
					'redirect' => wc_get_checkout_url(),
				)
			);
			return;
		}

		// Check if payment was successful based on API response.
		$api_status = $verification['order_status'];
		$is_success = XMoney_WC_Helper::is_successful_payment_status($api_status);

		if ($is_success) {
			// Payment verified successful via API.
			$order->payment_complete();
			$order->add_order_note(
				sprintf(
					XMoney_WC_Helper::get_order_note('verified_success'),
					$api_status
				)
			);

			// Store xMoney order ID.
			$xmoney_order_id = $verification['order_id'];
			if ($xmoney_order_id) {
				$order->update_meta_data('_xmoney_order_id', $xmoney_order_id);
			}

			// Store customer ID.
			$customer_id = $verification['customer_id'];
			if ($customer_id) {
				$order->update_meta_data('_xmoney_customer_id', $customer_id);
			}

			$order->save();

			// Clear cart.
			WC()->cart->empty_cart();

			wp_send_json_success(
				array(
					'redirect' => $this->get_return_url($order),
				)
			);
		} else {
			// Payment failed based on API verification.
			$order->update_status('failed', sprintf(
				XMoney_WC_Helper::get_order_note('verified_failed'),
				$api_status
			));

			wp_send_json_error(
				array(
					'message' => XMoney_WC_Helper::get_error('payment_failed'),
				)
			);
		}
	}

	/**
	 * Get return URL for order.
	 *
	 * @param WC_Order $order Order object.
	 * @return string Return URL.
	 */
	private function get_return_url($order)
	{
		return apply_filters('woocommerce_get_return_url', $order->get_checkout_order_received_url(), $order);
	}
}

new XMoney_WC_Ajax();
