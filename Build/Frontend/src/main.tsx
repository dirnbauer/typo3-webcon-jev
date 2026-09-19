import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { defineShadcnApp, ui, useTypo3, type AppProps } from '@webconsulting/shadcn-ui/runtime.js';
import { createApi } from '@/api';
import { adoptModuleStyles } from '@/styles';
import { DecisionEditor } from '@/editor';
import { Playground } from '@/playground';
import { RunLog } from '@/runlog';
import { ConnectionPanel } from '@/connection';
import { emptyDecisionDraft } from '@/format';
import type { Connection, Decision } from '@/types';

const { Badge, Button, Tabs, TabsContent, TabsList, TabsTrigger, Toaster, toast } = ui;

/**
 * `webcon_jev/decisions` — where the questions Jev answers are defined.
 *
 * Four things an editor needs and no more: what the decision asks, what it answers for a state
 * you paste in, what it has been answering in production, and whether the API is reachable at all.
 */
function JevApp({ props, shell }: AppProps) {
  const { ajaxUrls } = useTypo3();
  const api = useMemo(() => createApi(ajaxUrls as Record<string, string>), [ajaxUrls]);
  const anchor = useRef<HTMLDivElement | null>(null);

  const defaults = (props.defaults ?? {}) as { confidenceThreshold?: number };
  const connection = (props.connection ?? {}) as Connection;

  const [decisions, setDecisions] = useState<Decision[]>(() =>
    Array.isArray(props.decisions) ? (props.decisions as Decision[]) : [],
  );
  const [selected, setSelected] = useState<number>(() =>
    Array.isArray(props.decisions) && props.decisions.length > 0 ? (props.decisions[0] as Decision).uid : 0,
  );
  const [draft, setDraft] = useState<Decision | null>(null);
  const [saving, setSaving] = useState(false);
  const [tab, setTab] = useState('editor');

  useEffect(() => adoptModuleStyles(anchor.current), []);

  const stored = useMemo(
    () => decisions.find((decision) => decision.uid === selected) ?? null,
    [decisions, selected],
  );

  // The draft follows the selection until it is edited; after that it is the editor's own state.
  useEffect(() => {
    setDraft(stored === null ? null : structuredClone(stored));
  }, [stored]);

  useEffect(() => {
    shell.setContext({
      view: 'jev-decisions',
      tab,
      decision: draft?.identifier ?? null,
      questions: draft?.questions.map((question) => question.name) ?? [],
    });
  }, [shell, tab, draft]);

  const dirty = useMemo(
    () => draft !== null && JSON.stringify(draft) !== JSON.stringify(stored ?? emptyDecisionDraft(0.6)),
    [draft, stored],
  );

  const startNew = useCallback(() => {
    setSelected(0);
    setDraft(emptyDecisionDraft(defaults.confidenceThreshold ?? 0.6) as Decision);
    setTab('editor');
  }, [defaults.confidenceThreshold]);

  const reload = useCallback(async () => {
    const response = await api.decisions();
    setDecisions(response.decisions);

    return response.decisions;
  }, [api]);

  const save = useCallback(async () => {
    if (draft === null) {
      return;
    }
    setSaving(true);
    try {
      const response = await api.save(draft);
      if (!response.ok) {
        toast.error('The decision was not saved', {
          description: response.error ?? 'The DataHandler refused the record. Check the TYPO3 log.',
        });

        return;
      }
      const saved = response.decision;
      await reload();
      if (saved) {
        setSelected(saved.uid);
      }
      toast.success('Decision saved');
    } catch (error) {
      toast.error('The decision was not saved', {
        description: error instanceof Error ? error.message : String(error),
      });
    } finally {
      setSaving(false);
    }
  }, [api, draft, reload]);

  const remove = useCallback(async () => {
    if (draft === null || draft.uid === 0) {
      return;
    }
    const response = await api.remove(draft.uid);
    if (!response.ok) {
      toast.error('The decision was not deleted', { description: response.error });

      return;
    }
    const remaining = await reload();
    setSelected(remaining[0]?.uid ?? 0);
    toast.success('Decision deleted');
  }, [api, draft, reload]);

  return (
    <div ref={anchor} className="jev-layout">
      <Toaster />

      <nav className="jev-stack">
        <div className="jev-row" style={{ justifyContent: 'space-between' }}>
          <strong className="jev-small">Decisions</strong>
          <Badge variant="secondary">{decisions.length}</Badge>
        </div>
        <div className="jev-list">
          {decisions.map((decision) => (
            <button
              type="button"
              key={decision.uid}
              className="jev-list-item"
              aria-current={decision.uid === selected}
              onClick={() => setSelected(decision.uid)}
            >
              {decision.title || 'Untitled'}
              <small className="jev-mono">{decision.identifier || '—'}</small>
            </button>
          ))}
          {decisions.length === 0 ? (
            <p className="jev-small jev-muted">None yet.</p>
          ) : null}
        </div>
        <Button variant="outline" size="sm" onClick={startNew}>
          New decision
        </Button>
      </nav>

      <main>
        <Tabs value={tab} onValueChange={setTab}>
          <TabsList>
            <TabsTrigger value="editor">Editor</TabsTrigger>
            <TabsTrigger value="playground">Playground</TabsTrigger>
            <TabsTrigger value="runs">Runs</TabsTrigger>
            <TabsTrigger value="connection">Connection</TabsTrigger>
          </TabsList>

          <TabsContent value="editor">
            {draft === null ? (
              <p className="jev-empty">Pick a decision on the left, or make a new one.</p>
            ) : (
              <DecisionEditor
                draft={draft}
                dirty={dirty}
                saving={saving}
                onChange={setDraft}
                onSave={() => void save()}
                onRevert={() => setDraft(stored === null ? null : structuredClone(stored))}
                onDelete={() => void remove()}
              />
            )}
          </TabsContent>

          <TabsContent value="playground">
            {draft === null ? (
              <p className="jev-empty">Pick a decision to try it.</p>
            ) : (
              <Playground api={api} decision={draft} />
            )}
          </TabsContent>

          <TabsContent value="runs">
            <RunLog api={api} decisionUid={selected} />
          </TabsContent>

          <TabsContent value="connection">
            <ConnectionPanel api={api} initial={connection} />
          </TabsContent>
        </Tabs>
      </main>
    </div>
  );
}

defineShadcnApp('webcon_jev/decisions', JevApp);
