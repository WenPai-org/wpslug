/**
 * wenpai-admin-ui kit behaviour. No jQuery. Product pages may no-op
 * by using data-wenpai-post on toggles (those submit via admin-post).
 */
(function () {
	"use strict";

	function inApp(el) {
		return el && el.closest && el.closest(".wenpai-app");
	}

	function reduceMotion() {
		return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
	}

	function setToggle(btn, on) {
		btn.classList.toggle("on", on);
		btn.setAttribute("aria-pressed", on ? "true" : "false");
		var label = on ? btn.getAttribute("data-on") : btn.getAttribute("data-off");
		if (!label) {
			return;
		}
		var knob = btn.querySelector("i");
		btn.textContent = "";
		if (knob) {
			btn.appendChild(knob);
		} else {
			var i = document.createElement("i");
			btn.appendChild(i);
		}
		btn.appendChild(document.createTextNode(" " + label));
	}

	function syncChoice(radio) {
		var name = radio.getAttribute("name");
		if (!name) {
			return;
		}
		var root = radio.form || radio.closest(".wenpai-app") || document;
		var nodes = root.querySelectorAll(".choice input[type=\"radio\"]");
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i].name !== name) {
				continue;
			}
			var lab = nodes[i].closest(".choice");
			if (lab) {
				lab.classList.toggle("is-on", nodes[i].checked);
			}
		}
	}

	function syncGate(gate) {
		var box = gate.querySelector('input[type="checkbox"]');
		if (!box) {
			return;
		}
		var host = gate.closest("form") || gate.closest(".card") || gate.parentElement;
		if (!host) {
			return;
		}
		var apply = host.querySelectorAll("[data-wenpai-need-apply]");
		for (var i = 0; i < apply.length; i++) {
			apply[i].disabled = !box.checked;
		}
	}

	/* ---- depends_on: conditional fields (settings framework 0.3.0) ----
	 * One condition:  data-wenpai-depends-on="opt[field]" data-wenpai-depends-value="1"
	 *                 (value may be a JSON list = any of) [data-wenpai-depends-not]
	 * Several (AND):  data-wenpai-depends='[{"on":"opt[a]","value":["1"],"not":false}]'
	 * Hidden blocks get `hidden` + `inert`; their inputs still submit.
	 * Without JS nothing is hidden.
	 */
	var DEP_SEL = "[data-wenpai-depends-on], [data-wenpai-depends]";

	function attrQuote(s) {
		return String(s).replace(/["\\]/g, "\\$&");
	}

	function parseList(raw) {
		if (raw && raw.charAt(0) === "[") {
			try {
				var arr = JSON.parse(raw);
				if (Array.isArray(arr)) {
					return arr.map(String);
				}
			} catch (err) {
				/* fall through: a plain value that starts with "[" */
			}
		}
		return [raw === null ? "1" : raw];
	}

	function conditionsOf(el) {
		var raw = el.getAttribute("data-wenpai-depends");
		if (raw) {
			try {
				var list = JSON.parse(raw);
				return Array.isArray(list) ? list.map(function (c) {
					return { on: String(c.on), value: (Array.isArray(c.value) ? c.value : [c.value]).map(String), not: !!c.not };
				}) : [];
			} catch (err) {
				return [];
			}
		}
		return [{
			on: el.getAttribute("data-wenpai-depends-on"),
			value: parseList(el.getAttribute("data-wenpai-depends-value")),
			not: el.hasAttribute("data-wenpai-depends-not")
		}];
	}

	function controlsFor(scope, name) {
		var q = attrQuote(name);
		return scope.querySelectorAll('[name="' + q + '"], [name="' + q + '[]"]');
	}

	/* Current values of a field. Toggle: "1" or "0". Multiselect: the checked values. */
	function valuesOf(nodes) {
		var vals = [];
		var hiddenVals = [];
		var sawChoice = false;
		for (var i = 0; i < nodes.length; i++) {
			var n = nodes[i];
			var type = (n.type || "").toLowerCase();
			if (type === "checkbox" || type === "radio") {
				sawChoice = true;
				if (n.checked) {
					vals.push(n.value);
				}
			} else if (type === "hidden") {
				hiddenVals.push(n.value);
			} else if (n.tagName === "SELECT" && n.multiple) {
				for (var o = 0; o < n.options.length; o++) {
					if (n.options[o].selected) {
						vals.push(n.options[o].value);
					}
				}
			} else {
				vals.push(n.value);
			}
		}
		if (sawChoice && !vals.length) {
			return hiddenVals;
		}
		return vals.length ? vals : hiddenVals;
	}

	function hiddenByDepends(node) {
		var host = node.closest && node.closest(DEP_SEL);
		return !!(host && host.hidden);
	}

	function conditionHolds(scope, c) {
		var nodes = controlsFor(scope, c.on);
		if (!nodes.length) {
			return true; /* unknown trigger: never hide */
		}
		/* A trigger that is itself hidden counts as not met, so chains collapse. */
		var match = !hiddenByDepends(nodes[0]);
		if (match) {
			var vals = valuesOf(nodes);
			match = vals.some(function (v) {
				return c.value.indexOf(v) !== -1;
			});
		}
		return c.not ? !match : match;
	}

	function setShown(el, show, scope, conds) {
		if (!show) {
			var active = document.activeElement;
			if (active && el.contains(active)) {
				/* Hand focus back to the switch that hid this block. */
				var back = controlsFor(scope, conds[0].on);
				for (var i = 0; i < back.length; i++) {
					if (back[i].type !== "hidden" && !hiddenByDepends(back[i])) {
						back[i].focus();
						break;
					}
				}
			}
			el.hidden = true;
			el.setAttribute("inert", "");
		} else {
			el.hidden = false;
			el.removeAttribute("inert");
		}
	}

	function syncDepends(root) {
		var scope = root || document;
		var els = scope.querySelectorAll(".wenpai-app [data-wenpai-depends-on], .wenpai-app [data-wenpai-depends]");
		/* Repeat so chains settle (a hides b, b hides c). */
		for (var pass = 0; pass < 5; pass++) {
			var changed = false;
			for (var i = 0; i < els.length; i++) {
				var el = els[i];
				var form = el.closest("form") || el.closest(".wenpai-app") || document;
				var conds = conditionsOf(el);
				var show = conds.every(function (c) {
					return conditionHolds(form, c);
				});
				if (show === el.hidden) {
					setShown(el, show, form, conds);
					changed = true;
				}
			}
			if (!changed) {
				break;
			}
		}
	}

	/* A receipt that is already in the page at load is re-inserted so screen readers read it. */
	function announce(root) {
		var nodes = (root || document).querySelectorAll(".wenpai-app [data-wenpai-announce]");
		for (var i = 0; i < nodes.length; i++) {
			(function (el) {
				var html = el.innerHTML;
				el.innerHTML = "";
				window.setTimeout(function () {
					el.innerHTML = html;
				}, 120);
			})(nodes[i]);
		}
	}

	function relocateTopNotices() {
		if (!document.body || !document.body.classList.contains("wenpai-ui-page")) {
			return;
		}
		var main = document.querySelector(".wenpai-app > .wenpai-main");
		var content = document.getElementById("wpbody-content");
		if (!main || !content) {
			return;
		}
		var moved = [];
		var kids = content.children;
		for (var i = 0; i < kids.length; i++) {
			var el = kids[i];
			if (!el.classList) {
				continue;
			}
			/* Core version nag stays out of the product shell. */
			if (el.classList.contains("update-nag")) {
				continue;
			}
			if (
				el.classList.contains("notice") ||
				el.classList.contains("updated") ||
				el.classList.contains("error") ||
				el.classList.contains("fade")
			) {
				moved.push(el);
			}
		}
		for (var j = 0; j < moved.length; j++) {
			main.insertBefore(moved[j], main.firstChild);
		}
	}

	function boot(root) {
		relocateTopNotices();
		var app = root || document;
		var radios = app.querySelectorAll(".wenpai-app .choice input[type=\"radio\"]");
		for (var i = 0; i < radios.length; i++) {
			syncChoice(radios[i]);
		}
		syncDepends(app);
		announce(app);
		var gates = app.querySelectorAll(".wenpai-app .wenpai-gate");
		for (var g = 0; g < gates.length; g++) {
			syncGate(gates[g]);
		}
		if (reduceMotion()) {
			return;
		}
		var wraps = app.querySelectorAll(".wenpai-app .wenpai-notice-wrap[data-wenpai-fade]");
		for (var w = 0; w < wraps.length; w++) {
			(function (el) {
				window.setTimeout(function () {
					el.classList.add("is-gone");
				}, 3200);
			})(wraps[w]);
		}
	}

	document.addEventListener("click", function (e) {
		var btn = e.target.closest && e.target.closest(".wenpai-app button.toggle");
		if (!btn) {
			return;
		}
		if (btn.disabled || btn.classList.contains("is-locked") || btn.closest(".is-static")) {
			return;
		}
		if (btn.hasAttribute("data-wenpai-post") || btn.type === "submit") {
			return;
		}
		e.preventDefault();
		setToggle(btn, !btn.classList.contains("on"));
	});

	document.addEventListener("change", function (e) {
		var t = e.target;
		if (!inApp(t)) {
			return;
		}
		if (t.name && document.querySelector(DEP_SEL)) {
			syncDepends(document);
		}
		if (t.matches(".choice input[type=\"radio\"]")) {
			syncChoice(t);
		}
		if (t.matches(".wenpai-gate input[type=\"checkbox\"]")) {
			var gate = t.closest(".wenpai-gate");
			if (gate) {
				syncGate(gate);
			}
		}
	});

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", function () {
			boot(document);
		});
	} else {
		boot(document);
	}
})();
