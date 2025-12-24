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
      console.log("xMoney Classic Checkout initializing...");

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
            console.error("Error destroying checkout instance:", e);
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
        console.warn("xMoney payment form container not found");
        return;
      }

      // Destroy existing instance.
      if (this.checkoutInstance) {
        try {
          this.checkoutInstance.destroy();
        } catch (e) {
          console.error("Error destroying checkout instance:", e);
        }
        this.checkoutInstance = null;
      }

      // Wait for SDK to load.
      if (typeof window.XMoneyPaymentForm === "undefined") {
        console.log("Waiting for xMoney SDK...");
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
            console.error("Payment intent error:", response);
            self.showError(
              (response.data && response.data.message) ||
                "Failed to initialize payment form."
            );
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX error:", { status: status, error: error });
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
        console.error("Container not found");
        return;
      }

      if (typeof window.XMoneyPaymentForm === "undefined") {
        console.error("SDK not loaded");
        return;
      }

      // Clear container.
      $("#xmoney-wc-payment-form").empty().removeClass("xmoney-ready");

      var options = {
        locale: xmoneyWc.locale || "en-US",
        buttonType: "pay",
        displaySubmitButton: true,
        displaySaveCardOption: xmoneyWc.enableSavedCards || false,
        enableSavedCards: xmoneyWc.enableSavedCards || false,
        validationMode: "onBlur",
        googlePay: {
          enabled: xmoneyWc.enableGooglePay || false,
        },
        applePay: {
          enabled: xmoneyWc.enableApplePay || false,
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
        console.log("Creating xMoney payment form...");
        this.checkoutInstance = new window.XMoneyPaymentForm({
          container: "xmoney-wc-payment-form",
          publicKey: paymentIntent.publicKey,
          orderPayload: paymentIntent.payload,
          orderChecksum: paymentIntent.checksum,
          options: options,
          onReady: function () {
            console.log("xMoney payment form ready");
            $("#xmoney-wc-payment-form").addClass("xmoney-ready");
          },
          onError: function (error) {
            console.error("xMoney error:", error);
            self.showError("Payment form error.");
          },
          onPaymentComplete: function (data) {
            console.log("Payment complete:", data);
            self.handlePaymentComplete(data);
          },
        });
      } catch (error) {
        console.error("Error creating payment form:", error);
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
            var urlParams = new URLSearchParams(
              response.redirect.split("?")[1]
            );
            var orderId =
              urlParams.get("order_id") || urlParams.get("order-received");
            var orderKey = urlParams.get("key");

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

      $.ajax({
        type: "POST",
        url: xmoneyWc.ajaxUrl,
        data: {
          action: "xmoney_wc_handle_payment_complete",
          nonce: xmoneyWc.nonce,
          order_id: orderId,
          order_key: orderKey,
          transaction_status: data.transactionStatus || data.status || "",
          order_id_xmoney: data.externalOrderId || data.orderId || "",
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
