export type QuestionType = 'choice' | 'score' | 'noul';

export interface Criterion {
  uid: number;
  identifier: string;
  description: string;
  outcomeValue: string;
}

export interface Question {
  uid: number;
  name: string;
  type: QuestionType;
  instructions: string;
  criteria: Criterion[];
}

export interface Decision {
  uid: number;
  identifier: string;
  title: string;
  description: string;
  stateTemplate: string;
  model: string;
  confidenceThreshold: number;
  cacheLifetime: number;
  defaultOutcome: string;
  languageId: number;
  questions: Question[];
}

export interface Answer {
  name: string;
  type: QuestionType;
  value: string | number | null;
  confidence: number;
  probabilities?: Record<string, number> | number[];
  legend?: string[];
}

export interface RunResult {
  model: string;
  answers: Record<string, Answer>;
  usage: { inputTokens: number; outputTokens: number; costUsd: number };
  durationMs: number;
  fromCache: boolean;
  isFallback: boolean;
  fallbackReason: string | null;
}

export interface PlaygroundResponse {
  ok: boolean;
  error?: string;
  state?: unknown;
  result?: RunResult;
  summary?: string;
  needsHumanReview?: boolean;
  outcomes?: Record<string, string>;
}

export interface Connection {
  endpoint: string;
  model: string;
  enabled: boolean;
  tokenSource: string;
  hasToken: boolean;
  maxCallsPerMinute?: number;
  cacheLifetime?: number;
}

export interface Totals {
  runs: number;
  calls: number;
  fallbacks: number;
  inputTokens: number;
  costUsd: number;
  avgDurationMs: number;
}

export interface RunRow {
  uid: number;
  crdate: number;
  decision: number;
  decisionIdentifier: string;
  context: string;
  origin: string;
  model: string;
  durationMs: number;
  inputTokens: number;
  costUsd: number;
  fromCache: boolean;
  isFallback: boolean;
  fallbackReason: string;
  answers: Record<string, Answer>;
}
