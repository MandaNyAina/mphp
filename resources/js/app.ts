import { mount, type Component } from 'svelte';
import '../css/app.css';

// Every resources/pages/**/*.svelte is a page PHP can render with $view->page('Name', $props).
// Pages load lazily: the browser only downloads the page it needs.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const pages = import.meta.glob<{ default: Component<any> }>('../pages/**/*.svelte');

async function boot(): Promise<void> {
  const target = document.getElementById('app');
  const name = target?.dataset.component;
  if (!target || !name) return;

  const load = pages[`../pages/${name}.svelte`];
  if (!load) throw new Error(`Page "${name}" not found: create resources/pages/${name}.svelte`);

  // PHP ships the props as JSON inside <script type="application/json">: CSP-safe, no inline JS.
  const props: unknown = JSON.parse(document.getElementById('mphp-props')?.textContent || '{}');
  const { default: Page } = await load();

  mount(Page, { target, props: { props } });
}

void boot();
