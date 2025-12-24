/**
 * xMoney WooCommerce Admin Scripts
 */
(function ($) {
  "use strict";

  var XMoneyAdmin = {
    init: function () {
      this.bindEvents();
    },

    bindEvents: function () {
      // Show/hide password
      $(".xmoney-show-password").on(
        "click",
        this.handlePasswordToggle.bind(this)
      );
    },

    handlePasswordToggle: function (e) {
      e.preventDefault();
      var $button = $(e.currentTarget);
      var $input = $button.siblings("input");

      if ($input.attr("type") === "password") {
        $input.attr("type", "text");
        $button.addClass("active");
      } else {
        $input.attr("type", "password");
        $button.removeClass("active");
      }
    },
  };

  $(document).ready(function () {
    XMoneyAdmin.init();
  });
})(jQuery);

