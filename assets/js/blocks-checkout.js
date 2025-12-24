/**
 * xMoney WooCommerce Blocks Checkout Integration
 */
(function () {
  "use strict";

  const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
  const { createElement, useState, useEffect, useRef, useCallback } =
    window.wp.element;
  const { getSetting } = window.wc.wcSettings;
  const { decodeEntities } = window.wp.htmlEntities;

  // Get settings from PHP
  const settings = getSetting("xmoney_wc_data", {});
  const title = settings.title
    ? decodeEntities(settings.title)
    : "Credit/Debit Card";
  const description = settings.description
    ? decodeEntities(settings.description)
    : "";
  const icon = settings.icon || "";

  // Generate unique container ID to avoid conflicts
  let containerCounter = 0;

  /**
   * Label component - shows payment method name and icon
   */
  const Label = (props) => {
    const { PaymentMethodLabel } = props.components;
    return createElement(
      "span",
      { className: "xmoney-wc-label" },
      icon
        ? createElement("img", {
            src: icon,
            alt: title,
            className: "xmoney-wc-icon",
            style: {
              height: "24px",
              marginRight: "8px",
              verticalAlign: "middle",
            },
          })
        : null,
      createElement(PaymentMethodLabel, { text: title })
    );
  };

  /**
   * Content component - the payment form
   */
  const Content = (props) => {
    const { eventRegistration, emitResponse } = props;
    const { onPaymentSetup } = eventRegistration;
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [paymentFormReady, setPaymentFormReady] = useState(false);
    const formInstanceRef = useRef(null);
    const containerRef = useRef(null);
    const [containerId] = useState(
      () => `xmoney-wc-blocks-form-${++containerCounter}`
    );
    const mountedRef = useRef(true);
    const initializingRef = useRef(false);

    // Initialize payment form
    const initPaymentForm = useCallback(async () => {
      // Prevent multiple initializations
      if (initializingRef.current || formInstanceRef.current) {
        return;
      }
      initializingRef.current = true;

      try {
        // Wait for SDK to load
        if (typeof window.XMoneyPaymentForm === "undefined") {
          await new Promise((resolve, reject) => {
            if (typeof window.XMoneyPaymentForm !== "undefined") {
              resolve();
              return;
            }
            const existingScript = document.querySelector(
              'script[src*="xmoney.js"]'
            );
            if (existingScript) {
              existingScript.addEventListener("load", resolve);
              return;
            }
            const script = document.createElement("script");
            script.src = "https://secure.xmoney.com/sdk/v1/xmoney.js";
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
          });
        }

        if (!mountedRef.current) return;

        // Create payment intent from cart
        const response = await fetch(settings.ajaxUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: new URLSearchParams({
            action: "xmoney_wc_create_payment_intent_from_cart",
            nonce: settings.nonce,
          }),
        });

        const data = await response.json();

        if (!data.success || !data.data) {
          throw new Error(
            data.data?.message || "Failed to create payment intent"
          );
        }

        if (!mountedRef.current) return;

        // Wait for container to be in DOM
        await new Promise((resolve) => {
          const checkContainer = () => {
            if (document.getElementById(containerId)) {
              resolve();
            } else {
              requestAnimationFrame(checkContainer);
            }
          };
          checkContainer();
        });

        if (!mountedRef.current) return;

        // Parse wallet settings
        const enableGooglePay =
          settings.enableGooglePay === true ||
          settings.enableGooglePay === "true";
        const enableApplePay =
          settings.enableApplePay === true ||
          settings.enableApplePay === "true";
        const enableSavedCards =
          settings.enableSavedCards === true ||
          settings.enableSavedCards === "true";

        // Create payment form
        formInstanceRef.current = new window.XMoneyPaymentForm({
          container: containerId,
          publicKey: data.data.publicKey,
          orderPayload: data.data.payload,
          orderChecksum: data.data.checksum,
          options: {
            locale: settings.locale || "en-US",
            displaySubmitButton: false,
            buttonType: "pay",
            validationMode: "onBlur",
            displaySaveCardOption: enableSavedCards,
            enableSavedCards: enableSavedCards,
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
          onReady: () => {
            if (mountedRef.current) {
              setIsLoading(false);
              setPaymentFormReady(true);
            }
          },
          onError: (err) => {
            if (mountedRef.current) {
              // Don't show error for 404 on cards endpoint - it's not critical
              if (err && typeof err === "string" && err.includes("404")) {
                return;
              }
              setError(err?.message || "Payment form error");
              setIsLoading(false);
            }
          },
          onPaymentComplete: (result) => {
            window.xmoneyPaymentResult = result;
          },
        });
      } catch (err) {
        if (mountedRef.current) {
          setError(err.message || "Failed to load payment form");
          setIsLoading(false);
        }
      } finally {
        initializingRef.current = false;
      }
    }, [containerId]);

    // Initialize on mount
    useEffect(() => {
      mountedRef.current = true;

      // Small delay to ensure container is rendered
      const timer = setTimeout(() => {
        initPaymentForm();
      }, 100);

      return () => {
        mountedRef.current = false;
        clearTimeout(timer);

        // Cleanup form instance
        if (formInstanceRef.current) {
          try {
            formInstanceRef.current.destroy();
          } catch (e) {
            // Ignore destroy errors
          }
          formInstanceRef.current = null;
        }
      };
    }, [initPaymentForm]);

    // Handle payment setup (when user clicks Place Order)
    useEffect(() => {
      const unsubscribe = onPaymentSetup(async () => {
        if (!paymentFormReady || !formInstanceRef.current) {
          return {
            type: emitResponse.responseTypes.ERROR,
            message: "Payment form is not ready. Please wait.",
          };
        }

        try {
          // Check if payment already completed (e.g., Google Pay or Apple Pay).
          // Wallet payments complete when user authorizes, before clicking "Place Order".
          const existingResult = window.xmoneyPaymentResult;

          if (existingResult) {
            const txStatus =
              existingResult.transactionStatus || existingResult.status || "";
            const successStatuses = ["complete-ok", "in-progress", "open-ok"];
            const isExistingSuccess = successStatuses.some(
              (s) => txStatus.toLowerCase() === s.toLowerCase()
            );

            if (isExistingSuccess) {
              return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                  paymentMethodData: {
                    xmoney_payment_result: JSON.stringify(existingResult),
                    xmoney_transaction_status:
                      existingResult.transactionStatus || "",
                    xmoney_external_order_id:
                      existingResult.externalOrderId || "",
                  },
                },
              };
            }
          }

          // Clear previous result for card payments.
          window.xmoneyPaymentResult = null;

          // Submit the payment form
          formInstanceRef.current.submit();

          // Wait for payment result from onPaymentComplete callback
          const result = await new Promise((resolve, reject) => {
            let attempts = 0;
            const maxAttempts = 600; // 60 seconds

            const checkResult = setInterval(() => {
              attempts++;
              if (window.xmoneyPaymentResult) {
                clearInterval(checkResult);
                const txStatus =
                  window.xmoneyPaymentResult.transactionStatus ||
                  window.xmoneyPaymentResult.status ||
                  "";

                // Check for success statuses (case-insensitive)
                const successStatuses = [
                  "complete-ok",
                  "in-progress",
                  "open-ok",
                ];
                const isSuccess = successStatuses.some(
                  (s) => txStatus.toLowerCase() === s.toLowerCase()
                );

                if (isSuccess) {
                  resolve(window.xmoneyPaymentResult);
                } else {
                  reject(new Error("Payment was not completed: " + txStatus));
                }
              } else if (attempts >= maxAttempts) {
                clearInterval(checkResult);
                reject(new Error("Payment timeout"));
              }
            }, 100);
          });

          return {
            type: emitResponse.responseTypes.SUCCESS,
            meta: {
              paymentMethodData: {
                xmoney_payment_result: JSON.stringify(result),
                xmoney_transaction_status: result.transactionStatus || "",
                xmoney_external_order_id: result.externalOrderId || "",
              },
            },
          };
        } catch (err) {
          return {
            type: emitResponse.responseTypes.ERROR,
            message: err.message || "Payment failed",
          };
        }
      });

      return unsubscribe;
    }, [onPaymentSetup, paymentFormReady, emitResponse.responseTypes]);

    // Render
    if (error) {
      return createElement(
        "div",
        { className: "xmoney-wc-error" },
        createElement(
          "p",
          {
            style: {
              color: "#d63638",
              padding: "12px",
              background: "#fcf0f1",
              borderRadius: "4px",
            },
          },
          error
        )
      );
    }

    return createElement(
      "div",
      { className: "xmoney-wc-blocks-wrapper" },
      description &&
        createElement("p", { style: { marginBottom: "16px" } }, description),
      createElement(
        "div",
        {
          id: containerId,
          ref: containerRef,
          className: "xmoney-wc-blocks-form",
          style: { minHeight: "300px", position: "relative" },
        },
        isLoading &&
          createElement(
            "div",
            {
              className: "xmoney-wc-loading",
              style: {
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                minHeight: "200px",
                color: "#666",
              },
            },
            "Loading payment form..."
          )
      )
    );
  };

  /**
   * Register the payment method
   */
  registerPaymentMethod({
    name: "xmoney_wc",
    label: createElement(Label, null),
    content: createElement(Content, null),
    edit: createElement("div", null, "xMoney Payment Form (Editor Preview)"),
    canMakePayment: () => true,
    ariaLabel: title,
    supports: {
      features: settings.supports || ["products"],
    },
  });
})();
