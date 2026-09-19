import { useEffect, useState } from 'react';
import { ui } from '@webconsulting/shadcn-ui/runtime.js';
import type { Api } from '@/api';
import type { RunRow } from '@/types';
import { answerValue, money, ms, when } from '@/format';

const {
  Badge, Button, Card, CardContent, CardDescription, CardHeader, CardTitle,
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} = ui;

const CONTEXT_LABEL: Record<string, string> = {
  powermail_cond: 'condition',
  powermail_finisher: 'routing',
  playground: 'playground',
  cli: 'CLI',
};

/**
 * Every call, with what it cost and whether it actually reached Jev.
 *
 * A form that quietly fell back to its default receiver for a week looks exactly like a form that
 * worked — until you read this.
 */
export function RunLog({ api, decisionUid }: { api: Api; decisionUid: number }) {
  const [rows, setRows] = useState<RunRow[]>([]);
  const [loading, setLoading] = useState(true);

  const load = async () => {
    setLoading(true);
    try {
      const response = await api.runs(decisionUid);
      setRows(response.runs);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [decisionUid]);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Runs</CardTitle>
        <CardDescription>
          The most recent calls for this decision. &ldquo;cached&rdquo; never reached the API and
          cost nothing; &ldquo;fallback&rdquo; means the default outcome was used.
        </CardDescription>
      </CardHeader>
      <CardContent className="jev-stack">
        <div className="jev-row jev-row-end">
          <Button variant="outline" size="sm" onClick={() => void load()} disabled={loading}>
            {loading ? 'Loading…' : 'Refresh'}
          </Button>
        </div>
        {rows.length === 0 ? (
          <p className="jev-empty">
            {loading ? 'Loading…' : 'Nothing yet. Run the decision in the playground or submit a form.'}
          </p>
        ) : (
          <div className="jev-scroll">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>When</TableHead>
                  <TableHead>Where from</TableHead>
                  <TableHead>Answers</TableHead>
                  <TableHead className="jev-nowrap">Latency</TableHead>
                  <TableHead className="jev-nowrap">Cost</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((row) => (
                  <TableRow key={row.uid}>
                    <TableCell className="jev-nowrap jev-small">{when(row.crdate)}</TableCell>
                    <TableCell className="jev-small">
                      <div className="jev-row">
                        <Badge variant="secondary">{CONTEXT_LABEL[row.context] ?? row.context}</Badge>
                        {row.fromCache ? <Badge variant="outline">cached</Badge> : null}
                        {row.isFallback ? <Badge variant="destructive">fallback</Badge> : null}
                      </div>
                      <span className="jev-muted">{row.origin}</span>
                    </TableCell>
                    <TableCell className="jev-small">
                      {row.isFallback ? (
                        <span className="jev-muted">{row.fallbackReason || '—'}</span>
                      ) : (
                        Object.values(row.answers).map((answer) => (
                          <div className="jev-mono" key={answer.name}>
                            {answer.name}={answerValue(answer)}{' '}
                            <span className="jev-muted">{(answer.confidence * 100).toFixed(0)}%</span>
                          </div>
                        ))
                      )}
                    </TableCell>
                    <TableCell className="jev-nowrap jev-small">{ms(row.durationMs)}</TableCell>
                    <TableCell className="jev-nowrap jev-small">{money(row.costUsd)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
