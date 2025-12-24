/**
 * xMoney WooCommerce Classic Checkout Script
 */
(function ($) {
  "use strict";

  var XMoneyCheckout = {
    checkoutInstance: null,
    currentOrderId: null,
    currentOrderKey: null,
    isProcessing: false,

    /**
     * Initialize checkout.
     */
    init: function () {
      // Wait for WooCommerce checkout form to be ready.
      $(document.body).on("updated_checkout", this.onCheckoutUpdate.bind(this));
      $(document.body).on(
        "payment_method_selected",
        this.onPaymentMethodSelected.bind(this)
      );

      // Initialize on page load if already on checkout.
      if ($("body").hasClass("woocommerce-checkout")) {
        if ($('input[name="payment_method"]:checked').val() === "xmoney_wc") {
          this.initPaymentForm();
        }
      }
    },

    /**
     * Handle checkout update.
     */
    onCheckoutUpdate: function () {
      var selectedMethod = $('input[name="payment_method"]:checked').val();
      if (
        selectedMethod === "xmoney_wc" &&
        $("#xmoney-wc-payment-form").length &&
        !this.checkoutInstance
      ) {
        this.initPaymentForm();
      }
    },

    /**
     * Handle payment method selection.
     */
    onPaymentMethodSelected: function () {
      var selectedMethod = $('input[name="payment_method"]:checked').val();
      if (selectedMethod === "xmoney_wc") {
        if (!this.checkoutInstance) {
          this.initPaymentForm();
        }
      } else {
        if (this.checkoutInstance) {
          try {
            this.checkoutInstance.destroy();
          } catch (e) {
            // Ignore destroy errors
          }
          this.checkoutInstance = null;
        }
      }
    },

    /**
     * Initialize payment form.
     */
    initPaymentForm: function () {
      var self = this;

      // Check if container exists.
      if (!$("#xmoney-wc-payment-form").length) {
        return;
      }

      // Destroy existing instance.
      if (this.checkoutInstance) {
        try {
          this.checkoutInstance.destroy();
        } catch (e) {
          // Ignore destroy errors
        }
        this.checkoutInstance = null;
      }

      // Wait for SDK to load.
      if (typeof window.XMoneyPaymentForm === "undefined") {
        var checkSDK = setInterval(function () {
          if (typeof window.XMoneyPaymentForm !== "undefined") {
            clearInterval(checkSDK);
            self.createPaymentIntentFromCart();
          }
        }, 100);
        setTimeout(function () {
          clearInterval(checkSDK);
        }, 10000);
        return;
      }

      this.createPaymentIntentFromCart();
    },

    /**
     * Create payment intent from cart.
     */
    createPaymentIntentFromCart: function () {
      var self = this;

      $.ajax({
        type: "POST",
        url: xmoneyWc.ajaxUrl,
        data: {
          action: "xmoney_wc_create_payment_intent_from_cart",
          nonce: xmoneyWc.nonce,
        },
        dataType: "json",
        success: function (response) {
          if (response.success && response.data) {
            self.createPaymentForm(response.data);
          } else {
            self.showError(
              (response.data && response.data.message) ||
                "Failed to initialize payment form."
            );
          }
        },
        error: function () {
          self.showError("Failed to initialize payment form.");
        },
      });
    },

    /**
     * Create payment form instance.
     */
    createPaymentForm: function (paymentIntent) {
      var self = this;

      if (!$("#xmoney-wc-payment-form").length) {
        return;
      }

      if (typeof window.XMoneyPaymentForm === "undefined") {
        return;
      }

      // Clear container.
      $("#xmoney-wc-payment-form").empty().removeClass("xmoney-ready");

      // Build options with wallet settings.
      var enableGooglePay = xmoneyWc.enableGooglePay === true || xmoneyWc.enableGooglePay === "true";
      var enableApplePay = xmoneyWc.enableApplePay === true || xmoneyWc.enableApplePay === "true";
      var enableSavedCards = xmoneyWc.enableSavedCards === true || xmoneyWc.enableSavedCards === "true";

      var options = {
        locale: xmoneyWc.locale || "en-US",
        buttonType: "pay",
        displaySubmitButton: true,
        displaySaveCardOption: enableSavedCards,
        enableSavedCards: enableSavedCards,
        validationMode: "onBlur",
        googlePay: {
          enabled: enableGooglePay,
        },
        applePay: {
          enabled: enableApplePay,
        },
        appearance: {
          theme: "light",
          variables: {
            colorPrimary: "#2271b1",
            borderRadius: "4px",
          },
        },
      };

      try {
        this.checkoutInstance = new window.XMoneyPaymentForm({
          container: "xmoney-wc-payment-form",
          publicKey: paymentIntent.publicKey,
          orderPayload: paymentIntent.payload,
          orderChecksum: paymentIntent.checksum,
          options: options,
          onReady: function () {
            $("#xmoney-wc-payment-form").addClass("xmoney-ready");
          },
          onError: function () {
            self.showError("Payment form error.");
          },
          onPaymentComplete: function (data) {
            self.handlePaymentComplete(data);
          },
        });
      } catch (error) {
        this.showError("Failed to create payment form.");
      }
    },

    /**
     * Handle payment completion.
     */
    handlePaymentComplete: function (data) {
      var self = this;

      if (this.isProcessing) return;
      this.setProcessing(true);

      // Create WooCommerce order first.
      var $form = $("form.checkout");

      $.ajax({
        type: "POST",
        url: wc_checkout_params.checkout_url,
        data: $form.serialize(),
        dataType: "json",
        success: function (response) {
          if (response.result === "success" && response.redirect) {
            // Extract order info from redirect URL.
            var orderId = null;
            var orderKey = null;

            try {
              var url = new URL(response.redirect, window.location.origin);
              var urlParams = url.searchParams;

              // Try different parameter names.
              orderId = urlParams.get("order_id") || urlParams.get("order-received");
              orderKey = urlParams.get("key");

              // Also try to extract from path.
              if (!orderId) {
                var pathMatch = url.pathname.match(/order-received\/(\d+)/);
                if (pathMatch) {
                  orderId = pathMatch[1];
                }
              }
            } catch (e) {
              // Fallback to simple parsing.
              var parts = response.redirect.split("?");
              if (parts[1]) {
                var params = new URLSearchParams(parts[1]);
                orderId = params.get("order_id") || params.get("order-received");
                orderKey = params.get("key");
              }
            }

            if (orderId) {
              self.processPaymentComplete(data, orderId, orderKey);
            } else {
              // Just redirect to thank you page.
              window.location.href = response.redirect;
            }
          } else {
            self.showError(response.messages || "Failed to create order.");
            self.setProcessing(false);
          }
        },
        error: function () {
          self.showError("Failed to create order.");
          self.setProcessing(false);
        },
      });
    },

    /**
     * Process payment completion.
     */
    processPaymentComplete: function (data, orderId, orderKey) {
      var self = this;

      var transactionStatus = data.transactionStatus || data.status || "";
      var xmoneyOrderId = data.externalOrderId || data.orderId || "";

      $.ajax({
        type: "POST",
        url: xmoneyWc.ajaxUrl,
        data: {
          action: "xmoney_wc_handle_payment_complete",
          nonce: xmoneyWc.nonce,
          order_id: orderId,
          order_key: orderKey,
          transaction_status: transactionStatus,
          order_id_xmoney: xmoneyOrderId,
        },
        dataType: "json",
        success: function (response) {
          if (response.success && response.data && response.data.redirect) {
            window.location.href = response.data.redirect;
          } else {
            self.showError(
              (response.data && response.data.message) || "Payment failed."
            );
            self.setProcessing(false);
          }
        },
        error: function () {
          self.showError("Failed to process payment.");
          self.setProcessing(false);
        },
      });
    },

    /**
     * Set processing state.
     */
    setProcessing: function (isProcessing) {
      this.isProcessing = isProcessing;
      var $form = $("form.checkout");

      if (isProcessing) {
        $form.addClass("processing").block({
          message: null,
          overlayCSS: { background: "#fff", opacity: 0.6 },
        });
      } else {
        $form.removeClass("processing").unblock();
      }
    },

    /**
     * Show error message.
     */
    showError: function (message) {
      $(".woocommerce-error, .woocommerce-message").remove();
      $("form.checkout").prepend(
        '<div class="woocommerce-error">' + message + "</div>"
      );
      $("html, body").animate(
        { scrollTop: $("form.checkout").offset().top - 100 },
        500
      );
    },
  };

  // Initialize when DOM is ready.
  $(document).ready(function () {
    XMoneyCheckout.init();
  });
})(jQuery);
