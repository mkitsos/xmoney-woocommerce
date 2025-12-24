# xMoney for WooCommerce

A WooCommerce payment gateway plugin that integrates xMoney Payment Form (Embedded Checkout) directly into your checkout page. Customers can complete payments without leaving your site.

## Features

- ✅ **Embedded Checkout** - Payment form embedded directly on your checkout page
- ✅ **No Redirects** - Customers stay on your site throughout the payment process
- ✅ **PCI Compliant** - Card data never touches your servers
- ✅ **Classic & Blocks Checkout** - Works with both WooCommerce checkout types
- ✅ **Google Pay & Apple Pay** - Support for digital wallets
- ✅ **Saved Cards** - Optional one-click payments for returning customers
- ✅ **Server-to-Server Notifications** - IPN/webhook support for order status updates
- ✅ **Test Mode** - Test your integration before going live

## Installation

1. Download or clone this repository
2. Upload the `xmoney-woocommerce` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to WooCommerce → Settings → Payments → xMoney
5. Configure your API credentials

## Configuration

### Getting Your API Credentials

1. Sign up for an xMoney account at [https://dashboard.xmoney.com](https://dashboard.xmoney.com)
2. Navigate to API Settings
3. Copy your:
   - **Site ID**
   - **Public Key** (starts with `pk_test_` or `pk_live_`)
   - **Secret Key** (starts with `sk_test_` or `sk_live_`)

### Plugin Settings

1. **Enable/Disable** - Turn the gateway on or off
2. **Title** - Payment method title shown to customers
3. **Description** - Payment method description
4. **Live Mode** - Toggle between test and live mode
5. **Test Credentials** - Site ID, Public Key, and Secret Key for testing
6. **Live Credentials** - Site ID, Public Key, and Secret Key for production
7. **Enable Saved Cards** - Allow customers to save cards for future purchases
8. **Enable Google Pay** - Show Google Pay button (if supported)
9. **Enable Apple Pay** - Show Apple Pay button (if supported)

## How It Works

1. Customer selects xMoney as payment method on checkout
2. Payment form loads embedded in the checkout page
3. Customer enters payment details (or uses saved card/digital wallet)
4. Payment is processed securely via xMoney
5. Order is created and marked as paid on success
6. Customer is redirected to order confirmation page

## Development

### File Structure

```
xmoney-woocommerce/
├── xmoney-woocommerce.php           # Main plugin file
├── includes/
│   ├── class-xmoney-wc-gateway.php  # Payment gateway class
│   ├── class-xmoney-wc-helper.php   # Helper functions
│   ├── class-xmoney-wc-ajax.php     # AJAX handlers
│   ├── class-xmoney-wc-ipn.php      # IPN/webhook handler
│   └── blocks/
│       └── class-xmoney-wc-blocks-support.php  # Blocks checkout support
├── assets/
│   ├── logo.png                     # Plugin logo
│   ├── js/
│   │   ├── checkout.js              # Classic checkout JS
│   │   ├── blocks-checkout.js       # Blocks checkout JS
│   │   └── blocks-checkout.asset.php
│   └── css/
│       └── checkout.css             # Frontend styles
└── README.md
```

### Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

## Support

For issues, questions, or contributions, please visit:
- [xMoney Documentation](https://docs.xmoney.com)
- [xMoney Dashboard](https://dashboard.xmoney.com)

## License

GPL v2 or later
