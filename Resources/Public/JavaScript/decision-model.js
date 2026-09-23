/**
 * webcon_jev: the decision the editor works on, as plain data.
 *
 * Every question and option carries a key that survives reordering and saving, so the form can
 * move and re-render rows without losing which input had the focus. The key never leaves the
 * browser: toPayload() strips it.
 */
export const QUESTION_TYPES = ['choice', 'score', 'noul'];

let counter = 0;
const nextKey = (prefix) => `${prefix}${Date.now().toString(36)}${(++counter).toString(36)}`;

export function emptyDecision(defaults) {
  return withKeys({
    uid: 0,
    title: '',
    identifier: '',
    description: '',
    stateTemplate: '',
    model: '',
    confidenceThreshold: defaults?.confidenceThreshold ?? 0.6,
    cacheLifetime: -1,
    defaultOutcome: '',
    hidden: false,
    questions: [],
  });
}

/**
 * The decision as the server sent it, with the keys the form needs.
 */
export function withKeys(decision) {
  return {
    ...decision,
    confidenceThreshold: String(decision.confidenceThreshold ?? ''),
    cacheLifetime: String(decision.cacheLifetime ?? ''),
    questions: (decision.questions ?? []).map((question) => ({
      ...question,
      key: nextKey('q'),
      criteria: (question.criteria ?? []).map((criterion) => ({ ...criterion, key: nextKey('c') })),
    })),
  };
}

export function newQuestion() {
  return { uid: 0, key: nextKey('q'), name: '', type: 'choice', instructions: '', hidden: false, criteria: [] };
}

export function newCriterion(identifier = '') {
  return { uid: 0, key: nextKey('c'), identifier, description: '', outcomeValue: '', hidden: false };
}

/**
 * What the save and the playground endpoints receive.
 */
export function toPayload(draft) {
  return {
    uid: draft.uid,
    title: draft.title,
    identifier: draft.identifier,
    description: draft.description,
    stateTemplate: draft.stateTemplate,
    model: draft.model,
    confidenceThreshold: String(draft.confidenceThreshold),
    cacheLifetime: String(draft.cacheLifetime),
    defaultOutcome: draft.defaultOutcome,
    hidden: Boolean(draft.hidden),
    questions: draft.questions.map((question) => ({
      uid: question.uid,
      name: question.name,
      type: question.type,
      instructions: question.instructions,
      criteria: question.criteria.map((criterion) => ({
        uid: criterion.uid,
        identifier: criterion.identifier,
        description: criterion.description,
        outcomeValue: criterion.outcomeValue,
      })),
    })),
  };
}

/**
 * A comparable fingerprint, for "are there unsaved changes".
 */
export function fingerprint(draft) {
  return JSON.stringify(toPayload(draft));
}

/**
 * Take over what the server saved — new uids, a normalised identifier — while keeping the keys
 * the form rows are rendered under. Rows are matched by position, which the save preserves.
 *
 * @returns {object|null} null when the shapes disagree and the caller should reload instead
 */
export function applySaved(draft, saved) {
  const questions = saved.questions ?? [];
  if (questions.length !== draft.questions.length) {
    return null;
  }

  const merged = { ...withKeys({ ...saved, questions: [] }), questions: [] };
  for (const [index, question] of draft.questions.entries()) {
    const savedQuestion = questions[index];
    const criteria = savedQuestion.criteria ?? [];
    if (criteria.length !== question.criteria.length) {
      return null;
    }
    merged.questions.push({
      ...savedQuestion,
      key: question.key,
      criteria: criteria.map((criterion, criterionIndex) => ({ ...criterion, key: question.criteria[criterionIndex].key })),
    });
  }

  return merged;
}
