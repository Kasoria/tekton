<script>
  import { Button } from '$lib/components/ui/button/index.js';
  import { t } from '$lib/i18n.svelte.js';

  let {
    currentPage,
    structures = [],
    viewport = $bindable('desktop'),
    viewMode = $bindable('preview'),
    editMode = $bindable(false),
    sidebar = $bindable(null),
    versions = [],
    theme,
    editor,
    onBack,
    onSelectPage,
    onCreateTemplate,
    onRequestDelete,
    onPublish,
    onUnpublish,
    onPreview,
    onViewPage,
  } = $props();

  const GLOBAL_TEMPLATES = ['header', 'footer'];

  // Local UI state for page selector dropdown
  let showPages = $state(false);
  let showNewTemplate = $state(false);
  let newTemplateName = $state('');

  function handleCreate() {
    const name = newTemplateName.trim();
    if (!name) return;
    onCreateTemplate?.(name);
    newTemplateName = '';
    showNewTemplate = false;
    showPages = false;
  }

  function handleSelectPage(p) {
    onSelectPage?.(p);
    showPages = false;
  }
</script>

<header class="tk-topbar">
  <!-- Left: logo + page selector -->
  <div class="flex items-center gap-3.5">
    <div class="flex items-center gap-2">
      <button class="flex items-center gap-2 bg-transparent border-none cursor-pointer" onclick={onBack} title="Back to Dashboard">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
          <rect x="2" y="6" width="20" height="2.2" rx="1" fill="#c97d3c"/>
          <rect x="5" y="10.5" width="14" height="2.2" rx="1" fill="#c97d3c" opacity="0.55"/>
          <rect x="8" y="15" width="8" height="2.2" rx="1" fill="#c97d3c" opacity="0.3"/>
        </svg>
        <span class="font-heading text-[15px] font-bold tracking-tight">tekton</span>
      </button>
    </div>
    <div class="w-px h-4 bg-border"></div>

    <!-- Page selector -->
    <div class="relative">
      <button
        class="flex items-center gap-[7px] px-2 py-1 bg-transparent border-none rounded-[5px] text-foreground cursor-pointer text-[13px] font-medium font-body"
        onclick={() => (showPages = !showPages)}
      >
        <span class="text-muted text-[12px]">{t('editing', 'editing')}</span>
        <span>{currentPage?.title || currentPage?.template_key || t('select_page', 'Select page')}</span>
        {#if currentPage}
          <span class="w-[5px] h-[5px] rounded-full {currentPage.status === 'publish' ? 'bg-green' : 'bg-gold'}"></span>
        {/if}
        <svg width="10" height="10" viewBox="0 0 10 10" class="transition-transform {showPages ? 'rotate-180' : ''}">
          <path d="M2.5 4L5 6.5L7.5 4" class="stroke-muted" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        </svg>
      </button>

      {#if showPages}
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div class="fixed inset-0 z-[29]" onclick={() => (showPages = false)}></div>
        <div class="absolute top-[calc(100%+6px)] -left-1 w-[230px] z-30 bg-card-hover border border-dim rounded-[10px] p-[5px] shadow-[0_16px_48px_var(--color-shadow)]">
          <!-- Global templates -->
          {#each structures.filter(s => GLOBAL_TEMPLATES.includes(s.template_key)) as p}
            <div class="flex items-center rounded-[5px] {p.template_key === currentPage?.template_key ? 'bg-border/20' : ''}">
              <button
                class="flex-1 flex items-center gap-[7px] px-2.5 py-[7px] border-none rounded-[5px] cursor-pointer text-[13px] font-body text-foreground bg-transparent"
                onclick={() => handleSelectPage(p)}
              >
                <span class="w-[5px] h-[5px] rounded-full bg-copper/60"></span>
                <span class="{p.template_key === currentPage?.template_key ? 'font-semibold' : 'font-normal'}">{p.title || p.template_key}</span>
                <span class="text-[12px] text-muted font-mono ml-auto">{t('global', 'global')}</span>
              </button>
            </div>
          {/each}

          <!-- Separator -->
          {#if structures.some(s => !GLOBAL_TEMPLATES.includes(s.template_key))}
            <div class="h-px bg-border/30 my-1"></div>
          {/if}

          <!-- Page templates -->
          {#each structures.filter(s => !GLOBAL_TEMPLATES.includes(s.template_key)) as p}
            <div class="flex items-center rounded-[5px] {p.template_key === currentPage?.template_key ? 'bg-border/20' : ''} group">
              <button
                class="flex-1 flex items-center gap-[7px] px-2.5 py-[7px] border-none rounded-[5px] cursor-pointer text-[13px] font-body text-foreground bg-transparent"
                onclick={() => handleSelectPage(p)}
              >
                <span class="w-[5px] h-[5px] rounded-full {p.status === 'publish' ? 'bg-green' : 'bg-gold'}"></span>
                <span class="{p.template_key === currentPage?.template_key ? 'font-semibold' : 'font-normal'}">{p.title || p.template_key}</span>
              </button>
              <button
                class="opacity-0 group-hover:opacity-100 px-1.5 py-1 border-none bg-transparent text-dim hover:text-gold cursor-pointer text-[12px] transition-opacity"
                onclick={(e) => { e.stopPropagation(); onRequestDelete?.(p.template_key); }}
                title="Delete template"
              >×</button>
            </div>
          {/each}

          <!-- New template -->
          <div class="border-t border-border/30 mt-1 pt-1">
            {#if showNewTemplate}
              <div class="flex items-center gap-1 px-1.5">
                <input
                  type="text"
                  bind:value={newTemplateName}
                  onkeydown={(e) => { if (e.key === 'Enter') handleCreate(); if (e.key === 'Escape') { showNewTemplate = false; newTemplateName = ''; } }}
                  placeholder={t('template_name', 'Template name...')}
                  class="flex-1 px-2 py-[6px] bg-background border border-border/50 rounded-[5px] text-[12px] font-body text-foreground outline-none placeholder:text-dim"
                  autofocus
                />
                <button
                  class="px-2 py-[6px] border-none rounded-[5px] bg-copper text-white text-[12px] font-medium font-body cursor-pointer"
                  onclick={handleCreate}
                >{t('add', 'Add')}</button>
              </div>
            {:else}
              <button
                class="flex items-center gap-[7px] w-full px-2.5 py-[7px] border-none rounded-[5px] cursor-pointer text-[12px] font-body text-muted bg-transparent hover:text-foreground transition-colors"
                onclick={() => (showNewTemplate = true)}
              >
                <span class="text-[14px] leading-none">+</span>
                <span>{t('new_template', 'New template')}</span>
              </button>
            {/if}
          </div>
        </div>
      {/if}
    </div>
  </div>

  <!-- Center: viewport + sidebar toggles -->
  <div class="flex items-center gap-3 absolute left-1/2 -translate-x-1/2">
    <!-- View mode toggle -->
    <div class="flex gap-px bg-card-hover rounded-md p-0.5">
      {#each [
        { key: 'preview', label: t('preview', 'Preview') },
        { key: 'code', label: t('code', 'Code') },
      ] as v}
        <button
          class="px-2.5 py-1 border-none rounded cursor-pointer text-[12px] font-medium font-body transition-colors {viewMode === v.key ? 'bg-border/60 text-foreground' : 'bg-transparent text-muted'}"
          onclick={() => (viewMode = v.key)}
        >{v.label}</button>
      {/each}
    </div>

    <div class="w-px h-4 bg-border"></div>

    <!-- Edit mode toggle -->
    <button
      class="px-2.5 py-1 rounded cursor-pointer text-[12px] font-medium font-body transition-all {editMode ? 'bg-copper border border-copper text-white' : 'bg-transparent border border-border text-muted hover:text-foreground hover:border-dim'}"
      onclick={() => {
        editMode = !editMode;
        if (!editMode) {
          editor?.deselectComponent();
        }
      }}
      title={editMode ? t('exit_edit_mode', 'Exit edit mode') : t('enter_edit_mode', 'Enter edit mode')}
    >
      {editMode ? t('editing_mode', 'Editing') : t('edit', 'Edit')}
    </button>

    <div class="w-px h-4 bg-border"></div>

    <!-- Viewport -->
    <div class="flex gap-px bg-card-hover rounded-md p-0.5">
      {#each [
        { key: 'desktop', label: '⊞' },
        { key: 'tablet', label: '▭' },
        { key: 'mobile', label: '▯' },
      ] as m}
        <button
          class="w-7 h-6 flex items-center justify-center border-none rounded cursor-pointer text-[12px] transition-colors {viewport === m.key ? 'bg-border/60 text-foreground' : 'bg-transparent text-muted'}"
          onclick={() => (viewport = m.key)}
          title={m.key}
        >{m.label}</button>
      {/each}
    </div>

    <div class="w-px h-4 bg-border"></div>

    <!-- Sidebar toggles -->
    <div class="flex gap-px bg-card-hover rounded-md p-0.5">
      {#each [
        { key: 'tree', label: t('tree', 'Tree') },
        { key: 'versions', label: t('history', 'History') },
        { key: 'fields', label: t('fields', 'Fields') },
        { key: 'plugins', label: t('plugins', 'Plugins') },
        { key: 'design', label: t('design', 'Design') },
      ] as s}
        <button
          class="px-3 py-1 border-none rounded cursor-pointer text-[12px] font-medium font-body transition-colors {sidebar === s.key ? 'bg-border/60 text-foreground' : 'bg-transparent text-muted'}"
          onclick={() => (sidebar = sidebar === s.key ? null : s.key)}
        >{s.label}</button>
      {/each}
    </div>
  </div>

  <!-- Right: actions -->
  <div class="flex items-center gap-2">
    <!-- Undo / Redo -->
    <button
      class="w-7 h-6 flex items-center justify-center rounded bg-transparent border-none cursor-pointer transition-colors {editor?.canUndo ? 'text-muted hover:text-foreground' : 'text-dim/30 cursor-default'}"
      onclick={() => editor?.undo()}
      disabled={!editor?.canUndo}
      title="Undo (Ctrl+Z)"
    >
      <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M4 7h7a3 3 0 010 6H8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 4L4 7l3 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <button
      class="w-7 h-6 flex items-center justify-center rounded bg-transparent border-none cursor-pointer transition-colors {editor?.canRedo ? 'text-muted hover:text-foreground' : 'text-dim/30 cursor-default'}"
      onclick={() => editor?.redo()}
      disabled={!editor?.canRedo}
      title="Redo (Ctrl+Shift+Z)"
    >
      <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M12 7H5a3 3 0 000 6h3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 4l3 3-3 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div class="w-px h-4 bg-border"></div>
    <button
      class="w-7 h-6 flex items-center justify-center rounded bg-transparent border-none text-muted hover:text-foreground cursor-pointer transition-colors"
      onclick={() => theme?.toggle()}
      title={theme?.mode === 'system' ? 'Theme: System' : theme?.mode === 'light' ? 'Theme: Light' : 'Theme: Dark'}
    >
      {#if theme?.mode === 'system'}
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="10" rx="1.5" stroke="currentColor" stroke-width="1.3"/><line x1="5" y1="15" x2="11" y2="15" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      {:else if theme?.resolved === 'light'}
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="1.3"/><path d="M8 2v1.5M8 12.5V14M2 8h1.5M12.5 8H14M3.75 3.75l1.06 1.06M11.19 11.19l1.06 1.06M12.25 3.75l-1.06 1.06M4.81 11.19l-1.06 1.06" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      {:else}
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M13.5 9.5a5.5 5.5 0 01-7-7 5.5 5.5 0 107 7z" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
      {/if}
    </button>
    <div class="w-px h-4 bg-border"></div>
    {#if currentPage}
      <span class="text-[12px] text-muted-foreground font-mono">v{versions[0]?.version_number || '–'}</span>
    {/if}
    {#if currentPage?.preview_url || currentPage?.url}
      <button
        class="px-3 py-[5px] bg-transparent border border-border rounded-md text-muted-foreground cursor-pointer text-xs font-body font-medium hover:border-dim transition-colors"
        onclick={onPreview}
      >{t('preview_link', 'Preview ↗')}</button>
    {/if}
    {#if currentPage?.status === 'publish'}
      <button
        class="px-3 py-[5px] bg-transparent border border-border rounded-md text-muted-foreground cursor-pointer text-xs font-body font-medium hover:border-dim transition-colors"
        onclick={onUnpublish}
      >{t('unpublish', 'Unpublish')}</button>
      <button
        class="px-3 py-[5px] bg-transparent border border-copper/30 rounded-md text-copper cursor-pointer text-xs font-body font-medium hover:border-copper/60 hover:bg-copper/5 transition-colors"
        onclick={onViewPage}
      >{t('view_link', 'View ↗')}</button>
    {:else}
      <Button onclick={onPublish}>{t('publish', 'Publish')}</Button>
    {/if}
  </div>
</header>

<style>
  .tk-topbar {
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 14px;
    border-bottom: 1px solid var(--color-border);
    background: var(--color-card);
    flex-shrink: 0;
    z-index: 20;
    position: relative;
  }
</style>
