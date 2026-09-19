/**
 * The module's own layout CSS, adopted into the shell's shadow root.
 *
 * The shell's stylesheet is Tailwind built with `source(none)` over shadcn_ui's own files, so a
 * utility class used only here would not exist in it. Rather than depend on which classes another
 * extension happens to have compiled, this ships the handful of layout rules the module needs and
 * builds them from the theme's own custom properties, so light and dark still follow the backend.
 */
const CSS = `
.jev-layout { display: grid; grid-template-columns: minmax(200px, 260px) 1fr; gap: 1rem; align-items: start; }
@media (max-width: 860px) { .jev-layout { grid-template-columns: 1fr; } }

.jev-stack { display: flex; flex-direction: column; gap: 0.75rem; }
.jev-row { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.jev-row-end { justify-content: flex-end; }
.jev-grow { flex: 1 1 auto; min-width: 0; }
.jev-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0.75rem; }

.jev-list { display: flex; flex-direction: column; gap: 0.25rem; }
.jev-list-item {
  display: block; width: 100%; text-align: left; cursor: pointer;
  padding: 0.5rem 0.625rem; border: 1px solid transparent; border-radius: var(--radius-md, 0.5rem);
  background: transparent; color: inherit; font: inherit; line-height: 1.35;
}
.jev-list-item:hover { background: var(--muted); }
.jev-list-item[aria-current='true'] { background: var(--accent); border-color: var(--border-strong); }
.jev-list-item small { display: block; color: var(--muted-foreground); font-size: 0.75rem; }

.jev-muted { color: var(--muted-foreground); }
.jev-small { font-size: 0.8125rem; }
.jev-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.8125rem; }

.jev-panel {
  border: 1px solid var(--border); border-radius: var(--radius-md, 0.5rem);
  padding: 0.75rem; background: var(--card);
}
.jev-panel + .jev-panel { margin-top: 0.75rem; }
.jev-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.5rem; }

.jev-bar { height: 0.5rem; border-radius: 999px; background: var(--muted); overflow: hidden; }
.jev-bar > span { display: block; height: 100%; background: var(--primary); }
.jev-bar[data-weak='true'] > span { background: var(--destructive); }

.jev-dist { display: grid; grid-template-columns: minmax(80px, 160px) 1fr 3.5rem; gap: 0.5rem; align-items: center; }
.jev-dist + .jev-dist { margin-top: 0.3rem; }

.jev-scroll { overflow-x: auto; }
.jev-nowrap { white-space: nowrap; }
.jev-pre {
  margin: 0; padding: 0.625rem; border-radius: var(--radius-md, 0.5rem);
  background: var(--muted); color: var(--foreground);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.75rem;
  white-space: pre-wrap; word-break: break-word; max-height: 16rem; overflow: auto;
}
.jev-empty { padding: 2rem 1rem; text-align: center; color: var(--muted-foreground); }
`;

let sheet: CSSStyleSheet | null = null;

/**
 * Add the module's sheet to whichever shadow root it ended up in, once per root.
 */
export function adoptModuleStyles(node: Node | null): void {
  const root = node?.getRootNode();
  if (!(root instanceof ShadowRoot) || typeof CSSStyleSheet === 'undefined') {
    return;
  }

  try {
    if (sheet === null) {
      sheet = new CSSStyleSheet();
      sheet.replaceSync(CSS);
    }
    if (!root.adoptedStyleSheets.includes(sheet)) {
      root.adoptedStyleSheets = [...root.adoptedStyleSheets, sheet];
    }
  } catch {
    // Constructable stylesheets are unavailable (jsdom, very old engines): fall back to a <style>.
    if (root.querySelector('style[data-webcon-jev]') === null) {
      const style = document.createElement('style');
      style.dataset.webconJev = '';
      style.textContent = CSS;
      root.appendChild(style);
    }
  }
}
