import { useEffect, useState } from 'react';
import { ui } from '@webconsulting/shadcn-ui/runtime.js';
import type { Api } from '@/api';
import type { Connection, Totals } from '@/types';
import { money, ms } from '@/format';

const {
  Alert, AlertDescription, AlertTitle, Badge, Button, Card, CardContent,
  CardDescription, CardHeader, CardTitle, Separator,
} = ui;

/**
 * Whether this installation can reach Jev, and what it has spent.
 */
export function ConnectionPanel({ api, initial }: { api: Api; initial: Connection }) {
  const [connection, setConnection] = useState<Connection>(initial);
  const [today, setToday] = useState<Totals | null>(null);
  const [month, setMonth] = useState<Totals | null>(null);
  const [pinging, setPinging] = useState(false);
  const [ping, setPing] = useState<{ ok: boolean; error?: string } | null>(null);

  useEffect(() => {
    void (async () => {
      const status = await api.status();
      setConnection(status.connection);
      setToday(status.today);
      setMonth(status.month);
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const runPing = async () => {
    setPinging(true);
    try {
      setPing(await api.ping());
    } catch (error) {
      setPing({ ok: false, error: error instanceof Error ? error.message : String(error) });
    } finally {
      setPinging(false);
    }
  };

  return (
    <div className="jev-stack">
      <Card>
        <CardHeader>
          <CardTitle>Connection</CardTitle>
          <CardDescription>
            The token is read from nr-vault, falling back to the TYPESAFE_API_KEY environment
            variable. It never leaves the server — only the decision does.
          </CardDescription>
        </CardHeader>
        <CardContent className="jev-stack">
          <Row label="Endpoint" value={connection.endpoint} mono />
          <Row label="Model" value={connection.model} mono />
          <Row label="Token" value={connection.tokenSource} />
          <Row label="Decisions enabled" value={connection.enabled ? 'yes' : 'no — everything falls back'} />
          {connection.maxCallsPerMinute !== undefined ? (
            <Row
              label="Budget guard"
              value={
                connection.maxCallsPerMinute === 0
                  ? 'off'
                  : `${connection.maxCallsPerMinute} calls a minute`
              }
            />
          ) : null}

          {connection.hasToken ? null : (
            <Alert variant="destructive">
              <AlertTitle>No API token</AlertTitle>
              <AlertDescription>
                Set TYPESAFE_API_KEY in .ddev/config.local.yaml, restart ddev, then run{' '}
                <code className="jev-mono">vendor/bin/typo3 webcon-jev:token:import</code>.
              </AlertDescription>
            </Alert>
          )}

          <Separator />
          <div className="jev-row jev-row-end">
            <Button variant="outline" onClick={runPing} disabled={pinging || !connection.hasToken}>
              {pinging ? 'Asking…' : 'Send a test question'}
            </Button>
          </div>
          {ping === null ? null : ping.ok ? (
            <Alert>
              <AlertTitle>Jev answered</AlertTitle>
              <AlertDescription>The token, the endpoint and the network all work.</AlertDescription>
            </Alert>
          ) : (
            <Alert variant="destructive">
              <AlertTitle>No answer</AlertTitle>
              <AlertDescription>{ping.error}</AlertDescription>
            </Alert>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>What it has cost</CardTitle>
          <CardDescription>
            Jev bills input tokens only, at $0.042 per million. Output is free.
          </CardDescription>
        </CardHeader>
        <CardContent className="jev-stack">
          <TotalsRow label="Last 24 hours" totals={today} />
          <Separator />
          <TotalsRow label="Last 30 days" totals={month} />
        </CardContent>
      </Card>
    </div>
  );
}

function Row({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
  return (
    <div className="jev-row">
      <span className="jev-small jev-muted" style={{ minWidth: '9rem' }}>
        {label}
      </span>
      <span className={mono ? 'jev-mono jev-grow' : 'jev-grow'}>{value}</span>
    </div>
  );
}

function TotalsRow({ label, totals }: { label: string; totals: Totals | null }) {
  if (totals === null) {
    return <p className="jev-small jev-muted">{label}: loading…</p>;
  }

  return (
    <div className="jev-stack" style={{ gap: '0.35rem' }}>
      <strong className="jev-small">{label}</strong>
      <div className="jev-row">
        <Badge variant="secondary">{totals.runs} runs</Badge>
        <Badge variant="outline">{totals.calls} reached the API</Badge>
        {totals.fallbacks > 0 ? <Badge variant="destructive">{totals.fallbacks} fell back</Badge> : null}
        <Badge variant="outline">{totals.inputTokens.toLocaleString()} tokens</Badge>
        <Badge>{money(totals.costUsd)}</Badge>
        <span className="jev-small jev-muted">average {ms(totals.avgDurationMs)}</span>
      </div>
    </div>
  );
}
