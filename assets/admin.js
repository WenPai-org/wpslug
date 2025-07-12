jQuery(document).ready(function ($) {
  function initTabSwitching() {
    $(".wpslug-tab").on("click", function (e) {
      e.preventDefault();
      var target = $(this).data("tab");

      $(".wpslug-tab").removeClass("active");
      $(this).addClass("active");

      $(".wpslug-section").removeClass("active");
      $('.wpslug-section[data-section="' + target + '"]').addClass("active");

      $("#wpslug_current_tab").val(target);
    });

    if (wpslug_ajax.current_tab && wpslug_ajax.current_tab !== "general") {
      $('.wpslug-tab[data-tab="' + wpslug_ajax.current_tab + '"]').click();
    }
  }

  function initDependentFields() {
    function toggleDependentFields() {
      var enableConversion = $("#enable_conversion").is(":checked");

      $(".wpslug-dependent").each(function () {
        var $row = $(this);
        var dependsOn = $row.data("depends");

        if (dependsOn === "enable_conversion") {
          if (enableConversion) {
            $row.removeClass("disabled");
            $row.find("input, select").prop("disabled", false);
          } else {
            $row.addClass("disabled");
            $row.find("input, select").prop("disabled", true);
          }
        }
      });
    }

    $("#enable_conversion").on("change", toggleDependentFields);
    toggleDependentFields();
  }

  function initConversionModeToggle() {
    function toggleModeBasedTabs() {
      var selectedMode = $("#conversion_mode").val();

      $(".wpslug-tab").each(function () {
        var tabName = $(this).data("tab");
        var $tab = $(this);

        if (
          tabName === "pinyin" ||
          tabName === "transliteration" ||
          tabName === "translation"
        ) {
          if (tabName === selectedMode) {
            $tab.show();
          } else {
            $tab.hide();
            if ($tab.hasClass("active")) {
              $tab.removeClass("active");
              $('.wpslug-tab[data-tab="general"]').addClass("active");
              $(".wpslug-section").removeClass("active");
              $('.wpslug-section[data-section="general"]').addClass("active");
              $("#wpslug_current_tab").val("general");
            }
          }
        }
      });
    }

    $("#conversion_mode").on("change", function () {
      toggleModeBasedTabs();
      updateTranslationServiceToggle();
    });
    toggleModeBasedTabs();
  }

  function updateTranslationServiceToggle() {
    var conversionMode = $("#conversion_mode").val();
    var $translationService = $("#translation_service");

    if (
      conversionMode === "translation" &&
      $translationService.val() === "none"
    ) {
      $translationService.val("google");
      $translationService.trigger("change");
    }
  }

  function initTranslationServiceToggle() {
    function toggleApiSections() {
      var service = $("#translation_service").val();

      $(".wpslug-api-section").hide();
      if (service && service !== "none") {
        $('.wpslug-api-section[data-service="' + service + '"]').show();
      }
    }

    $("#translation_service").on("change", toggleApiSections);
    toggleApiSections();
  }

  function initSEOOptimizationToggle() {
    function toggleSEOFeatures() {
      var seoEnabled = $("#enable_seo_optimization").is(":checked");
      var pinyinFormat = $("#pinyin_format").val();
      var conversionMode = $("#conversion_mode").val();
      var removeStopWords = $("#remove_stop_words").is(":checked");

      var isFirstLetterMode =
        conversionMode === "pinyin" && pinyinFormat === "first";
      var shouldDisableSEO = !seoEnabled || isFirstLetterMode;

      var $seoOptions = $(
        ".wpslug-seo-dependent input, .wpslug-seo-dependent select, .wpslug-seo-dependent textarea",
      );
      var $seoOptimizationCheckbox = $("#enable_seo_optimization");
      var $stopWordsOptions = $(
        ".wpslug-stopwords-dependent input, .wpslug-stopwords-dependent select, .wpslug-stopwords-dependent textarea",
      );

      if (isFirstLetterMode) {
        $seoOptimizationCheckbox.prop("disabled", true).prop("checked", false);
        $(".wpslug-seo-dependent").addClass("disabled");
        $seoOptions.prop("disabled", true);

        if ($(".pinyin-first-notice").length === 0) {
          $seoOptimizationCheckbox
            .closest("td")
            .append(
              '<div class="pinyin-first-notice">SEO optimization is automatically disabled in first letter mode for maximum brevity.</div>',
            );
        }
      } else {
        $seoOptimizationCheckbox.prop("disabled", false);
        $(".pinyin-first-notice").remove();

        if (seoEnabled) {
          $(".wpslug-seo-dependent").removeClass("disabled");
          $seoOptions.prop("disabled", false);

          if (removeStopWords) {
            $(".wpslug-stopwords-dependent").removeClass("disabled");
            $stopWordsOptions.prop("disabled", false);
          } else {
            $(".wpslug-stopwords-dependent").addClass("disabled");
            $stopWordsOptions.prop("disabled", true);
          }
        } else {
          $(".wpslug-seo-dependent").addClass("disabled");
          $seoOptions.prop("disabled", true);
        }
      }
    }

    $(document).on("change", "#enable_seo_optimization", toggleSEOFeatures);
    $(document).on("change", "#remove_stop_words", toggleSEOFeatures);
    $("#pinyin_format").on("change", toggleSEOFeatures);
    $("#conversion_mode").on("change", toggleSEOFeatures);
    toggleSEOFeatures();
  }

  function initPreviewFunctionality() {
    function performPreview() {
      var text = $("#wpslug-preview-input").val().trim();
      var $button = $("#wpslug-preview-button");
      var $result = $("#wpslug-preview-result");

      if (!text) {
        showNotice("error", wpslug_ajax.strings.no_text);
        return;
      }

      $button.prop("disabled", true).text(wpslug_ajax.strings.converting);
      $result.html('<div class="wpslug-loading-spinner">Converting...</div>');

      $.ajax({
        url: wpslug_ajax.ajax_url,
        type: "POST",
        data: {
          action: "wpslug_preview",
          text: text,
          nonce: wpslug_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            var data = response.data;
            var html = buildPreviewResult(data);
            $result.html(html);
            hideNotice();
          } else {
            showNotice(
              "error",
              response.data.message || wpslug_ajax.strings.conversion_error,
            );
            $result.html('<div class="wpslug-error">Preview failed</div>');
          }
        },
        error: function () {
          showNotice("error", wpslug_ajax.strings.conversion_error);
          $result.html('<div class="wpslug-error">Connection error</div>');
        },
        complete: function () {
          $button.prop("disabled", false).text(wpslug_ajax.strings.preview);
        },
      });
    }

    function buildPreviewResult(data) {
      var html = '<div class="result-item">';
      html += '<span class="result-label">Original:</span> ';
      html +=
        '<span class="result-value">' + escapeHtml(data.original) + "</span>";
      html += "</div>";

      if (data.converted && data.converted !== data.original) {
        html += '<div class="result-item">';
        html += '<span class="result-label">Converted:</span> ';
        html +=
          '<span class="result-value">' +
          escapeHtml(data.converted) +
          "</span>";
        html += "</div>";
      }

      if (data.optimized && data.optimized !== data.converted) {
        html += '<div class="result-item">';
        html += '<span class="result-label">Optimized:</span> ';
        html +=
          '<span class="result-value">' +
          escapeHtml(data.optimized) +
          "</span>";
        html += "</div>";
      }

      html += '<div class="result-item">';
      html += '<span class="result-label">Final Slug:</span> ';
      html +=
        '<span class="result-final">' + escapeHtml(data.final) + "</span>";
      html += "</div>";

      html += '<div class="result-meta">';
      html += "Mode: " + escapeHtml(data.mode);
      if (data.detected_language) {
        html += " | Detected: " + escapeHtml(data.detected_language);
      }
      html += "</div>";

      return html;
    }

    $("#wpslug-preview-button").on("click", performPreview);
    $("#wpslug-preview-input").on("keypress", function (e) {
      if (e.which === 13) {
        performPreview();
      }
    });
  }

  function initApiTesting() {
    $(".wpslug-test-api").on("click", function () {
      var $button = $(this);
      var service = $button.data("service");
      var originalText = $button.text();

      if (service === "google") {
        var apiKey = $('input[name="wpslug_options[google_api_key]"]')
          .val()
          .trim();
        if (!apiKey) {
          showNotice("error", "Google API key is required for testing.");
          return;
        }
      } else if (service === "baidu") {
        var appId = $('input[name="wpslug_options[baidu_app_id]"]')
          .val()
          .trim();
        var secretKey = $('input[name="wpslug_options[baidu_secret_key]"]')
          .val()
          .trim();
        if (!appId || !secretKey) {
          showNotice(
            "error",
            "Both Baidu App ID and Secret Key are required for testing.",
          );
          return;
        }
      }

      $button.prop("disabled", true).text(wpslug_ajax.strings.testing);

      $.ajax({
        url: wpslug_ajax.ajax_url,
        type: "POST",
        data: {
          action: "wpslug_test_api",
          service: service,
          nonce: wpslug_ajax.nonce,
        },
        success: function (response) {
          if (response.success) {
            showNotice(
              "success",
              response.data.message || wpslug_ajax.strings.api_test_success,
            );
            $button.after(
              '<span class="wpslug-api-status connected">Connected</span>',
            );
          } else {
            showNotice(
              "error",
              response.data.message || wpslug_ajax.strings.api_test_failed,
            );
            $button.after(
              '<span class="wpslug-api-status disconnected">Failed</span>',
            );
          }
        },
        error: function () {
          showNotice("error", wpslug_ajax.strings.api_test_failed);
          $button.after(
            '<span class="wpslug-api-status disconnected">Error</span>',
          );
        },
        complete: function () {
          $button.prop("disabled", false).text(originalText);
          setTimeout(function () {
            $(".wpslug-api-status").fadeOut(function () {
              $(this).remove();
            });
          }, 3000);
        },
      });
    });
  }

  function initResetSettings() {
    $("#wpslug-reset-settings").on("click", function () {
      if (confirm(wpslug_ajax.strings.reset_confirm)) {
        showNotice("info", "Resetting settings to defaults...");

        setTimeout(function () {
          var $form = $("#wpslug-settings-form");

          $form.find('input[type="checkbox"]').prop("checked", false);
          $form.find('input[type="text"], input[type="number"]').val("");
          $form.find("select").prop("selectedIndex", 0);
          $form.find("textarea").val("");

          $("#enable_conversion").prop("checked", true);
          $('input[name="wpslug_options[auto_convert]"]').prop("checked", true);
          $('input[name="wpslug_options[force_lowercase]"]').prop(
            "checked",
            true,
          );
          $('input[name="wpslug_options[preserve_english]"]').prop(
            "checked",
            true,
          );
          $('input[name="wpslug_options[preserve_numbers]"]').prop(
            "checked",
            true,
          );
          $('input[name="wpslug_options[preserve_media_extension]"]').prop(
            "checked",
            true,
          );
          $('input[name="wpslug_options[enable_seo_optimization]"]').prop(
            "checked",
            true,
          );
          $('input[name="wpslug_options[smart_punctuation]"]').prop(
            "checked",
            true,
          );
          $('input[name="wpslug_options[mixed_content_optimization]"]').prop(
            "checked",
            true,
          );
          $('input[name="wpslug_options[remove_stop_words]"]').prop(
            "checked",
            true,
          );

          $('input[name="wpslug_options[max_length]"]').val("100");
          $('input[name="wpslug_options[seo_max_words]"]').val("5");

          $("#conversion_mode").val("pinyin");
          $("#translation_service").val("none");
          $('select[name="wpslug_options[pinyin_separator]"]').val("-");
          $('select[name="wpslug_options[pinyin_format]"]').val("full");
          $('select[name="wpslug_options[transliteration_method]"]').val(
            "basic",
          );
          $('select[name="wpslug_options[translation_source_lang]"]').val(
            "auto",
          );
          $('select[name="wpslug_options[translation_target_lang]"]').val("en");
          $('select[name="wpslug_options[media_conversion_mode]"]').val(
            "normal",
          );

          $('input[name="wpslug_options[enabled_post_types][]"]').prop(
            "checked",
            false,
          );
          $(
            'input[name="wpslug_options[enabled_post_types][]"][value="post"]',
          ).prop("checked", true);
          $(
            'input[name="wpslug_options[enabled_post_types][]"][value="page"]',
          ).prop("checked", true);

          $('input[name="wpslug_options[enabled_taxonomies][]"]').prop(
            "checked",
            false,
          );
          $(
            'input[name="wpslug_options[enabled_taxonomies][]"][value="category"]',
          ).prop("checked", true);
          $(
            'input[name="wpslug_options[enabled_taxonomies][]"][value="post_tag"]',
          ).prop("checked", true);

          $("#stop_words_list").val(
            "the,a,an,and,or,but,in,on,at,to,for,of,with,by,from,up,about,into,through,during,before,after,above,below,between,among,since,without,within",
          );

          $("#enable_conversion").trigger("change");
          $("#conversion_mode").trigger("change");
          $("#enable_seo_optimization").trigger("change");
          $("#remove_stop_words").trigger("change");
          $("#pinyin_format").trigger("change");
          $("#translation_service").trigger("change");

          showNotice(
            "success",
            "Settings reset to defaults. Remember to save your changes.",
          );
        }, 100);
      }
    });
  }

  function initFormValidation() {
    $("#wpslug-settings-form").on("submit", function (e) {
      var hasError = false;

      console.log("WP Slug - Form submission started");

      var checkboxes = $('input[type="checkbox"]');
      checkboxes.each(function () {
        var $this = $(this);
        var name = $this.attr("name");
        var checked = $this.is(":checked");
        var value = $this.val();

        console.log(
          "  Checkbox " + name + ": checked=" + checked + ", value=" + value,
        );
      });

      var conversionMode = $("#conversion_mode").val();
      if (conversionMode === "translation") {
        var translationService = $("#translation_service").val();
        if (translationService === "google") {
          var apiKey = $('input[name="wpslug_options[google_api_key]"]')
            .val()
            .trim();
          if (!apiKey) {
            showNotice(
              "error",
              "Google API key is required when using Google Translate service.",
            );
            hasError = true;
          }
        } else if (translationService === "baidu") {
          var appId = $('input[name="wpslug_options[baidu_app_id]"]')
            .val()
            .trim();
          var secretKey = $('input[name="wpslug_options[baidu_secret_key]"]')
            .val()
            .trim();
          if (!appId || !secretKey) {
            showNotice(
              "error",
              "Both Baidu App ID and Secret Key are required when using Baidu Translate service.",
            );
            hasError = true;
          }
        }
      }

      var maxLength = parseInt(
        $('input[name="wpslug_options[max_length]"]').val(),
      );
      if (maxLength && (maxLength < 0 || maxLength > 500)) {
        showNotice("error", "Maximum length must be between 0 and 500.");
        hasError = true;
      }

      var seoMaxWords = parseInt(
        $('input[name="wpslug_options[seo_max_words]"]').val(),
      );
      if (seoMaxWords && (seoMaxWords < 1 || seoMaxWords > 30)) {
        showNotice("error", "SEO maximum words must be between 1 and 30.");
        hasError = true;
      }

      var enableSEO = $("#enable_seo_optimization").is(":checked");
      var removeStopWords = $("#remove_stop_words").is(":checked");
      var stopWordsList = $("#stop_words_list").val().trim();

      if (enableSEO && removeStopWords && stopWordsList === "") {
        showNotice(
          "error",
          'Stop words list cannot be empty when "Remove Stop Words" is enabled.',
        );
        hasError = true;
      }

      if (hasError) {
        e.preventDefault();
        $("html, body").animate(
          {
            scrollTop: 0,
          },
          500,
        );
        return false;
      }

      console.log("WP Slug - Form validation passed");
      hideNotice();
    });
  }

  function initAdvancedFeatures() {
    $(".wpslug-checkbox-item").on("click", function (e) {
      if ($(e.target).is('input[type="checkbox"]')) {
        return;
      }

      var $checkbox = $(this).find('input[type="checkbox"]');
      if (!$checkbox.is(":disabled")) {
        $checkbox.prop("checked", !$checkbox.prop("checked"));
      }
    });

    $('.wpslug-checkbox-item input[type="checkbox"]').on("click", function (e) {
      e.stopPropagation();
    });

    $("#wpslug-debug-checkboxes").on("click", function () {
      var checkboxData = {};
      var hiddenData = {};

      $('input[type="checkbox"]').each(function () {
        var $this = $(this);
        var name = $this.attr("name");
        var checked = $this.is(":checked");
        var value = $this.val();

        checkboxData[name] = {
          checked: checked,
          value: value,
        };
      });

      $('input[type="hidden"]').each(function () {
        var $this = $(this);
        var name = $this.attr("name");
        var value = $this.val();

        if (name && name.indexOf("wpslug_options") !== -1) {
          hiddenData[name] = value;
        }
      });

      console.log("WP Slug Debug - Checkbox states:", checkboxData);
      console.log("WP Slug Debug - Hidden field states:", hiddenData);

      alert(
        "Debug information logged to console. Check browser developer tools.",
      );
    });

    $("#wpslug-test-form-data").on("click", function () {
      var formData = new FormData($("#wpslug-settings-form")[0]);
      var formObject = {};

      for (var pair of formData.entries()) {
        if (formObject[pair[0]]) {
          if (Array.isArray(formObject[pair[0]])) {
            formObject[pair[0]].push(pair[1]);
          } else {
            formObject[pair[0]] = [formObject[pair[0]], pair[1]];
          }
        } else {
          formObject[pair[0]] = pair[1];
        }
      }

      console.log(
        "WP Slug Debug - Form data that would be submitted:",
        formObject,
      );

      var wpslugOptions = {};
      Object.keys(formObject).forEach(function (key) {
        if (key.startsWith("wpslug_options[")) {
          var optionKey = key.replace("wpslug_options[", "").replace("]", "");
          wpslugOptions[optionKey] = formObject[key];
        }
      });

      console.log(
        "WP Slug Debug - WPSlug options that would be submitted:",
        wpslugOptions,
      );

      alert(
        "Form data information logged to console. Check browser developer tools.",
      );
    });
  }

  function initKeyboardShortcuts() {
    $(document).on("keydown", function (e) {
      if (e.ctrlKey || e.metaKey) {
        switch (e.which) {
          case 13:
            if ($("#wpslug-preview-input").is(":focus")) {
              e.preventDefault();
              $("#wpslug-preview-button").click();
            }
            break;
          case 83:
            if ($("#wpslug-settings-form").length) {
              e.preventDefault();
              $("#wpslug-settings-form").submit();
            }
            break;
        }
      }
    });
  }

  function initResponsiveEnhancements() {
    function checkWindowSize() {
      var $tabs = $(".wpslug-tabs");

      if ($(window).width() < 782) {
        $tabs.addClass("mobile-view");
      } else {
        $tabs.removeClass("mobile-view");
      }
    }

    $(window).on("resize", checkWindowSize);
    checkWindowSize();
  }

  function initSmartDefaults() {
    $("#remove_stop_words").on("change", function () {
      var isChecked = $(this).is(":checked");
      var $stopWordsList = $("#stop_words_list");

      if (isChecked && $stopWordsList.val().trim() === "") {
        $stopWordsList.val(
          "the,a,an,and,or,but,in,on,at,to,for,of,with,by,from,up,about,into,through,during,before,after,above,below,between,among,since,without,within",
        );
      }
    });
  }

  function initSEOFeatures() {
    var $previewInput = $("#wpslug-preview-input");
    var $previewResult = $("#wpslug-preview-result");

    $previewInput.on("input", function () {
      var text = $(this).val().trim();
      if (text.length > 0) {
        $(this).removeClass("invalid").addClass("valid");
      } else {
        $(this).removeClass("valid invalid");
      }
    });

    $('input[name="wpslug_options[seo_max_words]"]').on("input", function () {
      var value = parseInt($(this).val());
      var $warning = $(this).siblings(".seo-warning");

      if (value > 10) {
        if ($warning.length === 0) {
          $(this).after(
            '<span class="seo-warning">High word count may impact SEO</span>',
          );
        }
      } else {
        $warning.remove();
      }
    });
  }

  function initMediaSettings() {
    function toggleMediaSettings() {
      var mediaDisabled = $(
        'input[name="wpslug_options[disable_file_convert]"]',
      ).is(":checked");
      var $mediaOptions = $(
        'select[name="wpslug_options[media_conversion_mode]"], input[name="wpslug_options[media_file_prefix]"], input[name="wpslug_options[preserve_media_extension]"]',
      );

      if (mediaDisabled) {
        $mediaOptions.prop("disabled", true).closest("tr").addClass("disabled");
      } else {
        $mediaOptions
          .prop("disabled", false)
          .closest("tr")
          .removeClass("disabled");
      }
    }

    $('input[name="wpslug_options[disable_file_convert]"]').on(
      "change",
      toggleMediaSettings,
    );
    toggleMediaSettings();
  }

  function showNotice(type, message) {
    var $notice = $("#wpslug-status");
    $notice
      .removeClass("notice-success notice-error notice-info")
      .addClass("notice-" + type)
      .html("<p>" + escapeHtml(message) + "</p>")
      .show();

    if (type === "success") {
      setTimeout(function () {
        hideNotice();
      }, 5000);
    }

    $("html, body").animate(
      {
        scrollTop: 0,
      },
      300,
    );

    console.log("WP Slug Notice: " + type + " - " + message);
  }

  function hideNotice() {
    $("#wpslug-status").fadeOut();
  }

  function escapeHtml(text) {
    var map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return text.replace(/[&<>"']/g, function (m) {
      return map[m];
    });
  }

  function debugCheckboxStates() {
    var checkboxes = $('input[type="checkbox"]');
    console.log("WP Slug - Checkbox states on page load:");

    checkboxes.each(function () {
      var $this = $(this);
      var name = $this.attr("name");
      var checked = $this.is(":checked");
      var value = $this.val();

      console.log("  " + name + ": checked=" + checked + ", value=" + value);
    });
  }

  initTabSwitching();
  initDependentFields();
  initConversionModeToggle();
  initTranslationServiceToggle();
  initSEOOptimizationToggle();
  initPreviewFunctionality();
  initApiTesting();
  initResetSettings();
  initFormValidation();
  initAdvancedFeatures();
  initKeyboardShortcuts();
  initResponsiveEnhancements();
  initSmartDefaults();
  initSEOFeatures();
  initMediaSettings();

  $(".settings-error.notice-success").hide();

  debugCheckboxStates();

  $(window).on("load", function () {
    hideNotice();
    $(".settings-error.notice-success").hide();

    if (window.location.search.indexOf("settings-updated=true") !== -1) {
      console.log(
        "WP Slug - Settings were just updated, checking current values...",
      );

      setTimeout(function () {
        var checkboxes = $('input[type="checkbox"]');
        checkboxes.each(function () {
          var $this = $(this);
          var name = $this.attr("name");
          var checked = $this.is(":checked");
          var value = $this.val();

          console.log(
            "  Post-save checkbox " +
              name +
              ": checked=" +
              checked +
              ", value=" +
              value,
          );
        });
      }, 1000);
    }
  });
});
