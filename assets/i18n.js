/*
 * Small language switcher, shared by all pages.
 *
 * Each page loads a text file that sets `window.TEXTE = { de: {...}, en: {...} }`.
 * Elements with `data-t="key"` get the text of that key (HTML allowed).
 * Elements with `data-t-attr="attr:key"` get an attribute (e.g. title, aria-label).
 *
 * To add a language: add one more block (e.g. `fr: {...}`) to the text file.
 * The switcher button appears by itself. Missing keys fall back to German.
 */
(function () {
  var FALLBACK = "de";
  var STORE_KEY = "wpum-lang";

  function readStored() {
    try { return localStorage.getItem(STORE_KEY); } catch (e) { return null; }
  }
  function store(lang) {
    try { localStorage.setItem(STORE_KEY, lang); } catch (e) { /* ignore */ }
  }

  function pickLang(texts) {
    var fromUrl = new URLSearchParams(location.search).get("lang");
    if (fromUrl && texts[fromUrl]) return fromUrl;
    var stored = readStored();
    if (stored && texts[stored]) return stored;
    return FALLBACK;
  }

  function apply(lang) {
    var texts = window.TEXTE || {};
    var dict = texts[lang] || {};
    var base = texts[FALLBACK] || {};
    function t(key) { return key in dict ? dict[key] : (key in base ? base[key] : key); }

    document.documentElement.lang = lang;
    document.querySelectorAll("[data-t]").forEach(function (el) {
      el.innerHTML = t(el.getAttribute("data-t"));
    });
    document.querySelectorAll("[data-t-attr]").forEach(function (el) {
      el.getAttribute("data-t-attr").split(";").forEach(function (pair) {
        var parts = pair.split(":");
        if (parts.length === 2) el.setAttribute(parts[0].trim(), t(parts[1].trim()));
      });
    });
    if (dict["meta.title"] || base["meta.title"]) document.title = t("meta.title");

    document.querySelectorAll(".lang-switch button").forEach(function (b) {
      b.setAttribute("aria-pressed", String(b.dataset.lang === lang));
    });
    store(lang);
    document.dispatchEvent(new CustomEvent("langchange", { detail: { lang: lang } }));
  }

  function buildSwitch(texts, current) {
    document.querySelectorAll(".lang-switch").forEach(function (box) {
      box.innerHTML = "";
      Object.keys(texts).forEach(function (lang) {
        var b = document.createElement("button");
        b.type = "button";
        b.dataset.lang = lang;
        b.textContent = lang.toUpperCase();
        b.setAttribute("aria-pressed", String(lang === current));
        b.addEventListener("click", function () { apply(lang); });
        box.appendChild(b);
      });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var texts = window.TEXTE || {};
    var lang = pickLang(texts);
    buildSwitch(texts, lang);
    apply(lang);
  });
})();
