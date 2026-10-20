jQuery(document).ready(function ($) {
  /*
   * Disable-with-reason linkage (SEO, media), Test Connection and the WPMind
   * status are kit features now (depends_on mode "disable", field actions,
   * status field). This file keeps the live preview and small helpers.
   */
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

  initPreviewFunctionality();
  initKeyboardShortcuts();
  initSmartDefaults();
  initSEOFeatures();

  $(".settings-error.notice-success").hide();
});
