jQuery(document).ready(function ($) {
  /*
   * SEO group: disabled (not hidden) by several conditions. The kit's
   * depends_on only hides (AND), so this stays here until the kit has a
   * disable mode. Values are never changed: disabled controls are not
   * submitted, so the saved values are kept, and the reason is shown.
   */
  function initSEOOptimizationToggle() {
    var strings = wpslug_ajax.strings || {};

    function currentMode() {
      var $radio = $('input[name="wpslug_options[conversion_mode]"]:checked');
      return $radio.length ? $radio.val() : wpslug_ajax.conversion_mode;
    }

    function setReason($wrap, text) {
      var $reason = $wrap.children(".wpslug-disabled-reason");
      var $controls = $wrap.find("input:not([type=hidden]), select, textarea");
      if (!text) {
        $reason.remove();
        $controls.each(function () {
          var ids = ($(this).attr("aria-describedby") || "").split(" ").filter(function (id) {
            return id && id.indexOf("wpslug-reason-") !== 0;
          });
          if (ids.length) {
            $(this).attr("aria-describedby", ids.join(" "));
          } else {
            $(this).removeAttr("aria-describedby");
          }
        });
        return;
      }
      if (!$reason.length) {
        var id = "wpslug-reason-" + Math.random().toString(36).slice(2, 9);
        $reason = $('<p class="wenpai-field__desc wpslug-disabled-reason"></p>').attr("id", id);
        $wrap.append($reason);
        $controls.each(function () {
          var ids = ($(this).attr("aria-describedby") || "").split(" ").filter(Boolean);
          ids.push(id);
          $(this).attr("aria-describedby", ids.join(" "));
        });
      }
      $reason.text(text);
    }

    function setDisabled($wrap, reason) {
      var off = !!reason;
      $wrap.toggleClass("disabled", off);
      $wrap.find("input, select, textarea").prop("disabled", off);
      setReason($wrap, reason);
    }

    function toggleSEOFeatures() {
      var $seoInputs = $('input[name="wpslug_options[enable_seo_optimization]"]');
      var $seoField = $seoInputs.first().closest(".wenpai-field");
      var seoEnabled = $seoInputs.filter(":checkbox").is(":checked");
      var removeStopWords = $('input[name="wpslug_options[remove_stop_words]"]:checkbox').is(":checked");
      var $format = $('select[name="wpslug_options[pinyin_format]"]');
      var pinyinFormat = $format.length ? $format.val() : wpslug_ajax.pinyin_format;
      var firstLetter = currentMode() === "pinyin" && pinyinFormat === "first";

      if ($seoField.length) {
        setDisabled($seoField, firstLetter ? strings.seo_first_letter : "");
      }

      $(".wpslug-seo-dependent").each(function () {
        var $wrap = $(this);
        var reason = "";
        if (firstLetter) {
          reason = strings.seo_first_letter;
        } else if (!seoEnabled) {
          reason = strings.seo_off;
        } else if ($wrap.hasClass("wpslug-stopwords-dependent") && !removeStopWords) {
          reason = strings.stop_words_off;
        }
        setDisabled($wrap, reason);
      });
    }

    $(document).on("change", 'input[name="wpslug_options[enable_seo_optimization]"]', toggleSEOFeatures);
    $(document).on("change", 'input[name="wpslug_options[remove_stop_words]"]', toggleSEOFeatures);
    $(document).on("change", 'select[name="wpslug_options[pinyin_format]"]', toggleSEOFeatures);
    $(document).on("change", 'input[name="wpslug_options[conversion_mode]"]', toggleSEOFeatures);
    toggleSEOFeatures();
  }

  function initPreviewFunctionality() {
    var timer = null;
    var seq = 0;

    function setPreviewInputState(text) {
      var $input = $("#wpslug-preview-input");
      if (text) {
        $input.removeClass("invalid").addClass("valid");
      } else {
        $input.removeClass("valid invalid");
      }
    }

    function showPreviewRow() {
      $("#wpslug-preview-result-row").prop("hidden", false);
    }

    function clearPreviewResult() {
      $("#wpslug-preview-result")
        .removeClass("result-final wpslug-error")
        .empty();
      $("#wpslug-preview-result-row").prop("hidden", true);
    }

    function showPreviewOk(slug) {
      $("#wpslug-preview-result")
        .removeClass("wpslug-error")
        .addClass("result-final")
        .text(slug);
      showPreviewRow();
    }

    function showPreviewErr(msg) {
      $("#wpslug-preview-result")
        .removeClass("result-final")
        .addClass("wpslug-error")
        .text(msg);
      showPreviewRow();
    }

    function performPreview() {
      var text = $("#wpslug-preview-input").val().trim();
      var $result = $("#wpslug-preview-result");
      var ticket = ++seq;

      setPreviewInputState(text);
      if (!text) {
        clearPreviewResult();
        return;
      }

      if (!$result.text()) {
        $result.removeClass("result-final wpslug-error").text(wpslug_ajax.strings.converting);
        showPreviewRow();
      }

      var extra = $("#wpslug-settings-form").serialize();
      $.ajax({
        url: wpslug_ajax.ajax_url,
        type: "POST",
        data:
          (extra ? extra + "&" : "") +
          $.param({
            action: "wpslug_preview",
            text: text,
            nonce: wpslug_ajax.nonce,
          }),
        success: function (response) {
          if (ticket !== seq) {
            return;
          }
          var slug = response.success ? previewSlug(response.data) : "";
          if (slug) {
            showPreviewOk(slug);
          } else {
            showPreviewErr(wpslug_ajax.strings.conversion_error);
          }
        },
        error: function () {
          if (ticket !== seq) {
            return;
          }
          showPreviewErr(wpslug_ajax.strings.conversion_error);
        },
      });
    }

    function previewSlug(data) {
      if (!data) {
        return "";
      }
      return data.final || data.optimized || data.converted || "";
    }

    function schedulePreview() {
      clearTimeout(timer);
      var text = $("#wpslug-preview-input").val().trim();
      setPreviewInputState(text);
      if (!text) {
        seq += 1;
        clearPreviewResult();
        return;
      }
      timer = setTimeout(performPreview, 250);
    }

    $("#wpslug-preview-input").on("input", schedulePreview);
    $("#wpslug-settings-form").on("change", "input, select, textarea", schedulePreview);
  }

  function initApiTesting() {
    $(".wpslug-test-api").on("click", function () {
      var $button = $(this);
      var service = $button.data("service");
      var originalText = $button.text();

      // Secret inputs never show the saved key, so do not check them here:
      // the server tests the saved credentials and says what is missing.
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

  function initKeyboardShortcuts() {
    $(document).on("keydown", function (e) {
      if (e.ctrlKey || e.metaKey) {
        switch (e.which) {
          case 13:
            if ($("#wpslug-preview-input").is(":focus")) {
              e.preventDefault();
              $("#wpslug-preview-input").trigger("input");
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

  function initSmartDefaults() {
    $('input[name="wpslug_options[remove_stop_words]"]').on("change", function () {
      var isChecked = $(this).is(":checked");
      var $stopWordsList = $('textarea[name="wpslug_options[stop_words_list]"]');

      if (isChecked && $stopWordsList.val().trim() === "") {
        $stopWordsList.val(
          "the,a,an,and,or,but,in,on,at,to,for,of,with,by,from,up,about,into,through,during,before,after,above,below,between,among,since,without,within",
        );
      }
    });
  }

  function initSEOFeatures() {
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
        $mediaOptions.prop("disabled", true).closest(".field").addClass("disabled");
      } else {
        $mediaOptions
          .prop("disabled", false)
          .closest(".field")
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

  initSEOOptimizationToggle();
  initPreviewFunctionality();
  initApiTesting();
  initKeyboardShortcuts();
  initSmartDefaults();
  initSEOFeatures();
  initMediaSettings();

  $(".settings-error.notice-success").hide();
});
