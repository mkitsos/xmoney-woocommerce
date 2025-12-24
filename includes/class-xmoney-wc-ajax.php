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
	}

	/**
	 * Create payment intent (payload + checksum).
	 */
	public function create_payment_intent()
	{
		// Verify nonce.
		if (! check_ajax_referer('xmoney_wc_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security token.', 'xmoney-woocommerce')));
			return;
		}

		// Get order ID from request.
		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;

		if (! $order_id) {
			wp_send_json_error(array('message' => __('Order ID is required.', 'xmoney-woocommerce')));
			return;
		}

		$order = wc_get_order($order_id);

		if (! $order) {
			wp_send_json_error(array('message' => __('Order not found.', 'xmoney-woocommerce')));
			return;
		}

		// Verify order key.
		$order_key = isset($_POST['order_key']) ? sanitize_text_field(wp_unslash($_POST['order_key'])) : '';
		if ($order->get_order_key() !== $order_key) {
			wp_send_json_error(array('message' => __('Invalid order key.', 'xmoney-woocommerce')));
			return;
		}

		// Get configuration.
		$configuration = XMoney_WC_Helper::get_configuration();

		if (empty($configuration['public_key']) || empty($configuration['secret_key'])) {
			wp_send_json_error(array('message' => __('Payment gateway is not configured.', 'xmoney-woocommerce')));
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
				'publicKey' => $configuration['public_key'],
				'payload'   => $payload,
				'checksum'  => $checksum,
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
			wp_send_json_error(array('message' => __('Invalid security token.', 'xmoney-woocommerce')));
			return;
		}

		// Check if cart exists.
		if (! WC()->cart || WC()->cart->is_empty()) {
			wp_send_json_error(array('message' => __('Cart is empty.', 'xmoney-woocommerce')));
			return;
		}

		// Get configuration.
		$configuration = XMoney_WC_Helper::get_configuration();

		if (empty($configuration['public_key']) || empty($configuration['secret_key'])) {
			wp_send_json_error(array('message' => __('Payment gateway is not configured.', 'xmoney-woocommerce')));
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
			)
		);
	}

	/**
	 * Handle payment completion callback from frontend.
	 */
	public function handle_payment_complete()
	{
		// Verify nonce.
		if (! check_ajax_referer('xmoney_wc_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Invalid security token.', 'xmoney-woocommerce')));
			return;
		}

		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
		// Accept both 'status' and 'transaction_status' (xMoney SDK uses transactionStatus).
		$status = isset($_POST['transaction_status']) ? sanitize_text_field(wp_unslash($_POST['transaction_status'])) : '';
		if (empty($status)) {
			$status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
		}
		$order_id_xmoney = isset($_POST['order_id_xmoney']) ? sanitize_text_field(wp_unslash($_POST['order_id_xmoney'])) : '';

		if (! $order_id) {
			wp_send_json_error(array('message' => __('Missing order ID.', 'xmoney-woocommerce')));
			return;
		}

		$order = wc_get_order($order_id);

		if (! $order) {
			wp_send_json_error(array('message' => __('Order not found.', 'xmoney-woocommerce')));
			return;
		}

		// Update order based on payment status.
		// Valid success statuses: complete-ok, in-progress, open-ok (case-insensitive).
		$status_lower = strtolower($status);
		$success_statuses = array('complete-ok', 'in-progress', 'open-ok');
		$is_success = in_array($status_lower, $success_statuses, true);

		if ($is_success) {
			// Payment successful.
			$order->payment_complete();
			$order->add_order_note(
				sprintf(
					/* translators: %s: Transaction status */
					__('Payment completed via xMoney. Status: %s', 'xmoney-woocommerce'),
					$status
				)
			);

			// Store xMoney order ID if provided.
			if ($order_id_xmoney) {
				$order->update_meta_data('_xmoney_order_id', $order_id_xmoney);
				$order->save();
			}

			// Clear cart.
			WC()->cart->empty_cart();

			wp_send_json_success(
				array(
					'redirect' => $this->get_return_url($order),
				)
			);
		} else {
			// Payment failed or unknown status.
			$order->update_status('failed', sprintf(
				/* translators: %s: Transaction status */
				__('Payment failed via xMoney. Status: %s', 'xmoney-woocommerce'),
				$status
			));

			wp_send_json_error(
				array(
					'message' => __('Payment failed. Please try again.', 'xmoney-woocommerce'),
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
