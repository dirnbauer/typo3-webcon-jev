/**
 * webcon_jev: the decision editor.
 *
 * A Lit element that renders into the page itself (no shadow root), so the backend's own form,
 * card and panel styles apply and follow the light or dark scheme. It saves through the module's
 * AJAX endpoint and puts every problem the server names next to the input it is about.
 *
 * Keyboard: Ctrl/Cmd+S saves, Ctrl/Cmd+Enter runs the playground, and every control — moving a
 * question, removing an option — is an ordinary button in the tab order.
 */
import { LitElement, html, nothing } from 'lit';
import { ifDefined } from 'lit/directives/if-defined.js';
import { live } from 'lit/directives/live.js';
import { repeat } from 'lit/directives/repeat.js';
import '@typo3/backend/element/icon-element.js';
import ImmediateAction from '@typo3/backend/action-button/immediate-action.js';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';
import Hotkeys from '@typo3/backend/hotkeys.js';
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';
import labels from '~labels/webcon_jev.module';
import { postJson } from '@webconsulting/webcon-jev/api.js';
import { confirmAndDelete } from '@webconsulting/webcon-jev/delete-decision.js';
import {
  QUESTION_TYPES,
  applySaved,
  emptyDecision,
  fingerprint,
  newCriterion,
  newQuestion,
  toPayload,
  withKeys,
} from '@webconsulting/webcon-jev/decision-model.js';
import { placeholders } from '@webconsulting/webcon-jev/format.js';
import '@webconsulting/webcon-jev/playground.js';

const SCOPE = 'webcon-jev/decision-editor';

/** Label keys of the decision's own fields, by the name the draft and the error paths use. */
const DECISION_FIELDS = {
  title: 'field.title',
  identifier: 'field.identifier',
  description: 'field.description',
  stateTemplate: 'field.stateTemplate',
  confidenceThreshold: 'field.threshold',
  defaultOutcome: 'field.defaultOutcome',
  cacheLifetime: 'field.cacheLifetime',
  model: 'field.model',
};

const QUESTION_FIELDS = {
  name: 'field.question.name',
  type: 'field.question.type',
  instructions: 'field.question.instructions',
};

const CRITERION_FIELDS = {
  identifier: 'field.criterion.identifier',
  description: 'field.criterion.description',
  outcomeValue: 'field.criterion.outcome',
};

class DecisionEditor extends LitElement {
  static properties = {
    config: { type: Object },
    draft: { state: true },
    errors: { state: true },
    saving: { state: true },
    revision: { state: true },
    announcement: { state: true },
  };

  constructor() {
    super();
    this.config = {};
    this.draft = null;
    this.errors = new Map();
    this.saving = false;
    this.revision = 0;
    this.announcement = '';
    this.savedFingerprint = '';
    this.leaving = false;
    this.onBeforeUnload = this.onBeforeUnload.bind(this);
    this.onDocumentClick = this.onDocumentClick.bind(this);
  }

  createRenderRoot() {
    // The server rendered a "loading" note in here for the moment before this module ran.
    this.replaceChildren();

    return this;
  }

  connectedCallback() {
    super.connectedCallback();
    window.addEventListener('beforeunload', this.onBeforeUnload);
    document.addEventListener('click', this.onDocumentClick);
    Hotkeys.setScope(SCOPE);
    Hotkeys.register(
      [Hotkeys.normalizedCtrlModifierKey, 's'],
      (event) => {
        event.preventDefault();
        this.save();
      },
      { scope: SCOPE, allowOnEditables: true, bindElement: this.docHeaderSaveButton() ?? undefined },
    );
  }

  disconnectedCallback() {
    window.removeEventListener('beforeunload', this.onBeforeUnload);
    document.removeEventListener('click', this.onDocumentClick);
    super.disconnectedCallback();
  }

  willUpdate(changed) {
    if (changed.has('config') && this.draft === null) {
      this.draft = this.config.decision ? withKeys(this.config.decision) : emptyDecision(this.config.defaults);
      this.savedFingerprint = fingerprint(this.draft);
    }
  }

  firstUpdated() {
    if (window.location.hash === '#webcon-jev-playground') {
      const playground = this.querySelector('#webcon-jev-playground');
      playground?.scrollIntoView({ block: 'start' });
      playground?.querySelector('textarea, button')?.focus();
    } else if (this.draft.uid === 0) {
      this.querySelector('#webcon-jev-title')?.focus();
    }
  }

  render() {
    if (this.draft === null) {
      return nothing;
    }

    const draft = this.draft;
    const dirty = this.isDirty();
    const heading = draft.uid === 0
      ? labels.get('editor.title.new')
      : (draft.title || draft.identifier || labels.get('editor.untitled'));

    return html`
      <div class="webcon-jev-editor-head">
        <h1>${heading}</h1>
        ${dirty ? html`<span class="badge badge-warning">${labels.get('editor.unsaved')}</span>` : nothing}
        ${draft.hidden ? html`<span class="badge badge-default">${labels.get('decisions.disabled')}</span>` : nothing}
      </div>
      <p class="webcon-jev-lead">${labels.get('editor.intro')}</p>
      <div class="visually-hidden" role="status" aria-live="polite">${this.announcement}</div>

      <div class="webcon-jev-editor">
        <form
          id=${this.config.formId}
          class="webcon-jev-editor-form"
          novalidate
          autocomplete="off"
          @submit=${(event) => this.onSubmit(event)}
        >
          ${this.renderErrorSummary()}
          ${this.renderDecisionCard()}
          ${this.renderStateCard()}
          ${this.renderBehaviourCard()}
          ${this.renderQuestionsCard()}
          <p class="text-variant webcon-jev-shortcut">
            <kbd>${this.modifierLabel()}</kbd> + <kbd>S</kbd> ${labels.get('editor.shortcut.save')}
          </p>
        </form>
        <aside class="webcon-jev-editor-aside" aria-label=${labels.get('playground.title')}>
          <webcon-jev-playground
            .draft=${draft}
            .revision=${this.revision}
            .config=${this.config}
            @webcon-jev-invalid=${(event) => this.onPlaygroundInvalid(event)}
          ></webcon-jev-playground>
        </aside>
      </div>
    `;
  }

  // Sections -------------------------------------------------------------------------------------

  renderErrorSummary() {
    if (this.errors.size === 0) {
      return nothing;
    }

    return html`
      <div class="callout callout-danger" id="webcon-jev-error-summary" tabindex="-1">
        <div class="callout-content">
          <div class="callout-title">${labels.get('editor.errors.title', { count: this.errors.size })}</div>
          <div class="callout-body">
            <ul class="webcon-jev-error-list">
              ${[...this.errors].map(([path, message]) => html`
                <li>
                  <a href="#${this.idForPath(path)}" @click=${(event) => this.focusPath(event, path)}>
                    ${this.describePath(path)}: ${message}
                  </a>
                </li>
              `)}
            </ul>
          </div>
        </div>
      </div>
    `;
  }

  renderDecisionCard() {
    const draft = this.draft;

    return this.card('decision', labels.get('editor.section.decision'), labels.get('editor.section.decision.description'), html`
      <div class="form-row">
        ${this.group('title', labels.get('field.title'), labels.get('field.title.help'), this.input('title', draft.title, { required: true }), 'webcon-jev-grow')}
        ${this.group('identifier', labels.get('field.identifier'), labels.get('field.identifier.help'), this.input('identifier', draft.identifier, { mono: true, placeholder: 'contact_routing' }))}
      </div>
      ${this.group('description', labels.get('field.description'), labels.get('field.description.help'), this.textarea('description', draft.description, { rows: 2 }))}
      <div class="form-group">
        <div class="form-check form-switch">
          <input
            type="checkbox"
            class="form-check-input"
            role="switch"
            id="webcon-jev-active"
            aria-describedby="webcon-jev-active-help"
            .checked=${live(!draft.hidden)}
            @change=${(event) => {
              this.draft.hidden = !event.target.checked;
              this.touch();
            }}
          />
          <label class="form-check-label" for="webcon-jev-active">${labels.get('field.active')}</label>
        </div>
        <div class="form-text" id="webcon-jev-active-help">${labels.get('field.active.help')}</div>
      </div>
    `);
  }

  renderStateCard() {
    const template = this.draft.stateTemplate;
    const reads = placeholders(template);

    return this.card('state', labels.get('editor.section.state'), labels.get('editor.section.state.description'), html`
      ${this.group('stateTemplate', labels.get('field.stateTemplate'), labels.get('field.stateTemplate.help'), this.textarea('stateTemplate', template, {
        rows: 5,
        mono: true,
        placeholder: 'Subject: {{field.subject}}\nMessage: {{field.message}}',
      }))}
      <div class="form-text">
        ${template.trim() === ''
          ? labels.get('editor.state.empty')
          : reads.length === 0
            ? labels.get('editor.state.static')
            : html`${labels.get('editor.state.reads')}
              <ul class="webcon-jev-chips">${reads.map((path) => html`<li><code>${path}</code></li>`)}</ul>`}
      </div>
    `);
  }

  renderBehaviourCard() {
    const draft = this.draft;
    const defaults = this.config.defaults ?? {};

    return this.card('behaviour', labels.get('editor.section.behaviour'), labels.get('editor.section.behaviour.description'), html`
      <div class="form-row">
        ${this.group('confidenceThreshold', labels.get('field.threshold'), labels.get('field.threshold.help'), this.input('confidenceThreshold', draft.confidenceThreshold, {
          type: 'number', min: '0', max: '1', step: '0.05', inputmode: 'decimal',
        }), 'webcon-jev-narrow')}
        ${this.group('defaultOutcome', labels.get('field.defaultOutcome'), labels.get('field.defaultOutcome.help'), this.input('defaultOutcome', draft.defaultOutcome, {
          mono: true, placeholder: 'office@example.com',
        }), 'webcon-jev-grow')}
      </div>
      <div class="form-row">
        ${this.group('cacheLifetime', labels.get('field.cacheLifetime'), labels.get('field.cacheLifetime.help', [defaults.cacheLifetime ?? 0]), this.input('cacheLifetime', draft.cacheLifetime, {
          type: 'number', min: '-1', step: '1', inputmode: 'numeric',
        }), 'webcon-jev-narrow')}
        ${this.group('model', labels.get('field.model'), labels.get('field.model.help', [defaults.model ?? '']), this.input('model', draft.model, {
          mono: true, placeholder: defaults.model ?? '',
        }), 'webcon-jev-grow')}
      </div>
    `);
  }

  renderQuestionsCard() {
    const questions = this.draft.questions;
    const listError = this.errors.get('questions');

    return this.card('questions', labels.get('editor.section.questions'), labels.get('editor.section.questions.description'), html`
      ${listError ? html`<p class="form-text webcon-jev-error" id="webcon-jev-questions" tabindex="-1">${listError}</p>` : nothing}
      ${questions.length === 0
        ? html`
          <div class="webcon-jev-empty">
            <typo3-backend-icon identifier="tx_webconjev_question" size="large"></typo3-backend-icon>
            <p>${labels.get('editor.questions.empty')}</p>
          </div>`
        : repeat(questions, (question) => question.key, (question, index) => this.renderQuestion(question, index))}
    `, html`
      <button type="button" class="btn btn-default" id="webcon-jev-add-question" @click=${() => this.addQuestion()}>
        <typo3-backend-icon identifier="actions-plus" size="small"></typo3-backend-icon>
        ${labels.get('editor.addQuestion')}
      </button>
    `);
  }

  renderQuestion(question, index) {
    const path = `questions.${index}`;
    const number = index + 1;
    const count = this.draft.questions.length;
    const problems = [...this.errors.keys()].filter((key) => key === path || key.startsWith(`${path}.`)).length;
    const headingId = `webcon-jev-q-${question.key}-heading`;

    return html`
      <section class="panel panel-default webcon-jev-question" id="webcon-jev-q-${question.key}" aria-labelledby=${headingId}>
        <div class="panel-heading">
          <div class="panel-heading-row">
            <h3 class="panel-title webcon-jev-question-title" id=${headingId}>
              ${labels.get('editor.question.number', [number])}
              ${question.name !== '' ? html`<span class="webcon-jev-mono">${question.name}</span>` : nothing}
              <span class="badge badge-default">${labels.get(`type.${question.type}`)}</span>
              ${question.hidden ? html`<span class="badge badge-warning" title=${labels.get('editor.hidden.description')}>${labels.get('decisions.disabled')}</span>` : nothing}
              ${problems > 0 ? html`<span class="badge badge-danger">${labels.get('editor.problems', { count: problems })}</span>` : nothing}
            </h3>
            <div class="panel-actions">
              <div class="btn-group" role="group" aria-label=${labels.get('editor.question.actions', [number])}>
                ${this.iconButton('actions-chevron-up', labels.get('editor.question.moveUp', [number]), index === 0, () => this.moveQuestion(index, -1, 'up'), `webcon-jev-q-${question.key}-up`)}
                ${this.iconButton('actions-chevron-down', labels.get('editor.question.moveDown', [number]), index === count - 1, () => this.moveQuestion(index, 1, 'down'), `webcon-jev-q-${question.key}-down`)}
                ${this.iconButton('actions-delete', labels.get('editor.question.remove', [number]), false, () => this.removeQuestion(index))}
              </div>
            </div>
          </div>
        </div>
        <div class="panel-body">
          <div class="form-row">
            ${this.group(`${path}.name`, labels.get('field.question.name'), labels.get('field.question.name.help'), this.input(`${path}.name`, question.name, {
              mono: true, required: true, placeholder: 'department',
            }))}
            ${this.group(`${path}.type`, labels.get('field.question.type'), labels.get(`type.${question.type}.help`), html`
              <select
                class="form-select"
                id=${this.idForPath(`${path}.type`)}
                aria-describedby=${this.describedBy(`${path}.type`, true)}
                aria-invalid=${this.errors.has(`${path}.type`) ? 'true' : 'false'}
                .value=${live(question.type)}
                @change=${(event) => this.set(`${path}.type`, event.target.value)}
              >
                ${QUESTION_TYPES.map((type) => html`<option value=${type} ?selected=${type === question.type}>${labels.get(`type.${type}.option`)}</option>`)}
              </select>
            `, 'webcon-jev-grow')}
          </div>
          ${this.group(`${path}.instructions`, labels.get('field.question.instructions'), labels.get('field.question.instructions.help'), this.textarea(`${path}.instructions`, question.instructions, {
            rows: 2, required: true, placeholder: labels.get('field.question.instructions.placeholder'),
          }))}
          ${this.renderCriteria(question, index)}
        </div>
      </section>
    `;
  }

  renderCriteria(question, questionIndex) {
    const path = `questions.${questionIndex}.criteria`;
    const type = question.type;
    const listError = this.errors.get(path);
    const fieldsetId = this.idForPath(path);

    return html`
      <fieldset
        class="webcon-jev-criteria ${listError ? 'has-error' : ''}"
        id=${fieldsetId}
        tabindex="-1"
        aria-describedby="${fieldsetId}-help${listError ? ` ${fieldsetId}-error` : ''}"
      >
        <legend class="form-label">${labels.get(`editor.criteria.${type}`)}</legend>
        <p class="form-text" id="${fieldsetId}-help">${labels.get(`editor.criteria.${type}.help`)}</p>
        ${question.criteria.length === 0
          ? html`<p class="text-variant">${labels.get(`editor.criteria.${type}.empty`)}</p>`
          : html`
            <ol class="webcon-jev-criteria-list">
              ${repeat(question.criteria, (criterion) => criterion.key, (criterion, index) => this.renderCriterion(question, questionIndex, criterion, index))}
            </ol>`}
        ${listError ? html`<p class="form-text webcon-jev-error" id="${fieldsetId}-error">${listError}</p>` : nothing}
        <button type="button" class="btn btn-default btn-sm" id="webcon-jev-q-${question.key}-add" @click=${() => this.addCriterion(questionIndex)}>
          <typo3-backend-icon identifier="actions-plus" size="small"></typo3-backend-icon>
          ${labels.get(type === 'score' ? 'editor.addLevel' : 'editor.addOption')}
        </button>
      </fieldset>
    `;
  }

  renderCriterion(question, questionIndex, criterion, index) {
    const path = `questions.${questionIndex}.criteria.${index}`;
    const type = question.type;
    const count = question.criteria.length;
    const name = labels.get(type === 'score' ? 'editor.level.number' : 'editor.option.number', [index + 1]);
    const levelLabel = type !== 'score'
      ? labels.get('field.criterion.description')
      : index === 0
        ? labels.get('field.criterion.levelLowest', [index + 1])
        : index === count - 1
          ? labels.get('field.criterion.levelHighest', [index + 1])
          : labels.get('field.criterion.level', [index + 1]);

    return html`
      <li class="webcon-jev-criterion">
        <div class="form-row">
          ${type !== 'score' ? this.group(`${path}.identifier`, labels.get('field.criterion.identifier'), '', this.input(`${path}.identifier`, criterion.identifier, {
            mono: true, required: true, help: false, placeholder: type === 'noul' ? 'yes' : 'sales', label: `${name}: ${labels.get('field.criterion.identifier')}`,
          }), 'webcon-jev-col-id') : nothing}
          ${this.group(`${path}.description`, levelLabel, '', this.input(`${path}.description`, criterion.description, {
            required: true, help: false, label: `${name}: ${levelLabel}`,
          }), 'webcon-jev-col-meaning')}
          ${type === 'choice' ? this.group(`${path}.outcomeValue`, labels.get('field.criterion.outcome'), '', this.input(`${path}.outcomeValue`, criterion.outcomeValue, {
            mono: true, help: false, placeholder: 'sales@example.com', label: `${name}: ${labels.get('field.criterion.outcome')}`,
          }), 'webcon-jev-col-outcome') : nothing}
          <div class="form-group webcon-jev-col-actions">
            <div class="btn-group" role="group" aria-label=${labels.get('editor.criterion.actions', [name])}>
              ${this.iconButton('actions-chevron-up', labels.get('editor.criterion.moveUp', [name]), index === 0, () => this.moveCriterion(questionIndex, index, -1, 'up'), `webcon-jev-c-${criterion.key}-up`, true)}
              ${this.iconButton('actions-chevron-down', labels.get('editor.criterion.moveDown', [name]), index === count - 1, () => this.moveCriterion(questionIndex, index, 1, 'down'), `webcon-jev-c-${criterion.key}-down`, true)}
              ${this.iconButton('actions-delete', labels.get('editor.criterion.remove', [name]), false, () => this.removeCriterion(questionIndex, index), undefined, true)}
            </div>
          </div>
        </div>
      </li>
    `;
  }

  // Building blocks ------------------------------------------------------------------------------

  card(id, title, description, body, footer = nothing) {
    return html`
      <section class="card webcon-jev-card" aria-labelledby="webcon-jev-card-${id}">
        <div class="card-header">
          <div class="card-header-body">
            <h2 class="card-title" id="webcon-jev-card-${id}">${title}</h2>
            ${description ? html`<span class="card-subtitle">${description}</span>` : nothing}
          </div>
        </div>
        <div class="card-body">${body}</div>
        ${footer !== nothing ? html`<div class="card-footer">${footer}</div>` : nothing}
      </section>
    `;
  }

  /**
   * A labelled field with its help text and, when the server refused it, the reason.
   */
  group(path, label, help, control, className = '') {
    const id = this.idForPath(path);
    const error = this.errors.get(path);

    return html`
      <div class="form-group ${className} ${error ? 'has-error' : ''}">
        <label class="form-label" for=${id}>${label}</label>
        ${control}
        ${help ? html`<div class="form-text" id="${id}-help">${help}</div>` : nothing}
        ${error ? html`<div class="form-text webcon-jev-error" id="${id}-error">${error}</div>` : nothing}
      </div>
    `;
  }

  input(path, value, options = {}) {
    const id = this.idForPath(path);
    const error = this.errors.has(path);

    return html`
      <input
        type=${options.type ?? 'text'}
        class="form-control ${options.mono ? 'webcon-jev-mono' : ''}"
        id=${id}
        min=${ifDefined(options.min)}
        max=${ifDefined(options.max)}
        step=${ifDefined(options.step)}
        inputmode=${ifDefined(options.inputmode)}
        placeholder=${ifDefined(options.placeholder || undefined)}
        spellcheck=${options.mono ? 'false' : 'true'}
        aria-required=${options.required ? 'true' : 'false'}
        aria-invalid=${error ? 'true' : 'false'}
        aria-describedby=${ifDefined(this.describedBy(path, options.help !== false) || undefined)}
        aria-label=${ifDefined(options.label)}
        .value=${live(String(value ?? ''))}
        @input=${(event) => this.set(path, event.target.value)}
      />
    `;
  }

  textarea(path, value, options = {}) {
    const id = this.idForPath(path);
    const error = this.errors.has(path);

    return html`
      <textarea
        class="form-control ${options.mono ? 'webcon-jev-mono' : ''}"
        id=${id}
        rows=${options.rows ?? 3}
        placeholder=${ifDefined(options.placeholder || undefined)}
        spellcheck=${options.mono ? 'false' : 'true'}
        aria-required=${options.required ? 'true' : 'false'}
        aria-invalid=${error ? 'true' : 'false'}
        aria-describedby=${ifDefined(this.describedBy(path, true) || undefined)}
        .value=${live(String(value ?? ''))}
        @input=${(event) => this.set(path, event.target.value)}
      ></textarea>
    `;
  }

  iconButton(icon, label, disabled, action, id = undefined, small = false) {
    return html`
      <button
        type="button"
        class="btn btn-default ${small ? 'btn-sm' : ''}"
        id=${ifDefined(id)}
        title=${label}
        aria-label=${label}
        ?disabled=${disabled}
        @click=${action}
      >
        <typo3-backend-icon identifier=${icon} size="small"></typo3-backend-icon>
      </button>
    `;
  }

  /**
   * The ids a control is described by: its help text if it has one, its error if it has one.
   */
  describedBy(path, hasHelp) {
    const id = this.idForPath(path);

    return [hasHelp ? `${id}-help` : '', this.errors.has(path) ? `${id}-error` : ''].filter(Boolean).join(' ');
  }

  // Paths ----------------------------------------------------------------------------------------

  /**
   * The element an error path points at. Paths count questions and options by position, the
   * way the server names them; ids use the keys, which survive reordering.
   */
  idForPath(path) {
    const [root, questionIndex, field, criterionIndex, criterionField] = path.split('.');
    if (root !== 'questions') {
      return `webcon-jev-${root}`;
    }
    const question = this.draft.questions[Number(questionIndex)];
    if (questionIndex === undefined || question === undefined) {
      return 'webcon-jev-questions';
    }
    if (field === undefined) {
      return `webcon-jev-q-${question.key}`;
    }
    if (field !== 'criteria') {
      return `webcon-jev-q-${question.key}-${field}`;
    }
    const criterion = question.criteria[Number(criterionIndex)];
    if (criterionIndex === undefined || criterion === undefined) {
      return `webcon-jev-q-${question.key}-criteria`;
    }

    return `webcon-jev-c-${criterion.key}-${criterionField}`;
  }

  describePath(path) {
    const [root, questionIndex, field, criterionIndex, criterionField] = path.split('.');
    if (root !== 'questions') {
      return labels.get(DECISION_FIELDS[root] ?? 'editor.section.decision');
    }
    if (questionIndex === undefined) {
      return labels.get('editor.section.questions');
    }
    const question = labels.get('editor.question.number', [Number(questionIndex) + 1]);
    if (field === undefined) {
      return question;
    }
    if (field !== 'criteria') {
      return `${question} › ${labels.get(QUESTION_FIELDS[field] ?? 'editor.section.questions')}`;
    }
    const type = this.draft.questions[Number(questionIndex)]?.type ?? 'choice';
    if (criterionIndex === undefined) {
      return `${question} › ${labels.get(`editor.criteria.${type}`)}`;
    }
    const criterion = labels.get(type === 'score' ? 'editor.level.number' : 'editor.option.number', [Number(criterionIndex) + 1]);

    return `${question} › ${criterion} › ${labels.get(CRITERION_FIELDS[criterionField] ?? 'field.criterion.description')}`;
  }

  focusPath(event, path) {
    event.preventDefault();
    const element = this.querySelector(`#${CSS.escape(this.idForPath(path))}`);
    element?.scrollIntoView({ block: 'center' });
    element?.focus();
  }

  // Editing --------------------------------------------------------------------------------------

  set(path, value) {
    const segments = path.split('.');
    let target = this.draft;
    for (const segment of segments.slice(0, -1)) {
      target = target[segment];
    }
    target[segments.at(-1)] = value;

    if (this.errors.has(path)) {
      const errors = new Map(this.errors);
      errors.delete(path);
      this.errors = errors;
    }
    this.touch();
  }

  touch() {
    this.revision++;
    this.requestUpdate();
  }

  /**
   * Adding, removing or moving rows changes what every position-based error path points at, so
   * the messages from before no longer belong where they would land.
   */
  restructured(message) {
    this.errors = new Map();
    this.touch();
    if (message) {
      this.announce(message);
    }
  }

  addQuestion() {
    const question = newQuestion();
    this.draft.questions.push(question);
    this.restructured(labels.get('editor.question.added', [this.draft.questions.length]));
    this.focusAfterRender(`#webcon-jev-q-${question.key}-name`);
  }

  removeQuestion(index) {
    const [removed] = this.draft.questions.splice(index, 1);
    const next = this.draft.questions[index] ?? this.draft.questions[index - 1];
    this.restructured(labels.get('editor.question.removed', [index + 1]));
    this.focusAfterRender(next ? `#webcon-jev-q-${next.key}-name` : '#webcon-jev-add-question');

    Notification.info(labels.get('editor.question.removed', [index + 1]), labels.get('editor.question.removedHint'), 8, [{
      label: labels.get('editor.undo'),
      action: new ImmediateAction(() => {
        this.draft.questions.splice(Math.min(index, this.draft.questions.length), 0, removed);
        this.restructured(labels.get('editor.question.restored', [index + 1]));
        this.focusAfterRender(`#webcon-jev-q-${removed.key}-name`);
      }),
    }]);
  }

  moveQuestion(index, offset, direction) {
    const target = index + offset;
    const questions = this.draft.questions;
    if (target < 0 || target >= questions.length) {
      return;
    }
    const [question] = questions.splice(index, 1);
    questions.splice(target, 0, question);
    this.restructured(labels.get('editor.question.moved', [index + 1, target + 1]));
    this.keepFocusOnMove(`webcon-jev-q-${question.key}`, direction);
  }

  addCriterion(questionIndex) {
    const question = this.draft.questions[questionIndex];
    // A noul describes its two sides; offer the ids it expects.
    const suggested = question.type === 'noul'
      ? ['yes', 'no'].find((id) => !question.criteria.some((criterion) => criterion.identifier === id)) ?? ''
      : '';
    const criterion = newCriterion(suggested);
    question.criteria.push(criterion);
    this.restructured(labels.get('editor.criterion.added'));
    this.focusAfterRender(question.type === 'score' || suggested !== ''
      ? `#webcon-jev-c-${criterion.key}-description`
      : `#webcon-jev-c-${criterion.key}-identifier`);
  }

  removeCriterion(questionIndex, index) {
    const question = this.draft.questions[questionIndex];
    question.criteria.splice(index, 1);
    const next = question.criteria[index] ?? question.criteria[index - 1];
    this.restructured(labels.get('editor.criterion.removed'));
    this.focusAfterRender(next
      ? `#webcon-jev-c-${next.key}-${question.type === 'score' ? 'description' : 'identifier'}`
      : `#webcon-jev-q-${question.key}-add`);
  }

  moveCriterion(questionIndex, index, offset, direction) {
    const criteria = this.draft.questions[questionIndex].criteria;
    const target = index + offset;
    if (target < 0 || target >= criteria.length) {
      return;
    }
    const [criterion] = criteria.splice(index, 1);
    criteria.splice(target, 0, criterion);
    this.restructured(labels.get('editor.criterion.moved', [index + 1, target + 1]));
    this.keepFocusOnMove(`webcon-jev-c-${criterion.key}`, direction);
  }

  /**
   * Keep the focus on the button that moved the row; at the end of the list, where that button is
   * now disabled, on its sibling instead.
   */
  keepFocusOnMove(prefix, direction) {
    this.updateComplete.then(() => {
      const same = this.querySelector(`#${CSS.escape(`${prefix}-${direction}`)}`);
      const other = this.querySelector(`#${CSS.escape(`${prefix}-${direction === 'up' ? 'down' : 'up'}`)}`);
      (same && !same.disabled ? same : other)?.focus();
    });
  }

  focusAfterRender(selector) {
    this.updateComplete.then(() => this.querySelector(selector)?.focus());
  }

  announce(message) {
    // Clearing first makes a repeated message ("option removed" twice) be read again.
    this.announcement = '';
    this.updateComplete.then(() => {
      this.announcement = message;
    });
  }

  onPlaygroundInvalid(event) {
    this.errors = new Map(event.detail.map((error) => [error.path, error.message]));
  }

  // Saving, deleting, leaving --------------------------------------------------------------------

  isDirty() {
    return this.draft !== null && fingerprint(this.draft) !== this.savedFingerprint;
  }

  onSubmit(event) {
    event.preventDefault();
    this.save();
  }

  async save() {
    if (this.saving || this.draft === null) {
      return;
    }

    this.saving = true;
    let response;
    try {
      response = await postJson(this.config.urls.save, { decision: toPayload(this.draft) });
    } catch (error) {
      response = { status: 0, data: { ok: false, message: String(error?.message ?? error) } };
    } finally {
      this.saving = false;
    }

    const { status, data } = response;
    if (data.ok && data.decision) {
      this.saved(data);

      return;
    }

    if (Array.isArray(data.errors)) {
      this.errors = new Map(data.errors.map((error) => [error.path, error.message]));
      Notification.error(labels.get('editor.saveFailed.title'), data.message ?? '');
      await this.updateComplete;
      this.querySelector('#webcon-jev-error-summary')?.focus();

      return;
    }

    const details = Array.isArray(data.details) ? data.details.join(' ') : '';
    Notification.error(
      labels.get('editor.saveFailed.title'),
      [data.message ?? labels.get('error.status', [status]), details].filter(Boolean).join(' '),
    );
  }

  saved(data) {
    this.errors = new Map();
    Notification.success(labels.get('editor.saved.title'), data.message ?? '');

    if (this.draft.uid === 0) {
      // A new decision gains its delete button and its record-editor link with a fresh page.
      this.querySelector('webcon-jev-playground')?.adoptUid(data.decision.uid);
      this.leaving = true;
      window.location.replace(data.urls.edit);

      return;
    }

    const merged = applySaved(this.draft, data.decision);
    if (merged === null) {
      this.leaving = true;
      window.location.reload();

      return;
    }

    this.draft = merged;
    this.savedFingerprint = fingerprint(merged);
    this.touch();
    this.announce(data.message ?? labels.get('editor.saved.title'));
  }

  async delete() {
    if (this.draft === null || this.draft.uid === 0) {
      return;
    }

    const gone = await confirmAndDelete({
      url: this.config.urls.delete,
      uid: this.draft.uid,
      title: this.draft.title || this.draft.identifier,
      forms: this.config.usage?.forms ?? 0,
      rules: this.config.usage?.rules ?? 0,
    });
    if (gone) {
      this.leaving = true;
      window.location.assign(this.config.urls.list);
    }
  }

  onBeforeUnload(event) {
    if (!this.leaving && this.isDirty()) {
      event.preventDefault();
      event.returnValue = '';
    }
  }

  /**
   * The DocHeader's delete button, and every link that would leave unsaved changes behind.
   */
  onDocumentClick(event) {
    const target = event.target instanceof Element ? event.target : null;
    if (target === null) {
      return;
    }

    if (target.closest('[data-webcon-jev-action="delete"]') !== null) {
      event.preventDefault();
      this.delete();

      return;
    }

    const link = target.closest('a[href]');
    if (
      link === null
      || event.defaultPrevented
      || event.button !== 0
      || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
      || link.target === '_blank'
      || link.getAttribute('href').startsWith('#')
      || !this.isDirty()
    ) {
      return;
    }

    event.preventDefault();
    Modal.confirm(labels.get('editor.leave.title'), labels.get('editor.leave.message'), SeverityEnum.warning, [
      {
        text: labels.get('editor.leave.stay'),
        active: true,
        btnClass: 'btn-default',
        name: 'stay',
        trigger: (clickEvent, modal) => modal.hideModal(),
      },
      {
        text: labels.get('editor.leave.discard'),
        btnClass: 'btn-warning',
        name: 'discard',
        trigger: (clickEvent, modal) => {
          modal.hideModal();
          this.leaving = true;
          window.location.assign(link.href);
        },
      },
    ]);
  }

  docHeaderSaveButton() {
    return document.querySelector(`button[form="${CSS.escape(this.config.formId ?? '')}"]`);
  }

  modifierLabel() {
    return Hotkeys.normalizedCtrlModifierKey === 'meta' ? '⌘' : labels.get('keys.ctrl');
  }
}

customElements.define('webcon-jev-decision-editor', DecisionEditor);
