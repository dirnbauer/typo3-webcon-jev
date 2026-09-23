/**
 * webcon_jev: the playground next to the decision editor.
 *
 * It sends what the editor holds right now — saved or not — so a changed wording can be tried
 * before any visitor meets it. The server bypasses the cache for these runs, so running the same
 * sample twice after an edit shows exactly what the edit changed.
 */
import { LitElement, html, nothing } from 'lit';
import { live } from 'lit/directives/live.js';
import { repeat } from 'lit/directives/repeat.js';
import { styleMap } from 'lit/directives/style-map.js';
import '@typo3/backend/element/icon-element.js';
import Hotkeys from '@typo3/backend/hotkeys.js';
import labels from '~labels/webcon_jev.module';
import { postJson } from '@webconsulting/webcon-jev/api.js';
import { toPayload } from '@webconsulting/webcon-jev/decision-model.js';
import { answerValue, distribution, milliseconds, number, percent, placeholders, usd } from '@webconsulting/webcon-jev/format.js';

const STORAGE_PREFIX = 'webcon-jev:playground:';
const DEFAULT_FIELD = 'field.message';
const FIELD_NAME = /^[A-Za-z0-9_-]+$/;

export class Playground extends LitElement {
  static properties = {
    draft: { attribute: false },
    revision: { type: Number },
    config: { attribute: false },
    running: { state: true },
    outcome: { state: true },
    samples: { state: true },
    freeFields: { state: true },
  };

  constructor() {
    super();
    this.draft = null;
    this.revision = 0;
    this.config = {};
    this.running = false;
    this.outcome = null;
    this.samples = {};
    this.freeFields = [DEFAULT_FIELD];
    this.storageKey = '';
  }

  createRenderRoot() {
    return this;
  }

  connectedCallback() {
    super.connectedCallback();
    Hotkeys.register(
      [Hotkeys.normalizedCtrlModifierKey, 'enter'],
      (event) => {
        event.preventDefault();
        this.run();
      },
      { scope: 'webcon-jev/decision-editor', allowOnEditables: true },
    );
  }

  willUpdate(changed) {
    if (changed.has('draft') && this.draft && this.storageKey === '') {
      this.restoreSamples();
    }
  }

  /**
   * The paths the sample needs values for: every {{path}} in the state template. With no template
   * the whole context goes over as JSON, so the sample is a free list of form fields instead.
   */
  paths() {
    const template = this.draft?.stateTemplate ?? '';
    const found = placeholders(template);
    if (found.length > 0) {
      return { mode: 'template', paths: found };
    }

    return template.trim() === ''
      ? { mode: 'free', paths: this.freeFields }
      : { mode: 'static', paths: [] };
  }

  render() {
    if (!this.draft) {
      return nothing;
    }

    const status = this.config.status ?? {};
    const hasQuestions = this.draft.questions.length > 0;
    const { mode, paths } = this.paths();

    return html`
      <section class="card webcon-jev-playground" id="webcon-jev-playground" aria-labelledby="webcon-jev-playground-title">
        <div class="card-header">
          <div class="card-icon"><typo3-backend-icon identifier="actions-play" size="medium"></typo3-backend-icon></div>
          <div class="card-header-body">
            <h2 class="card-title" id="webcon-jev-playground-title">${labels.get('playground.title')}</h2>
            <span class="card-subtitle">${labels.get('playground.subtitle')}</span>
          </div>
        </div>
        <div class="card-body">
          ${!status.enabled ? this.callout('warning', labels.get('playground.disabled')) : nothing}
          ${status.enabled && !status.tokenPresent ? this.callout('warning', labels.get('playground.noToken')) : nothing}
          ${hasQuestions ? this.renderSample(mode, paths) : this.renderEmpty()}
        </div>
        ${hasQuestions ? html`
          <div class="card-footer webcon-jev-actions">
            <button
              type="button"
              class="btn btn-primary"
              ?disabled=${this.running}
              aria-keyshortcuts="Control+Enter Meta+Enter"
              @click=${() => this.run()}
            >
              <typo3-backend-icon identifier=${this.running ? 'spinner-circle' : 'actions-play'} size="small"></typo3-backend-icon>
              ${labels.get(this.running ? 'playground.running' : 'playground.run')}
            </button>
            <span class="text-variant">
              <kbd>${Hotkeys.normalizedCtrlModifierKey === 'meta' ? '⌘' : labels.get('keys.ctrl')}</kbd> + <kbd>${labels.get('keys.enter')}</kbd>
            </span>
          </div>` : nothing}
        <div class="card-body webcon-jev-playground-result" aria-live="polite" aria-busy=${this.running ? 'true' : 'false'}>
          ${this.renderOutcome()}
        </div>
      </section>
    `;
  }

  renderEmpty() {
    return html`
      <div class="webcon-jev-empty">
        <typo3-backend-icon identifier="tx_webconjev_question" size="large"></typo3-backend-icon>
        <p>${labels.get('playground.empty')}</p>
      </div>
    `;
  }

  renderSample(mode, paths) {
    if (mode === 'static') {
      return html`<p class="text-variant">${labels.get('playground.static')}</p>`;
    }

    return html`
      <p class="text-variant">${labels.get(mode === 'template' ? 'playground.sample.template' : 'playground.sample.free')}</p>
      ${repeat(paths, (path) => path, (path, index) => this.renderSampleField(mode, path, index))}
      ${mode === 'free' ? html`
        <button type="button" class="btn btn-default btn-sm" id="webcon-jev-sample-add" @click=${() => this.addFreeField()}>
          <typo3-backend-icon identifier="actions-plus" size="small"></typo3-backend-icon>
          ${labels.get('playground.addField')}
        </button>` : nothing}
    `;
  }

  renderSampleField(mode, path, index) {
    const id = `webcon-jev-sample-${index}`;

    if (mode === 'template') {
      return html`
        <div class="form-group">
          <label class="form-label" for=${id}><code>{{${path}}}</code></label>
          <textarea
            class="form-control"
            id=${id}
            rows="3"
            .value=${live(this.samples[path] ?? '')}
            @input=${(event) => this.setSample(path, event.target.value)}
          ></textarea>
        </div>
      `;
    }

    const name = path.replace(/^field\./, '');
    const invalid = name !== '' && !FIELD_NAME.test(name);

    return html`
      <div class="webcon-jev-sample">
        <div class="form-group ${invalid ? 'has-error' : ''}">
          <label class="form-label" for="${id}-name">${labels.get('playground.fieldName')}</label>
          <div class="input-group">
            <span class="input-group-text webcon-jev-mono">field.</span>
            <input
              type="text"
              class="form-control webcon-jev-mono"
              id="${id}-name"
              autocomplete="off"
              spellcheck="false"
              aria-invalid=${invalid ? 'true' : 'false'}
              .value=${live(name)}
              @change=${(event) => this.renameFreeField(index, event.target.value)}
            />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for=${id}>${labels.get('playground.fieldValue', [path])}</label>
          <textarea
            class="form-control"
            id=${id}
            rows="3"
            .value=${live(this.samples[path] ?? '')}
            @input=${(event) => this.setSample(path, event.target.value)}
          ></textarea>
        </div>
        ${this.freeFields.length > 1 ? html`
          <button type="button" class="btn btn-default btn-sm" @click=${() => this.removeFreeField(index)}>
            <typo3-backend-icon identifier="actions-delete" size="small"></typo3-backend-icon>
            ${labels.get('playground.removeField', [path])}
          </button>` : nothing}
      </div>
    `;
  }

  renderOutcome() {
    const outcome = this.outcome;
    if (outcome === null) {
      return html`<p class="text-variant">${labels.get('playground.idle')}</p>`;
    }
    if (outcome.kind === 'invalid') {
      return this.callout('danger', outcome.message, labels.get('playground.invalid.title'));
    }
    if (outcome.kind === 'error') {
      return this.callout('danger', outcome.message, labels.get('playground.error.title'));
    }

    const data = outcome.data;
    const result = data.result ?? {};
    const answers = Object.values(result.answers ?? {});
    const threshold = Number(data.threshold ?? 0.6);

    return html`
      <h3 class="h5">${labels.get('playground.result')}</h3>
      <p class="webcon-jev-meta">
        ${milliseconds(result.durationMs)}
        · ${labels.get('playground.tokens', [number(result.usage?.inputTokens ?? 0)])}
        · ${usd(result.usage?.costUsd ?? 0)}
        ${result.model ? html`· <span class="webcon-jev-mono">${result.model}</span>` : nothing}
      </p>
      ${result.isFallback
        ? this.callout('danger', result.fallbackReason ?? '', labels.get('playground.fallback.title'))
        : nothing}
      ${answers.map((answer) => this.renderAnswer(answer, threshold))}
      ${this.renderRouting(data)}
      <details class="webcon-jev-details">
        <summary>${labels.get('playground.state')}</summary>
        <pre class="webcon-jev-pre">${typeof data.state === 'string' ? data.state : JSON.stringify(data.state ?? null, null, 2)}</pre>
      </details>
    `;
  }

  renderAnswer(answer, threshold) {
    const weak = answer.value === null || answer.value === undefined || Number(answer.confidence) < threshold;
    const rows = distribution(
      answer,
      (level) => labels.get('playground.level', [level]),
      { yes: labels.get('playground.yes'), no: labels.get('playground.no') },
    );

    return html`
      <div class="webcon-jev-answer ${weak ? 'is-weak' : ''}">
        <div class="webcon-jev-answer-head">
          <span class="webcon-jev-mono fw-bold">${answer.name}</span>
          <span class="badge badge-default">${labels.get(`type.${answer.type}`)}</span>
          <span class="badge ${weak ? 'badge-warning' : 'badge-success'}">
            ${answerValue(answer)} · ${labels.get('playground.confidence', [percent(answer.confidence)])}
          </span>
        </div>
        ${weak ? html`<p class="form-text">${labels.get('playground.weak', [percent(threshold)])}</p>` : nothing}
        ${rows.length > 0 ? html`
          <table class="webcon-jev-distribution">
            <caption class="visually-hidden">${labels.get('playground.distribution', [answer.name])}</caption>
            <tbody>
              ${rows.map((row) => html`
                <tr>
                  <th scope="row">${row.label}</th>
                  <td class="webcon-jev-distribution-bar">
                    <span class="webcon-jev-bar"><span style=${styleMap({ inlineSize: `${Math.max(0, Math.min(1, row.value)) * 100}%` })}></span></span>
                  </td>
                  <td class="text-end">${percent(row.value)}</td>
                </tr>
              `)}
            </tbody>
          </table>` : nothing}
      </div>
    `;
  }

  renderRouting(data) {
    const routes = Object.entries(data.outcomes ?? {});
    if (routes.length === 0) {
      return nothing;
    }

    return html`
      <div class="webcon-jev-routing">
        <h3 class="h5">
          ${labels.get('playground.routing')}
          ${data.needsHumanReview ? html`<span class="badge badge-warning">${labels.get('playground.review')}</span>` : nothing}
        </h3>
        <dl class="webcon-jev-facts">
          ${routes.map(([question, route]) => html`
            <dt class="webcon-jev-mono">${question}</dt>
            <dd>
              <span class="webcon-jev-mono">${route.outcome}</span>
              ${route.isDefault ? html`<span class="badge badge-default">${labels.get('playground.default')}</span>` : nothing}
            </dd>
          `)}
        </dl>
      </div>
    `;
  }

  callout(severity, message, title = '') {
    return html`
      <div class="callout callout-${severity}">
        <div class="callout-content">
          ${title !== '' ? html`<div class="callout-title">${title}</div>` : nothing}
          <div class="callout-body">${message}</div>
        </div>
      </div>
    `;
  }

  async run() {
    if (this.running || !this.draft || this.draft.questions.length === 0) {
      return;
    }

    this.running = true;
    let response;
    try {
      response = await postJson(this.config.urls.playground, {
        draft: toPayload(this.draft),
        context: this.context(),
      });
    } catch (error) {
      response = { status: 0, data: { ok: false, message: String(error?.message ?? error) } };
    } finally {
      this.running = false;
    }

    const { status, data } = response;
    if (Array.isArray(data.errors)) {
      this.outcome = { kind: 'invalid', message: data.message ?? '' };
      this.dispatchEvent(new CustomEvent('webcon-jev-invalid', { detail: data.errors, bubbles: true }));

      return;
    }
    if (data.result === undefined) {
      this.outcome = { kind: 'error', message: data.message ?? labels.get('error.status', [status]) };

      return;
    }

    this.outcome = { kind: 'answer', data };
  }

  /**
   * The sample as the context an integration would pass: dotted paths become nested objects.
   */
  context() {
    const context = {};
    for (const path of this.paths().paths) {
      const value = this.samples[path] ?? '';
      if (value.trim() === '') {
        continue;
      }
      const segments = path.split('.');
      let target = context;
      for (const segment of segments.slice(0, -1)) {
        target[segment] = typeof target[segment] === 'object' && target[segment] !== null ? target[segment] : {};
        target = target[segment];
      }
      target[segments.at(-1)] = value;
    }

    return context;
  }

  setSample(path, value) {
    this.samples = { ...this.samples, [path]: value };
    this.storeSamples();
  }

  addFreeField() {
    let index = this.freeFields.length + 1;
    while (this.freeFields.includes(`field.field_${index}`)) {
      index++;
    }
    this.freeFields = [...this.freeFields, `field.field_${index}`];
    this.storeSamples();
    this.updateComplete.then(() => this.querySelector(`#webcon-jev-sample-${this.freeFields.length - 1}-name`)?.focus());
  }

  renameFreeField(index, name) {
    const trimmed = name.trim();
    if (!FIELD_NAME.test(trimmed)) {
      this.requestUpdate();

      return;
    }
    const oldPath = this.freeFields[index];
    const newPath = `field.${trimmed}`;
    if (newPath === oldPath || this.freeFields.includes(newPath)) {
      return;
    }
    const samples = { ...this.samples, [newPath]: this.samples[oldPath] ?? '' };
    delete samples[oldPath];
    this.samples = samples;
    this.freeFields = this.freeFields.map((path, position) => (position === index ? newPath : path));
    this.storeSamples();
  }

  removeFreeField(index) {
    this.freeFields = this.freeFields.filter((path, position) => position !== index);
    this.storeSamples();
    this.updateComplete.then(() => this.querySelector('#webcon-jev-sample-add')?.focus());
  }

  /**
   * Samples survive a reload — including the one after a new decision's first save.
   */
  restoreSamples() {
    this.storageKey = STORAGE_PREFIX + (this.draft?.uid > 0 ? this.draft.uid : 'new');
    try {
      const stored = JSON.parse(window.sessionStorage.getItem(this.storageKey) ?? 'null');
      if (stored && typeof stored === 'object') {
        this.samples = typeof stored.samples === 'object' && stored.samples !== null ? stored.samples : {};
        this.freeFields = Array.isArray(stored.freeFields) && stored.freeFields.length > 0 ? stored.freeFields : [DEFAULT_FIELD];
      }
    } catch {
      // Storage unavailable or corrupt: start with an empty sample.
    }
  }

  storeSamples() {
    try {
      window.sessionStorage.setItem(this.storageKey, JSON.stringify({ samples: this.samples, freeFields: this.freeFields }));
    } catch {
      // Private mode or a full quota: the sample just does not survive a reload.
    }
  }

  /**
   * A new decision gets its uid on the first save; carry its sample over to the new key.
   */
  adoptUid(uid) {
    const previous = this.storageKey;
    this.storageKey = STORAGE_PREFIX + uid;
    this.storeSamples();
    if (previous !== this.storageKey) {
      try {
        window.sessionStorage.removeItem(previous);
      } catch {
        // Nothing to clean up.
      }
    }
  }
}

customElements.define('webcon-jev-playground', Playground);
