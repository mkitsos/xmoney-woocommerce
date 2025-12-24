<?php
/**
 * XMoney WooCommerce Blocks Payment Method Support
 *
 * @package XMoney_WooCommerce
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * XMoney Blocks Payment Method Class
 */
final class XMoney_WC_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'xmoney_wc';

	/**
	 * Gateway instance.
	 *
	 * @var XMoney_WC_Gateway
	 */
	private $gateway;

	/**
	 * Initialize the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_xmoney_wc_settings', array() );
		
		// Get gateway instance.
		$gateways = WC()->payment_gateways()->payment_gateways();
		$this->gateway = isset( $gateways['xmoney_wc'] ) ? $gateways['xmoney_wc'] : null;
	}

	/**
	 * Returns if this payment method should be active.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_path       = 'assets/js/blocks-checkout.js';
		$script_url        = XMONEY_WC_PLUGIN_URL . $script_path;
		$script_asset_path = XMONEY_WC_PLUGIN_DIR . 'assets/js/blocks-checkout.asset.php';
		
		$script_asset = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => XMONEY_WC_VERSION,
			);

		wp_register_script(
			'xmoney-wc-blocks',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		// Register and enqueue styles.
		wp_register_style(
			'xmoney-wc-blocks',
			XMONEY_WC_PLUGIN_URL . 'assets/css/checkout.css',
			array(),
			XMONEY_WC_VERSION
		);
		wp_enqueue_style( 'xmoney-wc-blocks' );

		return array( 'xmoney-wc-blocks' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$configuration = XMoney_WC_Helper::get_configuration();
		
		return array(
			'title'             => $this->get_setting( 'title', __( 'xMoney', 'xmoney-woocommerce' ) ),
			'description'       => $this->get_setting( 'description', __( 'Pay securely with your credit or debit card.', 'xmoney-woocommerce' ) ),
			'supports'          => array_filter( $this->gateway ? $this->gateway->supports : array(), array( $this->gateway, 'supports' ) ),
			'icon'              => XMONEY_WC_PLUGIN_URL . 'assets/logo.png',
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'xmoney_wc_nonce' ),
			'locale'            => XMoney_WC_Helper::get_xmoney_locale(),
			'publicKey'         => $configuration['public_key'],
			'enableSavedCards'  => 'yes' === $this->get_setting( 'enable_saved_cards', 'no' ),
			'enableGooglePay'   => 'yes' === $this->get_setting( 'enable_google_pay', 'yes' ),
			'enableApplePay'    => 'yes' === $this->get_setting( 'enable_apple_pay', 'yes' ),
		);
	}
}

