<script>
  import { Button } from '$lib/components/ui/button/index.js';
  import { createChatStore } from '$lib/stores/chat.svelte.js';
  import { createPageStore } from '$lib/stores/page.svelte.js';
  import { api } from '$lib/api.js';

  let { onBack } = $props();

  const chat = createChatStore();
  const page = createPageStore();

  let input = $state('');
  let showPages = $state(false);
  let viewport = $state('desktop');
  let sidebar = $state(null);
  let selectedComp = $state(null);
  let previewHtml = $state('');
  let versions = $state([]);
  let fieldGroups = $state([]);

  let messagesEnd;
  let textareaEl;

  // Current selected page
  let currentPage = $state(null);

  $effect(() => {
    page.loadStructures();
  });

  // When structures load, pick the first one
  $effect(() => {
    if (page.structures.length > 0 && !currentPage) {
      selectPage(page.structures[0]);
    }
  });

  // Auto-scroll messages
  $effect(() => {
    if (chat.messages.length || chat.isStreaming) {
      messagesEnd?.scrollIntoView({ behavior: 'smooth' });
    }
  });

  // Refresh preview when structure changes
  $effect(() => {
    if (page.currentStructure?.components) {
      refreshPreview();
    }
  });

  function selectPage(p) {
    currentPage = p;
    chat.setTemplateKey(p.template_key);
    page.loadStructure(p.template_key);
    api.getChatHistory(p.template_key).then(history => {
      chat.loadHistory(history);
    });
    loadSidebarData(p.template_key);
    showPages = false;
  }

  async function loadSidebarData(templateKey) {
    try {
      const [v, fg] = await Promise.all([
        api.getVersions(templateKey).catch(() => []),
        api.getFieldGroups().catch(() => []),
      ]);
      versions = v;
      fieldGroups = fg;
    } catch { /* ignore */ }
  }

  async function refreshPreview() {
    if (!page.currentStructure?.components) return;
    try {
      const result = await api.preview(
        page.currentStructure.components,
        currentPage?.template_key || 'preview'
      );
      previewHtml = result.html || '';
    } catch { /* ignore */ }
  }

  async function send(text) {
    const val = text || input;
    if (!val.trim()) return;
    input = '';

    // Detect command type
    let type = 'generate_page';
    if (val.startsWith('/fullstack')) type = 'fullstack';
    else if (val.startsWith('/plugin')) type = 'generate_plugin';

    const structure = await chat.sendMessage(val, type);
    if (structure) {
      page.setStructure(structure);
      // Reload sidebar data
      if (currentPage) {
        loadSidebarData(currentPage.template_key);
        page.loadStructures();
      }
    }
  }

  async function handlePublish() {
    if (!currentPage || !page.currentStructure) return;
    await api.saveStructure({
      template_key: currentPage.template_key,
      title: page.currentStructure.title || currentPage.title || currentPage.template_key,
      components: page.currentStructure.components || [],
      styles: page.currentStructure.styles || [],
      status: 'published',
    });
    page.loadStructures();
  }

  async function handleRollback(versionNumber) {
    if (!currentPage) return;
    await api.rollback(currentPage.template_key, versionNumber);
    page.loadStructure(currentPage.template_key);
    loadSidebarData(currentPage.template_key);
  }

  function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      send();
    }
  }

  function autoResize(e) {
    e.target.style.height = '20px';
    e.target.style.height = e.target.scrollHeight + 'px';
  }

  // Component tree from current structure
  const TYPE_MAP = {
    section: { letter: 'S', hue: '#c97d3c' },
    container: { letter: 'C', hue: '#8a7d6b' },
    heading: { letter: 'H', hue: '#b86e4a' },
    text: { letter: 'T', hue: '#7d8a6b' },
    button: { letter: 'B', hue: '#c9a43c' },
    grid: { letter: 'G', hue: '#6b7d8a' },
    image: { letter: 'I', hue: '#7dab6e' },
    'flex-row': { letter: 'R', hue: '#8a6b7d' },
    'flex-column': { letter: 'F', hue: '#6b8a7d' },
    link: { letter: 'L', hue: '#7d6b8a' },
    list: { letter: 'Li', hue: '#8a7d6b' },
    spacer: { letter: '—', hue: '#5c5753' },
    divider: { letter: '÷', hue: '#5c5753' },
    video: { letter: 'V', hue: '#6b7d8a' },
    icon: { letter: 'Ic', hue: '#8a847d' },
  };

  function flattenTree(components, depth = 0) {
    let result = [];
    for (const comp of (components || [])) {
      result.push({
        id: comp.id,
        type: comp.type,
        label: comp.props?.label || comp.props?.className || comp.type,
        depth,
      });
      if (comp.children) {
        result = result.concat(flattenTree(comp.children, depth + 1));
      }
    }
    return result;
  }

  let tree = $derived(flattenTree(page.currentStructure?.components));

  const vw = { desktop: '100%', tablet: '768px', mobile: '375px' };

  const sidebarLabels = {
    tree: 'Component Tree',
    versions: 'Version History',
    fields: 'Field Groups',
    plugins: 'Generated Plugins',
  };

  function relativeTime(dateStr) {
    if (!dateStr) return '';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return `${days}d ago`;
  }
</script>

<div class="tk-builder">
  <!-- Grain -->
  <div
    class="fixed inset-0 pointer-events-none z-[999] opacity-[0.022] mix-blend-overlay"
    style="background-image: url(&quot;data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E&quot;)"
  ></div>

  <!-- TOP BAR -->
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
          <span class="text-muted text-[11px]">editing</span>
          <span>{currentPage?.title || currentPage?.template_key || 'Select page'}</span>
          {#if currentPage}
            <span class="w-[5px] h-[5px] rounded-full {currentPage.status === 'published' ? 'bg-green' : 'bg-gold'}"></span>
          {/if}
          <svg width="10" height="10" viewBox="0 0 10 10" class="transition-transform {showPages ? 'rotate-180' : ''}">
            <path d="M2.5 4L5 6.5L7.5 4" stroke="#5c5753" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
          </svg>
        </button>

        {#if showPages}
          <!-- svelte-ignore a11y_no_static_element_interactions -->
          <div class="fixed inset-0 z-[29]" onclick={() => (showPages = false)}></div>
          <div class="absolute top-[calc(100%+6px)] -left-1 w-[230px] z-30 bg-card-hover border border-dim rounded-[10px] p-[5px] shadow-[0_16px_48px_#00000060]">
            {#each page.structures as p}
              <button
                class="flex items-center justify-between w-full px-2.5 py-[7px] border-none rounded-[5px] cursor-pointer text-[13px] font-body text-foreground {p.template_key === currentPage?.template_key ? 'bg-border/20' : 'bg-transparent'}"
                onclick={() => selectPage(p)}
              >
                <div class="flex items-center gap-[7px]">
                  <span class="w-[5px] h-[5px] rounded-full {p.status === 'published' ? 'bg-green' : 'bg-gold'}"></span>
                  <span class="{p.template_key === currentPage?.template_key ? 'font-semibold' : 'font-normal'}">{p.title || p.template_key}</span>
                </div>
              </button>
            {/each}
          </div>
        {/if}
      </div>
    </div>

    <!-- Center: viewport + sidebar toggles -->
    <div class="flex items-center gap-3 absolute left-1/2 -translate-x-1/2">
      <!-- Viewport -->
      <div class="flex gap-px bg-card-hover rounded-md p-0.5">
        {#each [
          { key: 'desktop', label: '⊞' },
          { key: 'tablet', label: '▭' },
          { key: 'mobile', label: '▯' },
        ] as m}
          <button
            class="w-7 h-6 flex items-center justify-center border-none rounded cursor-pointer text-[11px] transition-colors {viewport === m.key ? 'bg-border/60 text-foreground' : 'bg-transparent text-muted'}"
            onclick={() => (viewport = m.key)}
            title={m.key}
          >{m.label}</button>
        {/each}
      </div>

      <div class="w-px h-4 bg-border"></div>

      <!-- Sidebar toggles -->
      <div class="flex gap-px bg-card-hover rounded-md p-0.5">
        {#each [
          { key: 'tree', label: 'Tree' },
          { key: 'versions', label: 'History' },
          { key: 'fields', label: 'Fields' },
          { key: 'plugins', label: 'Plugins' },
        ] as s}
          <button
            class="px-3 py-1 border-none rounded cursor-pointer text-[11px] font-medium font-body transition-colors {sidebar === s.key ? 'bg-border/60 text-foreground' : 'bg-transparent text-muted'}"
            onclick={() => (sidebar = sidebar === s.key ? null : s.key)}
          >{s.label}</button>
        {/each}
      </div>
    </div>

    <!-- Right: actions -->
    <div class="flex items-center gap-2">
      {#if currentPage}
        <span class="text-[10px] text-dim font-mono">v{versions[0]?.version_number || '–'}</span>
      {/if}
      <button
        class="px-3 py-[5px] bg-transparent border border-border rounded-md text-muted-foreground cursor-pointer text-xs font-body font-medium hover:border-dim"
        onclick={refreshPreview}
      >Preview</button>
      <Button onclick={handlePublish}>Publish</Button>
    </div>
  </header>

  <!-- MAIN -->
  <div class="flex flex-1 overflow-hidden">

    <!-- LEFT: CHAT -->
    <div class="w-[400px] shrink-0 flex flex-col bg-background border-r border-border">
      <!-- Messages -->
      <div class="flex-1 overflow-auto p-4 pb-2">
        <div class="flex flex-col gap-5">
          {#each chat.messages as m}
            <div class="flex flex-col gap-[5px]">
              <span class="text-[10px] font-semibold uppercase tracking-[1.2px] pl-0.5 {m.role === 'user' ? 'text-muted' : 'text-copper'}">
                {m.role === 'user' ? 'You' : 'Tekton'}
              </span>
              <div class="rounded-[10px] text-[13.5px] leading-[1.65] px-3.5 py-3 {m.role === 'user' ? 'bg-card-hover text-foreground border-l-2 border-dim' : 'bg-card text-foreground/75 border-l-2 border-copper/20'}">
                <div class="whitespace-pre-wrap">{m.content}</div>

                {#if m.structure}
                  <div class="inline-flex items-center gap-[5px] mt-2.5 px-2.5 py-1 rounded-[5px] bg-green/5 border border-green/10">
                    <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 5.5L4 7.5L8 3" stroke="#7dab6e" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="text-[11px] text-green font-medium">Preview updated</span>
                  </div>
                {/if}
              </div>
            </div>
          {/each}

          <!-- Streaming indicator -->
          {#if chat.isStreaming}
            <div class="flex flex-col gap-[5px]">
              <span class="text-[10px] font-semibold uppercase tracking-[1.2px] pl-0.5 text-copper">Tekton</span>
              <div class="px-3.5 py-3 rounded-[10px] bg-card border-l-2 border-copper/20 flex items-center gap-2">
                <div class="tk-ember w-[7px] h-[7px] rounded-full bg-copper shrink-0"></div>
                <span class="text-xs text-muted">
                  {#if chat.currentStream}
                    {chat.currentStream}
                  {:else}
                    Building changes...
                  {/if}
                </span>
              </div>
            </div>
          {/if}
          <div bind:this={messagesEnd}></div>
        </div>
      </div>

      <!-- Input area -->
      <div class="p-4 pt-2 border-t border-border">
        <div class="flex gap-2 items-end bg-card rounded-[10px] border border-border px-3 py-2.5 focus-within:border-dim transition-colors">
          <textarea
            bind:this={textareaEl}
            bind:value={input}
            onkeydown={handleKeydown}
            oninput={autoResize}
            placeholder="Describe what to build or change..."
            rows="1"
            class="flex-1 bg-transparent border-none text-foreground text-[13px] leading-[1.5] resize-none outline-none font-body min-h-[20px] max-h-[100px] placeholder:text-dim"
          ></textarea>
          <button
            onclick={() => send()}
            disabled={!input.trim() || chat.isStreaming}
            class="w-[30px] h-[30px] rounded-[7px] border-none cursor-pointer flex items-center justify-center transition-all shrink-0 {input.trim() ? 'bg-copper opacity-100' : 'bg-transparent opacity-40'}"
            aria-label="Send message"
          >
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
              <path d="M7 11V3M7 3L4 6M7 3l3 3" stroke={input.trim() ? '#1a1816' : '#5c5753'} stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
        <div class="flex items-center gap-1.5 mt-1.5 pl-0.5">
          {#each ['/fullstack', '/plugin', '/undo'] as cmd}
            <button
              class="px-[7px] py-[2px] bg-transparent border border-border/50 rounded text-dim cursor-pointer text-[10px] font-mono transition-colors hover:text-muted hover:border-dim"
              onclick={() => { input = cmd + ' '; textareaEl?.focus(); }}
            >{cmd}</button>
          {/each}
          <span class="ml-auto text-[10px] text-border">shift+enter for newline</span>
        </div>
      </div>
    </div>

    <!-- CENTER: PREVIEW -->
    <div
      class="flex-1 flex items-stretch justify-center overflow-auto transition-all duration-300"
      style="padding: {viewport === 'desktop' ? '0' : '24px'}; background: {viewport !== 'desktop' ? '#151311' : '#151311'}; background-image: {viewport !== 'desktop' ? 'radial-gradient(#2a272518 1px, transparent 1px)' : 'none'}; background-size: 20px 20px;"
    >
      <div
        class="bg-white overflow-auto transition-all duration-300"
        style="width: {vw[viewport]}; max-width: 100%; {viewport === 'desktop' ? 'height: 100%;' : 'min-height: 600px;'} border-radius: {viewport === 'desktop' ? '0' : '8px'}; box-shadow: {viewport !== 'desktop' ? '0 8px 60px #00000050' : 'none'};"
      >
        {#if previewHtml}
          <iframe
            srcdoc={previewHtml}
            title="Preview"
            class="w-full h-full border-none"
            sandbox="allow-same-origin"
          ></iframe>
        {:else if page.currentStructure}
          <div class="flex items-center justify-center h-full text-muted-foreground text-sm">
            Loading preview...
          </div>
        {:else}
          <div class="flex flex-col items-center justify-center h-full gap-3 text-center px-8" style="font-family: 'Outfit', sans-serif; color: #8a847d;">
            <div style="font-size: 14px;">No template selected</div>
            <div style="font-size: 12px; color: #5c5753;">Use the chat to generate a page, or select a template from the dropdown above.</div>
          </div>
        {/if}
      </div>
    </div>

    <!-- RIGHT SIDEBAR (on demand) -->
    {#if sidebar}
      <div class="w-[260px] shrink-0 bg-card border-l border-border flex flex-col overflow-hidden">
        <div class="px-3.5 py-2.5 border-b border-border flex items-center justify-between">
          <span class="text-[10px] font-semibold uppercase tracking-[1.5px] text-muted">{sidebarLabels[sidebar]}</span>
          <button
            class="bg-transparent border-none text-dim cursor-pointer text-[15px] leading-none p-0.5 hover:text-muted"
            onclick={() => (sidebar = null)}
          >×</button>
        </div>

        <div class="flex-1 overflow-auto p-2">
          <!-- Tree -->
          {#if sidebar === 'tree'}
            {#if tree.length === 0}
              <div class="text-xs text-muted text-center py-4">No components yet.</div>
            {:else}
              <div class="flex flex-col gap-px">
                {#each tree as n}
                  <button
                    class="flex items-center gap-[7px] py-1 px-1.5 rounded-[5px] cursor-pointer w-full text-left border {selectedComp === n.id ? 'bg-copper/5 border-copper/10' : 'bg-transparent border-transparent'}"
                    style="padding-left: {6 + n.depth * 16}px;"
                    onclick={() => (selectedComp = n.id)}
                  >
                    <span
                      class="w-4 h-4 rounded-[3px] shrink-0 text-[8px] font-bold font-mono flex items-center justify-center"
                      style="background: {(TYPE_MAP[n.type]?.hue || '#5c5753')}15; color: {TYPE_MAP[n.type]?.hue || '#5c5753'};"
                    >{TYPE_MAP[n.type]?.letter || '?'}</span>
                    <span class="text-[11.5px] truncate {selectedComp === n.id ? 'text-foreground font-semibold' : 'text-muted-foreground font-normal'}">{n.label}</span>
                    <span class="ml-auto text-[9px] text-border font-mono shrink-0">{n.type}</span>
                  </button>
                {/each}
              </div>
            {/if}

          <!-- Versions -->
          {:else if sidebar === 'versions'}
            {#if versions.length === 0}
              <div class="text-xs text-muted text-center py-4">No versions yet.</div>
            {:else}
              <div class="flex flex-col gap-0.5">
                {#each versions as v, i}
                  <div class="px-2 py-2 rounded-[5px] {i === 0 ? 'bg-copper/5' : ''}">
                    <div class="flex items-center gap-2 mb-[3px]">
                      <span class="text-[11px] font-semibold font-mono {i === 0 ? 'text-copper' : 'text-dim'}">v{v.version_number}</span>
                      {#if i === 0}
                        <span class="text-[9px] text-green font-semibold">CURRENT</span>
                      {/if}
                      <span class="ml-auto text-[10px] text-border">{relativeTime(v.created_at)}</span>
                    </div>
                    <div class="text-xs text-muted-foreground leading-[1.4]">{v.change_summary || v.change_type}</div>
                    {#if i !== 0}
                      <button
                        class="mt-[5px] px-2.5 py-[3px] bg-transparent border border-border rounded text-muted cursor-pointer text-[10px] font-body hover:border-dim"
                        onclick={() => handleRollback(v.version_number)}
                      >Restore</button>
                    {/if}
                  </div>
                {/each}
              </div>
            {/if}

          <!-- Fields -->
          {:else if sidebar === 'fields'}
            {#if fieldGroups.length === 0}
              <div class="text-xs text-muted text-center py-4">No field groups yet.</div>
            {:else}
              <div class="flex flex-col gap-2">
                {#each fieldGroups as g}
                  <div class="p-2.5 rounded-[7px] border border-border bg-card-hover">
                    <div class="text-xs font-semibold text-foreground mb-0.5">{g.title}</div>
                    <div class="text-[10px] text-dim font-mono mb-2">{g.slug}</div>
                    {#each (g.fields || []) as f}
                      <div class="text-[11px] text-muted py-px font-mono flex items-center gap-1.5">
                        <span class="w-[3px] h-[3px] rounded-sm bg-copper/40 shrink-0"></span>
                        {f.name || f.key || f}
                      </div>
                    {/each}
                  </div>
                {/each}
              </div>
            {/if}

          <!-- Plugins -->
          {:else if sidebar === 'plugins'}
            <div class="text-xs text-muted text-center py-4">
              <div class="mb-2">Generated plugins will appear here.</div>
              <div class="text-dim">Use <span class="font-mono">/plugin</span> in the chat to generate one.</div>
            </div>
          {/if}
        </div>
      </div>
    {/if}
  </div>
</div>

<style>
  .tk-builder {
    width: 100vw;
    height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--color-background);
    color: var(--color-foreground);
    font-family: var(--font-body);
    overflow: hidden;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99999;
  }

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

  /* WP style overrides within builder */
  .tk-builder :global(h1),
  .tk-builder :global(h2),
  .tk-builder :global(h3),
  .tk-builder :global(h4),
  .tk-builder :global(h5),
  .tk-builder :global(h6) {
    color: var(--color-foreground);
    font-size: inherit;
    font-weight: inherit;
    margin: 0;
    padding: 0;
    line-height: inherit;
  }

  .tk-builder :global(p) {
    color: inherit;
    font-size: inherit;
    margin: 0;
    line-height: inherit;
  }

  .tk-builder :global(a) {
    color: inherit;
    text-decoration: none;
  }

  .tk-builder :global(button) {
    font-family: var(--font-body);
    line-height: inherit;
  }

  .tk-builder :global(input),
  .tk-builder :global(textarea) {
    font-family: var(--font-body);
    color: var(--color-foreground);
    background: transparent;
    border: none;
    box-shadow: none;
  }

  .tk-builder :global(input:focus),
  .tk-builder :global(textarea:focus) {
    box-shadow: none;
    outline: none;
  }

  @keyframes ember {
    0%, 100% { opacity: 0.35; transform: scale(0.85); }
    50% { opacity: 1; transform: scale(1.15); }
  }

  :global(.tk-ember) {
    animation: ember 1.6s ease-in-out infinite;
  }
</style>
