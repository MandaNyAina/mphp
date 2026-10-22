import { route, routes, type ParamsOf, type RouteBodies, type RouteName, type RouteResponses } from '../generated/routes';

/** Thrown for any non-2xx answer. `errors` holds the per-field messages of a 422. */
export class ApiError extends Error {
  constructor(
    readonly status: number,
    message: string,
    readonly errors: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

// URL params and the JSON body are required exactly when the PHP route declares them, with the PHP types.
type Init<N extends RouteName> = (N extends keyof RouteBodies ? { body: RouteBodies[N] } : { body?: undefined }) &
  (ParamsOf<N> extends undefined ? { params?: never } : { params: ParamsOf<N> });
type Args<N extends RouteName> = Record<never, never> extends Init<N> ? [init?: Init<N>] : [init: Init<N>];

const csrfToken = (): string => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

/**
 * Call a named PHP route. Name, URL params and the response type all come from the PHP
 * attributes via `php bin/mphp types:generate`:
 *
 *   const names = await api('names.index');                       // NameData[]
 *   await api('names.update', { params: { id: 3 }, body: { firstname: 'Ada' } });
 */
export async function api<N extends RouteName>(name: N, ...[init]: Args<N>): Promise<RouteResponses[N]> {
  const url = (route as (name: RouteName, params?: unknown) => string)(name, init?.params);

  const headers = new Headers({ Accept: 'application/json', 'X-CSRF-Token': csrfToken() });
  if (init?.body !== undefined) headers.set('Content-Type', 'application/json');

  const response = await fetch(url, {
    method: routes[name].method,
    headers,
    body: init?.body === undefined ? undefined : JSON.stringify(init.body),
    credentials: 'same-origin',
  });

  if (response.status === 204) return undefined as RouteResponses[N];

  const data = (await response.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]> };
  if (!response.ok) throw new ApiError(response.status, data.message ?? response.statusText, data.errors);

  return data as RouteResponses[N];
}
