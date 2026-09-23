/**
 * webcon_jev: numbers the way the backend user reads them, and the shapes Jev answers in.
 *
 * The backend sets <html lang> to the user's interface language, so Intl formats "0,42" for a
 * German user and "0.42" for an English one without being told.
 */
const locale = document.documentElement.lang || undefined;

export function number(value, maximumFractionDigits = 0) {
  return new Intl.NumberFormat(locale, { maximumFractionDigits }).format(Number(value) || 0);
}

export function percent(value) {
  return new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 0 }).format(Number(value) || 0);
}

/**
 * Jev bills fractions of a cent, so a cost keeps the digits that make it non-zero.
 */
export function usd(value) {
  const amount = Number(value) || 0;

  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: amount > 0 && amount < 0.01 ? 6 : 4,
  }).format(amount);
}

export function milliseconds(value) {
  return Number(value) >= 1 ? `${number(value)} ms` : '—';
}

/**
 * The answer itself, as text: the chosen option, or the number on the scale.
 */
export function answerValue(answer) {
  if (answer.value === null || answer.value === undefined) {
    return '—';
  }

  return typeof answer.value === 'number' ? number(answer.value, 2) : String(answer.value);
}

/**
 * The distribution an answer was read off, as rows — whichever shape it came in: a map for a
 * choice, a list for a score, and for a noul the one probability and its complement.
 *
 * @param {object} answer
 * @param {(index: number) => string} levelLabel For a score whose legend is missing
 * @param {{yes: string, no: string}} noulLabels
 * @returns {Array<{label: string, value: number}>}
 */
export function distribution(answer, levelLabel, noulLabels) {
  const probabilities = answer.probabilities;
  if (Array.isArray(probabilities)) {
    return probabilities.map((value, index) => ({
      label: answer.legend?.[index] ?? levelLabel(index + 1),
      value: Number(value) || 0,
    }));
  }
  if (probabilities && typeof probabilities === 'object') {
    return Object.entries(probabilities).map(([label, value]) => ({ label, value: Number(value) || 0 }));
  }
  if (answer.type === 'noul' && typeof answer.value === 'number') {
    return [
      { label: noulLabels.yes, value: answer.value },
      { label: noulLabels.no, value: 1 - answer.value },
    ];
  }

  return [];
}

/**
 * The {{paths}} a state template reads, in order of first appearance.
 *
 * @param {string} template
 * @returns {string[]}
 */
export function placeholders(template) {
  const found = new Set();
  for (const match of String(template ?? '').matchAll(/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/g)) {
    found.add(match[1]);
  }

  return [...found];
}
