/**
 * xMoney WooCommerce Admin Scripts
 */
(function ($) {
  "use strict";

  var XMoneyAdmin = {
    init: function () {
      this.bindEvents();
      this.initColorPickers();
    },

    bindEvents: function () {
      // Show/hide password
      $(".xmoney-show-password").on(
        "click",
        this.handlePasswordToggle.bind(this)
      );

      // Tab switching
      $(".xmoney-tabs .xmoney-tab").on("click", this.handleTabSwitch.bind(this));

      // Theme mode switching
      $('input[name="woocommerce_xmoney_wc_theme_mode"]').on(
        "change",
        this.handleThemeModeChange.bind(this)
      );

      // Theme option click for visual feedback
      $(".xmoney-theme-option").on("click", function () {
        $(".xmoney-theme-option").removeClass("active");
        $(this).addClass("active");
      });

      // Use theme colors button
      $(".xmoney-use-theme-colors").on(
        "click",
        this.handleUseThemeColors.bind(this)
      );
    },

    handleTabSwitch: function (e) {
      e.preventDefault();
      var $tab = $(e.currentTarget);
      var tabId = $tab.data("tab");

      // Update tab buttons
      $(".xmoney-tabs .xmoney-tab").removeClass("active");
      $tab.addClass("active");

      // Update tab content
      $(".xmoney-tab-content").removeClass("active");
      $('.xmoney-tab-content[data-tab="' + tabId + '"]').addClass("active");
    },

    handleThemeModeChange: function (e) {
      var mode = $(e.currentTarget).val();
      var $customColors = $(".xmoney-custom-colors");

      if (mode === "custom") {
        $customColors.slideDown(200);
      } else {
        $customColors.slideUp(200);
      }
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

    initColorPickers: function () {
      // Sync color picker with text input
      $(".xmoney-color-picker-input").each(function () {
        var $picker = $(this);
        var $text = $picker.siblings(".xmoney-color-text");

        // Update text when picker changes
        $picker.on("input", function () {
          $text.val($(this).val().toUpperCase());
        });

        // Update picker when text changes
        $text.on("input", function () {
          var val = $(this).val();
          if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
            $picker.val(val);
          }
        });

        // Initialize picker from text if has value
        if ($text.val() && /^#[0-9A-Fa-f]{6}$/i.test($text.val())) {
          $picker.val($text.val());
        }
      });
    },

    handleUseThemeColors: function (e) {
      e.preventDefault();
      var $primaryPicker = $("#xmoney_color_primary_picker");
      var $primaryText = $("#woocommerce_xmoney_wc_color_primary");

      // Get the placeholder (detected theme color)
      var themeColor = $primaryText.attr("placeholder");
      if (themeColor && themeColor !== "#7c3aed") {
        $primaryPicker.val(themeColor);
        $primaryText.val(themeColor.toUpperCase());
      }
    },
  };

  $(document).ready(function () {
    XMoneyAdmin.init();
  });
})(jQuery);

