(function () {
  "use strict";

  if (typeof window.wpslugEditor === "undefined") {
    return;
  }

  var cfg = window.wpslugEditor;
  var strings = cfg.strings || {};
  var candidate = "";

  function setStatus(el, message, kind) {
    if (!el) {
      return;
    }
    el.textContent = message || "";
    el.classList.remove("is-error", "is-ok");
    if (kind) {
      el.classList.add(kind);
    }
  }

  function classicTitle() {
    var title = document.getElementById("title");
    if (title && title.value) {
      return title.value.trim();
    }
    return "";
  }

  function blockTitle() {
    if (!window.wp || !wp.data || !wp.data.select) {
      return "";
    }
    try {
      var title = wp.data.select("core/editor").getEditedPostAttribute("title");
      return title ? String(title).trim() : "";
    } catch (e) {
      return "";
    }
  }

  function applyClassicSlug(slug) {
    var editable = document.getElementById("editable-post-name");
    var editableFull = document.getElementById("editable-post-name-full");
    var newPost = document.getElementById("new-post-slug");
    var postName = document.getElementById("post_name");

    if (editableFull) {
      editableFull.textContent = slug;
    }
    if (editable) {
      editable.textContent = slug;
    }
    if (newPost) {
      newPost.value = slug;
    }
    if (postName) {
      postName.value = slug;
    }

    // Reveal the permalink edit UI if still in view mode.
    var editToggle = document.getElementById("edit-slug-buttons");
    var okButton = document.getElementById("save-post");
    if (window.jQuery) {
      var $ = window.jQuery;
      var sample = $("#sample-permalink");
      if (sample.length && editable) {
        // Keep WP's sample permalink in sync when possible.
        var link = sample.find("a");
        if (link.length) {
          var href = link.attr("href") || "";
          // Best-effort: leave href alone; text path often uses #editable-post-name.
        }
      }
      if ($("#edit-slug-box").length && $("#edit-slug-buttons").length === 0) {
        // Permalink already showing; nothing else required.
      }
    }

    void okButton;
    void editToggle;
  }

  function applyBlockSlug(slug) {
    if (!window.wp || !wp.data || !wp.data.dispatch) {
      return false;
    }
    try {
      wp.data.dispatch("core/editor").editPost({ slug: slug });
      return true;
    } catch (e) {
      return false;
    }
  }

  function requestPreview(feature, title, postId, onDone) {
    var body = new window.FormData();
    body.append("action", "wpslug_editor_preview");
    body.append("nonce", cfg.nonce);
    body.append("feature", feature);
    body.append("text", title);
    body.append("post_type", cfg.postType || "post");
    if (postId) {
      body.append("post_id", String(postId));
    }

    window
      .fetch(cfg.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: body,
      })
      .then(function (res) {
        return res.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data || !payload.data.slug) {
          var message =
            (payload &&
              payload.data &&
              payload.data.message) ||
            strings.error ||
            "error";
          onDone(new Error(message), null);
          return;
        }
        onDone(null, payload.data.slug);
      })
      .catch(function () {
        onDone(new Error(strings.error || "error"), null);
      });
  }

  function bindClassic() {
    var root = document.getElementById("wpslug-editor-tools");
    if (!root) {
      return;
    }

    var seoBtn = root.querySelector(".wpslug-editor-seo");
    var pinyinBtn = root.querySelector(".wpslug-editor-pinyin");
    var applyBtn = root.querySelector(".wpslug-editor-apply");
    var previewWrap = root.querySelector(".wpslug-editor-preview");
    var previewValue = root.querySelector(".wpslug-editor-preview-value");
    var statusEl = root.querySelector(".wpslug-editor-status");
    var postId = root.getAttribute("data-post-id") || "0";

    function run(feature, button) {
      var title = classicTitle();
      if (!title) {
        setStatus(statusEl, strings.emptyTitle || "", "is-error");
        return;
      }
      var original = button.textContent;
      button.disabled = true;
      button.textContent = strings.generating || "…";
      setStatus(statusEl, "", null);

      requestPreview(feature, title, postId, function (err, slug) {
        button.disabled = false;
        button.textContent = original;
        if (err) {
          setStatus(statusEl, err.message || strings.error, "is-error");
          return;
        }
        candidate = slug;
        if (previewValue) {
          previewValue.textContent = slug;
        }
        if (previewWrap) {
          previewWrap.hidden = false;
        }
        setStatus(statusEl, "", null);
      });
    }

    if (seoBtn) {
      seoBtn.addEventListener("click", function () {
        run("seo_slug", seoBtn);
      });
    }
    if (pinyinBtn) {
      pinyinBtn.addEventListener("click", function () {
        run("semantic_pinyin", pinyinBtn);
      });
    }
    if (applyBtn) {
      applyBtn.addEventListener("click", function () {
        if (!candidate) {
          return;
        }
        applyClassicSlug(candidate);
        setStatus(statusEl, strings.applied || "", "is-ok");
      });
    }
  }

  function bindBlock() {
    if (!cfg.isBlock || !window.wp || !wp.plugins || !wp.editPost || !wp.element) {
      return;
    }

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useState = wp.element.useState;
    var registerPlugin = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
    var Button = wp.components.Button;
    var TextControl = wp.components.TextControl || null;

    function Panel() {
      var state = useState("");
      var slug = state[0];
      var setSlug = state[1];
      var busyState = useState(false);
      var busy = busyState[0];
      var setBusy = busyState[1];
      var msgState = useState("");
      var message = msgState[0];
      var setMessage = msgState[1];

      function postId() {
        try {
          return wp.data.select("core/editor").getCurrentPostId() || 0;
        } catch (e) {
          return 0;
        }
      }

      function generate(feature) {
        var title = blockTitle();
        if (!title) {
          setMessage(strings.emptyTitle || "");
          return;
        }
        setBusy(true);
        setMessage("");
        requestPreview(feature, title, postId(), function (err, value) {
          setBusy(false);
          if (err) {
            setMessage(err.message || strings.error);
            return;
          }
          setSlug(value);
          candidate = value;
        });
      }

      function apply() {
        if (!slug) {
          return;
        }
        if (applyBlockSlug(slug)) {
          setMessage(strings.applied || "");
        } else {
          setMessage(strings.error || "");
        }
      }

      return el(
        PluginDocumentSettingPanel,
        {
          name: "wpslug-slug-preview",
          title: strings.panelTitle || "WPSlug",
          className: "wpslug-block-panel",
        },
        el(
          "p",
          { className: "description" },
          strings.hint || ""
        ),
        !cfg.wpmindReady
          ? el("p", { className: "description" }, strings.wpmindMissing || "")
          : null,
        el(
          Button,
          {
            isSecondary: true,
            isBusy: busy,
            disabled: busy,
            onClick: function () {
              generate("seo_slug");
            },
          },
          strings.seoButton || "SEO"
        ),
        el(
          Button,
          {
            isSecondary: true,
            isBusy: busy,
            disabled: busy,
            onClick: function () {
              generate("semantic_pinyin");
            },
          },
          strings.pinyinButton || "Pinyin"
        ),
        slug
          ? el(
              Fragment,
              null,
              el(
                "p",
                null,
                el("span", null, (strings.previewLabel || "Preview") + ": "),
                el("code", { className: "wpslug-editor-preview-value" }, slug)
              ),
              el(
                Button,
                {
                  isPrimary: true,
                  onClick: apply,
                },
                strings.applyButton || "Apply"
              )
            )
          : null,
        message ? el("p", { className: "description" }, message) : null,
        TextControl ? null : null
      );
    }

    registerPlugin("wpslug-slug-preview", {
      render: Panel,
      icon: "admin-links",
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bindClassic);
  } else {
    bindClassic();
  }
  bindBlock();
})();
