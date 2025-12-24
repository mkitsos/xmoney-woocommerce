<?php
/**
 * XMoney WooCommerce Payment Gateway
 *
 * @package XMoney_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * XMoney Payment Gateway Class
 */
class XMoney_WC_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'xmoney_wc';
		$this->icon               = XMONEY_WC_PLUGIN_URL . 'assets/logo.png';
		$this->has_fields         = true;
		$this->method_title       = __( 'xMoney Payments', 'xmoney-woocommerce' );
		$this->method_description = __( 'Accept payments via xMoney Payment Form directly on your checkout page.', 'xmoney-woocommerce' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title       = $this->get_option( 'title', __( 'xMoney Payments', 'xmoney-woocommerce' ) );
		$this->description = $this->get_option( 'description', __( 'Pay securely with your credit or debit card.', 'xmoney-woocommerce' ) );
		$this->enabled     = $this->get_option( 'enabled', 'yes' );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                => array(
				'title'   => __( 'Enable/Disable', 'xmoney-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable xMoney Payments', 'xmoney-woocommerce' ),
				'default' => 'yes',
			),
			'title'                  => array(
				'title'       => __( 'Title', 'xmoney-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the customer sees during checkout.', 'xmoney-woocommerce' ),
				'default'     => __( 'xMoney Payments', 'xmoney-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'            => array(
				'title'       => __( 'Description', 'xmoney-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the customer sees during checkout.', 'xmoney-woocommerce' ),
				'default'     => __( 'Pay securely with your credit or debit card.', 'xmoney-woocommerce' ),
				'desc_tip'    => true,
			),
			'live_mode'              => array(
				'title'   => __( 'Live Mode', 'xmoney-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable live mode', 'xmoney-woocommerce' ),
				'default' => 'no',
			),
			'test_site_id'           => array(
				'title'       => __( 'Test Site ID', 'xmoney-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your xMoney test site ID.', 'xmoney-woocommerce' ),
				'default'     => '',
			),
			'test_public_key'        => array(
				'title'       => __( 'Test Public Key', 'xmoney-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your xMoney test public key (starts with pk_test_).', 'xmoney-woocommerce' ),
				'default'     => '',
			),
			'test_secret_key'        => array(
				'title'       => __( 'Test Secret Key', 'xmoney-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Your xMoney test secret key (starts with sk_test_).', 'xmoney-woocommerce' ),
				'default'     => '',
			),
			'live_site_id'           => array(
				'title'       => __( 'Live Site ID', 'xmoney-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your xMoney live site ID.', 'xmoney-woocommerce' ),
				'default'     => '',
			),
			'live_public_key'        => array(
				'title'       => __( 'Live Public Key', 'xmoney-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your xMoney live public key (starts with pk_live_).', 'xmoney-woocommerce' ),
				'default'     => '',
			),
			'live_secret_key'        => array(
				'title'       => __( 'Live Secret Key', 'xmoney-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Your xMoney live secret key (starts with sk_live_).', 'xmoney-woocommerce' ),
				'default'     => '',
			),
			'enable_saved_cards'     => array(
				'title'       => __( 'Enable Saved Cards', 'xmoney-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Allow customers to save cards for future purchases', 'xmoney-woocommerce' ),
				'description' => __( 'Enable one-click payments for returning customers.', 'xmoney-woocommerce' ),
				'default'     => 'no',
			),
			'enable_google_pay'      => array(
				'title'   => __( 'Enable Google Pay', 'xmoney-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Google Pay', 'xmoney-woocommerce' ),
				'default' => 'yes',
			),
			'enable_apple_pay'       => array(
				'title'   => __( 'Enable Apple Pay', 'xmoney-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Apple Pay', 'xmoney-woocommerce' ),
				'default' => 'yes',
			),
		);
	}

	/**
	 * Process admin options and save to options table.
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		$saved = parent::process_admin_options();

		// Save settings to options table for easier access.
		if ( $saved ) {
			update_option( 'xmoney_wc_live_mode', $this->get_option( 'live_mode', 'no' ) );
			update_option( 'xmoney_wc_test_site_id', $this->get_option( 'test_site_id', '' ) );
			update_option( 'xmoney_wc_test_public_key', $this->get_option( 'test_public_key', '' ) );
			update_option( 'xmoney_wc_test_secret_key', $this->get_option( 'test_secret_key', '' ) );
			update_option( 'xmoney_wc_live_site_id', $this->get_option( 'live_site_id', '' ) );
			update_option( 'xmoney_wc_live_public_key', $this->get_option( 'live_public_key', '' ) );
			update_option( 'xmoney_wc_live_secret_key', $this->get_option( 'live_secret_key', '' ) );
		}

		return $saved;
	}

	/**
	 * Check if gateway is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		$configuration = XMoney_WC_Helper::get_configuration();

		// Check if credentials are configured.
		if ( empty( $configuration['public_key'] ) || empty( $configuration['secret_key'] ) ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Payment fields on checkout page.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}

		// Container for the embedded payment form.
		echo '<div id="xmoney-wc-payment-form" class="xmoney-wc-payment-form-container"></div>';
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

		// Payment will be processed via JavaScript/AJAX.
		// Return success to allow checkout to proceed.
		// The actual payment processing happens in the frontend.
		return array(
			'result'   => 'success',
			'redirect' => add_query_arg(
				array(
					'order_id' => $order_id,
					'key'      => $order->get_order_key(),
				),
				wc_get_checkout_url()
			),
		);
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		// Enqueue xMoney SDK.
		wp_enqueue_script(
			'xmoney-sdk',
			'https://secure.xmoney.com/sdk/v1/xmoney.js',
			array(),
			XMONEY_WC_VERSION,
			true
		);

		// Enqueue plugin JavaScript.
		wp_enqueue_script(
			'xmoney-wc-checkout',
			XMONEY_WC_PLUGIN_URL . 'assets/js/checkout.js',
			array( 'jquery', 'xmoney-sdk' ),
			XMONEY_WC_VERSION,
			true
		);

		// Enqueue plugin CSS.
		wp_enqueue_style(
			'xmoney-wc-checkout',
			XMONEY_WC_PLUGIN_URL . 'assets/css/checkout.css',
			array(),
			XMONEY_WC_VERSION
		);

		// Localize script with data.
		wp_localize_script(
			'xmoney-wc-checkout',
			'xmoneyWc',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'xmoney_wc_nonce' ),
				'gatewayId'   => $this->id,
				'locale'      => XMoney_WC_Helper::get_xmoney_locale(),
				'enableSavedCards' => 'yes' === $this->get_option( 'enable_saved_cards', 'no' ),
				'enableGooglePay'   => 'yes' === $this->get_option( 'enable_google_pay', 'yes' ),
				'enableApplePay'    => 'yes' === $this->get_option( 'enable_apple_pay', 'yes' ),
			)
		);
	}
}

