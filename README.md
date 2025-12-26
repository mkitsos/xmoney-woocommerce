<div align="center">
  <a href="https://www.xmoney.com">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="./assets/xMoney_White.svg">
      <img alt="xMoney" src="./assets/xMoney_Black.svg" height="40">
    </picture>
  </a>
  <h1>xMoney for WooCommerce</h1>
  <p>
    Accept credit card, debit card, Google Pay, and Apple Pay payments on your WooCommerce store with xMoney's Embedded Checkout. The payment form integrates directly into your checkout page for a seamless customer experience.
  </p>
</div>

## Features

### Payments

- ✅ **Embedded Checkout** - Payment form embedded directly on your checkout page
- ✅ **No Redirects** - Customers stay on your site throughout the payment process
- ✅ **Google Pay** - One-click payments with Google Pay (on supported devices)
- ✅ **Apple Pay** - One-click payments with Apple Pay (on supported devices)
- ✅ **Saved Cards** - Optional one-click payments for returning logged-in customers

### Checkout Compatibility

- ✅ **Classic Checkout** - Full support for traditional WooCommerce checkout
- ✅ **Blocks Checkout** - Full support for the new WooCommerce Blocks checkout
- ✅ **HPOS Compatible** - Works with High-Performance Order Storage

### Security

- ✅ **PCI DSS Compliant** - Card data never touches your servers
- ✅ **SSL Detection** - Warns if your site doesn't have SSL enabled

### Merchant Experience

- ✅ **Modern Settings UI** - Clean, intuitive admin interface
- ✅ **Auto Environment Detection** - Automatically detects test/live mode from API keys
- ✅ **Appearance Customization** - Customize payment form colors to match your brand
- ✅ **Detailed Order Notes** - Comprehensive payment status in order notes

## Requirements

| Requirement | Minimum Version        |
| ----------- | ---------------------- |
| WordPress   | 5.8+                   |
| WooCommerce | 6.0+                   |
| PHP         | 7.4+                   |
| SSL         | Required for live mode |

### Regional Availability

xMoney is currently available for merchants in the **European Economic Area (EEA)**:

Austria, Belgium, Bulgaria, Croatia, Cyprus, Czech Republic, Denmark, Estonia, Finland, France, Germany, Greece, Hungary, Iceland, Ireland, Italy, Latvia, Liechtenstein, Lithuania, Luxembourg, Malta, Netherlands, Norway, Poland, Portugal, Romania, Slovakia, Slovenia, Spain, Sweden.

## Installation

### From WordPress Admin

1. Download the plugin ZIP file
2. Go to **Plugins → Add New → Upload Plugin**
3. Choose the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Manual Installation

1. Download or clone this repository
2. Upload the `xmoney-woocommerce` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress

### Configuration

1. Go to **WooCommerce → Settings → Payments**
2. Click **Manage** next to **xMoney**
3. Enter your API credentials and configure options

## Getting Your API Credentials

1. Sign up for an xMoney account at <https://dashboard.xmoney.com>
2. Navigate to **Settings → API Keys**
3. Copy your:
   - **Public Key** - Starts with `pk_test_` (test) or `pk_live_` (live)
   - **Secret Key** - Starts with `sk_test_` (test) or `sk_live_` (live)

> **Note:** The environment (test/live) is automatically detected based on your key prefixes. No need to toggle modes manually.

## Settings

### Settings Tab

| Setting              | Description                                                  |
| -------------------- | ------------------------------------------------------------ |
| **Enable xMoney**    | Turn the payment method on or off                            |
| **Title**            | Payment method title shown at checkout (default: "xMoney")   |
| **Description**      | Optional description shown during checkout                   |
| **Public Key**       | Your xMoney public API key                                   |
| **Secret Key**       | Your xMoney secret API key                                   |
| **Saved Cards**      | Allow logged-in customers to save cards for future purchases |
| **Google Pay**       | Enable Google Pay as a payment option                        |
| **Apple Pay**        | Enable Apple Pay as a payment option                         |
| **Shipping Methods** | Restrict to specific shipping methods (optional)             |
| **Virtual Orders**   | Enable for orders with only virtual products                 |

### Appearance Tab

Customize the payment form to match your brand:

| Setting              | Description                              |
| -------------------- | ---------------------------------------- |
| **Theme Mode**       | Light, Dark, or Custom color scheme      |
| **Primary Color**    | Buttons and accent color                 |
| **Danger Color**     | Error and validation color               |
| **Background Color** | Form background                          |
| **Text Colors**      | Primary, secondary, and placeholder text |
| **Border Colors**    | Input borders and focus states           |
| **Border Radius**    | Corner roundness (e.g., 4px, 8px)        |

## How It Works

### Payment Flow

```text
Customer selects xMoney -> Payment form loads -> Customer enters details
                                                          |
                                                          v
Order confirmed <- Payment verified via API <- Payment processed
```

1. Customer selects xMoney as payment method on checkout
2. Embedded payment form loads securely via xMoney SDK
3. Customer enters card details or uses Google Pay/Apple Pay
4. On "Place Order", payment is processed via xMoney
5. **Payment status is verified server-side via xMoney API**
6. Order is marked as paid only after API verification
7. Customer sees order confirmation

## File Structure

```text
xmoney-woocommerce/
├── xmoney-woocommerce.php              # Main plugin file
├── assets/
│   ├── logo.png                        # Plugin logo
│   ├── css/
│   │   ├── checkout.css                # Frontend checkout styles
│   │   └── admin.css                   # Admin settings styles
│   └── js/
│       ├── checkout.js                 # Classic checkout JavaScript
│       ├── blocks-checkout.js          # Blocks checkout JavaScript
│       ├── blocks-checkout.asset.php   # Blocks dependencies
│       └── admin.js                    # Admin settings JavaScript
├── includes/
│   ├── class-xmoney-wc-gateway.php     # Payment gateway class
│   ├── class-xmoney-wc-helper.php      # Helper functions & API calls
│   ├── class-xmoney-wc-ajax.php        # AJAX handlers
│   ├── class-xmoney-wc-ipn.php         # IPN/webhook handler
│   └── blocks/
│       └── class-xmoney-wc-blocks-support.php  # Blocks checkout support
└── README.md
```

## Order Meta Data

The plugin stores the following meta data on orders:

| Meta Key                 | Description           |
| ------------------------ | --------------------- |
| `_xmoney_order_id`       | xMoney order ID       |
| `_xmoney_customer_id`    | xMoney customer ID    |
| `_xmoney_transaction_id` | xMoney transaction ID |

## Testing

### Test Mode

Use test API keys (starting with `pk_test_` and `sk_test_`) to test the integration without processing real payments.

When in test mode:

- A yellow notice appears on the checkout page
- A "Test Mode" badge appears in admin settings
- No real charges are made

### Test Cards

| Card Number         | Result             |
| ------------------- | ------------------ |
| 4111 1111 1111 1111 | Successful payment |
| 4000 0000 0000 0002 | Declined           |
| 4000 0000 0000 9995 | Insufficient funds |

Use any future expiry date and any 3-digit CVV.

## Troubleshooting

### Payment form not loading

1. Check browser console for JavaScript errors
2. Verify your public key is correct
3. Ensure your site has a valid SSL certificate
4. Check that WooCommerce is properly configured

### Gateway not appearing at checkout

1. Ensure the plugin is enabled in WooCommerce → Settings → Payments
2. Verify your store is in an EEA country
3. Check shipping method restrictions in plugin settings
4. Ensure API credentials are configured

## Hooks & Filters

### Actions

```php
// After successful payment verification
do_action( 'xmoney_wc_payment_complete', $order, $verification_data );
```

### Filters

```php
// Modify order data before sending to xMoney
$order_data = apply_filters( 'xmoney_wc_order_data', $order_data, $order );

// Modify appearance configuration
$appearance = apply_filters( 'xmoney_wc_appearance_config', $appearance );
```

## Changelog

### 1.0.0

- Initial release
- Embedded checkout for Classic and Blocks checkout
- Google Pay and Apple Pay support
- Saved cards for logged-in customers
- Server-side payment verification
- Appearance customization
- EEA region support

## Support

- **Documentation:** <https://docs.xmoney.com>
- **Dashboard:** <https://dashboard.xmoney.com>
- **Email:** <support@xmoney.com>

## License

This plugin is licensed under the GPL v2 or later.

```text
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```
