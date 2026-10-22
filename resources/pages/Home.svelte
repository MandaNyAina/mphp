<script lang="ts">
  import type { HomeProps, NameData } from '../js/generated/types';
  import { api, ApiError } from '../js/lib/api';
  import { Form } from '../js/lib/form.svelte';

  let { props }: { props: HomeProps } = $props();

  // svelte-ignore state_referenced_locally: props are the initial server state, the list is local from here on
  let names = $state<NameData[]>(props.names);
  // Field names, types and validation messages all come from the PHP NameInput class.
  const createForm = new Form('names.store', { firstname: '' });
  let error = $state('');
  let editingId = $state<number | null>(null);
  let editingValue = $state('');

  async function run(action: () => Promise<void>) {
    error = '';
    try {
      await action();
    } catch (e) {
      error = e instanceof ApiError ? Object.values(e.errors).flat()[0] ?? e.message : 'Something went wrong.';
    }
  }

  const add = async (event: SubmitEvent) => {
    event.preventDefault();
    const created = await createForm.submit();
    if (created) {
      names.push(created);
      createForm.data.firstname = '';
    }
  };

  const save = (id: number) =>
    run(async () => {
      const updated = await api('names.update', { params: { id }, body: { firstname: editingValue } });
      names = names.map((n) => (n.id === id ? updated : n));
      editingId = null;
    });

  const remove = (id: number) =>
    run(async () => {
      await api('names.destroy', { params: { id } });
      names = names.filter((n) => n.id !== id);
    });
</script>

<main>
  <header>
    <h1>{props.appName}</h1>
    <p>PHP {props.phpVersion} · Svelte 5 · TypeScript</p>
  </header>

  <section>
    <h2>Names</h2>

    <form onsubmit={add}>
      <input bind:value={createForm.data.firstname} placeholder="Add a name" maxlength="50" aria-label="First name" />
      <button type="submit" disabled={createForm.processing}>Add</button>
    </form>

    {#if createForm.errors.firstname}<p class="error" role="alert">{createForm.errors.firstname[0]}</p>{/if}
    {#if error}<p class="error" role="alert">{error}</p>{/if}

    <ul>
      {#each names as name (name.id)}
        <li>
          {#if editingId === name.id}
            <input bind:value={editingValue} maxlength="50" aria-label="Edit name" />
            <button onclick={() => save(name.id)}>Save</button>
            <button class="ghost" onclick={() => (editingId = null)}>Cancel</button>
          {:else}
            <span>{name.firstname}</span>
            <button class="ghost" onclick={() => ((editingId = name.id), (editingValue = name.firstname))}>Edit</button>
            <button class="ghost danger" onclick={() => remove(name.id)}>Delete</button>
          {/if}
        </li>
      {:else}
        <li class="empty">Nothing yet: add the first one.</li>
      {/each}
    </ul>
  </section>
</main>

<style>
  main {
    max-width: 36rem;
    margin: 4rem auto;
    padding: 0 1.25rem;
  }
  header p {
    opacity: 0.6;
    margin-top: -0.5rem;
  }
  form {
    display: flex;
    gap: 0.5rem;
  }
  form input {
    flex: 1;
  }
  ul {
    list-style: none;
    padding: 0;
    margin: 1rem 0 0;
  }
  li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 0;
    border-bottom: 1px solid var(--line);
  }
  li span {
    flex: 1;
  }
  li input {
    flex: 1;
  }
  .empty {
    opacity: 0.6;
  }
  .error {
    color: var(--danger);
    margin: 0.75rem 0 0;
  }
  .danger {
    color: var(--danger);
  }
</style>
