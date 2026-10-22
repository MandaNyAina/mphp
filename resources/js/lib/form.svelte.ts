import type { ParamsOf, RouteBodies, RouteName, RouteResponses } from '../generated/routes';
import { api, ApiError } from './api';

type Args<N extends RouteName> = ParamsOf<N> extends undefined ? [] : [params: ParamsOf<N>];

/**
 * Reactive form bound to a named PHP route. Field names/types come from the PHP FormRequest,
 * validation messages from the 422 answer:
 *
 *   const form = new Form('names.store', { firstname: '' });
 *   <input bind:value={form.data.firstname} />  {form.errors.firstname?.[0]}
 *   const created = await form.submit();   // NameData | undefined
 */
export class Form<N extends keyof RouteBodies> {
  data: RouteBodies[N];
  errors = $state<Record<string, string[]>>({});
  message = $state('');
  processing = $state(false);

  readonly #name: N;

  constructor(name: N, initial: RouteBodies[N]) {
    this.#name = name;
    this.data = $state(initial);
  }

  /** Resolves with the response, or undefined when the server refused (errors are then filled in). */
  async submit(...args: Args<N>): Promise<RouteResponses[N] | undefined> {
    this.processing = true;
    this.errors = {};
    this.message = '';

    try {
      const call = api as (name: RouteName, init: unknown) => Promise<RouteResponses[N]>;
      return await call(this.#name, { params: args[0], body: $state.snapshot(this.data) });
    } catch (error) {
      if (error instanceof ApiError) {
        this.errors = error.errors;
        this.message = error.message;
      } else {
        this.message = 'Something went wrong.';
      }
      return undefined;
    } finally {
      this.processing = false;
    }
  }
}
