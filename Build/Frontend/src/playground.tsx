import { useState } from 'react';
import { ui } from '@webconsulting/shadcn-ui/runtime.js';
import type { Api } from '@/api';
import type { Decision, PlaygroundResponse } from '@/types';
import { AnswerCard } from '@/bars';
import { money, ms } from '@/format';

const {
  Alert, AlertDescription, AlertTitle, Badge, Button, Card, CardContent, CardDescription,
  CardHeader, CardTitle, Label, Textarea,
} = ui;

/**
 * Try a decision against a state you typed. Caching is bypassed server-side, so editing the
 * wording and running again actually tells you whether the wording mattered.
 */
export function Playground({ api, decision }: { api: Api; decision: Decision }) {
  const [text, setText] = useState('');
  const [running, setRunning] = useState(false);
  const [response, setResponse] = useState<PlaygroundResponse | null>(null);

  const run = async () => {
    setRunning(true);
    try {
      setResponse(await api.play(decision.uid, { field: { message: text } }));
    } catch (error) {
      setResponse({ ok: false, error: error instanceof Error ? error.message : String(error) });
    } finally {
      setRunning(false);
    }
  };

  const result = response?.result;

  return (
    <div className="jev-stack">
      <Card>
        <CardHeader>
          <CardTitle>Try it</CardTitle>
          <CardDescription>
            What you type arrives as <code className="jev-mono">field.message</code>. With no state
            template, that is the whole state; with one, only what the template reads is sent.
          </CardDescription>
        </CardHeader>
        <CardContent className="jev-stack">
          <Textarea
            rows={6}
            value={text}
            placeholder="Our invoice 4711 was charged twice and nobody has answered my mail for a week."
            onChange={(e) => setText(e.target.value)}
          />
          <div className="jev-row jev-row-end">
            <Button onClick={run} disabled={running || decision.uid === 0 || text.trim() === ''}>
              {running ? 'Asking Jev…' : 'Run'}
            </Button>
          </div>
          {decision.uid === 0 ? (
            <p className="jev-small jev-muted">Save the decision first — the playground runs the stored one.</p>
          ) : null}
        </CardContent>
      </Card>

      {response === null ? null : (
        <Card>
          <CardHeader>
            <CardTitle>Result</CardTitle>
            {result ? (
              <CardDescription>
                {ms(result.durationMs)} · {result.usage.inputTokens} input tokens ·{' '}
                {money(result.usage.costUsd)} · model {result.model || '—'}
              </CardDescription>
            ) : null}
          </CardHeader>
          <CardContent className="jev-stack">
            {response.error || result?.isFallback ? (
              <Alert variant="destructive">
                <AlertTitle>No answer — the default would be used</AlertTitle>
                <AlertDescription>{response.error ?? result?.fallbackReason}</AlertDescription>
              </Alert>
            ) : null}

            {result && Object.values(result.answers).length > 0 ? (
              <>
                {Object.values(result.answers).map((answer) => (
                  <AnswerCard key={answer.name} answer={answer} threshold={decision.confidenceThreshold} />
                ))}
                {response.outcomes && Object.keys(response.outcomes).length > 0 ? (
                  <div className="jev-panel">
                    <div className="jev-panel-head">
                      <strong>Where this would go</strong>
                      {response.needsHumanReview ? <Badge variant="destructive">needs review</Badge> : null}
                    </div>
                    {Object.entries(response.outcomes).map(([question, outcome]) => (
                      <div className="jev-row" key={question}>
                        <span className="jev-mono jev-small">{question}</span>
                        <span className="jev-muted">→</span>
                        <span className="jev-mono jev-small">{outcome}</span>
                      </div>
                    ))}
                  </div>
                ) : null}
              </>
            ) : null}

            <div>
              <Label>State as sent</Label>
              <pre className="jev-pre">{JSON.stringify(response.state ?? null, null, 2)}</pre>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
