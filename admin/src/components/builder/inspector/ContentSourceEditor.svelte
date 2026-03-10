<script>
  import { t } from '$lib/i18n.svelte.js';

  let { value = {}, onchange } = $props();

  // Parse current source
  let sourceType = $derived(value?.source || 'static');
  let sourceGroup = $derived(value?.group || '');
  let sourceField = $derived(value?.field || '');
  let sourceKey = $derived(value?.key || '');
  let sourceFallback = $derived(value?.fallback || '');
  let staticValue = $derived(
    typeof value === 'string' ? value : (value?.value || value?.fallback || '')
  );

  function emit(updates) {
    if (sourceType === 'static') {
      onchange?.({ source: 'static', value: updates.value ?? staticValue });
    } else if (sourceType === 'field') {
      onchange?.({ source: 'field', group: updates.group ?? sourceGroup, field: updates.field ?? sourceField, fallback: updates.fallback ?? sourceFallback });
    } else if (sourceType === 'post') {
      onchange?.({ source: 'post', field: updates.field ?? sourceField });
    } else if (sourceType === 'option') {
      onchange?.({ source: 'option', key: updates.key ?? sourceKey });
    }
  }

  function setSourceType(type) {
    if (type === 'static') {
      onchange?.({ source: 'static', value: sourceFallback || staticValue || '' });
    } else if (type === 'field') {
      onchange?.({ source: 'field', group: sourceGroup, field: sourceField, fallback: staticValue || sourceFallback });
    } else if (type === 'post') {
      onchange?.({ source: 'post', field: 'post_title' });
    } else if (type === 'option') {
      onchange?.({ source: 'option', key: sourceKey || 'blogname' });
    }
  }
</script>

<div class="flex flex-col gap-2">
  <!-- Source type segmented control -->
  <div class="flex gap-px bg-card-hover rounded-[5px] p-[2px]">
    {#each ['static', 'field', 'post', 'option'] as st}
      <button
        class="flex-1 px-2 py-[3px] border-none rounded-[3px] cursor-pointer text-[11px] font-medium font-body transition-colors capitalize {sourceType === st ? 'bg-border/60 text-foreground' : 'bg-transparent text-dim'}"
        onclick={() => setSourceType(st)}
      >{st}</button>
    {/each}
  </div>

  {#if sourceType === 'static'}
    <input
      type="text"
      value={staticValue}
      oninput={(e) => emit({ value: e.target.value })}
      placeholder={t('static_value', 'Static value...')}
      class="tk-inspector-input"
    />
  {:else if sourceType === 'field'}
    <div class="flex gap-1.5">
      <input
        type="text"
        value={sourceGroup}
        oninput={(e) => emit({ group: e.target.value })}
        placeholder={t('group', 'group')}
        class="tk-inspector-input flex-1"
      />
      <input
        type="text"
        value={sourceField}
        oninput={(e) => emit({ field: e.target.value })}
        placeholder={t('field', 'field')}
        class="tk-inspector-input flex-1"
      />
    </div>
    <input
      type="text"
      value={sourceFallback}
      oninput={(e) => emit({ fallback: e.target.value })}
      placeholder={t('fallback_text', 'Fallback text...')}
      class="tk-inspector-input"
    />
  {:else if sourceType === 'post'}
    <input
      type="text"
      value={sourceField}
      oninput={(e) => emit({ field: e.target.value })}
      placeholder="post_title, post_content, featured_image..."
      class="tk-inspector-input"
    />
  {:else if sourceType === 'option'}
    <input
      type="text"
      value={sourceKey}
      oninput={(e) => emit({ key: e.target.value })}
      placeholder="blogname, blogdescription..."
      class="tk-inspector-input"
    />
  {/if}
</div>

<style>
  :global(.tk-inspector-input) {
    width: 100%;
    padding: 4px 8px;
    background: var(--color-card-hover);
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 12px;
    font-family: var(--font-body);
    color: var(--color-foreground);
    outline: none;
  }
  :global(.tk-inspector-input:focus) {
    border-color: var(--color-dim);
  }
  :global(.tk-inspector-input::placeholder) {
    color: var(--color-dim);
  }
</style>
