/**
 * Global page-transition loader — shows a full-screen spinner the instant
 * a same-site link is clicked or a form is submitted, so navigation never
 * feels like a dead click while the next page (login, registration,
 * wallet actions, everything) is loading.
 *
 * Include this on every page (it's already wired into the shared
 * header/footer templates). No markup needed — it builds its own overlay.
 */
(function () {
  'use strict';
  if (window.__pageLoaderInit) return;
  window.__pageLoaderInit = true;

  var overlay, hideTimer;

  function build() {
    overlay = document.createElement('div');
    overlay.id = 'pageLoaderOverlay';
    overlay.className = 'page-loader-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    overlay.innerHTML = '<div class="page-loader-box"><div class="page-loader-spinner"></div><div class="page-loader-label">Loading&hellip;</div></div>';
    document.body.appendChild(overlay);
  }

  function showLoader() {
    if (!overlay) build();
    overlay.classList.add('visible');
    clearTimeout(hideTimer);
    // Safety net: if something prevents the actual navigation (a blocked
    // popup, a cancelled request, etc.) never leave the site stuck behind
    // the spinner.
    hideTimer = setTimeout(hideLoader, 10000);
  }

  function hideLoader() {
    if (overlay) overlay.classList.remove('visible');
    clearTimeout(hideTimer);
  }

  window.pageLoaderShow = showLoader;
  window.pageLoaderHide = hideLoader;

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }
  ready(build);

  function isModifiedClick(e) {
    return e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || (typeof e.button === 'number' && e.button !== 0);
  }

  function closestLink(el) {
    while (el && el !== document) {
      if (el.tagName === 'A') return el;
      el = el.parentNode;
    }
    return null;
  }

  document.addEventListener('click', function (e) {
    if (isModifiedClick(e)) return;
    var a = closestLink(e.target);
    if (!a) return;
    if (a.hasAttribute('data-no-loader') || a.hasAttribute('download')) return;
    var target = a.getAttribute('target');
    if (target && target !== '_self') return;
    var href = a.getAttribute('href');
    if (!href || href.charAt(0) === '#') return;
    if (/^(mailto:|tel:|javascript:|sms:|whatsapp:)/i.test(href)) return;

    var url;
    try { url = new URL(href, window.location.href); } catch (err) { return; }
    if (url.origin !== window.location.origin) return;

    var here = window.location;
    var sameDocument = url.pathname === here.pathname && url.search === here.search && url.hash;
    if (sameDocument) return; // pure in-page anchor scroll, nothing actually loads

    showLoader();
  }, true);

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.tagName !== 'FORM') return;
    if (form.hasAttribute('data-no-loader')) return;
    var target = form.getAttribute('target');
    if (target && target !== '_self') return;
    showLoader();
  }, true);

  // If the page was restored from the back/forward cache (or the user
  // navigated back onto a page that still had the overlay up), make sure
  // it's hidden.
  window.addEventListener('pageshow', hideLoader);
})();
