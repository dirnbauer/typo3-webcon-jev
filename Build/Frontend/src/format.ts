import type { Answer } from '@/types';

export function money(usd: number): string {
  if (usd === 0) {
    return '$0';
  }

  return usd < 0.01 ? `$${usd.toFixed(6)}` : `$${usd.toFixed(4)}`;
}

export function ms(value: number): string {
  return value >= 1 ? `${Math.round(value)} ms` : '—';
}

export function when(timestamp: number): string {
  return new Date(timestamp * 1000).toLocaleString();
}

export function answerValue(answer: Answer): string {
  if (answer.value === null) {
    return '—';
  }

  return typeof answer.value === 'number' ? answer.value.toFixed(2) : answer.value;
}

/**
 * Both shapes of `probabilities` — the map a choice returns and the list a score does — as rows.
 */
export function distribution(answer: Answer): Array<{ label: string; value: number }> {
  const probabilities = answer.probabilities;
  if (Array.isArray(probabilities)) {
    return probabilities.map((value, index) => ({
      label: answer.legend?.[index] ?? `Level ${index}`,
      value,
    }));
  }
  if (probabilities && typeof probabilities === 'object') {
    return Object.entries(probabilities).map(([label, value]) => ({ label, value }));
  }
  if (answer.type === 'noul' && typeof answer.value === 'number') {
    return [
      { label: 'yes', value: answer.value },
      { label: 'no', value: 1 - answer.value },
    ];
  }

  return [];
}

export function emptyDecisionDraft(defaultThreshold: number) {
  return {
    uid: 0,
    identifier: '',
    title: '',
    description: '',
    stateTemplate: '',
    model: '',
    confidenceThreshold: defaultThreshold,
    cacheLifetime: -1,
    defaultOutcome: '',
    languageId: 0,
    questions: [],
  };
}
