/**
 * xMoney WooCommerce Checkout Script
 */
(function($) {
	'use strict';

	var XMoneyCheckout = {
		checkoutInstance: null,
		currentOrderId: null,
		currentOrderKey: null,
		isProcessing: false,

		/**
		 * Initialize checkout.
		 */
		init: function() {
			// Wait for WooCommerce checkout form to be ready.
			$(document.body).on('updated_checkout', this.onCheckoutUpdate.bind(this));
			$(document.body).on('checkout_place_order', this.onPlaceOrder.bind(this));
			$(document.body).on('payment_method_selected', this.onPaymentMethodSelected.bind(this));

			// Initialize on page load if already on checkout.
			if ($('body').hasClass('woocommerce-checkout')) {
				// Check if payment method is already selected.
				if ($('input[name="payment_method"]:checked').val() === 'xmoney_wc') {
					this.initPaymentForm();
				}
			}
		},

		/**
		 * Handle checkout update.
		 */
		onCheckoutUpdate: function() {
			// Reinitialize payment form if xMoney is selected.
			var selectedMethod = $('input[name="payment_method"]:checked').val();
			if (selectedMethod === 'xmoney_wc' && $('#xmoney-wc-payment-form').length && !this.checkoutInstance) {
				this.initPaymentForm();
			}
		},

		/**
		 * Handle payment method selection.
		 */
		onPaymentMethodSelected: function() {
			var selectedMethod = $('input[name="payment_method"]:checked').val();
			if (selectedMethod === 'xmoney_wc') {
				// Initialize payment form when xMoney is selected.
				if (!this.checkoutInstance) {
					this.initPaymentForm();
				}
			} else {
				// Destroy instance if another payment method is selected.
				if (this.checkoutInstance) {
					try {
						this.checkoutInstance.destroy();
					} catch (e) {
						console.error('Error destroying checkout instance:', e);
					}
					this.checkoutInstance = null;
				}
			}
		},

		/**
		 * Handle place order button click.
		 */
		onPlaceOrder: function(e, $form) {
			var paymentMethod = $form.find('input[name="payment_method"]:checked').val();

			// Only handle xMoney payments.
			if (paymentMethod !== 'xmoney_wc') {
				return;
			}

			// Prevent default submission - payment form handles it.
			e.preventDefault();

			// If payment form is not initialized, show error.
			if (!this.checkoutInstance) {
				this.showError('Please wait for the payment form to load.');
				return;
			}

			// Submit payment form.
			try {
				this.checkoutInstance.submit();
			} catch (error) {
				console.error('Error submitting payment form:', error);
				this.showError('Failed to submit payment. Please try again.');
			}
		},

		/**
		 * Initialize payment form.
		 */
		initPaymentForm: function(orderId, orderKey) {
			var self = this;

			// Destroy existing instance if any.
			if (this.checkoutInstance) {
				try {
					this.checkoutInstance.destroy();
				} catch (e) {
					console.error('Error destroying checkout instance:', e);
				}
				this.checkoutInstance = null;
			}

			// If order ID/key provided, use existing order.
			if (orderId && orderKey) {
				this.createPaymentIntentFromOrder(orderId, orderKey);
				return;
			}

			// Otherwise, create payment intent from cart.
			this.createPaymentIntentFromCart();
		},

		/**
		 * Create payment intent from cart.
		 */
		createPaymentIntentFromCart: function() {
			var self = this;

			// Create payment intent from cart data.
			$.ajax({
				type: 'POST',
				url: xmoneyWc.ajaxUrl,
				data: {
					action: 'xmoney_wc_create_payment_intent_from_cart',
					nonce: xmoneyWc.nonce
				},
				dataType: 'json',
				success: function(response) {
					if (response.success && response.data) {
						self.createPaymentForm(response.data);
					} else {
						self.showError(response.data?.message || 'Failed to initialize payment form.');
					}
				},
				error: function() {
					self.showError('Failed to initialize payment form. Please try again.');
				}
			});
		},

		/**
		 * Create payment intent from existing order.
		 */
		createPaymentIntentFromOrder: function(orderId, orderKey) {
			var self = this;

			$.ajax({
				type: 'POST',
				url: xmoneyWc.ajaxUrl,
				data: {
					action: 'xmoney_wc_create_payment_intent',
					nonce: xmoneyWc.nonce,
					order_id: orderId,
					order_key: orderKey
				},
				dataType: 'json',
				success: function(response) {
					if (response.success && response.data) {
						self.createPaymentForm(response.data, orderId, orderKey);
					} else {
						self.showError(response.data?.message || 'Failed to initialize payment form.');
					}
				},
				error: function() {
					self.showError('Failed to initialize payment form. Please try again.');
				}
			});
		},

			// Create payment intent.
			$.ajax({
				type: 'POST',
				url: xmoneyWc.ajaxUrl,
				data: {
					action: 'xmoney_wc_create_payment_intent',
					nonce: xmoneyWc.nonce,
					order_id: orderId,
					order_key: orderKey
				},
				dataType: 'json',
				success: function(response) {
					if (response.success && response.data) {
						self.createPaymentForm(response.data, orderId, orderKey);
					} else {
						self.showError(response.data?.message || 'Failed to initialize payment form.');
					}
				},
				error: function() {
					self.showError('Failed to initialize payment form. Please try again.');
				}
			});
		},

		/**
		 * Create payment form instance.
		 */
		createPaymentForm: function(paymentIntent, orderId, orderKey) {
			// Store order ID/key for later use.
			if (orderId) {
				this.currentOrderId = orderId;
			}
			if (orderKey) {
				this.currentOrderKey = orderKey;
			}
			var self = this;

			// Check if xMoney SDK is loaded.
			if (typeof window.XMoneyPaymentForm === 'undefined') {
				console.error('xMoney SDK not loaded');
				this.showError('Payment form failed to load. Please refresh the page.');
				return;
			}

			// Prepare options.
			var options = {
				locale: xmoneyWc.locale || 'en-US',
				buttonType: 'pay',
				displaySubmitButton: true,
				displaySaveCardOption: xmoneyWc.enableSavedCards || false,
				enableSavedCards: xmoneyWc.enableSavedCards || false,
				validationMode: 'onBlur',
				googlePay: {
					enabled: xmoneyWc.enableGooglePay || false,
					appearance: {
						color: 'black',
						type: 'pay',
						radius: 12,
						borderType: 'no_border'
					}
				},
				applePay: {
					enabled: xmoneyWc.enableApplePay || false,
					appearance: {
						style: 'black',
						type: 'pay',
						radius: 12
					}
				},
				appearance: {
					theme: 'custom',
					variables: {
						colorPrimary: '#2271b1',
						colorDanger: '#d63638',
						colorBackground: '#ffffff',
						colorText: '#1d2327',
						colorTextSecondary: '#646970',
						colorTextPlaceholder: '#8c8f94',
						colorBorder: '#8c8f94',
						colorBorderFocus: '#2271b1',
						borderRadius: '4px'
					}
				}
			};

			// Create payment form instance.
			try {
				this.checkoutInstance = new window.XMoneyPaymentForm({
					container: 'xmoney-wc-payment-form',
					publicKey: paymentIntent.publicKey,
					orderPayload: paymentIntent.payload,
					orderChecksum: paymentIntent.checksum,
					options: options,
					onReady: function() {
						console.log('xMoney payment form ready');
						$('#xmoney-wc-payment-form').addClass('xmoney-ready');
					},
					onError: function(error) {
						console.error('xMoney payment form error:', error);
						self.showError('Payment form error. Please refresh the page.');
					},
					onPaymentComplete: function(data) {
						self.handlePaymentComplete(data, orderId, orderKey);
					},
					onSubmitPending: function(isPending) {
						if (isPending) {
							self.setProcessing(true);
						} else {
							self.setProcessing(false);
						}
					}
				});
			} catch (error) {
				console.error('Error creating payment form:', error);
				this.showError('Failed to create payment form. Please refresh the page.');
			}
		},

		/**
		 * Handle payment completion.
		 */
		handlePaymentComplete: function(data, orderId, orderKey) {
			var self = this;

			if (this.isProcessing) {
				return; // Prevent duplicate submissions.
			}

			this.setProcessing(true);

			// If order ID/key not available, create order first.
			if (!orderId || !orderKey) {
				// Create order via AJAX first.
				var $form = $('form.checkout');
				var orderData = {
					action: 'woocommerce_checkout_place_order',
					security: wc_checkout_params.checkout_nonce
				};

				$.ajax({
					type: 'POST',
					url: wc_checkout_params.checkout_url,
					data: $form.serialize() + '&' + $.param(orderData),
					dataType: 'json',
					success: function(response) {
						if (response.result === 'success' && response.redirect) {
							// Extract order ID and key from redirect URL.
							var urlParams = new URLSearchParams(response.redirect.split('?')[1]);
							var newOrderId = urlParams.get('order_id');
							var newOrderKey = urlParams.get('key');

							if (newOrderId && newOrderKey) {
								self.currentOrderId = newOrderId;
								self.currentOrderKey = newOrderKey;
								self.processPaymentComplete(data, newOrderId, newOrderKey);
							} else {
								self.showError('Failed to create order. Please try again.');
								self.setProcessing(false);
							}
						} else {
							self.showError(response.messages || 'Failed to create order.');
							self.setProcessing(false);
						}
					},
					error: function() {
						self.showError('Failed to create order. Please try again.');
						self.setProcessing(false);
					}
				});
			} else {
				this.processPaymentComplete(data, orderId, orderKey);
			}
		},

		/**
		 * Process payment completion.
		 */
		processPaymentComplete: function(data, orderId, orderKey) {
			var self = this;

			// Send payment result to server.
			$.ajax({
				type: 'POST',
				url: xmoneyWc.ajaxUrl,
				data: {
					action: 'xmoney_wc_handle_payment_complete',
					nonce: xmoneyWc.nonce,
					order_id: orderId,
					order_key: orderKey,
					status: data.status || '',
					order_id_xmoney: data.orderId || ''
				},
				dataType: 'json',
				success: function(response) {
					if (response.success && response.data && response.data.redirect) {
						// Redirect to thank you page.
						window.location.href = response.data.redirect;
					} else {
						self.showError(response.data?.message || 'Payment processing failed.');
						self.setProcessing(false);
					}
				},
				error: function() {
					self.showError('Failed to process payment. Please contact support.');
					self.setProcessing(false);
				}
			});
		},

		/**
		 * Set processing state.
		 */
		setProcessing: function(isProcessing) {
			this.isProcessing = isProcessing;
			var $form = $('form.checkout');

			if (isProcessing) {
				$form.addClass('processing').block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});
			} else {
				$form.removeClass('processing').unblock();
			}
		},

		/**
		 * Show error message.
		 */
		showError: function(message) {
			// Use WooCommerce notice system if available.
			if (typeof wc_add_to_cart_message !== 'undefined') {
				$('.woocommerce-error, .woocommerce-message').remove();
				$('form.checkout').prepend('<div class="woocommerce-error">' + message + '</div>');
				$('html, body').animate({
					scrollTop: $('form.checkout').offset().top - 100
				}, 500);
			} else {
				alert(message);
			}
		}
	};

	// Initialize when DOM is ready.
	$(document).ready(function() {
		XMoneyCheckout.init();
	});

})(jQuery);

