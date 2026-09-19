import { ui } from '@webconsulting/shadcn-ui/runtime.js';
import type { Criterion, Decision, Question, QuestionType } from '@/types';

const {
  Badge, Button, Card, CardContent, CardDescription, CardHeader, CardTitle,
  Input, Label, Select, SelectContent, SelectItem, SelectTrigger, SelectValue, Separator, Textarea,
} = ui;

const TYPE_HELP: Record<QuestionType, string> = {
  choice: 'Pick one of several options. Give every option an id and a sentence saying what it means.',
  score: 'Place it on an ordered scale. List the levels from lowest to highest; the answer may land between two.',
  noul: 'How likely a yes/no statement is true, as a probability. Options are optional — use ids "yes" and "no".',
};

const OUTCOME_HELP: Record<QuestionType, string> = {
  choice: 'What happens when this option wins — for powermail routing, the address that gets the mail.',
  score: 'Not used for a score: the position decides, not an outcome value.',
  noul: 'What happens on this side of the answer.',
};

interface EditorProps {
  draft: Decision;
  dirty: boolean;
  saving: boolean;
  onChange: (next: Decision) => void;
  onSave: () => void;
  onRevert: () => void;
  onDelete: () => void;
}

export function DecisionEditor({ draft, dirty, saving, onChange, onSave, onRevert, onDelete }: EditorProps) {
  const set = <K extends keyof Decision>(key: K, value: Decision[K]) => onChange({ ...draft, [key]: value });

  const setQuestion = (index: number, next: Question) => {
    const questions = [...draft.questions];
    questions[index] = next;
    set('questions', questions);
  };

  const addQuestion = () =>
    set('questions', [
      ...draft.questions,
      { uid: 0, name: '', type: 'choice', instructions: '', criteria: [] },
    ]);

  return (
    <div className="jev-stack">
      <Card>
        <CardHeader>
          <CardTitle>{draft.uid > 0 ? draft.title || 'Untitled decision' : 'New decision'}</CardTitle>
          <CardDescription>
            A decision is a set of typed questions about one kind of state, plus what to do when the
            answer is not certain enough to act on.
          </CardDescription>
        </CardHeader>
        <CardContent className="jev-stack">
          <div className="jev-fields">
            <Field label="Title" hint="What an editor calls this.">
              <Input value={draft.title} onChange={(e) => set('title', e.target.value)} />
            </Field>
            <Field label="Identifier" hint="How code refers to it. Leave empty to derive it from the title.">
              <Input
                value={draft.identifier}
                placeholder="contact_routing"
                onChange={(e) => set('identifier', e.target.value)}
              />
            </Field>
          </div>
          <Field label="What this decides" hint="For whoever reads the run log in six months.">
            <Textarea rows={2} value={draft.description} onChange={(e) => set('description', e.target.value)} />
          </Field>
          <Field
            label="State template"
            hint="What Jev gets to read. Use {{field.marker}} for a form value. Leave empty to send every filled field as JSON."
          >
            <Textarea
              rows={4}
              className="jev-mono"
              placeholder="Subject: {{field.subject}}&#10;Message: {{field.message}}"
              value={draft.stateTemplate}
              onChange={(e) => set('stateTemplate', e.target.value)}
            />
          </Field>
          <div className="jev-fields">
            <Field label="Confidence threshold" hint="Below this, the default outcome is used. 0.5 is a coin toss.">
              <Input
                type="number"
                min={0}
                max={1}
                step={0.05}
                value={draft.confidenceThreshold}
                onChange={(e) => set('confidenceThreshold', Number(e.target.value))}
              />
            </Field>
            <Field label="Default outcome" hint="Used when Jev cannot answer or is not certain enough.">
              <Input
                value={draft.defaultOutcome}
                placeholder="office@example.com"
                onChange={(e) => set('defaultOutcome', e.target.value)}
              />
            </Field>
            <Field label="Cache lifetime" hint="Seconds. -1 follows the extension configuration, 0 never caches.">
              <Input
                type="number"
                min={-1}
                value={draft.cacheLifetime}
                onChange={(e) => set('cacheLifetime', Number(e.target.value))}
              />
            </Field>
            <Field label="Model" hint="Empty uses the configured model.">
              <Input value={draft.model} placeholder="jev-latest" onChange={(e) => set('model', e.target.value)} />
            </Field>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Questions</CardTitle>
          <CardDescription>
            All of them go over in one request and are answered in one pass, so asking four costs
            barely more than asking one.
          </CardDescription>
        </CardHeader>
        <CardContent className="jev-stack">
          {draft.questions.length === 0 ? (
            <p className="jev-empty">No questions yet. A decision without one never calls Jev.</p>
          ) : null}
          {draft.questions.map((question, index) => (
            <QuestionEditor
              key={question.uid > 0 ? `q${question.uid}` : `new${index}`}
              question={question}
              onChange={(next) => setQuestion(index, next)}
              onRemove={() => set('questions', draft.questions.filter((_, i) => i !== index))}
            />
          ))}
          <div>
            <Button variant="outline" size="sm" onClick={addQuestion}>
              Add question
            </Button>
          </div>
        </CardContent>
      </Card>

      <div className="jev-row jev-row-end">
        {draft.uid > 0 ? (
          <Button variant="ghost" onClick={onDelete}>
            Delete
          </Button>
        ) : null}
        <Button variant="outline" onClick={onRevert} disabled={!dirty || saving}>
          Revert
        </Button>
        <Button onClick={onSave} disabled={!dirty || saving}>
          {saving ? 'Saving…' : 'Save decision'}
        </Button>
      </div>
    </div>
  );
}

function QuestionEditor({
  question,
  onChange,
  onRemove,
}: {
  question: Question;
  onChange: (next: Question) => void;
  onRemove: () => void;
}) {
  const set = <K extends keyof Question>(key: K, value: Question[K]) => onChange({ ...question, [key]: value });

  const setCriterion = (index: number, next: Criterion) => {
    const criteria = [...question.criteria];
    criteria[index] = next;
    set('criteria', criteria);
  };

  return (
    <div className="jev-panel">
      <div className="jev-panel-head">
        <span className="jev-row">
          <strong className="jev-mono">{question.name || 'unnamed'}</strong>
          <Badge variant="secondary">{question.type}</Badge>
        </span>
        <Button variant="ghost" size="sm" onClick={onRemove}>
          Remove
        </Button>
      </div>

      <div className="jev-stack">
        <div className="jev-fields">
          <Field label="Name" hint="The key the answer comes back under. Conditions refer to it.">
            <Input value={question.name} placeholder="department" onChange={(e) => set('name', e.target.value)} />
          </Field>
          <Field label="Type" hint={TYPE_HELP[question.type]}>
            <Select value={question.type} onValueChange={(value: string) => set('type', value as QuestionType)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="choice">Choice</SelectItem>
                <SelectItem value="score">Score</SelectItem>
                <SelectItem value="noul">Noul</SelectItem>
              </SelectContent>
            </Select>
          </Field>
        </div>

        <Field
          label="Question"
          hint="Put it as you would to a colleague who can only see the state — no background, no example answers."
        >
          <Textarea
            rows={2}
            value={question.instructions}
            placeholder="Which department should answer this enquiry?"
            onChange={(e) => set('instructions', e.target.value)}
          />
        </Field>

        <Separator />

        <div className="jev-panel-head">
          <span className="jev-small jev-muted">
            {question.type === 'score' ? 'Levels, lowest first' : 'Options'}
          </span>
          <Button
            variant="outline"
            size="sm"
            onClick={() =>
              set('criteria', [...question.criteria, { uid: 0, identifier: '', description: '', outcomeValue: '' }])
            }
          >
            Add {question.type === 'score' ? 'level' : 'option'}
          </Button>
        </div>

        {question.criteria.map((criterion, index) => (
          <div className="jev-fields" key={criterion.uid > 0 ? `c${criterion.uid}` : `newc${index}`}>
            {question.type === 'score' ? null : (
              <Field label="Id" hint="How this option is named in the answer.">
                <Input
                  value={criterion.identifier}
                  placeholder={question.type === 'noul' ? 'yes' : 'sales'}
                  onChange={(e) => setCriterion(index, { ...criterion, identifier: e.target.value })}
                />
              </Field>
            )}
            <Field label="Meaning" hint="What the model reads to tell the options apart.">
              <Input
                value={criterion.description}
                onChange={(e) => setCriterion(index, { ...criterion, description: e.target.value })}
              />
            </Field>
            {question.type === 'score' ? null : (
              <Field label="Outcome" hint={OUTCOME_HELP[question.type]}>
                <Input
                  value={criterion.outcomeValue}
                  placeholder="sales@example.com"
                  onChange={(e) => setCriterion(index, { ...criterion, outcomeValue: e.target.value })}
                />
              </Field>
            )}
            <div style={{ display: 'flex', alignItems: 'flex-end' }}>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => set('criteria', question.criteria.filter((_, i) => i !== index))}
              >
                Remove
              </Button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function Field({ label, hint, children }: { label: string; hint?: string; children: React.ReactNode }) {
  return (
    <div className="jev-stack" style={{ gap: '0.3rem' }}>
      <Label>{label}</Label>
      {children}
      {hint ? <span className="jev-small jev-muted">{hint}</span> : null}
    </div>
  );
}
