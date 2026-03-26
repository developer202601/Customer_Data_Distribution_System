import './bootstrap';

import jQuery from 'jquery';
window.$ = window.jQuery = jQuery;

import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import * as bootstrap from 'bootstrap';
// Ensure legacy inline scripts can access bootstrap via `window.bootstrap`
window.bootstrap = bootstrap;
import 'admin-lte/dist/js/adminlte.min.js';
import 'select2';
import 'select2/dist/css/select2.css';
import Chart from 'chart.js/auto';
window.Chart = Chart;

document.addEventListener('DOMContentLoaded', () => {
  // Theme handling preserved
  const themeToggle = document.getElementById('theme-toggle');
  const THEME_STORAGE_KEY = 'cdds-theme-mode';
  const THEMES = { LIGHT: 'light', DARK: 'dark' };
  const applyTheme = (theme) => {
    document.body.classList.remove('theme-light', 'theme-dark');
    document.body.classList.add(`theme-${theme}`);
    if (themeToggle) {
      themeToggle.dataset.theme = theme;
      themeToggle.setAttribute('aria-pressed', theme === THEMES.DARK ? 'true' : 'false');
    }
  };
  const loadStoredTheme = () => { try { const s = localStorage.getItem(THEME_STORAGE_KEY); return (s === THEMES.DARK || s === THEMES.LIGHT) ? s : null; } catch { return null; } };
  const detectPreferredTheme = () => window.matchMedia('(prefers-color-scheme: dark)').matches ? THEMES.DARK : THEMES.LIGHT;
  const saveTheme = (t) => { try { localStorage.setItem(THEME_STORAGE_KEY, t); } catch { } };
  const toggleTheme = () => { const cur = document.body.classList.contains('theme-dark') ? THEMES.DARK : THEMES.LIGHT; const next = cur === THEMES.DARK ? THEMES.LIGHT : THEMES.DARK; applyTheme(next); saveTheme(next); };
  const initTheme = () => { applyTheme(loadStoredTheme() || detectPreferredTheme()); };
  if (themeToggle) themeToggle.addEventListener('click', toggleTheme);
  initTheme();

  // Simplified loader implementation
  const loader = document.getElementById('page-loader');
  if (!loader) return;
  const NAV_FLASH_KEY = 'cdds-loader-shown';
  const statusUrl = loader.dataset.statusUrl;
  const statusStreamUrl = loader.dataset.statusStreamUrl;
  const readyRedirect = loader.dataset.readyRedirect;
  const staticComplete = loader.dataset.staticComplete === '1';
  const rootEl = document.documentElement;
  const navFlashSeen = (() => {
    try {
      const seen = sessionStorage.getItem(NAV_FLASH_KEY) === '1';
      sessionStorage.removeItem(NAV_FLASH_KEY);
      return seen;
    } catch {
      return false;
    }
  })();
  if (navFlashSeen && rootEl?.hasAttribute('data-loader-init')) {
    rootEl.removeAttribute('data-loader-init');
  }
  let lastProgress = 0;
  // Remove complex JS animation for reliability; use CSS transitions
  let animationFrame = null;
  let pollTimer = null;
  let eventSource = null;
  let noStatusHideTimer = null;

  const els = {
    msg: loader.querySelector('[data-loader-message]'),
    bar: loader.querySelector('[data-loader-bar]'),
    pct: loader.querySelector('[data-loader-percent]'),
    line: loader.querySelector('[data-loader-line-fill]'),
    heartbeat: loader.querySelector('[data-loader-heartbeat]'),
  };

  const showLoader = () => {
    loader.classList.remove('page-loader--hidden');
    try {
      sessionStorage.setItem(NAV_FLASH_KEY, '1');
    } catch { }

    // Safety: on pages that do not provide process-status endpoints,
    // do not allow the full-screen loader to remain indefinitely.
    if (!statusUrl && !statusStreamUrl && !staticComplete) {
      if (noStatusHideTimer) clearTimeout(noStatusHideTimer);
      noStatusHideTimer = setTimeout(() => {
        hideLoader();
        noStatusHideTimer = null;
      }, 2500);
    }
  };
  const hideLoader = () => {
    loader.classList.add('page-loader--hidden');
    if (rootEl?.hasAttribute('data-loader-init')) {
      rootEl.removeAttribute('data-loader-init');
    }
  };

  // Hide loader when restoring from bfcache (Back/Forward Cache)
  window.addEventListener('pageshow', (event) => {
    if (event.persisted || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
      hideLoader();
    }
  });

  const setWidths = (value) => {
    if (els.bar) { els.bar.style.width = value + '%'; els.bar.setAttribute('aria-valuenow', String(value)); }
    if (els.line) { els.line.style.width = value + '%'; }
    if (els.pct) els.pct.textContent = value + '%';
  };

  const formatHeartbeat = (timestamp) => {
    if (!timestamp) return '';
    const last = new Date(timestamp).getTime();
    if (Number.isNaN(last)) return '';
    const delta = Math.max(0, Math.floor((Date.now() - last) / 1000));
    if (delta > 5) return `Stalled ${delta}s`;
    return `Active ${delta}s`;
  };

  const apply = (data) => {
    if (!data) return;
    const raw = typeof data.progress === 'number' ? data.progress : lastProgress;
    const target = raw < lastProgress ? lastProgress : raw; // monotonic
    if (els.msg) {
      const baseMessage = data.message || data.status || 'Processing…';
      const processedRows = Number.isFinite(Number(data.processed_rows)) ? Number(data.processed_rows) : null;
      const totalRows = Number.isFinite(Number(data.total_rows)) ? Number(data.total_rows) : null;

      if (processedRows !== null && totalRows !== null && totalRows > 0) {
        els.msg.textContent = `${baseMessage} (${processedRows}/${totalRows} rows checked...)`;
      } else {
        els.msg.textContent = baseMessage;
      }
    }
    if (els.heartbeat) {
      const heartbeat = formatHeartbeat(data.last_updated_at);
      if (heartbeat) {
        els.heartbeat.textContent = heartbeat;
      }
    }
    if (target !== lastProgress) {
      lastProgress = target;
      setWidths(lastProgress);
    } else {
      setWidths(lastProgress);
    }
    if (data.status === 'ready' || data.status === 'failed' || data.redirect_url) {
      clearInterval(pollTimer);
      try {
        document.dispatchEvent(new CustomEvent('cdds:loader-final', { detail: data }));
      } catch (_) { }
      // Slight delay to allow final UI update before redirect/hide
      setTimeout(() => {
        if (data.redirect_url) {
          const parser = document.createElement('a');
          parser.href = data.redirect_url;
          const isSamePath = parser.pathname === window.location.pathname;
          const isSameSearch = (parser.search || '') === (window.location.search || '');
          if (!(isSamePath && isSameSearch)) {
            window.location.href = data.redirect_url;
            return;
          }
        }
        if (data.status === 'ready' && readyRedirect) {
          // Avoid redirect loop if already on target
          const targetUrl = readyRedirect;
          const current = window.location.pathname + window.location.search;
          // If current path does not match target path portion, redirect
          const parser = document.createElement('a');
          parser.href = targetUrl;
          if (parser.pathname !== window.location.pathname) {
            window.location.href = targetUrl;
            return; // Do not hide loader; new navigation will replace page
          }
        }
        hideLoader();
      }, 800);
    }
  };

  const poll = () => {
    if (!statusUrl) return;
    fetch(statusUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store', credentials: 'same-origin' })
      .then(r => r.ok ? r.json() : null)
      .then(apply)
      .catch(() => { });
  };

  const startStream = () => {
    if (!statusStreamUrl || typeof window.EventSource === 'undefined') {
      return false;
    }

    try {
      eventSource = new EventSource(statusStreamUrl, { withCredentials: true });
    } catch (err) {
      eventSource = null;
      return false;
    }

    eventSource.onmessage = (event) => {
      if (!event?.data) return;
      try {
        const data = JSON.parse(event.data);
        apply(data);
        if (data.status === 'ready' || data.status === 'failed' || data.redirect_url) {
          eventSource?.close();
          eventSource = null;
        }
      } catch (_) { }
    };

    eventSource.onerror = () => {
      if (eventSource) {
        eventSource.close();
        eventSource = null;
      }
      if (!pollTimer) {
        poll();
        pollTimer = setInterval(poll, 2000);
      }
    };

    return true;
  };

  if (staticComplete) {
    // Non-process pages: show loader briefly on initial load, then hide.
    if (!navFlashSeen) {
      showLoader();
      setWidths(100);
    }
    setTimeout(() => {
      hideLoader();
    }, 200);
  } else {
    // Prefer SSE stream, fallback to polling
    if ((statusUrl || statusStreamUrl) && !startStream()) {
      poll();
      pollTimer = setInterval(poll, 2000);
    }
  }

  // Basic navigation & form triggers
  // Only show the loader on beforeunload if not explicitly opted-out via click/submit handlers
  window.CDDSLoaderIgnoreBeforeUnload = false;
  window.addEventListener('beforeunload', (e) => {
    if (window.CDDSLoaderIgnoreBeforeUnload) return;
    showLoader();
  });
  document.addEventListener('submit', (e) => {
    try {
      const form = e.target instanceof HTMLFormElement ? e.target : e.target.closest && e.target.closest('form');
      if (!form) return;
      // If ancestor or the form itself has data-loader-off="1", opt out
      if (form.closest && (form.closest('[data-loader-off="1"]') || form.closest('#overview-results'))) {
        // If opted out, set a short-lived flag to ignore the upcoming beforeunload event
        window.CDDSLoaderIgnoreBeforeUnload = true;
        setTimeout(() => { window.CDDSLoaderIgnoreBeforeUnload = false; }, 2000);
        return;
      }
      // Otherwise show the global page loader
      window.CDDSLoaderIgnoreBeforeUnload = false;
      showLoader();
    } catch (err) {
      // Fallback to showing loader on unexpected errors
      showLoader();
    }
  }, true);

  document.addEventListener('click', (e) => {
    const link = e.target.closest && e.target.closest('a[href]');
    if (!link) return;
    // Respect target=_blank and download links
    if (link.getAttribute('target') === '_blank' || link.hasAttribute('download')) return;
    const href = link.getAttribute('href');
    if (!href || href.startsWith('#')) return;
    // If the link or an ancestor has data-loader-off="1" or is inside the AJAX results container, do not show the full-page loader
    if (link.closest && (link.closest('[data-loader-off="1"]') || link.closest('#overview-results'))) {
      // Opt-out: ignore the next beforeunload so download dialogs don't trigger the page loader
      window.CDDSLoaderIgnoreBeforeUnload = true;
      setTimeout(() => { window.CDDSLoaderIgnoreBeforeUnload = false; }, 2000);
      return;
    }
    window.CDDSLoaderIgnoreBeforeUnload = false;
    showLoader();
  }, true);

  // Expose minimal API if needed
  window.CDDSLoader = { show: showLoader, hide: hideLoader };
});
