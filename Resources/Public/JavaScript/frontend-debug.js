/**
 * Puts webcon_jev's debug panel under each Powermail form while the visitor types.
 *
 * Loaded only when plugin.tx_webconjev.settings.debug is on. powermail_cond's script dispatches
 * `powermailcond:processed` with the condition endpoint's JSON; when Jev was asked, that JSON
 * carries the rendered panel as `webconJevDebug.html`. On a page that already holds a panel —
 * the thank-you page after a routed submission — the panel is moved up to the form's content
 * element, so it is not left at the very bottom of the page.
 */
(() => {
  'use strict';

  const hostFor = (form) => {
    const uid = form.querySelector('input.powermail_form_uid')?.value ?? '';
    let host = form.parentElement?.querySelector(`:scope > [data-webcon-jev-debug="${uid}"]`);
    if (!host) {
      host = document.createElement('div');
      host.className = 'webcon-jev-debug-host';
      host.dataset.webconJevDebug = uid;
      host.setAttribute('aria-live', 'polite');
      form.insertAdjacentElement('afterend', host);
    }
    return host;
  };

  document.addEventListener('powermailcond:processed', (event) => {
    const form = event.target;
    const html = event.detail?.webconJevDebug?.html;
    if (!(form instanceof HTMLFormElement) || typeof html !== 'string') {
      return;
    }
    hostFor(form).innerHTML = html;
  });

  const placePagePanel = () => {
    const panel = document.querySelector('[data-webcon-jev-debug="page"]');
    const anchor = document.querySelector('.powermail_create, .powermail_confirmation, [class*="powermail"]')
      ?.closest('.frame, [id^="c"]');
    if (panel && anchor && !anchor.contains(panel)) {
      anchor.insertAdjacentElement('beforeend', panel);
    }
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', placePagePanel);
  } else {
    placePagePanel();
  }
})();
