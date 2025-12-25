<?php
/**
 * XMoney WooCommerce IPN Handler
 *
 * Handles server-to-server notifications from xMoney.
 *
 * @package XMoney_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IPN Handler Class
 */
class XMoney_WC_IPN {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register IPN endpoint.
		add_action( 'init', array( $this, 'register_ipn_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'handle_ipn_request' ) );
	}

	/**
	 * Register IPN endpoint.
	 */
	public function register_ipn_endpoint() {
		add_rewrite_rule( '^xmoney-wc-ipn/?$', 'index.php?xmoney_wc_ipn=1', 'top' );
		add_rewrite_tag( '%xmoney_wc_ipn%', '([^&]+)' );
	}

	/**
	 * Handle IPN request.
	 *
	 * SECURITY: This handler verifies payment status via xMoney API
	 * rather than trusting the IPN data directly.
	 */
	public function handle_ipn_request() {
		global $wp_query;

		if ( ! isset( $wp_query->query_vars['xmoney_wc_ipn'] ) ) {
			return;
		}

		$this->log( 'IPN request received' );

		// Get request data.
		$raw_data = file_get_contents( 'php://input' );
		$data     = json_decode( $raw_data, true );

		if ( ! $data ) {
			$this->log( 'Invalid IPN data format' );
			$this->send_response( 400, 'Invalid data format' );
			return;
		}

		// Extract external order ID from IPN data (WooCommerce order ID or temp ID).
		$external_order_id = isset( $data['externalOrderId'] ) ? sanitize_text_field( $data['externalOrderId'] ) : '';

		if ( empty( $external_order_id ) ) {
			$this->log( 'External Order ID missing in IPN data' );
			$this->send_response( 400, 'External Order ID missing' );
			return;
		}

		// Extract WooCommerce order ID from external order ID.
		// Format can be: "123" or "temp-1234567890-abcdefgh".
		$wc_order_id = 0;
		if ( strpos( $external_order_id, 'temp-' ) === 0 ) {
			// For temp orders, we need to find the order by meta data.
			$this->log( 'IPN for temp order: ' . $external_order_id );
			// Temp orders are handled differently - skip for now.
			$this->send_response( 200, 'OK - temp order, will be handled by checkout' );
			return;
		} else {
			$wc_order_id = absint( $external_order_id );
		}

		if ( ! $wc_order_id ) {
			$this->log( 'Could not extract WC Order ID from: ' . $external_order_id );
			$this->send_response( 400, 'Invalid order ID format' );
			return;
		}

		$order = wc_get_order( $wc_order_id );

		if ( ! $order ) {
			$this->log( 'Order not found: ' . $wc_order_id );
			$this->send_response( 404, 'Order not found' );
			return;
		}

		// SECURITY: Verify payment status via xMoney API.
		// Never trust IPN data directly - always verify with the API.
		$verification = XMoney_WC_Helper::verify_payment_status( $external_order_id );

		if ( is_wp_error( $verification ) ) {
			$this->log( 'API verification failed: ' . $verification->get_error_message() );
			// Still return 200 to prevent retries - mark order for manual review.
			$order->add_order_note(
				sprintf(
					XMoney_WC_Helper::get_order_note( 'ipn_verification_failed' ),
					$verification->get_error_message()
				)
			);
			$this->send_response( 200, 'OK - verification pending' );
			return;
		}

		// Process based on API-verified status.
		$api_status = $verification['order_status'];
		$this->log( 'API verified status: ' . $api_status );
		$this->process_ipn_status( $order, $api_status, $verification );

		// Send success response.
		$this->send_response( 200, 'OK' );
	}

	/**
	 * Process IPN status.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $status Payment status.
	 * @param array    $data IPN data.
	 */
	private function process_ipn_status( WC_Order $order, string $status, array $data ) {
		switch ( $status ) {
			case 'complete-ok':
				// Payment successful.
				if ( ! $order->is_paid() ) {
					$order->payment_complete();
					$order->add_order_note( XMoney_WC_Helper::get_order_note( 'ipn_confirmed' ) );

					// Store transaction ID if provided.
					if ( isset( $data['transactionId'] ) ) {
						$order->update_meta_data( '_xmoney_transaction_id', sanitize_text_field( $data['transactionId'] ) );
					}

					// Store xMoney order ID if provided.
					if ( isset( $data['orderId'] ) ) {
						$order->update_meta_data( '_xmoney_order_id', sanitize_text_field( $data['orderId'] ) );
					}

					$order->save();
				}
				break;

			case 'complete-fail':
				// Payment failed.
				$order->update_status( 'failed', XMoney_WC_Helper::get_order_note( 'ipn_failed' ) );
				break;

			case 'cancel-ok':
			case 'refund-ok':
			case 'void-ok':
				// Refunded.
				$order->update_status( 'refunded', XMoney_WC_Helper::get_order_note( 'ipn_refunded' ) );
				break;

			case 'three-d-pending':
			case 'in-progress':
				// Payment pending.
				$order->update_status( 'on-hold', XMoney_WC_Helper::get_order_note( 'ipn_pending' ) );
				break;

			default:
				$this->log( 'Unknown IPN status: ' . $status );
				break;
		}
	}

	/**
	 * Send HTTP response.
	 *
	 * @param int    $status_code HTTP status code.
	 * @param string $message Response message.
	 */
	private function send_response( int $status_code, string $message ) {
		http_response_code( $status_code );
		echo esc_html( $message );
		exit;
	}

	/**
	 * Log message.
	 *
	 * @param string $message Log message.
	 */
	private function log( string $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->info( '[xMoney IPN] ' . $message, array( 'source' => 'xmoney-woocommerce' ) );
		}
	}
}

new XMoney_WC_IPN();

