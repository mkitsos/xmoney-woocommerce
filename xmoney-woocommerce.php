<?php
/**
 * Plugin Name: xMoney Payments for WooCommerce
 * Plugin URI: https://www.xmoney.com
 * Description: Accept payments via xMoney Payment Form (Embedded Checkout) directly on your WooCommerce checkout page.
 * Version: 1.0.0
 * Author: xMoney
 * Author URI: https://www.xmoney.com
 * Text Domain: xmoney-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package XMoney_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'XMONEY_WC_VERSION', '1.0.0' );
define( 'XMONEY_WC_PLUGIN_FILE', __FILE__ );
define( 'XMONEY_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'XMONEY_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'XMONEY_WC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main XMoney_WooCommerce Class
 */
final class XMoney_WooCommerce {

	/**
	 * Plugin instance.
	 *
	 * @var XMoney_WooCommerce
	 */
	private static $instance = null;

	/**
	 * Get plugin instance.
	 *
	 * @return XMoney_WooCommerce
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Check if WooCommerce is active.
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		
		// Register activation hook.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		
		// Register deactivation hook.
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Load plugin files.
		$this->includes();

		// Initialize gateway.
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once XMONEY_WC_PLUGIN_DIR . 'includes/class-xmoney-wc-helper.php';
		require_once XMONEY_WC_PLUGIN_DIR . 'includes/class-xmoney-wc-gateway.php';
		require_once XMONEY_WC_PLUGIN_DIR . 'includes/class-xmoney-wc-ajax.php';
		require_once XMONEY_WC_PLUGIN_DIR . 'includes/class-xmoney-wc-ipn.php';
	}

	/**
	 * Add gateway to WooCommerce.
	 *
	 * @param array $gateways List of gateways.
	 * @return array
	 */
	public function add_gateway( $gateways ) {
		$gateways[] = 'XMoney_WC_Gateway';
		return $gateways;
	}

	/**
	 * Show notice if WooCommerce is not installed.
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="error">
			<p>
				<strong><?php esc_html_e( 'xMoney Payments for WooCommerce', 'xmoney-woocommerce' ); ?></strong>
				<?php esc_html_e( 'requires WooCommerce to be installed and active.', 'xmoney-woocommerce' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		// Flush rewrite rules to register IPN endpoint.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		// Flush rewrite rules on deactivation.
		flush_rewrite_rules();
	}
}

/**
 * Main instance of XMoney_WooCommerce.
 *
 * @return XMoney_WooCommerce
 */
function XMoney_WC() {
	return XMoney_WooCommerce::instance();
}

// Initialize plugin.
XMoney_WC();

