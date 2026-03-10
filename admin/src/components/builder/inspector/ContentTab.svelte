<script>
  import { t } from '$lib/i18n.svelte.js';
  import ContentSourceEditor from './ContentSourceEditor.svelte';

  let { editor, comp, compType } = $props();

  let hasContent = $derived(['heading', 'text', 'button', 'link'].includes(compType));
  let contentProp = $derived(compType === 'button' || compType === 'link' ? 'text' : 'content');
  let contentValue = $derived.by(() => {
    if (!comp?.props) return '';
    const val = comp.props[contentProp];
    if (val && typeof val === 'object' && val.source) return val;
    return val || '';
  });
  let isContentObject = $derived(contentValue && typeof contentValue === 'object' && contentValue.source);

  let hasHref = $derived(['button', 'link'].includes(compType));
  let isHeading = $derived(compType === 'heading');

  function setContent(value) {
    if (typeof value === 'object' && value.source) {
      editor.updateContent(contentProp, value.fallback || value.value || '', !!value.source);
      // Also update the full content source object
      editor.updateProp(contentProp, value);
    } else {
      editor.updateContent(contentProp, value, false);
    }
  }
</script>

<div class="flex flex-col gap-3 p-3">
  {#if hasContent}
    <!-- Content source -->
    <div>
      <label class="tk-inspector-label">{t('content', 'Content')}</label>
      {#if isContentObject}
        <ContentSourceEditor value={contentValue} onchange={(v) => editor.updateProp(contentProp, v)} />
      {:else}
        <textarea
          value={typeof contentValue === 'string' ? contentValue : ''}
          oninput={(e) => setContent(e.target.value)}
          placeholder={t('enter_content', 'Enter content...')}
          rows="2"
          class="tk-inspector-input"
          style="resize: vertical; min-height: 48px;"
        ></textarea>
        <button
          class="mt-1 text-[11px] text-dim hover:text-muted bg-transparent border-none cursor-pointer p-0 font-body"
          onclick={() => editor.updateProp(contentProp, { source: 'field', group: '', field: '', fallback: typeof contentValue === 'string' ? contentValue : '' })}
        >{t('make_dynamic', '+ Make dynamic')}</button>
      {/if}
    </div>
  {/if}

  {#if isHeading}
    <!-- Heading level -->
    <div>
      <label class="tk-inspector-label">{t('heading_level', 'Heading Level')}</label>
      <div class="flex gap-px bg-card-hover rounded-[5px] p-[2px]">
        {#each [1, 2, 3, 4, 5, 6] as lvl}
          <button
            class="flex-1 px-1.5 py-[3px] border-none rounded-[3px] cursor-pointer text-[11px] font-medium font-body transition-colors {comp?.props?.level === lvl ? 'bg-border/60 text-foreground' : 'bg-transparent text-dim'}"
            onclick={() => editor.updateProp('level', lvl)}
          >H{lvl}</button>
        {/each}
      </div>
    </div>
  {/if}

  {#if hasHref}
    <!-- Link URL -->
    <div>
      <label class="tk-inspector-label">{t('link_url', 'Link URL')}</label>
      <input
        type="text"
        value={comp?.props?.href || ''}
        oninput={(e) => editor.updateProp('href', e.target.value)}
        placeholder="https://..."
        class="tk-inspector-input"
      />
    </div>
    <div>
      <label class="tk-inspector-label">{t('target', 'Target')}</label>
      <div class="flex gap-px bg-card-hover rounded-[5px] p-[2px]">
        {#each ['_self', '_blank'] as tgt}
          <button
            class="flex-1 px-2 py-[3px] border-none rounded-[3px] cursor-pointer text-[11px] font-medium font-body transition-colors {(comp?.props?.target || '_self') === tgt ? 'bg-border/60 text-foreground' : 'bg-transparent text-dim'}"
            onclick={() => editor.updateProp('target', tgt)}
          >{tgt === '_self' ? 'Same tab' : 'New tab'}</button>
        {/each}
      </div>
    </div>
  {/if}

  <!-- CSS Classes -->
  <div>
    <label class="tk-inspector-label">{t('css_classes', 'CSS Classes')}</label>
    <input
      type="text"
      value={comp?.props?.className || ''}
      oninput={(e) => editor.updateProp('className', e.target.value)}
      placeholder="class-one class-two"
      class="tk-inspector-input font-mono"
    />
  </div>
</div>

<style>
  .tk-inspector-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--color-muted);
    margin-bottom: 4px;
  }
</style>
