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
			'public_key'             => array(
				'title'       => __('Public Key', 'xmoney-woocommerce'),
				'type'        => 'text',
				'description' => __('Your xMoney public key (must start with pk_test_ or pk_live_).', 'xmoney-woocommerce'),
				'default'     => '',
			),
			'secret_key'             => array(
				'title'       => __('Secret Key', 'xmoney-woocommerce'),
				'type'        => 'password',
				'description' => __('Your xMoney secret key (must start with sk_test_ or sk_live_).', 'xmoney-woocommerce'),
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
		// Validate keys before saving.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$public_key = isset($_POST['woocommerce_xmoney_wc_public_key']) ? sanitize_text_field(wp_unslash($_POST['woocommerce_xmoney_wc_public_key'])) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$secret_key = isset($_POST['woocommerce_xmoney_wc_secret_key']) ? sanitize_text_field(wp_unslash($_POST['woocommerce_xmoney_wc_secret_key'])) : '';

		$errors = array();

		if (! empty($public_key) && ! XMoney_WC_Helper::is_valid_public_key($public_key)) {
			$errors[] = __('Public Key must start with pk_test_ or pk_live_.', 'xmoney-woocommerce');
		}

		if (! empty($secret_key) && ! XMoney_WC_Helper::is_valid_secret_key($secret_key)) {
			$errors[] = __('Secret Key must start with sk_test_ or sk_live_.', 'xmoney-woocommerce');
		}

		// If there are validation errors, show them and don't save.
		if (! empty($errors)) {
			foreach ($errors as $error) {
				WC_Admin_Settings::add_error($error);
			}
			return false;
		}

		$saved = parent::process_admin_options();

		// Save settings to options table for easier access.
		if ($saved) {
			update_option('xmoney_wc_public_key', $this->get_option('public_key', ''));
			update_option('xmoney_wc_secret_key', $this->get_option('secret_key', ''));
		}

		return $saved;
	}

	/**
	 * Output the gateway settings page with modern UI.
	 */
	public function admin_options()
	{
		// Enqueue admin assets.
		wp_enqueue_style(
			'xmoney-wc-admin',
			XMONEY_WC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			XMONEY_WC_VERSION
		);

		wp_enqueue_script(
			'xmoney-wc-admin',
			XMONEY_WC_PLUGIN_URL . 'assets/js/admin.js',
			array('jquery'),
			XMONEY_WC_VERSION,
			true
		);

		// Auto-detect environment from public key.
		$public_key = $this->get_option('public_key', '');
		$environment = XMoney_WC_Helper::get_environment($public_key);
		$is_configured = ! empty($public_key) && ! empty($this->get_option('secret_key'));

		?>
		<div class="xmoney-admin-wrap xmoney-gateway-settings">
			<div class="xmoney-admin-header">
				<div class="xmoney-header-content">
					<img src="<?php echo esc_url(XMONEY_WC_PLUGIN_URL . 'assets/logo.png'); ?>" alt="xMoney" class="xmoney-logo">
					<div class="xmoney-header-text">
						<h2><?php esc_html_e('xMoney', 'xmoney-woocommerce'); ?></h2>
						<p><?php esc_html_e('Accept payments with embedded checkout', 'xmoney-woocommerce'); ?></p>
					</div>
				</div>
				<div class="xmoney-header-status">
					<?php if ($is_configured) : ?>
						<?php if ('live' === $environment) : ?>
							<span class="xmoney-status xmoney-status-live">
								<span class="xmoney-status-dot"></span>
								<?php esc_html_e('Live', 'xmoney-woocommerce'); ?>
							</span>
						<?php else : ?>
							<span class="xmoney-status xmoney-status-test">
								<span class="xmoney-status-dot"></span>
								<?php esc_html_e('Test Mode', 'xmoney-woocommerce'); ?>
							</span>
						<?php endif; ?>
					<?php else : ?>
						<span class="xmoney-status xmoney-status-inactive">
							<span class="xmoney-status-dot"></span>
							<?php esc_html_e('Not configured', 'xmoney-woocommerce'); ?>
						</span>
					<?php endif; ?>
				</div>
			</div>

			<div class="xmoney-admin-content">
				<!-- Enable Gateway -->
				<div class="xmoney-card xmoney-card-primary">
					<div class="xmoney-card-header">
						<div class="xmoney-card-title">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<rect x="2" y="5" width="20" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
								<path d="M2 10H22" stroke="currentColor" stroke-width="2"/>
							</svg>
							<span><?php esc_html_e('Enable xMoney Payments', 'xmoney-woocommerce'); ?></span>
						</div>
						<label class="xmoney-toggle">
							<input type="checkbox" name="woocommerce_xmoney_wc_enabled" value="yes" <?php checked($this->enabled, 'yes'); ?>>
							<span class="xmoney-toggle-slider"></span>
						</label>
					</div>
					<p class="xmoney-card-description">
						<?php esc_html_e('When enabled, xMoney will appear as a payment option on your checkout page.', 'xmoney-woocommerce'); ?>
					</p>
				</div>

				<!-- API Credentials -->
				<div class="xmoney-card">
					<h2 class="xmoney-section-title">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="2"/>
							<path d="M7 11V7C7 4.23858 9.23858 2 12 2C14.7614 2 17 4.23858 17 7V11" stroke="currentColor" stroke-width="2"/>
						</svg>
						<?php esc_html_e('API Credentials', 'xmoney-woocommerce'); ?>
					</h2>
					<p class="xmoney-section-description">
						<?php esc_html_e('Enter your xMoney API credentials. The environment (test/live) is automatically detected from your key prefixes.', 'xmoney-woocommerce'); ?>
					</p>

					<div class="xmoney-field">
						<label for="woocommerce_xmoney_wc_public_key"><?php esc_html_e('Public Key', 'xmoney-woocommerce'); ?></label>
						<input type="text" id="woocommerce_xmoney_wc_public_key" name="woocommerce_xmoney_wc_public_key" value="<?php echo esc_attr($public_key); ?>" placeholder="pk_test_... or pk_live_...">
						<p class="xmoney-field-hint">
							<?php esc_html_e('Must start with pk_test_ (testing) or pk_live_ (production).', 'xmoney-woocommerce'); ?>
						</p>
					</div>
					<div class="xmoney-field">
						<label for="woocommerce_xmoney_wc_secret_key"><?php esc_html_e('Secret Key', 'xmoney-woocommerce'); ?></label>
						<input type="password" id="woocommerce_xmoney_wc_secret_key" name="woocommerce_xmoney_wc_secret_key" value="<?php echo esc_attr($this->get_option('secret_key')); ?>" placeholder="sk_test_... or sk_live_...">
						<button type="button" class="xmoney-show-password" aria-label="Show password">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2"/>
								<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/>
							</svg>
						</button>
						<p class="xmoney-field-hint">
							<?php esc_html_e('Must start with sk_test_ (testing) or sk_live_ (production).', 'xmoney-woocommerce'); ?>
						</p>
					</div>
				</div>

				<!-- Payment Methods -->
				<div class="xmoney-card">
					<h2 class="xmoney-section-title">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
							<rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
						</svg>
						<?php esc_html_e('Payment Methods', 'xmoney-woocommerce'); ?>
					</h2>
					<p class="xmoney-section-description">
						<?php esc_html_e("Select which payment methods you'd like to offer to your shoppers.", 'xmoney-woocommerce'); ?>
					</p>

					<div class="xmoney-payment-methods">
						<!-- Credit/Debit Card -->
						<div class="xmoney-payment-method">
							<div class="xmoney-payment-method-icon">
								<svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<rect x="2" y="5" width="20" height="14" rx="2" stroke="#1e1e1e" stroke-width="1.5"/>
									<path d="M2 10H22" stroke="#1e1e1e" stroke-width="1.5"/>
									<path d="M6 15H10" stroke="#1e1e1e" stroke-width="1.5" stroke-linecap="round"/>
								</svg>
							</div>
							<div class="xmoney-payment-method-info">
								<h3><?php esc_html_e('Credit/Debit Card', 'xmoney-woocommerce'); ?></h3>
								<p><?php esc_html_e('Accepts all major credit and debit cards', 'xmoney-woocommerce'); ?></p>
							</div>
							<label class="xmoney-toggle">
								<input type="checkbox" checked disabled>
								<span class="xmoney-toggle-slider"></span>
							</label>
						</div>

						<!-- Saved Cards -->
						<div class="xmoney-payment-method">
							<div class="xmoney-payment-method-icon">
								<svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H19C20.1046 3 21 3.89543 21 5V19C21 20.1046 20.1046 21 19 21Z" stroke="#1e1e1e" stroke-width="1.5"/>
									<path d="M12 8V16M8 12H16" stroke="#1e1e1e" stroke-width="1.5" stroke-linecap="round"/>
								</svg>
							</div>
							<div class="xmoney-payment-method-info">
								<h3><?php esc_html_e('Saved Cards', 'xmoney-woocommerce'); ?></h3>
								<p><?php esc_html_e('Allow customers to save cards for faster checkout', 'xmoney-woocommerce'); ?></p>
							</div>
							<label class="xmoney-toggle">
								<input type="checkbox" name="woocommerce_xmoney_wc_enable_saved_cards" value="yes" <?php checked($this->get_option('enable_saved_cards'), 'yes'); ?>>
								<span class="xmoney-toggle-slider"></span>
							</label>
						</div>

						<!-- Apple Pay -->
						<div class="xmoney-payment-method">
							<div class="xmoney-payment-method-icon xmoney-icon-apple-pay">
								<svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M17.0425 12.8587C17.0181 10.5169 18.9444 9.38937 19.0369 9.33187C17.9494 7.75687 16.2669 7.53812 15.6669 7.51687C14.2294 7.36812 12.8356 8.38687 12.1044 8.38687C11.3606 8.38687 10.2169 7.53062 9.00438 7.55562C7.44063 7.57937 5.98563 8.49562 5.18188 9.92812C3.52813 12.8337 4.76313 17.1494 6.34813 19.4969C7.14063 20.6456 8.06688 21.9331 9.27813 21.8894C10.4656 21.8419 10.9131 21.1269 12.3319 21.1269C13.7381 21.1269 14.1619 21.8894 15.3981 21.8619C16.6706 21.8419 17.4744 20.7019 18.2419 19.5419C19.1544 18.2206 19.5244 16.9219 19.5494 16.8544C19.5194 16.8444 17.0706 15.9194 17.0425 12.8587Z" fill="#1e1e1e"/>
									<path d="M14.7038 5.88562C15.3413 5.09812 15.7813 4.02312 15.6588 2.93562C14.7338 2.97312 13.5838 3.56062 12.9213 4.33062C12.3338 5.01312 11.8013 6.12312 11.9413 7.17312C12.9713 7.25062 14.0413 6.66062 14.7038 5.88562Z" fill="#1e1e1e"/>
								</svg>
							</div>
							<div class="xmoney-payment-method-info">
								<h3><?php esc_html_e('Apple Pay', 'xmoney-woocommerce'); ?></h3>
								<p><?php esc_html_e('Give your shoppers an easy and secure way to pay', 'xmoney-woocommerce'); ?></p>
							</div>
							<label class="xmoney-toggle">
								<input type="checkbox" name="woocommerce_xmoney_wc_enable_apple_pay" value="yes" <?php checked($this->get_option('enable_apple_pay'), 'yes'); ?>>
								<span class="xmoney-toggle-slider"></span>
							</label>
						</div>

						<!-- Google Pay -->
						<div class="xmoney-payment-method">
							<div class="xmoney-payment-method-icon xmoney-icon-google-pay">
								<svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M12.2168 11.8164V14.4727H16.9336C16.7266 15.6836 16.0664 16.6953 15.0742 17.3945L17.3438 19.1641C18.6914 17.9141 19.4688 16.0977 19.4688 13.9336C19.4688 13.3125 19.4141 12.7109 19.3086 12.1289H12.2168V11.8164Z" fill="#4285F4"/>
									<path d="M6.39453 13.7344L5.91406 14.0977L4.14062 15.4805C5.54688 18.2695 8.42578 20.1875 11.7578 20.1875C13.9219 20.1875 15.7305 19.4648 17.0547 18.2656L14.8438 16.5117C14.0938 17.0195 13.1211 17.3281 11.7578 17.3281C9.6875 17.3281 7.92969 15.8281 7.26172 13.8359L6.39453 13.7344Z" fill="#34A853"/>
									<path d="M4.14062 8.51953C3.64844 9.49219 3.375 10.5781 3.375 11.7188C3.375 12.8594 3.64844 13.9453 4.14062 14.918C4.14062 14.9297 6.45703 12.707 6.45703 12.707C6.26953 12.1992 6.16016 11.6523 6.16016 11.0898C6.16016 10.5273 6.26953 9.98047 6.45703 9.47266L4.14062 8.51953Z" fill="#FBBC05"/>
									<path d="M11.7578 6.08984C13.2461 6.08984 14.5703 6.60156 15.6211 7.58203L17.1133 6.08984C15.7227 4.80078 13.9219 4 11.7578 4C8.42578 4 5.54688 5.91797 4.14062 8.70703L6.45703 10.6602C7.12891 8.66797 8.88672 7.16797 11.7578 6.08984Z" fill="#EA4335"/>
								</svg>
							</div>
							<div class="xmoney-payment-method-info">
								<h3><?php esc_html_e('Google Pay', 'xmoney-woocommerce'); ?></h3>
								<p><?php esc_html_e('Offer customers a fast and secure checkout experience', 'xmoney-woocommerce'); ?></p>
							</div>
							<label class="xmoney-toggle">
								<input type="checkbox" name="woocommerce_xmoney_wc_enable_google_pay" value="yes" <?php checked($this->get_option('enable_google_pay'), 'yes'); ?>>
								<span class="xmoney-toggle-slider"></span>
							</label>
						</div>
					</div>
				</div>

				<!-- Display Settings -->
				<div class="xmoney-card">
					<h2 class="xmoney-section-title">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" stroke="currentColor" stroke-width="2"/>
							<path d="M19.4 15C19.2669 15.3016 19.2272 15.6362 19.286 15.9606C19.3448 16.285 19.4995 16.5843 19.73 16.82L19.79 16.88C19.976 17.0657 20.1235 17.2863 20.2241 17.5291C20.3248 17.7719 20.3766 18.0322 20.3766 18.295C20.3766 18.5578 20.3248 18.8181 20.2241 19.0609C20.1235 19.3037 19.976 19.5243 19.79 19.71C19.6043 19.896 19.3837 20.0435 19.1409 20.1441C18.8981 20.2448 18.6378 20.2966 18.375 20.2966C18.1122 20.2966 17.8519 20.2448 17.6091 20.1441C17.3663 20.0435 17.1457 19.896 16.96 19.71L16.9 19.65C16.6643 19.4195 16.365 19.2648 16.0406 19.206C15.7162 19.1472 15.3816 19.1869 15.08 19.32C14.7842 19.4468 14.532 19.6572 14.3543 19.9255C14.1766 20.1938 14.0813 20.5082 14.08 20.83V21C14.08 21.5304 13.8693 22.0391 13.4942 22.4142C13.1191 22.7893 12.6104 23 12.08 23C11.5496 23 11.0409 22.7893 10.6658 22.4142C10.2907 22.0391 10.08 21.5304 10.08 21V20.91C10.0723 20.579 9.96512 20.258 9.77251 19.9887C9.5799 19.7194 9.31074 19.5143 9 19.4C8.69838 19.2669 8.36381 19.2272 8.03941 19.286C7.71502 19.3448 7.41568 19.4995 7.18 19.73L7.12 19.79C6.93425 19.976 6.71368 20.1235 6.47088 20.2241C6.22808 20.3248 5.96783 20.3766 5.705 20.3766C5.44217 20.3766 5.18192 20.3248 4.93912 20.2241C4.69632 20.1235 4.47575 19.976 4.29 19.79C4.10405 19.6043 3.95653 19.3837 3.85588 19.1409C3.75523 18.8981 3.70343 18.6378 3.70343 18.375C3.70343 18.1122 3.75523 17.8519 3.85588 17.6091C3.95653 17.3663 4.10405 17.1457 4.29 16.96L4.35 16.9C4.58054 16.6643 4.73519 16.365 4.794 16.0406C4.85282 15.7162 4.81312 15.3816 4.68 15.08C4.55324 14.7842 4.34276 14.532 4.07447 14.3543C3.80618 14.1766 3.49179 14.0813 3.17 14.08H3C2.46957 14.08 1.96086 13.8693 1.58579 13.4942C1.21071 13.1191 1 12.6104 1 12.08C1 11.5496 1.21071 11.0409 1.58579 10.6658C1.96086 10.2907 2.46957 10.08 3 10.08H3.09C3.42099 10.0723 3.742 9.96512 4.0113 9.77251C4.28059 9.5799 4.48572 9.31074 4.6 9C4.73312 8.69838 4.77282 8.36381 4.714 8.03941C4.65519 7.71502 4.50054 7.41568 4.27 7.18L4.21 7.12C4.02405 6.93425 3.87653 6.71368 3.77588 6.47088C3.67523 6.22808 3.62343 5.96783 3.62343 5.705C3.62343 5.44217 3.67523 5.18192 3.77588 4.93912C3.87653 4.69632 4.02405 4.47575 4.21 4.29C4.39575 4.10405 4.61632 3.95653 4.85912 3.85588C5.10192 3.75523 5.36217 3.70343 5.625 3.70343C5.88783 3.70343 6.14808 3.75523 6.39088 3.85588C6.63368 3.95653 6.85425 4.10405 7.04 4.29L7.1 4.35C7.33568 4.58054 7.63502 4.73519 7.95941 4.794C8.28381 4.85282 8.61838 4.81312 8.92 4.68H9C9.29577 4.55324 9.54802 4.34276 9.72569 4.07447C9.90337 3.80618 9.99872 3.49179 10 3.17V3C10 2.46957 10.2107 1.96086 10.5858 1.58579C10.9609 1.21071 11.4696 1 12 1C12.5304 1 13.0391 1.21071 13.4142 1.58579C13.7893 1.96086 14 2.46957 14 3V3.09C14.0013 3.41179 14.0966 3.72618 14.2743 3.99447C14.452 4.26276 14.7042 4.47324 15 4.6C15.3016 4.73312 15.6362 4.77282 15.9606 4.714C16.285 4.65519 16.5843 4.50054 16.82 4.27L16.88 4.21C17.0657 4.02405 17.2863 3.87653 17.5291 3.77588C17.7719 3.67523 18.0322 3.62343 18.295 3.62343C18.5578 3.62343 18.8181 3.67523 19.0609 3.77588C19.3037 3.87653 19.5243 4.02405 19.71 4.21C19.896 4.39575 20.0435 4.61632 20.1441 4.85912C20.2448 5.10192 20.2966 5.36217 20.2966 5.625C20.2966 5.88783 20.2448 6.14808 20.1441 6.39088C20.0435 6.63368 19.896 6.85425 19.71 7.04L19.65 7.1C19.4195 7.33568 19.2648 7.63502 19.206 7.95941C19.1472 8.28381 19.1869 8.61838 19.32 8.92V9C19.4468 9.29577 19.6572 9.54802 19.9255 9.72569C20.1938 9.90337 20.5082 9.99872 20.83 10H21C21.5304 10 22.0391 10.2107 22.4142 10.5858C22.7893 10.9609 23 11.4696 23 12C23 12.5304 22.7893 13.0391 22.4142 13.4142C22.0391 13.7893 21.5304 14 21 14H20.91C20.5882 14.0013 20.2738 14.0966 20.0055 14.2743C19.7372 14.452 19.5268 14.7042 19.4 15Z" stroke="currentColor" stroke-width="2"/>
						</svg>
						<?php esc_html_e('Display Settings', 'xmoney-woocommerce'); ?>
					</h2>

					<div class="xmoney-field">
						<label for="woocommerce_xmoney_wc_title"><?php esc_html_e('Title', 'xmoney-woocommerce'); ?></label>
						<input type="text" id="woocommerce_xmoney_wc_title" name="woocommerce_xmoney_wc_title" value="<?php echo esc_attr($this->get_option('title')); ?>" placeholder="xMoney">
						<p class="xmoney-field-hint"><?php esc_html_e('This is the title customers see during checkout.', 'xmoney-woocommerce'); ?></p>
					</div>

					<div class="xmoney-field">
						<label for="woocommerce_xmoney_wc_description"><?php esc_html_e('Description', 'xmoney-woocommerce'); ?></label>
						<textarea id="woocommerce_xmoney_wc_description" name="woocommerce_xmoney_wc_description" rows="3" placeholder="Pay securely with your credit or debit card."><?php echo esc_textarea($this->get_option('description')); ?></textarea>
						<p class="xmoney-field-hint"><?php esc_html_e('This is the description customers see during checkout.', 'xmoney-woocommerce'); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
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
			// Payment already completed (from Blocks or Classic checkout).
			$payment_result = json_decode($payment_result_json, true);
			
			if ($payment_result) {
				$tx_status = strtolower($payment_result['transactionStatus'] ?? '');
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
