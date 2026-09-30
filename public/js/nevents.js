/* ============================================================
   N Events — Core JavaScript
   ============================================================ */

'use strict';

// ── Helpers ─────────────────────────────────────────────────
// Base URL of the app (works when served from a sub-path such as
// /Lordminds/N_Events/public — root-relative "/api/..." URLs would not).
const APP_URL = (document.querySelector('meta[name="app-url"]')?.content || '').replace(/\/$/, '');
const appUrl  = path => APP_URL + '/' + String(path).replace(/^\//, '');
const escapeHtml = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
// ── Animate On Scroll ───────────────────────────────────────
function initScrollAnimations() {
  const observer = new IntersectionObserver(
    entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.1, rootMargin: '0px 0px -50px 0px' }
  );

  document.querySelectorAll('.animate-on-scroll').forEach(el => observer.observe(el));
}

// ── Sticky Navbar ───────────────────────────────────────────
function initNavbar() {
  const navbar = document.querySelector('.ne-navbar, .ne-appbar');   // website navbar, or the app's top bar
  if (!navbar) return;

  const handler = () => {
    navbar.classList.toggle('scrolled', window.scrollY > 20);
  };
  window.addEventListener('scroll', handler, { passive: true });
  handler();
  // The home hero fills the screen below the navbar, whose height varies
  // (touch screens get 44px tap targets): give the hero the real height.
  const hero = document.querySelector('.ne-hero.band-hero');
  if (hero) {
    const fit = () => hero.style.setProperty('--ne-nav-h', navbar.getBoundingClientRect().height + 'px');
    fit();
    window.addEventListener('resize', fit, { passive: true });
  }
}

// ── Number Counter Animation ────────────────────────────────
function animateCounters() {
  document.querySelectorAll('[data-counter]').forEach(el => {
    const target = parseInt(el.dataset.counter, 10);
    const duration = 1500;
    const start = performance.now();

    const update = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      const eased    = 1 - Math.pow(1 - progress, 4);
      el.textContent = Math.floor(eased * target).toLocaleString();
      if (progress < 1) requestAnimationFrame(update);
      else el.textContent = target.toLocaleString() + (el.dataset.suffix || '');
    };
    requestAnimationFrame(update);
  });
}

// ── Counter observer ────────────────────────────────────────
function initCounters() {
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        animateCounters();
        observer.disconnect();
      }
    });
  }, { threshold: 0.5 });

  const counterSection = document.querySelector('.ne-hero-stats');
  if (counterSection) observer.observe(counterSection);
}

// ── Save Event (AJAX) ───────────────────────────────────────
function initSaveButtons(root = document) {
  root.querySelectorAll('.event-card-save:not([data-bound])').forEach(btn => {
    btn.dataset.bound = '1';
    btn.addEventListener('click', async e => {
      e.preventDefault();
      e.stopPropagation();

      const slug   = btn.dataset.slug;
      const action = btn.classList.contains('saved') ? 'unsave' : 'save';
      const url    = appUrl(`event/${encodeURIComponent(slug)}/${action}`);

      try {
        const res  = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: `_csrf=${encodeURIComponent(document.querySelector('meta[name="csrf-token"]')?.content || '')}`,
        });
        const data = await res.json();

        if (res.status === 401) {       // guests: sign in, then come back here
          window.location.href = appUrl('login');   // the server remembered this page
          return;
        }

        if (data.success) {
          const saved = data.action === 'saved';
          // every save button for this event on the page (card, sidebar, mobile action bar)
          document.querySelectorAll(`.event-card-save[data-slug="${CSS.escape(slug)}"]`).forEach(b => {
            b.classList.toggle('saved', saved);
            b.setAttribute('aria-pressed', saved ? 'true' : 'false');
            const icon = b.querySelector('i');
            if (icon) {                     // keep the icon (heart / bookmark), swap filled <-> outline
              icon.classList.toggle('fas', saved);
              icon.classList.toggle('far', !saved);
            }
          });
          showToast(data.action === 'saved' ? 'Event saved!' : 'Event removed from saved.', 'success');
        }
      } catch (err) {
        showToast('Something went wrong. Please try again.', 'error');
      }
    });
  });
}

// ── Share (native share sheet on phones, copy link elsewhere) ──
document.addEventListener('click', async e => {
  const btn = e.target.closest('[data-share]');
  if (!btn) return;
  let data = {};
  try { data = JSON.parse(btn.dataset.share); } catch (_) { data = { url: location.href }; }
  const url = data.url || location.href;
  // Inside the Android / iOS app (mobile/): the phone's own share sheet
  const appShare = window.Capacitor?.isNativePlatform?.() && window.Capacitor.Plugins?.Share;
  if (appShare) {
    try { await appShare.share({ title: data.title || document.title, url, dialogTitle: 'Share event' }); } catch (_) { /* dismissed */ }
    return;
  }
  if (navigator.share) {
    try { await navigator.share({ title: data.title || document.title, url }); } catch (_) { /* dismissed */ }
    return;
  }
  try {
    await navigator.clipboard.writeText(url);
    showToast('Link copied', 'success');
  } catch (_) {
    window.prompt('Copy this link:', url);
  }
});

// ── Toast Notifications ─────────────────────────────────────
function showToast(message, type = 'info') {
  let container = document.querySelector('.ne-toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'ne-toast-container';
    document.body.appendChild(container);
  }

  const icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', info: 'fa-circle-info', warning: 'fa-triangle-exclamation' };
  const toast = document.createElement('div');
  toast.className = `ne-toast ${type}`;
  toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
  const icon = document.createElement('i');
  icon.className = `fas ${icons[type] || icons.info}`;
  const text = document.createElement('span');
  text.textContent = message;          // never innerHTML: messages can contain user content
  toast.append(icon, text);

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.animation = 'fadeInDown 0.3s ease reverse';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// ── Search Autocomplete ─────────────────────────────────────
function initSearchAutocomplete() {
  const input = document.querySelector('#search-input');
  if (!input) return;

  let timeout;
  input.addEventListener('input', () => {
    clearTimeout(timeout);
    const q = input.value.trim();
    if (q.length < 2) { hideAutocomplete(); return; }
    timeout = setTimeout(() => fetchAutocomplete(q), 280);
  });

  document.addEventListener('click', e => {
    if (!e.target.closest('.ne-search-bar')) hideAutocomplete();
  });
}

async function fetchAutocomplete(q) {
  try {
    const res  = await fetch(appUrl(`api/events/autocomplete?q=${encodeURIComponent(q)}`));
    const data = await res.json();
    renderAutocomplete(data.results || []);
  } catch {}
}

function renderAutocomplete(results) {
  let dropdown = document.querySelector('#search-autocomplete');
  if (!dropdown) {
    dropdown = document.createElement('div');
    dropdown.id = 'search-autocomplete';
    dropdown.className = 'ne-ac';
    document.querySelector('.ne-search-bar')?.appendChild(dropdown);
  }
  if (!results.length) { hideAutocomplete(); return; }
  dropdown.innerHTML = results.map(r =>
    `<a href="${escapeHtml(appUrl('event/' + encodeURIComponent(r.slug)))}" class="ne-ac-item">${escapeHtml(r.title)}<span>${escapeHtml(r.date || '')}</span></a>`
  ).join('');
  dropdown.style.display = 'block';
}

function hideAutocomplete() {
  const d = document.querySelector('#search-autocomplete');
  if (d) d.style.display = 'none';
}

// ── Location picker (partials/location-picker.php) ──────────
// Type a city, district or state anywhere in India; results come from
// /api/locations. Picking one puts the district's id in the hidden field.
// data-allow-all offers "All India"; data-autosave saves the choice as the
// visitor's location (api/set-district) and reloads the server-rendered page.
function saveDistrict(districtId) {
  return fetch(appUrl('api/set-district'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: `district_id=${encodeURIComponent(districtId || 0)}&_csrf=${encodeURIComponent(document.querySelector('meta[name="csrf-token"]')?.content || '')}`,
  })
    .then(res => res.json())
    .then(data => { if (!data.success) throw new Error(data.message || 'Could not change location.'); });
}

function initLocationPickers() {
  document.querySelectorAll('[data-loc-picker]').forEach(box => {
    const input  = box.querySelector('.ne-loc-input');
    const hidden = box.querySelector('input[type="hidden"]');
    const list   = box.querySelector('.ne-loc-list');
    const allowAll = box.hasAttribute('data-allow-all');
    let items = [], active = -1, timer = null, seq = 0;

    const open = show => {
      list.hidden = !show;
      input.setAttribute('aria-expanded', show ? 'true' : 'false');
      if (!show) { active = -1; input.removeAttribute('aria-activedescendant'); }
    };
    const render = (results, note) => {
      items = (allowAll ? [{ district_id: '', label: 'All India', detail: 'Events from everywhere' }] : []).concat(results);
      list.innerHTML = items.map((r, i) =>
        `<li id="${list.id}-${i}" role="option" class="ne-loc-option" data-i="${i}" aria-selected="false">
           <span class="ne-loc-name">${escapeHtml(r.label)}</span><span class="ne-loc-detail">${escapeHtml(r.detail)}</span></li>`
      ).join('') + (note ? `<li class="ne-loc-note" role="presentation">${escapeHtml(note)}</li>` : '');
      active = -1;
      open(true);
    };
    const highlight = i => {
      const opts = list.querySelectorAll('.ne-loc-option');
      if (!opts.length) return;
      active = (i + opts.length) % opts.length;
      opts.forEach((o, j) => o.setAttribute('aria-selected', j === active ? 'true' : 'false'));
      input.setAttribute('aria-activedescendant', opts[active].id);
      opts[active].scrollIntoView({ block: 'nearest' });
    };
    const choose = i => {
      const r = items[i];
      if (!r) return;
      const text = r.district_id === '' ? 'All India' : `${r.label}, ${r.detail}`;
      input.value = input.dataset.current = text;
      hidden.value = r.district_id;
      open(false);
      hidden.dispatchEvent(new Event('change', { bubbles: true }));
      const nav = box.closest('[data-navigate]');   // Discover: open the results for that place
      if (nav) {
        const url = new URL(nav.dataset.navigate, window.location.href);
        if (r.district_id) url.searchParams.set('district', r.district_id);
        window.location.href = url.toString();
        return;
      }
      if (box.hasAttribute('data-autosave')) {
        input.disabled = true;
        box.classList.add('is-loading');
        saveDistrict(r.district_id)
          .then(() => window.location.reload())
          .catch(err => {
            input.disabled = false;
            box.classList.remove('is-loading');
            showToast(err.message || 'Could not change location. Please try again.', 'error');
          });
      }
    };
    const search = () => {
      const q = input.value.trim();
      if (q.length < 2) { render([], 'Type at least 2 letters of a city, district or state'); return; }
      const mine = ++seq;
      fetch(appUrl('api/locations?q=' + encodeURIComponent(q)), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => res.json())
        .then(data => { if (mine === seq) render(data.data || [], (data.data || []).length ? '' : `No place in India matches “${q}”`); })
        .catch(() => { if (mine === seq) render([], 'Could not load places. Check your connection.'); });
    };

    input.addEventListener('focus', () => { input.select(); render([], 'Type a city, district or state'); });
    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(search, 180); });
    input.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown') { e.preventDefault(); list.hidden ? search() : highlight(active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
      else if (e.key === 'Enter' && !list.hidden) {
        e.preventDefault();                    // never submit the surrounding form half-way
        choose(active >= 0 ? active : (items.length === (allowAll ? 2 : 1) ? items.length - 1 : -1));
      }
      else if (e.key === 'Escape' && !list.hidden) { e.preventDefault(); input.value = input.dataset.current || ''; open(false); }
    });
    // mousedown (not click) so the choice lands before the field loses focus
    list.addEventListener('mousedown', e => {
      const li = e.target.closest('.ne-loc-option');
      e.preventDefault();
      if (li) choose(+li.dataset.i);
    });
    input.addEventListener('blur', () => {
      open(false);
      input.value = input.dataset.current || '';   // typed text that wasn't picked is dropped
    });
  });
}

// ── State -> district selects (Discover's filter sheet) ─────
function initStateDistrictSelects() {
  document.querySelectorAll('[data-state-select]').forEach(stateSel => {
    const distSel = stateSel.form?.querySelector('[data-district-select]');
    if (!distSel) return;
    stateSel.addEventListener('change', () => {
      distSel.innerHTML = '<option value="">All districts</option>';
      distSel.disabled = !stateSel.value;
      if (!stateSel.value) return;
      fetch(appUrl('api/districts?state=' + encodeURIComponent(stateSel.value)), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => res.json())
        .then(data => {
          distSel.insertAdjacentHTML('beforeend', (data.data || []).map(d => `<option value="${d.id}">${escapeHtml(d.name)}</option>`).join(''));
        });
    });
  });
}

// ── App shell (body.is-app: inside the Android / iOS app) ───
// Back arrow, loading bar between screens, tap the current tab to go to the
// top, pull down at the top of a screen to refresh.
function initAppShell() {
  const body = document.body;
  if (!body.classList.contains('is-app')) return;

  // Back: return to the previous screen when it was in the app, else go to the parent screen
  document.querySelectorAll('[data-app-back]').forEach(a => a.addEventListener('click', e => {
    if (document.referrer.startsWith(location.origin) && history.length > 1) {
      e.preventDefault();
      history.back();
    }
  }));

  // Loading bar while the next screen loads
  const navigating = () => body.classList.add('is-navigating');
  document.addEventListener('click', e => {
    const a = e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.metaKey || e.ctrlKey || a.target === '_blank' || a.hasAttribute('download')
        || a.hasAttribute('data-bs-toggle') || a.hasAttribute('data-app-back') || a.hasAttribute('data-load-more')) return;
    const url = new URL(a.href, location.href);
    if (url.origin !== location.origin || (url.pathname === location.pathname && url.search === location.search && url.hash)) return;
    navigating();
  });
  document.addEventListener('submit', e => { if (!e.defaultPrevented) navigating(); });
  window.addEventListener('pageshow', () => body.classList.remove('is-navigating'));   // also when coming back

  // Tapping the tab you are on scrolls to the top instead of reloading
  document.querySelectorAll('.ne-tab.active').forEach(tab => tab.addEventListener('click', e => {
    if (window.scrollY > 0) { e.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
  }));

  // Pull to refresh
  const ptr = document.createElement('div');
  ptr.className = 'ne-ptr';
  ptr.setAttribute('aria-hidden', 'true');
  ptr.innerHTML = '<i class="fas fa-rotate-right"></i>';
  body.appendChild(ptr);
  const blocked = t => t.closest('.offcanvas, .modal, .dropdown-menu.show, textarea, input, select, .ne-loc-list, [data-no-ptr]')
                    || body.classList.contains('modal-open') || document.querySelector('.offcanvas.show, .dropdown-menu.show');
  let startY = null, pull = 0;
  const show = d => {
    const y = Math.min(d, 90);
    ptr.style.transform = `translateY(${y - 80}px) scale(${0.6 + Math.min(y / 90, 1) * 0.4}) rotate(${y * 3}deg)`;
    ptr.style.opacity = String(Math.min(y / 60, 1));
    ptr.classList.toggle('is-ready', y >= 70);
  };
  const reset = () => { ptr.style.transition = 'transform .2s, opacity .2s'; show(0); setTimeout(() => { ptr.style.transition = ''; }, 200); };
  window.addEventListener('touchstart', e => {
    startY = window.scrollY <= 0 && e.touches.length === 1 && !blocked(e.target) ? e.touches[0].clientY : null;
    pull = 0;
  }, { passive: true });
  window.addEventListener('touchmove', e => {
    if (startY === null) return;
    const d = (e.touches[0].clientY - startY) * 0.5;
    if (d <= 0 || window.scrollY > 0) { if (pull) reset(); startY = null; pull = 0; return; }
    pull = d;
    show(d);
  }, { passive: true });
  window.addEventListener('touchend', () => {
    if (startY === null) return;
    startY = null;
    if (pull >= 70) {
      ptr.classList.add('is-refreshing');
      ptr.style.transform = 'translateY(0) scale(1)';
      navigating();
      location.reload();
    } else if (pull) {
      reset();
    }
    pull = 0;
  }, { passive: true });
}

// ── Load more (app lists: the next page's rows are added below) ──
function initLoadMore() {
  document.addEventListener('click', async e => {
    const more = e.target.closest('[data-load-more]');
    if (!more) return;
    const list = document.querySelector('[data-load-list]');
    if (!list) return;
    e.preventDefault();
    if (more.classList.contains('is-loading')) return;
    more.classList.add('is-loading');
    more.textContent = 'Loading…';
    try {
      const html = await fetch(more.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.text());
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const rows = doc.querySelectorAll('[data-load-list] > *');
      const frag = document.createDocumentFragment();
      rows.forEach(r => frag.appendChild(document.importNode(r, true)));
      list.appendChild(frag);
      initSaveButtons(list);
      const next = doc.querySelector('[data-load-more]');
      if (next) {
        more.href = next.href;
        more.textContent = 'Load more events';
        more.classList.remove('is-loading');
      } else {
        more.remove();
      }
    } catch (_) {
      window.location.href = more.href;   // fall back to opening the next page
    }
  });
}

// ── Flash Messages ──────────────────────────────────────────
function initFlashMessages() {
  document.querySelectorAll('[data-flash]').forEach(el => {
    const type = el.dataset.flash;
    const msg  = el.dataset.msg;
    if (msg) showToast(msg, type);
  });
}

// ── Register redirect countdown ─────────────────────────────
function initRegisterRedirect() {
  const countdown = document.querySelector('[data-redirect-countdown]');
  if (!countdown) return;

  const url = countdown.dataset.url;
  let secs  = parseInt(countdown.dataset.seconds || '5', 10);

  const interval = setInterval(() => {
    secs--;
    countdown.textContent = secs;
    if (secs <= 0) {
      clearInterval(interval);
      window.location.href = url;
    }
  }, 1000);
}

// ── Form validation helpers ─────────────────────────────────
function initForms() {
  document.querySelectorAll('.ne-form-validated').forEach(form => {
    form.addEventListener('submit', e => {
      let valid = true;
      form.querySelectorAll('[required]').forEach(field => {
        if (!field.value.trim()) {
          field.classList.add('is-invalid');
          valid = false;
        } else {
          field.classList.remove('is-invalid');
        }
      });
      if (!valid) e.preventDefault();
    });
    form.querySelectorAll('[required]').forEach(field => {
      field.addEventListener('input', () => {
        if (field.value.trim()) field.classList.remove('is-invalid');
      });
    });
  });
}

// ── Interest category selection (onboarding) ────────────────
function initInterestSelector() {
  document.querySelectorAll('.ne-interest-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      chip.classList.toggle('selected');
      const input = chip.querySelector('input[type="checkbox"]');
      if (input) input.checked = chip.classList.contains('selected');
    });
  });
}

// ── Bootstrap init ──────────────────────────────────────────
function initBootstrap() {
  // Tooltips
  if (typeof bootstrap !== 'undefined') {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
      new bootstrap.Tooltip(el);
    });
  }
}

// ── Main init ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initNavbar();
  initAppShell();
  initLoadMore();
  initScrollAnimations();
  initSaveButtons();
  initSearchAutocomplete();
  initLocationPickers();
  initStateDistrictSelects();
  initFlashMessages();
  initRegisterRedirect();
  initForms();
  initInterestSelector();
  initBootstrap();
  initCounters();

  initLoadingButtons();
});

// ── Submit loading state ────────────────────────────────────
// <form data-loading>: once the submit is actually going ahead, the submit
// button shows a spinner and can't be double-clicked.
function initLoadingButtons() {
  document.querySelectorAll('form[data-loading]').forEach(form => {
    form.addEventListener('submit', e => {
      if (e.defaultPrevented) return;
      const btn = form.querySelector('button[type="submit"]');
      if (btn) { btn.classList.add('is-loading'); btn.setAttribute('aria-busy', 'true'); }
    });
  });
}

// Expose utilities
window.NEvents = { showToast, appUrl };
