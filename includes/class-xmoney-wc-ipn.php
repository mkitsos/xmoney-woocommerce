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
	 */
	public function handle_ipn_request() {
		global $wp_query;

		if ( ! isset( $wp_query->query_vars['xmoney_wc_ipn'] ) ) {
			return;
		}

		// Log IPN request.
		$this->log( 'IPN request received' );

		// Get request data.
		$raw_data = file_get_contents( 'php://input' );
		$data     = json_decode( $raw_data, true );

		if ( ! $data ) {
			$this->log( 'Invalid IPN data format' );
			$this->send_response( 400, 'Invalid data format' );
			return;
		}

		// Extract order ID from IPN data.
		$order_id = isset( $data['orderId'] ) ? absint( $data['orderId'] ) : 0;

		if ( ! $order_id ) {
			$this->log( 'Order ID missing in IPN data' );
			$this->send_response( 400, 'Order ID missing' );
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			$this->log( 'Order not found: ' . $order_id );
			$this->send_response( 404, 'Order not found' );
			return;
		}

		// Verify IPN signature if provided.
		// Note: xMoney may send signature verification in headers or data.
		// Adjust based on actual xMoney IPN implementation.

		// Process IPN based on status (xMoney uses 'transactionStatus' field).
		$status = isset( $data['transactionStatus'] ) ? sanitize_text_field( $data['transactionStatus'] ) : '';
		if ( empty( $status ) ) {
			$status = isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : '';
		}

		$this->process_ipn_status( $order, $status, $data );

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
					$order->add_order_note( __( 'Payment confirmed via xMoney IPN.', 'xmoney-woocommerce' ) );

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
				$order->update_status( 'failed', __( 'Payment failed via xMoney IPN.', 'xmoney-woocommerce' ) );
				break;

			case 'cancel-ok':
			case 'refund-ok':
			case 'void-ok':
				// Refunded.
				$order->update_status( 'refunded', __( 'Payment refunded via xMoney IPN.', 'xmoney-woocommerce' ) );
				break;

			case 'three-d-pending':
			case 'in-progress':
				// Payment pending.
				$order->update_status( 'on-hold', __( 'Payment pending via xMoney IPN.', 'xmoney-woocommerce' ) );
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

