<?php

/**
 * XMoney WooCommerce Payment Gateway
 *
 * @package XMoney_WooCommerce
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * XMoney Payment Gateway Class
 */
class XMoney_WC_Gateway extends WC_Payment_Gateway
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->id                 = 'xmoney_wc';
		$this->icon               = XMONEY_WC_PLUGIN_URL . 'assets/logo.png';
		$this->has_fields         = true;
		$this->method_title       = __('xMoney', 'xmoney-woocommerce');
		$this->method_description = __('Accept payments via xMoney Payment Form directly on your checkout page.', 'xmoney-woocommerce');

		// Supported features.
		$this->supports = array(
			'products',
			'refunds',
		);

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title              = $this->get_option('title', __('xMoney', 'xmoney-woocommerce'));
		$this->description        = $this->get_option('description', __('Pay securely with your credit or debit card.', 'xmoney-woocommerce'));
		$this->enabled            = $this->get_option('enabled', 'yes');
		$this->enable_for_methods = $this->get_option('enable_for_methods', array());
		$this->enable_for_virtual = 'yes' === $this->get_option('enable_for_virtual', 'yes');

		// Fix title if it was set to "xMoney Payments" (legacy).
		if ('xMoney Payments' === $this->title) {
			$this->title = __('xMoney', 'xmoney-woocommerce');
		}

		// Actions.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
	}

	/**
	 * Initialize form fields.
	 */
	public function init_form_fields()
	{
		// Get shipping methods for restrictions.
		$shipping_methods = array();
		if (function_exists('WC')) {
			$shipping_zones = WC_Shipping_Zones::get_zones();
			foreach ($shipping_zones as $zone) {
				$shipping_methods_in_zone = $zone['shipping_methods'];
				foreach ($shipping_methods_in_zone as $method) {
					$shipping_methods[$method->id] = $method->get_title();
				}
			}
			// Add default zone methods.
			$default_zone = new WC_Shipping_Zone(0);
			$default_methods = $default_zone->get_shipping_methods(true);
			foreach ($default_methods as $method) {
				$shipping_methods[$method->id] = $method->get_title();
			}
		}

		$this->form_fields = array(
			'enabled'                => array(
				'title'   => __('Enable/Disable', 'xmoney-woocommerce'),
				'type'    => 'checkbox',
				'label'   => __('Enable xMoney', 'xmoney-woocommerce'),
				'default' => 'yes',
			),
			'title'                  => array(
				'title'       => __('Title', 'xmoney-woocommerce'),
				'type'        => 'text',
				'description' => __('This controls the title which the customer sees during checkout.', 'xmoney-woocommerce'),
				'default'     => __('xMoney', 'xmoney-woocommerce'),
				'desc_tip'    => true,
			),
			'description'            => array(
				'title'       => __('Description', 'xmoney-woocommerce'),
				'type'        => 'textarea',
				'description' => __('This controls the description which the customer sees during checkout.', 'xmoney-woocommerce'),
				'default'     => __('Pay securely with your credit or debit card.', 'xmoney-woocommerce'),
				'desc_tip'    => true,
			),
			'live_mode'              => array(
				'title'   => __('Live Mode', 'xmoney-woocommerce'),
				'type'    => 'checkbox',
				'label'   => __('Enable live mode', 'xmoney-woocommerce'),
				'default' => 'no',
			),
			'test_site_id'           => array(
				'title'       => __('Test Site ID', 'xmoney-woocommerce'),
				'type'        => 'text',
				'description' => __('Your xMoney test site ID.', 'xmoney-woocommerce'),
				'default'     => '',
			),
			'test_public_key'        => array(
				'title'       => __('Test Public Key', 'xmoney-woocommerce'),
				'type'        => 'text',
				'description' => __('Your xMoney test public key (starts with pk_test_).', 'xmoney-woocommerce'),
				'default'     => '',
			),
			'test_secret_key'        => array(
				'title'       => __('Test Secret Key', 'xmoney-woocommerce'),
				'type'        => 'password',
				'description' => __('Your xMoney test secret key (starts with sk_test_).', 'xmoney-woocommerce'),
				'default'     => '',
			),
			'live_site_id'           => array(
				'title'       => __('Live Site ID', 'xmoney-woocommerce'),
				'type'        => 'text',
				'description' => __('Your xMoney live site ID.', 'xmoney-woocommerce'),
				'default'     => '',
			),
			'live_public_key'        => array(
				'title'       => __('Live Public Key', 'xmoney-woocommerce'),
				'type'        => 'text',
				'description' => __('Your xMoney live public key (starts with pk_live_).', 'xmoney-woocommerce'),
				'default'     => '',
			),
			'live_secret_key'        => array(
				'title'       => __('Live Secret Key', 'xmoney-woocommerce'),
				'type'        => 'password',
				'description' => __('Your xMoney live secret key (starts with sk_live_).', 'xmoney-woocommerce'),
				'default'     => '',
			),
			'enable_saved_cards'     => array(
				'title'       => __('Enable Saved Cards', 'xmoney-woocommerce'),
				'type'        => 'checkbox',
				'label'       => __('Allow customers to save cards for future purchases', 'xmoney-woocommerce'),
				'description' => __('Enable one-click payments for returning customers.', 'xmoney-woocommerce'),
				'default'     => 'no',
			),
			'enable_google_pay'      => array(
				'title'   => __('Enable Google Pay', 'xmoney-woocommerce'),
				'type'    => 'checkbox',
				'label'   => __('Enable Google Pay', 'xmoney-woocommerce'),
				'default' => 'yes',
			),
			'enable_apple_pay'       => array(
				'title'   => __('Enable Apple Pay', 'xmoney-woocommerce'),
				'type'    => 'checkbox',
				'label'   => __('Enable Apple Pay', 'xmoney-woocommerce'),
				'default' => 'yes',
			),
			'enable_for_methods'     => array(
				'title'             => __('Enable for shipping methods', 'xmoney-woocommerce'),
				'type'              => 'multiselect',
				'class'             => 'wc-enhanced-select',
				'css'               => 'width: 400px;',
				'default'           => '',
				'description'       => __('If xMoney is only available for certain shipping methods, set it up here. Leave blank to enable for all methods.', 'xmoney-woocommerce'),
				'options'           => $shipping_methods,
				'desc_tip'          => true,
				'custom_attributes' => array(
					'data-placeholder' => __('Select shipping methods', 'xmoney-woocommerce'),
				),
			),
			'enable_for_virtual'    => array(
				'title'   => __('Accept for virtual orders', 'xmoney-woocommerce'),
				'label'   => __('Accept xMoney if the order is virtual', 'xmoney-woocommerce'),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
		);
	}

	/**
	 * Process admin options and save to options table.
	 *
	 * @return bool
	 */
	public function process_admin_options()
	{
		$saved = parent::process_admin_options();

		// Save settings to options table for easier access.
		if ($saved) {
			update_option('xmoney_wc_live_mode', $this->get_option('live_mode', 'no'));
			update_option('xmoney_wc_test_site_id', $this->get_option('test_site_id', ''));
			update_option('xmoney_wc_test_public_key', $this->get_option('test_public_key', ''));
			update_option('xmoney_wc_test_secret_key', $this->get_option('test_secret_key', ''));
			update_option('xmoney_wc_live_site_id', $this->get_option('live_site_id', ''));
			update_option('xmoney_wc_live_public_key', $this->get_option('live_public_key', ''));
			update_option('xmoney_wc_live_secret_key', $this->get_option('live_secret_key', ''));
		}

		return $saved;
	}

	/**
	 * Check if gateway is available.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		// Check if gateway is enabled.
		if ('yes' !== $this->enabled) {
			return false;
		}

		// Always show the gateway in admin/settings, even if credentials aren't configured yet.
		if (is_admin()) {
			return true;
		}

		$configuration = XMoney_WC_Helper::get_configuration();

		// Check if credentials are configured.
		if (empty($configuration['public_key']) || empty($configuration['secret_key'])) {
			return false;
		}

		// Check shipping restrictions.
		$needs_shipping = false;
		if (WC()->cart && WC()->cart->needs_shipping()) {
			$needs_shipping = true;
		}

		// Virtual order, with virtual disabled.
		if (!$this->enable_for_virtual && !$needs_shipping) {
			return false;
		}

		// Check shipping method restrictions.
		if (!empty($this->enable_for_methods) && $needs_shipping) {
			$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

			if (empty($chosen_shipping_methods) || count($chosen_shipping_methods) > 1) {
				return false;
			}

			$chosen_method = $chosen_shipping_methods[0];
			if (strpos($chosen_method, ':') !== false) {
				$chosen_method = current(explode(':', $chosen_method));
			}

			if (!in_array($chosen_method, $this->enable_for_methods, true)) {
				return false;
			}
		}

		return parent::is_available();
	}

	/**
	 * Payment fields on checkout page.
	 */
	public function payment_fields()
	{
		if ($this->description) {
			echo wp_kses_post(wpautop(wptexturize($this->description)));
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
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);

		if (! $order) {
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}

		// Check if payment result is passed from Blocks checkout.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$payment_result_json = isset($_POST['xmoney_payment_result']) ? sanitize_text_field(wp_unslash($_POST['xmoney_payment_result'])) : '';
		
		if (!empty($payment_result_json)) {
			// Blocks checkout - payment already completed.
			$payment_result = json_decode($payment_result_json, true);
			
			if ($payment_result) {
				$tx_status = $payment_result['transactionStatus'] ?? '';
				$success_statuses = array('complete-ok', 'in-progress', 'open-ok');
				
				if (in_array($tx_status, $success_statuses, true)) {
					// Payment successful - mark order as complete.
					$order->payment_complete();
					$order->add_order_note(__('Payment completed via xMoney (Embedded Checkout).', 'xmoney-woocommerce'));
					
					// Store xMoney order ID.
					$xmoney_order_id = $payment_result['externalOrderId'] ?? '';
					if ($xmoney_order_id) {
						$order->update_meta_data('_xmoney_order_id', $xmoney_order_id);
						$order->save();
					}
					
					// Clear cart.
					WC()->cart->empty_cart();
					
					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url($order),
					);
				} else {
					// Payment failed.
					$order->update_status('failed', sprintf(
						/* translators: %s: Payment status */
						__('Payment failed via xMoney. Status: %s', 'xmoney-woocommerce'),
						$tx_status
					));
					
					return array(
						'result'   => 'fail',
						'redirect' => '',
					);
				}
			}
		}

		// Classic checkout - payment will be processed via JavaScript/AJAX.
		// For now, mark order as pending and let AJAX handler complete it.
		$order->update_status('pending', __('Awaiting xMoney payment.', 'xmoney-woocommerce'));
		
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
	public function enqueue_scripts()
	{
		if (! is_checkout()) {
			return;
		}

		// Enqueue xMoney SDK (load in footer, no version for external script).
		wp_enqueue_script(
			'xmoney-sdk',
			'https://secure.xmoney.com/sdk/v1/xmoney.js',
			array(),
			null, // No version for external scripts.
			true  // Load in footer.
		);

		// Enqueue plugin JavaScript (depends on jQuery and xMoney SDK).
		wp_enqueue_script(
			'xmoney-wc-checkout',
			XMONEY_WC_PLUGIN_URL . 'assets/js/checkout.js',
			array('jquery', 'xmoney-sdk'),
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
				'ajaxUrl'     => admin_url('admin-ajax.php'),
				'nonce'       => wp_create_nonce('xmoney_wc_nonce'),
				'gatewayId'   => $this->id,
				'locale'      => XMoney_WC_Helper::get_xmoney_locale(),
				'enableSavedCards' => 'yes' === $this->get_option('enable_saved_cards', 'no'),
				'enableGooglePay'   => 'yes' === $this->get_option('enable_google_pay', 'yes'),
				'enableApplePay'    => 'yes' === $this->get_option('enable_apple_pay', 'yes'),
			)
		);
	}
}
