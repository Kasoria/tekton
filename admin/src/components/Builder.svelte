<script>
  import { Button } from '$lib/components/ui/button/index.js';
  import { ConfirmDialog } from '$lib/components/ui/dialog/index.js';
  import { createChatStore } from '$lib/stores/chat.svelte.js';
  import { createPageStore } from '$lib/stores/page.svelte.js';
  import { api } from '$lib/api.js';
  import { t } from '$lib/i18n.svelte.js';
  import { createThemeStore } from '$lib/stores/theme.svelte.js';

  let { onBack, initialTemplateKey = null } = $props();

  const theme = createThemeStore();

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
  let deleteConfirm = $state({ open: false, key: '' });
  let newTemplateName = $state('');
  let showNewTemplate = $state(false);
  let attachedImages = $state([]);
  let imageWarning = $state('');
  let fileInputEl;
  let showClearMenu = $state(false);
  let isClearing = $state(false);
  let viewMode = $state('preview'); // 'preview' | 'code'
  let codeViewTab = $state('structure'); // 'structure' | 'html'
  let codeEditorValue = $state('');
  let codeEditorDirty = $state(false);
  let codeEditorError = $state('');
  let codeSaving = $state(false);

  const GLOBAL_TEMPLATES = ['header', 'footer'];

  let messagesEnd;
  let textareaEl;

  // Current selected page
  let currentPage = $state(null);

  $effect(() => {
    page.loadStructures();
  });

  // When structures load, pick the requested template or fall back to first
  $effect(() => {
    if (page.structures.length > 0 && !currentPage) {
      const target = initialTemplateKey
        ? page.structures.find(s => s.template_key === initialTemplateKey)
        : null;
      selectPage(target || page.structures[0]);
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

  // Sync code editor when structure changes (only if not dirty)
  $effect(() => {
    if (page.currentStructure && !codeEditorDirty) {
      codeEditorValue = JSON.stringify(page.currentStructure, null, 2);
      codeEditorError = '';
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
        page.currentStructure,
        currentPage?.template_key || 'preview'
      );
      previewHtml = result.html || '';
    } catch { /* ignore */ }
  }

  async function send(text) {
    const val = text || input;
    if (!val.trim() && attachedImages.length === 0) return;
    input = '';
    const imagesToSend = [...attachedImages];
    attachedImages = [];

    // Detect command type
    let type = 'generate_page';
    if (val.startsWith('/fullstack')) type = 'fullstack';
    else if (val.startsWith('/plugin')) type = 'generate_plugin';

    const structure = await chat.sendMessage(val, type, imagesToSend);
    if (structure) {
      page.setStructure(structure);
      // Reload sidebar data
      if (currentPage) {
        loadSidebarData(currentPage.template_key);
        page.loadStructures();
      }
    }
  }

  function showImageWarning(msg) {
    imageWarning = msg;
    setTimeout(() => { imageWarning = ''; }, 4000);
  }

  function handleImageUpload(e) {
    const files = Array.from(e.target.files || []);
    for (const file of files) {
      if (!file.type.startsWith('image/')) continue;
      if (file.size > 20 * 1024 * 1024) {
        showImageWarning(t('image_too_large', 'Image too large (max 20 MB)'));
        continue;
      }
      const reader = new FileReader();
      reader.onload = () => {
        const base64 = reader.result.split(',')[1];
        attachedImages = [...attachedImages, {
          data: base64,
          media_type: file.type,
          preview: reader.result,
          name: file.name,
        }];
      };
      reader.readAsDataURL(file);
    }
    // Reset input so same file can be re-selected
    e.target.value = '';
  }

  function removeImage(index) {
    attachedImages = attachedImages.filter((_, i) => i !== index);
  }

  async function handlePublish() {
    if (!currentPage || !page.currentStructure) return;
    const result = await api.saveStructure({
      template_key: currentPage.template_key,
      title: page.currentStructure.title || currentPage.title || currentPage.template_key,
      components: page.currentStructure.components || [],
      styles: page.currentStructure.styles || [],
      status: 'published',
    });
    if (result?.url) {
      currentPage = { ...currentPage, url: result.url, preview_url: result.preview_url, status: 'published' };
    }
    page.loadStructures();
  }

  async function handleUnpublish() {
    if (!currentPage || !page.currentStructure) return;
    const result = await api.saveStructure({
      template_key: currentPage.template_key,
      title: page.currentStructure.title || currentPage.title || currentPage.template_key,
      components: page.currentStructure.components || [],
      styles: page.currentStructure.styles || [],
      status: 'draft',
    });
    currentPage = { ...currentPage, url: null, preview_url: result?.preview_url || null, status: 'draft' };
    page.loadStructures();
  }

  async function saveCodeEdits() {
    if (!currentPage) return;
    codeEditorError = '';
    let parsed;
    try {
      parsed = JSON.parse(codeEditorValue);
    } catch (e) {
      codeEditorError = `Invalid JSON: ${e.message}`;
      return;
    }

    // Accept either a full structure object or just a components array.
    const components = Array.isArray(parsed) ? parsed : (parsed.components || []);
    const styles = parsed.styles || page.currentStructure?.styles || {};

    codeSaving = true;
    try {
      await api.saveStructure({
        template_key: currentPage.template_key,
        title: parsed.title || page.currentStructure?.title || currentPage.title || currentPage.template_key,
        components,
        styles,
        status: currentPage.status || 'draft',
        change_type: 'manual',
      });
      page.setStructure({ ...page.currentStructure, components, styles });
      codeEditorDirty = false;
      loadSidebarData(currentPage.template_key);
    } catch (e) {
      codeEditorError = `Save failed: ${e.message}`;
    } finally {
      codeSaving = false;
    }
  }

  function resetCodeEditor() {
    codeEditorValue = JSON.stringify(page.currentStructure, null, 2);
    codeEditorDirty = false;
    codeEditorError = '';
  }

  function handlePreview() {
    const url = currentPage?.preview_url || currentPage?.url;
    if (url) {
      window.open(url, '_blank');
    }
  }

  function viewPage() {
    if (currentPage?.url) {
      window.open(currentPage.url, '_blank');
    }
  }

  async function createTemplate() {
    const name = newTemplateName.trim();
    if (!name) return;
    const key = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    if (!key) return;

    await api.saveStructure({
      template_key: key,
      title: name,
      components: [],
      styles: [],
      status: 'draft',
    });

    newTemplateName = '';
    showNewTemplate = false;
    await page.loadStructures();

    const created = page.structures.find(s => s.template_key === key);
    if (created) selectPage(created);
  }

  function requestDeleteTemplate(templateKey) {
    if (GLOBAL_TEMPLATES.includes(templateKey)) return;
    deleteConfirm = { open: true, key: templateKey };
  }

  async function confirmDeleteTemplate() {
    const templateKey = deleteConfirm.key;
    deleteConfirm = { open: false, key: '' };
    await api.deleteStructure(templateKey);
    await page.loadStructures();
    if (currentPage?.template_key === templateKey) {
      currentPage = page.structures[0] || null;
      if (currentPage) {
        selectPage(currentPage);
      } else {
        previewHtml = '';
        versions = [];
      }
    }
  }

  let editingVersion = $state(null);
  let editingLabel = $state('');

  async function handleRollback(versionNumber) {
    if (!currentPage) return;
    await api.rollback(currentPage.template_key, versionNumber);
    page.loadStructure(currentPage.template_key);
    loadSidebarData(currentPage.template_key);
  }

  function startRenameVersion(v) {
    editingVersion = v.version_number;
    editingLabel = v.label || '';
  }

  async function saveVersionLabel(versionNumber) {
    if (!currentPage) return;
    await api.renameVersion(currentPage.template_key, versionNumber, editingLabel.trim());
    editingVersion = null;
    loadSidebarData(currentPage.template_key);
  }

  function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      send();
    }
  }

  function handlePaste(e) {
    const items = Array.from(e.clipboardData?.items || []);
    for (const item of items) {
      if (!item.type.startsWith('image/')) continue;
      e.preventDefault();
      const file = item.getAsFile();
      if (!file) continue;
      if (file.size > 20 * 1024 * 1024) {
        showImageWarning(t('image_too_large', 'Image too large (max 20 MB)'));
        continue;
      }
      const reader = new FileReader();
      reader.onload = () => {
        const base64 = reader.result.split(',')[1];
        attachedImages = [...attachedImages, {
          data: base64,
          media_type: file.type,
          preview: reader.result,
          name: 'pasted-image',
        }];
      };
      reader.readAsDataURL(file);
    }
  }

  async function clearChat(withSummary = false) {
    if (!currentPage) return;
    isClearing = true;
    showClearMenu = false;
    try {
      if (withSummary) {
        await api.summarizeAndClearChat(currentPage.template_key);
      } else {
        await api.clearChat(currentPage.template_key);
      }
      // Reload chat history (will be empty or contain just the summary)
      const history = await api.getChatHistory(currentPage.template_key);
      chat.loadHistory(history);
    } finally {
      isClearing = false;
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
    div: { letter: 'D', hue: '#6b6b6b' },
    heading: { letter: 'H', hue: '#b86e4a' },
    text: { letter: 'T', hue: '#7d8a6b' },
    button: { letter: 'B', hue: '#c9a43c' },
    grid: { letter: 'G', hue: '#6b7d8a' },
    image: { letter: 'I', hue: '#7dab6e' },
    'flex-row': { letter: 'R', hue: '#8a6b7d' },
    'flex-column': { letter: 'F', hue: '#6b8a7d' },
    link: { letter: 'L', hue: '#7d6b8a' },
    list: { letter: 'Li', hue: '#8a7d6b' },
    spacer: { letter: '—', hue: '#7a746e' },
    divider: { letter: '÷', hue: '#7a746e' },
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

  const sidebarLabels = $derived({
    tree: t('component_tree', 'Component Tree'),
    versions: t('version_history', 'Version History'),
    fields: t('field_groups', 'Field Groups'),
    plugins: t('generated_plugins', 'Generated Plugins'),
  });

  // Extract just the natural language part from the streaming text (hide JSON).
  let streamingDisplay = $derived(() => {
    const raw = chat.currentStream;
    if (!raw) return '';
    const fenceIdx = raw.indexOf('```');
    if (fenceIdx > 0) return raw.substring(0, fenceIdx).trim();
    if (raw.trim().startsWith('{') || raw.trim().startsWith('[')) return '';
    return raw;
  });

  // Detect when the AI has moved past natural language into JSON/structure generation.
  let isBuildingStructure = $derived(() => {
    if (!chat.isStreaming || !chat.currentStream) return false;
    const raw = chat.currentStream.trim();
    return raw.includes('```') || raw.startsWith('{') || raw.startsWith('[');
  });

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
          <span class="text-muted text-[12px]">{t('editing', 'editing')}</span>
          <span>{currentPage?.title || currentPage?.template_key || t('select_page', 'Select page')}</span>
          {#if currentPage}
            <span class="w-[5px] h-[5px] rounded-full {currentPage.status === 'published' ? 'bg-green' : 'bg-gold'}"></span>
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
            {#each page.structures.filter(s => GLOBAL_TEMPLATES.includes(s.template_key)) as p}
              <div class="flex items-center rounded-[5px] {p.template_key === currentPage?.template_key ? 'bg-border/20' : ''}">
                <button
                  class="flex-1 flex items-center gap-[7px] px-2.5 py-[7px] border-none rounded-[5px] cursor-pointer text-[13px] font-body text-foreground bg-transparent"
                  onclick={() => selectPage(p)}
                >
                  <span class="w-[5px] h-[5px] rounded-full bg-copper/60"></span>
                  <span class="{p.template_key === currentPage?.template_key ? 'font-semibold' : 'font-normal'}">{p.title || p.template_key}</span>
                  <span class="text-[12px] text-muted font-mono ml-auto">{t('global', 'global')}</span>
                </button>
              </div>
            {/each}

            <!-- Separator -->
            {#if page.structures.some(s => !GLOBAL_TEMPLATES.includes(s.template_key))}
              <div class="h-px bg-border/30 my-1"></div>
            {/if}

            <!-- Page templates -->
            {#each page.structures.filter(s => !GLOBAL_TEMPLATES.includes(s.template_key)) as p}
              <div class="flex items-center rounded-[5px] {p.template_key === currentPage?.template_key ? 'bg-border/20' : ''} group">
                <button
                  class="flex-1 flex items-center gap-[7px] px-2.5 py-[7px] border-none rounded-[5px] cursor-pointer text-[13px] font-body text-foreground bg-transparent"
                  onclick={() => selectPage(p)}
                >
                  <span class="w-[5px] h-[5px] rounded-full {p.status === 'published' ? 'bg-green' : 'bg-gold'}"></span>
                  <span class="{p.template_key === currentPage?.template_key ? 'font-semibold' : 'font-normal'}">{p.title || p.template_key}</span>
                </button>
                <button
                  class="opacity-0 group-hover:opacity-100 px-1.5 py-1 border-none bg-transparent text-dim hover:text-gold cursor-pointer text-[12px] transition-opacity"
                  onclick={(e) => { e.stopPropagation(); requestDeleteTemplate(p.template_key); }}
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
                    onkeydown={(e) => { if (e.key === 'Enter') createTemplate(); if (e.key === 'Escape') { showNewTemplate = false; newTemplateName = ''; } }}
                    placeholder={t('template_name', 'Template name...')}
                    class="flex-1 px-2 py-[6px] bg-background border border-border/50 rounded-[5px] text-[12px] font-body text-foreground outline-none placeholder:text-dim"
                    autofocus
                  />
                  <button
                    class="px-2 py-[6px] border-none rounded-[5px] bg-copper text-white text-[12px] font-medium font-body cursor-pointer"
                    onclick={createTemplate}
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
      <button
        class="w-7 h-6 flex items-center justify-center rounded bg-transparent border-none text-muted hover:text-foreground cursor-pointer transition-colors"
        onclick={() => theme.toggle()}
        title={theme.mode === 'system' ? 'Theme: System' : theme.mode === 'light' ? 'Theme: Light' : 'Theme: Dark'}
      >
        {#if theme.mode === 'system'}
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="10" rx="1.5" stroke="currentColor" stroke-width="1.3"/><line x1="5" y1="15" x2="11" y2="15" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
        {:else if theme.resolved === 'light'}
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
          onclick={handlePreview}
        >{t('preview_link', 'Preview ↗')}</button>
      {/if}
      {#if currentPage?.status === 'published'}
        <button
          class="px-3 py-[5px] bg-transparent border border-border rounded-md text-muted-foreground cursor-pointer text-xs font-body font-medium hover:border-dim transition-colors"
          onclick={handleUnpublish}
        >{t('unpublish', 'Unpublish')}</button>
        <button
          class="px-3 py-[5px] bg-transparent border border-copper/30 rounded-md text-copper cursor-pointer text-xs font-body font-medium hover:border-copper/60 hover:bg-copper/5 transition-colors"
          onclick={viewPage}
        >{t('view_link', 'View ↗')}</button>
      {:else}
        <Button onclick={handlePublish}>{t('publish', 'Publish')}</Button>
      {/if}
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
              <span class="text-[12px] font-semibold uppercase tracking-[1.2px] pl-0.5 {m.role === 'user' ? 'text-muted' : 'text-copper'}">
                {m.role === 'user' ? t('you', 'You') : t('tekton', 'Tekton')}
                {#if m.is_summary}<span class="text-[12px] text-muted-foreground font-normal normal-case tracking-normal ml-1">· {t('summary_of_previous', 'summary of previous session')}</span>{/if}
              </span>
              <div class="rounded-[10px] text-[13.5px] leading-[1.65] px-3.5 py-3 {m.role === 'user' ? 'bg-card-hover text-foreground border-l-2 border-dim' : 'bg-card text-foreground/75 border-l-2 border-copper/20'}">
                {#if m.images?.length}
                  <div class="flex gap-1.5 mb-2 flex-wrap">
                    {#each m.images as img}
                      <img src={img.preview} alt="" class="w-10 h-10 object-cover rounded-[5px] border border-border/50 opacity-80" />
                    {/each}
                  </div>
                {/if}
                <div class="whitespace-pre-wrap">{m.content}</div>

                {#if m.structure}
                  <div class="inline-flex items-center gap-[5px] mt-2.5 px-2.5 py-1 rounded-[5px] bg-green/5 border border-green/10">
                    <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 5.5L4 7.5L8 3" stroke="#7dab6e" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span class="text-[12px] text-green font-medium">{t('preview_updated', 'Preview updated')}</span>
                  </div>
                {/if}
              </div>
            </div>
          {/each}

          <!-- Streaming indicator -->
          {#if chat.isStreaming}
            <div class="flex flex-col gap-[5px]">
              <span class="text-[12px] font-semibold uppercase tracking-[1.2px] pl-0.5 text-copper">{t('tekton', 'Tekton')}</span>
              <div class="rounded-[10px] text-[13.5px] leading-[1.65] px-3.5 py-3 bg-card text-foreground/75 border-l-2 border-copper/20">
                {#if streamingDisplay()}
                  <div class="whitespace-pre-wrap">{streamingDisplay()}</div>
                {/if}
                {#if isBuildingStructure()}
                  <div class="flex items-center gap-2.5 {streamingDisplay() ? 'mt-3 pt-3 border-t border-border/30' : ''}">
                    <div class="tk-cooking flex gap-[3px]">
                      <span></span><span></span><span></span>
                    </div>
                    <span class="text-[12px] text-copper/80 font-medium">{t('generating_structure', 'Generating structure…')}</span>
                  </div>
                {:else if !streamingDisplay()}
                  <div class="flex items-center gap-2">
                    <div class="tk-ember w-[7px] h-[7px] rounded-full bg-copper shrink-0"></div>
                    <span class="text-muted">{t('thinking', 'Thinking...')}</span>
                  </div>
                {/if}
              </div>
            </div>
          {/if}
          <div bind:this={messagesEnd}></div>
        </div>
      </div>

      <!-- Input area -->
      <div class="p-4 pt-2 border-t border-border">
        <!-- Image warning -->
        {#if imageWarning}
          <div class="text-[12px] text-red-400 mb-1.5 px-1">{imageWarning}</div>
        {/if}
        <!-- Image thumbnails -->
        {#if attachedImages.length > 0}
          <div class="flex gap-1.5 mb-2 flex-wrap">
            {#each attachedImages as img, i}
              <div class="relative group">
                <img
                  src={img.preview}
                  alt={img.name}
                  class="w-12 h-12 object-cover rounded-[6px] border border-border"
                />
                <button
                  class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-background border border-border text-dim text-[12px] leading-none flex items-center justify-center cursor-pointer opacity-0 group-hover:opacity-100 transition-opacity hover:text-foreground"
                  onclick={() => removeImage(i)}
                >×</button>
              </div>
            {/each}
          </div>
        {/if}

        <div class="flex gap-2 items-start bg-card rounded-[10px] border border-border px-3 py-2.5 focus-within:border-dim transition-colors">
          <button
            onclick={() => fileInputEl?.click()}
            class="w-[30px] h-[30px] rounded-[7px] border-none cursor-pointer flex items-center justify-center transition-all shrink-0 bg-transparent opacity-70 hover:opacity-100"
            aria-label="Attach image"
            title="Attach image"
          >
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <rect x="2" y="2" width="12" height="12" rx="2" stroke="#8a847d" stroke-width="1.3"/>
              <circle cx="5.5" cy="5.5" r="1.2" fill="#8a847d"/>
              <path d="M2 11l3-3.5 2.5 2.5L10 7.5l4 4.5" stroke="#8a847d" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <input
            bind:this={fileInputEl}
            type="file"
            accept="image/jpeg,image/png,image/gif,image/webp"
            multiple
            onchange={handleImageUpload}
            class="hidden"
          />
          <textarea
            bind:this={textareaEl}
            bind:value={input}
            onkeydown={handleKeydown}
            oninput={autoResize}
            onpaste={handlePaste}
            placeholder={t('describe_prompt', 'Describe what to build or change...')}
            rows="3"
            class="flex-1 bg-transparent border-none text-foreground text-[13px] leading-[1.5] resize-none outline-none font-body min-h-[54px] max-h-[150px] placeholder:text-dim"
          ></textarea>
          <button
            onclick={() => send()}
            disabled={(!input.trim() && attachedImages.length === 0) || chat.isStreaming}
            class="w-[30px] h-[30px] rounded-[7px] border-none cursor-pointer flex items-center justify-center transition-all shrink-0 {input.trim() || attachedImages.length > 0 ? 'bg-copper opacity-100' : 'bg-transparent opacity-60'}"
            aria-label="Send message"
          >
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
              <path d="M7 11V3M7 3L4 6M7 3l3 3" stroke={input.trim() || attachedImages.length > 0 ? '#ffffff' : 'var(--color-muted)'} stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
        <div class="flex items-center justify-start gap-1.5 mt-1.5 pl-0.5">
          {#each ['/fullstack', '/plugin', '/undo'] as cmd}
            <button
              class="px-[7px] py-[2px] bg-transparent rounded cursor-pointer text-[12px] font-mono transition-colors"
              style="border: 1px solid var(--color-muted); color: var(--color-muted-foreground);"
              onclick={() => { input = cmd + ' '; textareaEl?.focus(); }}
            >{cmd}</button>
          {/each}

          <!-- Clear chat -->
          {#if chat.messages.length > 0}
            <div class="relative ml-auto">
              <button
                class="px-[7px] py-[2px] bg-transparent border border-border/50 rounded text-dim cursor-pointer text-[12px] font-body transition-colors hover:text-muted hover:border-dim {isClearing ? 'opacity-50 pointer-events-none' : ''}"
                onclick={() => (showClearMenu = !showClearMenu)}
              >{isClearing ? t('clearing', 'Clearing...') : t('clear_chat', 'Clear chat')}</button>
              {#if showClearMenu}
                <!-- svelte-ignore a11y_no_static_element_interactions -->
                <div class="fixed inset-0 z-[29]" onclick={() => (showClearMenu = false)}></div>
                <div class="absolute bottom-[calc(100%+6px)] right-0 w-[200px] z-30 bg-card-hover border border-dim rounded-[8px] p-1 shadow-[0_12px_40px_var(--color-shadow)]">
                  <button
                    class="flex flex-col items-start w-full px-2.5 py-2 border-none rounded-[5px] cursor-pointer text-left bg-transparent hover:bg-border/20 transition-colors"
                    onclick={() => clearChat(true)}
                  >
                    <span class="text-[12px] text-foreground font-medium">{t('clear_with_summary', 'Clear with summary')}</span>
                    <span class="text-[12px] text-muted leading-tight mt-0.5">{t('clear_with_summary_desc', 'AI summarizes the conversation, then clears')}</span>
                  </button>
                  <button
                    class="flex flex-col items-start w-full px-2.5 py-2 border-none rounded-[5px] cursor-pointer text-left bg-transparent hover:bg-border/20 transition-colors"
                    onclick={() => clearChat(false)}
                  >
                    <span class="text-[12px] text-foreground font-medium">{t('clear_all', 'Clear all')}</span>
                    <span class="text-[12px] text-muted leading-tight mt-0.5">{t('clear_all_desc', 'Remove entire chat history')}</span>
                  </button>
                </div>
              {/if}
            </div>
          {:else}
            <span class="ml-auto text-[12px] text-muted">{t('shift_enter_newline', 'shift+enter for newline')}</span>
          {/if}
        </div>
      </div>
    </div>

    <!-- CENTER: PREVIEW / CODE -->
    {#if viewMode === 'code'}
      <div class="flex-1 flex flex-col overflow-hidden" style="background: var(--color-code-bg);">
        {#if page.currentStructure}
          <!-- Code view toolbar -->
          <div class="flex items-center border-b border-border shrink-0">
            {#each [
              { key: 'structure', label: t('structure_json', 'Structure JSON') },
              { key: 'html', label: t('rendered_html', 'Rendered HTML') },
            ] as t}
              <button
                class="px-4 py-2 border-none cursor-pointer text-[12px] font-medium font-body transition-colors {codeViewTab === t.key ? 'text-foreground border-b-2 border-copper -mb-px' : 'text-muted bg-transparent'}"
                onclick={() => { codeViewTab = t.key; codeEditorError = ''; }}
              >{t.label}</button>
            {/each}

            <div class="ml-auto flex items-center gap-2 mr-2">
              {#if codeEditorError}
                <span class="text-[12px] text-red-400 max-w-[300px] truncate">{codeEditorError}</span>
              {/if}
              {#if codeViewTab === 'structure' && codeEditorDirty}
                <button
                  class="px-2.5 py-1 bg-transparent border border-border rounded text-[12px] text-muted cursor-pointer hover:text-foreground hover:border-dim transition-colors"
                  onclick={resetCodeEditor}
                >{t('discard', 'Discard')}</button>
                <button
                  class="px-2.5 py-1 bg-copper border-none rounded text-[12px] text-background font-medium cursor-pointer hover:opacity-90 transition-opacity disabled:opacity-50"
                  onclick={saveCodeEdits}
                  disabled={codeSaving}
                >{codeSaving ? t('saving', 'Saving...') : t('save', 'Save')}</button>
              {:else}
                <button
                  class="px-2 py-1 bg-transparent border border-border rounded text-[12px] text-muted cursor-pointer hover:text-foreground hover:border-dim transition-colors"
                  onclick={() => {
                    const text = codeViewTab === 'structure' ? codeEditorValue : previewHtml;
                    navigator.clipboard.writeText(text);
                  }}
                >{t('copy', 'Copy')}</button>
              {/if}
            </div>
          </div>

          {#if codeViewTab === 'structure'}
            <textarea
              class="tk-code-editor flex-1 m-0 p-4 text-[13px] leading-[1.6] font-mono text-muted-foreground border-none outline-none resize-none"
              style="background: var(--color-code-bg); tab-size: 2;"
              spellcheck="false"
              value={codeEditorValue}
              oninput={(e) => { codeEditorValue = e.target.value; codeEditorDirty = true; codeEditorError = ''; }}
              onkeydown={(e) => {
                if ((e.metaKey || e.ctrlKey) && e.key === 's') {
                  e.preventDefault();
                  if (codeEditorDirty) saveCodeEdits();
                }
                if (e.key === 'Tab') {
                  e.preventDefault();
                  const ta = e.target;
                  const start = ta.selectionStart;
                  const end = ta.selectionEnd;
                  ta.value = ta.value.substring(0, start) + '  ' + ta.value.substring(end);
                  ta.selectionStart = ta.selectionEnd = start + 2;
                  codeEditorValue = ta.value;
                  codeEditorDirty = true;
                }
              }}
            ></textarea>
          {:else}
            <pre class="flex-1 overflow-auto m-0 p-4 text-[13px] leading-[1.6] font-mono text-muted-foreground whitespace-pre-wrap break-words" style="background: var(--color-code-bg);">{previewHtml || t('no_preview_html', 'No preview HTML generated yet.')}</pre>
          {/if}
        {:else}
          <div class="flex items-center justify-center h-full text-muted-foreground text-sm">
            {t('no_template_selected', 'No template selected.')}
          </div>
        {/if}
      </div>
    {:else}
      <div
        class="flex-1 flex items-stretch justify-center overflow-auto transition-all duration-300"
        style="padding: {viewport === 'desktop' ? '0' : '24px'}; background: var(--color-preview-bg); background-image: {viewport !== 'desktop' ? 'radial-gradient(var(--color-border-subtle) 1px, transparent 1px)' : 'none'}; background-size: 20px 20px;"
      >
        <div
          class="bg-white overflow-auto transition-all duration-300"
          style="width: {vw[viewport]}; max-width: 100%; {viewport === 'desktop' ? 'height: 100%;' : 'min-height: 600px;'} border-radius: {viewport === 'desktop' ? '0' : '8px'}; box-shadow: {viewport !== 'desktop' ? '0 8px 60px var(--color-shadow)' : 'none'};"
        >
          {#if previewHtml}
            <iframe
              srcdoc={previewHtml}
              title="Preview"
              class="w-full h-full border-none"
              sandbox="allow-same-origin allow-scripts"
            ></iframe>
          {:else if page.currentStructure}
            <div class="flex items-center justify-center h-full text-muted-foreground text-sm">
              {t('loading_preview', 'Loading preview...')}
            </div>
          {:else}
            <div class="flex flex-col items-center justify-center h-full gap-3 text-center px-8 text-muted-foreground" style="font-family: 'Outfit', sans-serif;">
              <div style="font-size: 14px;">{t('no_template_selected', 'No template selected')}</div>
              <div style="font-size: 12px;" class="text-muted">{t('no_template_hint', 'Use the chat to generate a page, or select a template from the dropdown above.')}</div>
            </div>
          {/if}
        </div>
      </div>
    {/if}

  </div>

  <!-- RIGHT DRAWER (overlay, slides in) -->
  <!-- svelte-ignore a11y_no_static_element_interactions -->
  <div
    class="tk-drawer-backdrop {sidebar ? 'tk-drawer-open' : ''}"
    onclick={() => (sidebar = null)}
  ></div>
  <div class="tk-drawer {sidebar ? 'tk-drawer-open' : ''}">
    <div class="px-3.5 py-2.5 border-b border-border flex items-center justify-between shrink-0">
      <span class="text-[12px] font-semibold uppercase tracking-[1.5px] text-muted-foreground">{sidebarLabels[sidebar] || ''}</span>
      <button
        class="bg-transparent border-none text-dim cursor-pointer text-[15px] leading-none p-0.5 hover:text-muted"
        onclick={() => (sidebar = null)}
      >×</button>
    </div>

    <div class="flex-1 overflow-auto p-2">
      <!-- Tree -->
      {#if sidebar === 'tree'}
        {#if tree.length === 0}
          <div class="text-xs text-muted text-center py-4">{t('no_components_yet', 'No components yet.')}</div>
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
                  style="background: {(TYPE_MAP[n.type]?.hue || '#7a746e')}15; color: {TYPE_MAP[n.type]?.hue || '#7a746e'};"
                >{TYPE_MAP[n.type]?.letter || '?'}</span>
                <span class="text-[11.5px] truncate {selectedComp === n.id ? 'text-foreground font-semibold' : 'text-muted-foreground font-normal'}">{n.label}</span>
                <span class="ml-auto text-[12px] text-muted font-mono shrink-0">{n.type}</span>
              </button>
            {/each}
          </div>
        {/if}

      <!-- Versions -->
      {:else if sidebar === 'versions'}
        {#if versions.length === 0}
          <div class="text-xs text-muted text-center py-4">{t('no_versions_yet', 'No versions yet.')}</div>
        {:else}
          <div class="flex flex-col gap-0.5">
            {#each versions as v}
              <div class="px-2 py-2 rounded-[5px] {v.is_active ? 'bg-copper/5' : ''} group/ver">
                <div class="flex items-center gap-2 mb-[3px]">
                  <span class="text-[12px] font-semibold font-mono {v.is_active ? 'text-copper' : 'text-dim'}">v{v.version_number}</span>
                  {#if v.label}
                    <span class="text-[12px] text-copper/70 font-medium truncate max-w-[100px]">{v.label}</span>
                  {/if}
                  {#if v.is_active}
                    <span class="text-[12px] text-green font-semibold">{t('current', 'CURRENT')}</span>
                  {/if}
                  <span class="ml-auto text-[12px] text-muted shrink-0">{relativeTime(v.created_at)}</span>
                </div>
                <div class="text-xs text-muted-foreground leading-[1.4]">{v.change_summary || { ai_generate: t('ai_generate', 'AI generated'), manual: t('manual_edit', 'Manual edit'), rollback: t('restored', 'Restored'), publish: t('published', 'Published') }[v.change_type] || v.change_type}</div>

                {#if editingVersion === v.version_number}
                  <div class="flex items-center gap-1 mt-1.5">
                    <input
                      type="text"
                      bind:value={editingLabel}
                      onkeydown={(e) => { if (e.key === 'Enter') saveVersionLabel(v.version_number); if (e.key === 'Escape') editingVersion = null; }}
                      placeholder={t('version_label', 'Version label...')}
                      class="flex-1 px-1.5 py-[3px] bg-background border border-border/50 rounded text-[12px] font-body text-foreground outline-none placeholder:text-dim"
                      autofocus
                    />
                    <button
                      class="px-1.5 py-[3px] border-none rounded bg-copper text-white text-[12px] font-medium cursor-pointer"
                      onclick={() => saveVersionLabel(v.version_number)}
                    >{t('save', 'Save')}</button>
                    <button
                      class="px-1.5 py-[3px] border-none rounded bg-transparent text-dim text-[12px] cursor-pointer hover:text-muted"
                      onclick={() => (editingVersion = null)}
                    >×</button>
                  </div>
                {:else}
                  <div class="flex items-center gap-1.5 mt-1.5">
                    {#if !v.is_active}
                      <button
                        class="px-2.5 py-[3px] bg-transparent border border-border rounded text-muted cursor-pointer text-[12px] font-body hover:border-dim hover:text-foreground transition-colors"
                        onclick={() => handleRollback(v.version_number)}
                      >{t('restore', 'Restore')}</button>
                    {/if}
                    <button
                      class="px-2 py-[3px] bg-transparent border-none text-dim cursor-pointer text-[12px] font-body opacity-0 group-hover/ver:opacity-100 transition-opacity hover:text-muted"
                      onclick={() => startRenameVersion(v)}
                    >{v.label ? t('rename', 'Rename') : t('label', 'Label')}</button>
                  </div>
                {/if}
              </div>
            {/each}
          </div>
        {/if}

      <!-- Fields -->
      {:else if sidebar === 'fields'}
        {#if fieldGroups.length === 0}
          <div class="text-xs text-muted text-center py-4">{t('no_field_groups_sidebar', 'No field groups yet.')}</div>
        {:else}
          <div class="flex flex-col gap-2">
            {#each fieldGroups as g}
              <div class="p-2.5 rounded-[7px] border border-border bg-card-hover">
                <div class="text-xs font-semibold text-foreground mb-0.5">{g.title}</div>
                <div class="text-[12px] text-dim font-mono mb-2">{g.slug}</div>
                {#each (g.fields || []) as f}
                  <div class="text-[12px] text-muted py-px font-mono flex items-center gap-1.5">
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
          <div class="mb-2">{t('plugins_hint', 'Generated plugins will appear here.')}</div>
          <div class="text-dim">{t('plugins_hint_cmd', 'Use /plugin in the chat to generate one.')}</div>
        </div>
      {/if}
    </div>
  </div>

  <ConfirmDialog
    open={deleteConfirm.open}
    title={t('delete_template', 'Delete template')}
    description={t('delete_template_desc', 'This will permanently delete the template and all its versions. This cannot be undone.')}
    confirmLabel={t('delete', 'Delete')}
    onconfirm={confirmDeleteTemplate}
    oncancel={() => (deleteConfirm = { open: false, key: '' })}
  />
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

  /* Cooking / structure generation indicator */
  .tk-code-editor {
    font-family: 'Fira Code', 'Consolas', 'Monaco', monospace;
    color: var(--color-muted-foreground);
    caret-color: var(--color-copper);
    word-wrap: break-word;
    white-space: pre-wrap;
    overflow: auto;
  }
  .tk-code-editor:focus {
    color: var(--color-foreground);
  }

  .tk-cooking {
    display: flex;
    align-items: center;
    gap: 3px;
  }
  .tk-cooking span {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--color-copper, #c97d3c);
    animation: cooking 1.4s ease-in-out infinite;
  }
  .tk-cooking span:nth-child(2) { animation-delay: 0.15s; }
  .tk-cooking span:nth-child(3) { animation-delay: 0.3s; }

  @keyframes cooking {
    0%, 80%, 100% { opacity: 0.25; transform: scale(0.75); }
    40% { opacity: 1; transform: scale(1.1); }
  }

  /* Drawer overlay */
  .tk-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 40;
    background: rgba(0, 0, 0, 0);
    pointer-events: none;
    transition: background 0.25s ease;
  }
  .tk-drawer-backdrop.tk-drawer-open {
    background: rgba(0, 0, 0, 0.35);
    pointer-events: auto;
  }

  /* Drawer panel */
  .tk-drawer {
    position: fixed;
    top: 48px; /* below topbar */
    right: 0;
    bottom: 0;
    width: 280px;
    z-index: 50;
    background: var(--color-card);
    border-left: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transform: translateX(100%);
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: -8px 0 40px rgba(0, 0, 0, 0);
  }
  .tk-drawer.tk-drawer-open {
    transform: translateX(0);
    box-shadow: -8px 0 40px rgba(0, 0, 0, 0.3);
  }
</style>
