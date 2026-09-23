/**
 * webcon_jev: the connection page's "send a test question".
 */
import { html, nothing, render } from 'lit';
import Notification from '@typo3/backend/notification.js';
import labels from '~labels/webcon_jev.module';
import { postJson } from '@webconsulting/webcon-jev/api.js';
import { answerValue, number, percent, usd } from '@webconsulting/webcon-jev/format.js';

class ConnectionCheck {
  constructor() {
    document.addEventListener('click', (event) => {
      const button = event.target.closest?.('[data-webcon-jev-ping]');
      if (button instanceof HTMLButtonElement) {
        event.preventDefault();
        this.ping(button);
      }
    });
  }

  async ping(button) {
    const region = document.querySelector('[data-webcon-jev-ping-result]');
    button.disabled = true;
    region.setAttribute('aria-busy', 'true');
    render(html`<p class="text-variant">${labels.get('connection.ping.running')}</p>`, region);

    let data;
    try {
      ({ data } = await postJson(button.dataset.webconJevPing, {}));
    } catch (error) {
      data = { ok: false, message: labels.get('connection.ping.failed'), detail: String(error?.message ?? error) };
    }

    button.disabled = false;
    region.removeAttribute('aria-busy');

    const message = data.message ?? labels.get('connection.ping.failed');
    const answer = data.result?.answers?.mood;
    const usage = data.result?.usage;

    render(html`
      <div class="callout ${data.ok ? 'callout-success' : 'callout-danger'}">
        <div class="callout-content">
          <div class="callout-title">
            ${labels.get(data.ok ? 'connection.ping.okTitle' : 'connection.ping.failedTitle')}
          </div>
          <div class="callout-body">
            <p>${message}</p>
            ${data.detail ? html`<p class="webcon-jev-mono">${data.detail}</p>` : nothing}
            ${answer ? html`
              <p>
                ${labels.get('connection.ping.answer', [answerValue(answer), percent(answer.confidence)])}
                ${usage ? html`· ${labels.get('connection.ping.usage', [number(usage.inputTokens), usd(usage.costUsd)])}` : nothing}
              </p>` : nothing}
          </div>
        </div>
      </div>
    `, region);

    if (data.ok) {
      Notification.success(labels.get('connection.ping.okTitle'), message);
    } else {
      Notification.error(labels.get('connection.ping.failedTitle'), message);
    }
  }
}

export default new ConnectionCheck();
