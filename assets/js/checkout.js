/**
 * xMoney WooCommerce Classic Checkout Script
 */
(function ($) {
  "use strict";

  var XMoneyCheckout = {
    checkoutInstance: null,
    isProcessing: false,
    isInitializing: false,
    paymentFormReady: false,
    pendingPaymentResult: null,

    /**
     * Initialize checkout.
     */
    init: function () {
      var self = this;

      // Wait for WooCommerce checkout form to be ready.
      $(document.body).on("updated_checkout", this.onCheckoutUpdate.bind(this));
      $(document.body).on(
        "payment_method_selected",
        this.onPaymentMethodSelected.bind(this)
      );

      // Intercept checkout form submission.
      $("form.checkout").on("checkout_place_order_xmoney_wc", function () {
        return self.onPlaceOrder();
      });

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
        !this.checkoutInstance &&
        !this.isInitializing
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
        if (!this.checkoutInstance && !this.isInitializing) {
          this.initPaymentForm();
        }
      } else {
        // Reset state when switching away from xMoney.
        this.isInitializing = false;
        this.paymentFormReady = false;
        this.pendingPaymentResult = null;
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
     * Handle Place Order button click.
     */
    onPlaceOrder: function () {
      var self = this;

      // Check if payment already completed (wallet payments or card payment).
      if (this.pendingPaymentResult) {
        // Payment already done, proceed with order creation.
        this.isProcessing = false;
        return true;
      }

      // Check if form is ready.
      if (!this.paymentFormReady || !this.checkoutInstance) {
        this.showError("Payment form is not ready. Please wait.");
        return false;
      }

      // Prevent double processing.
      if (this.isProcessing) {
        return false;
      }

      this.isProcessing = true;
      this.setProcessing(true);

      // Submit the xMoney payment form.
      try {
        this.checkoutInstance.submit();
      } catch (e) {
        this.isProcessing = false;
        this.setProcessing(false);
        this.showError("Failed to submit payment.");
        return false;
      }

      // Wait for payment result (onPaymentComplete will be called).
      // Return false to prevent WooCommerce from submitting immediately.
      return false;
    },

    /**
     * Initialize payment form.
     */
    initPaymentForm: function () {
      var self = this;

      // Prevent multiple initializations.
      if (this.isInitializing || this.checkoutInstance) {
        return;
      }

      // Check if container exists.
      if (!$("#xmoney-wc-payment-form").length) {
        return;
      }

      this.isInitializing = true;
      this.paymentFormReady = false;
      this.pendingPaymentResult = null;

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
          self.isInitializing = false;
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
            self.isInitializing = false;
            self.showError(
              (response.data && response.data.message) ||
                "Failed to initialize payment form."
            );
          }
        },
        error: function () {
          self.isInitializing = false;
          self.showError("Failed to initialize payment form.");
        },
      });
    },

    /**
     * Create payment form instance.
     */
    createPaymentForm: function (paymentIntent) {
      var self = this;
      var containerId = "xmoney-wc-payment-form";

      // Verify SDK is available.
      if (typeof window.XMoneyPaymentForm === "undefined") {
        this.isInitializing = false;
        this.showError("Payment SDK not loaded.");
        return;
      }

      // Wait for container to be in DOM.
      var waitForContainer = function () {
        var container = document.getElementById(containerId);
        if (!container) {
          self.isInitializing = false;
          self.showError("Payment form container not found.");
          return;
        }

        // Clear the container completely.
        container.innerHTML = "";

        // Parse wallet settings from plugin.
        var enableGooglePay =
          xmoneyWc.enableGooglePay === true ||
          xmoneyWc.enableGooglePay === "true";
        var enableApplePay =
          xmoneyWc.enableApplePay === true ||
          xmoneyWc.enableApplePay === "true";
        var enableSavedCards =
          xmoneyWc.enableSavedCards === true ||
          xmoneyWc.enableSavedCards === "true";

        var sdkConfig = {
          container: containerId,
          publicKey: paymentIntent.publicKey,
          orderPayload: paymentIntent.payload,
          orderChecksum: paymentIntent.checksum,
          options: {
            locale: xmoneyWc.locale || "en-US",
            buttonType: "pay",
            displaySubmitButton: false,
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
          },
          onReady: function () {
            self.isInitializing = false;
            self.paymentFormReady = true;
            $("#xmoney-wc-payment-form").addClass("xmoney-ready");
          },
          onError: function (err) {
            self.isInitializing = false;
            self.paymentFormReady = false;
            self.isProcessing = false;
            self.setProcessing(false);
            self.checkoutInstance = null;
            self.showError(
              "Payment form error: " +
                (err && err.message ? err.message : "Unknown error")
            );
          },
          onPaymentComplete: function (data) {
            self.handlePaymentComplete(data);
          },
        };

        try {
          self.checkoutInstance = new window.XMoneyPaymentForm(sdkConfig);
        } catch (error) {
          self.isInitializing = false;
          self.showError("Failed to create payment form.");
        }
      };

      // Small delay to ensure DOM is ready.
      setTimeout(waitForContainer, 100);
    },

    /**
     * Handle payment completion from xMoney SDK.
     */
    handlePaymentComplete: function (data) {
      // Check transaction status.
      var txStatus = (
        data.transactionStatus ||
        data.status ||
        ""
      ).toLowerCase();
      var successStatuses = ["complete-ok", "in-progress", "open-ok"];
      var isSuccess = successStatuses.indexOf(txStatus) !== -1;

      if (!isSuccess) {
        this.isProcessing = false;
        this.setProcessing(false);
        this.showError(
          "Payment failed: " + (data.transactionStatus || "Unknown status")
        );
        return;
      }

      // Store the payment result.
      this.pendingPaymentResult = data;

      // Add hidden fields to the form with payment data.
      var $form = $("form.checkout");
      $form.find('input[name="xmoney_payment_result"]').remove();
      $form.find('input[name="xmoney_transaction_status"]').remove();
      $form.find('input[name="xmoney_external_order_id"]').remove();

      $form.append(
        $('<input type="hidden" name="xmoney_payment_result" />').val(
          JSON.stringify(data)
        )
      );
      $form.append(
        $('<input type="hidden" name="xmoney_transaction_status" />').val(
          data.transactionStatus || ""
        )
      );
      $form.append(
        $('<input type="hidden" name="xmoney_external_order_id" />').val(
          data.externalOrderId || ""
        )
      );

      // Reset processing state before re-submitting.
      this.isProcessing = false;

      // Remove the processing block to allow WooCommerce to handle the form.
      $form.removeClass("processing");
      if (typeof $form.unblock === "function") {
        $form.unblock();
      }

      // Now submit the WooCommerce checkout form.
      $form.submit();
    },

    /**
     * Set processing state.
     */
    setProcessing: function (isProcessing) {
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
