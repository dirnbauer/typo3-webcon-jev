import type { Connection, Decision, PlaygroundResponse, RunRow, Totals } from '@/types';

type AjaxUrls = Record<string, string>;

function url(urls: AjaxUrls, name: string): string {
  const found = urls[name];
  if (typeof found !== 'string' || found === '') {
    throw new Error(`The AJAX route "${name}" is not registered. Flush the backend caches.`);
  }

  return found;
}

async function json<T>(response: Response): Promise<T> {
  if (!response.ok && response.status !== 422 && response.status !== 400 && response.status !== 404) {
    throw new Error(`The server answered ${response.status}.`);
  }

  return (await response.json()) as T;
}

export function createApi(urls: AjaxUrls) {
  const post = async <T>(route: string, body: unknown): Promise<T> =>
    json<T>(
      await fetch(url(urls, route), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      }),
    );

  return {
    async status(): Promise<{ connection: Connection; today: Totals; month: Totals }> {
      return json(await fetch(url(urls, 'webcon_jev_status')));
    },
    async ping(): Promise<{ ok: boolean; error?: string; result?: unknown }> {
      return post('webcon_jev_ping', {});
    },
    async decisions(): Promise<{ decisions: Decision[] }> {
      return json(await fetch(url(urls, 'webcon_jev_decisions')));
    },
    async save(decision: Decision): Promise<{ ok: boolean; decision?: Decision; errors?: unknown[]; error?: string }> {
      return post('webcon_jev_decision_save', { decision });
    },
    async remove(uid: number): Promise<{ ok: boolean; error?: string }> {
      return post('webcon_jev_decision_delete', { uid });
    },
    async play(decision: number, context: unknown): Promise<PlaygroundResponse> {
      return post('webcon_jev_playground', { decision, context });
    },
    async runs(decision = 0): Promise<{ runs: RunRow[] }> {
      const base = url(urls, 'webcon_jev_runs');
      const separator = base.includes('?') ? '&' : '?';

      return json(await fetch(`${base}${separator}limit=60&decision=${decision}`));
    },
  };
}

export type Api = ReturnType<typeof createApi>;
