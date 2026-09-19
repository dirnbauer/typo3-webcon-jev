import { ui } from '@webconsulting/shadcn-ui/runtime.js';
import type { Answer } from '@/types';
import { answerValue, distribution } from '@/format';

const { Badge } = ui;

/**
 * An answer with the distribution it was read off, because the number on its own hides whether
 * the model was torn between two options or sure of one.
 */
export function AnswerCard({ answer, threshold }: { answer: Answer; threshold: number }) {
  const weak = answer.confidence < threshold;
  const rows = distribution(answer);

  return (
    <div className="jev-panel">
      <div className="jev-panel-head">
        <strong className="jev-mono">{answer.name}</strong>
        <span className="jev-row">
          <Badge variant="secondary">{answer.type}</Badge>
          <Badge variant={weak ? 'destructive' : 'default'}>
            {answerValue(answer)} · {(answer.confidence * 100).toFixed(0)}%
          </Badge>
        </span>
      </div>
      {weak ? (
        <p className="jev-small jev-muted" style={{ margin: '0 0 0.5rem' }}>
          Below the decision&apos;s threshold of {(threshold * 100).toFixed(0)}% — the default outcome
          would be used and the submission flagged for a human.
        </p>
      ) : null}
      {rows.map((row) => (
        <div className="jev-dist" key={row.label}>
          <span className="jev-small jev-nowrap" title={row.label}>
            {row.label}
          </span>
          <span className="jev-bar" data-weak={weak ? 'true' : 'false'}>
            <span style={{ width: `${Math.max(0, Math.min(1, row.value)) * 100}%` }} />
          </span>
          <span className="jev-small jev-muted jev-nowrap">{(row.value * 100).toFixed(1)}%</span>
        </div>
      ))}
    </div>
  );
}
