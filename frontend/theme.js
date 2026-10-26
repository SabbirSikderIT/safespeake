/* SafeSpeak theme toggle — light (default) / dark. Persisted in localStorage. */
(function () {
  var KEY = 'safespeak-theme';
  var root = document.documentElement;

  function read() {
    try { return localStorage.getItem(KEY); } catch (e) { return null; }
  }
  function apply(theme) {
    if (theme === 'light') root.setAttribute('data-theme', 'light');
    else root.removeAttribute('data-theme');
  }

  // Apply immediately (script is loaded in <head>) to avoid a flash.
  // Light is the default; dark only if the visitor chose it.
  apply(read() === 'dark' ? 'dark' : 'light');

  var SUN = '<svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>';
  var MOON = '<svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';

  function label(btn) {
    var light = root.getAttribute('data-theme') === 'light';
    var t = light ? 'Switch to dark mode' : 'Switch to light mode';
    btn.setAttribute('aria-label', t);
    btn.setAttribute('title', t);
    btn.setAttribute('aria-pressed', light ? 'true' : 'false');
  }

  function build() {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'theme-toggle';
    btn.innerHTML = SUN + MOON;
    label(btn);
    btn.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      root.classList.add('theme-anim');
      apply(next);
      try { localStorage.setItem(KEY, next); } catch (e) {}
      label(btn);
      window.setTimeout(function () { root.classList.remove('theme-anim'); }, 350);
      document.dispatchEvent(new CustomEvent('themechange', { detail: { theme: next } }));
    });
    return btn;
  }

  function mount() {
    if (document.querySelector('.theme-toggle')) return;
    var btn = build();
    // Top-right slot, in order of preference per page layout.
    var slot = document.querySelector('nav .nav-btns')
            || document.querySelector('.topbar-actions')
            || document.querySelector('.admin-header .admin-info');
    if (slot) { slot.appendChild(btn); return; }

    var topbar = document.querySelector('.topbar');
    if (topbar) { topbar.appendChild(btn); return; }

    var header = document.querySelector('header');
    if (header) {
      // Group the existing right-hand link with the toggle so layout is unchanged.
      var last = header.lastElementChild;
      if (last && last.tagName === 'A' && header.children.length > 1) {
        var wrap = document.createElement('div');
        wrap.className = 'header-right';
        header.replaceChild(wrap, last);
        wrap.appendChild(last);
        wrap.appendChild(btn);
      } else header.appendChild(btn);
      return;
    }
    btn.classList.add('theme-toggle-float');
    document.body.appendChild(btn);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount);
  else mount();
})();
