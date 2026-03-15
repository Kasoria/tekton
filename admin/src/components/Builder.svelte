<script>
  import { untrack } from 'svelte';
  import { ConfirmDialog } from '$lib/components/ui/dialog/index.js';
  import { createChatStore } from '$lib/stores/chat.svelte.js';
  import { createPageStore } from '$lib/stores/page.svelte.js';
  import { createEditorStore } from '$lib/stores/editor.svelte.js';
  import { createBridge } from '$lib/bridge.js';
  import { flattenTree, isContainerType, findById, getComponentPath } from '$lib/componentTree.js';
  import { api } from '$lib/api.js';
  import { t } from '$lib/i18n.svelte.js';
  import { createThemeStore } from '$lib/stores/theme.svelte.js';
  import ComponentTreeNode from './ComponentTreeNode.svelte';
  import DesignTokensPanel from './DesignTokensPanel.svelte';
  import { designTokensStore } from '$lib/stores/designTokens.svelte.js';
  import TopToolbar from './builder/TopToolbar.svelte';
  import ChatPanel from './builder/ChatPanel.svelte';
  import BuildPanel from './builder/BuildPanel.svelte';
  import Inspector from './builder/Inspector.svelte';
  import { TYPE_MAP } from '$lib/elementCategories.js';

  let { onBack, initialTemplateKey = null } = $props();

  const theme = createThemeStore();

  const chat = createChatStore();
  const page = createPageStore();

  // Bridge and editor store
  let bridge = $state(null);
  let iframeEl = $state(null);
  const editor = createEditorStore(page, () => bridge);

  let input = $state('');
  let leftMode = $state('ai'); // 'ai' | 'build'
  let viewport = $state('desktop');
  let sidebar = $state(null);
  let selectedComp = $state(null);
  let previewHtml = $state('');
  let versions = $state([]);
  let fieldGroups = $state([]);
  let deleteConfirm = $state({ open: false, key: '' });
  let attachedImages = $state([]);
  let imageWarning = $state('');
  let editMode = $state(false);
  let drawerWidth = $state(280);
  let isResizingDrawer = $state(false);
  let leftPanelWidth = $state(400);
  let isResizingLeft = $state(false);
  let inspectorWidth = $state(290);
  let isResizingInspector = $state(false);
  let viewMode = $state('preview'); // 'preview' | 'code'
  let codeViewTab = $state('structure'); // 'structure' | 'html'
  let codeEditorValue = $state('');
  let codeEditorDirty = $state(false);
  let codeEditorError = $state('');
  let codeSaving = $state(false);

  const GLOBAL_TEMPLATES = ['header', 'footer'];

  // Current selected page
  let currentPage = $state(null);

  $effect(() => {
    page.loadStructures();
    designTokensStore.load();
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

  // Refresh preview only on structural changes (load, AI generate, code save) — NOT on style/prop edits.
  // structureVersion is the only tracked dependency; everything else is untracked.
  $effect(() => {
    const _v = page.structureVersion;
    untrack(() => {
      refreshPreview();
    });
  });

  // Sync editor breakpoint with viewport selector
  $effect(() => {
    editor.setBreakpoint(viewport);
  });

  // Sync edit mode with iframe bridge
  $effect(() => {
    if (!bridge) return;
    if (editMode) {
      bridge.send('tekton:enableEditor');
    } else {
      bridge.send('tekton:disableEditor');
      selectedComp = null;
    }
  });

  // Initialize bridge when iframe loads
  function handleIframeLoad() {
    if (bridge) bridge.destroy();
    if (!iframeEl) return;
    bridge = createBridge(iframeEl);

    bridge.on('tekton:ready', () => {
      if (editMode) {
        bridge.send('tekton:enableEditor');
      }
      if (editor.selectedComponentId) {
        bridge.send('tekton:select', { componentId: editor.selectedComponentId });
      }
    });

    bridge.on('tekton:componentClick', ({ componentId, componentType }) => {
      if (componentId) {
        editor.selectComponent(componentId);
        selectedComp = componentId;
      } else {
        editor.deselectComponent();
        selectedComp = null;
      }
    });

    bridge.on('tekton:componentHover', ({ componentId }) => {
      editor.hoveredComponentId; // read for reactivity, actual state is in editor
    });

    bridge.on('tekton:componentLeave', () => {});

    bridge.on('tekton:contentEdit', ({ componentId, prop, value, isContentSource }) => {
      editor.updateContent(prop, value, isContentSource);
    });

    bridge.on('tekton:editStart', ({ componentId }) => {
      editor.startEditing(componentId);
    });

    bridge.on('tekton:editEnd', ({ componentId }) => {
      editor.stopEditing();
    });
  }

  // Handle keyboard shortcuts
  function handleGlobalKeydown(e) {
    if (e.key === 'Escape' && editor.selectedComponentId && !editor.isEditing) {
      editor.deselectComponent();
      selectedComp = null;
    }
    // Undo: Ctrl+Z / Cmd+Z
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
      // Don't intercept when typing in inputs
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
      e.preventDefault();
      editor.undo();
    }
    // Redo: Ctrl+Shift+Z / Cmd+Shift+Z
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && e.shiftKey) {
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
      e.preventDefault();
      editor.redo();
    }
    // Delete selected component
    if ((e.key === 'Delete' || e.key === 'Backspace') && editor.selectedComponentId && !editor.isEditing) {
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
      e.preventDefault();
      const id = editor.selectedComponentId;
      editor.deselectComponent();
      selectedComp = null;
      page.deleteComponent(id);
      editor.markDirty();
    }
  }

  // Cleanup on destroy
  $effect(() => {
    return () => {
      if (bridge) bridge.destroy();
      editor.destroy();
    };
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

    const result = await chat.sendMessage(val, type, imagesToSend);
    if (result?.structure) {
      page.setStructure(result.structure);
      // Reload sidebar data
      if (currentPage) {
        loadSidebarData(currentPage.template_key);
        page.loadStructures();
      }
    }
    if (result?.designTokens) {
      designTokensStore.load();
      if (bridge) {
        bridge.send('tekton:updateTokens', { tokens: result.designTokens });
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

  async function handlePublish() {
    if (!currentPage || !page.currentStructure) return;
    const result = await page.saveCurrentStructure('publish', 'Published', 'publish');
    currentPage = {
      ...currentPage,
      url: result?.url || currentPage.url,
      preview_url: result?.preview_url || currentPage.preview_url,
      status: 'publish',
    };
    page.loadStructures();
    loadSidebarData(currentPage.template_key);
  }

  async function handleUnpublish() {
    if (!currentPage || !page.currentStructure) return;
    await page.saveCurrentStructure('manual', 'Unpublished', 'draft');
    currentPage = { ...currentPage, url: null, preview_url: null, status: 'draft' };
    page.loadStructures();
    loadSidebarData(currentPage.template_key);
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

  async function createTemplate(name) {
    if (!name) return;
    const key = name.toLowerCase().replace(/[^a-z0-9\u00C0-\u024F]+/g, '-').replace(/^-|-$/g, '');
    if (!key) return;

    try {
      await api.saveStructure({
        template_key: key,
        title: name,
        components: [],
        styles: [],
        status: 'draft',
      });

      await page.loadStructures();
      const created = page.structures.find(s => s.template_key === key);
      if (created) selectPage(created);
    } catch (err) {
      console.error('[Tekton] Failed to create template:', err);
    }
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

  async function clearChat(withSummary = false) {
    if (!currentPage) return;
    try {
      if (withSummary) {
        await api.summarizeAndClearChat(currentPage.template_key);
      } else {
        await api.clearChat(currentPage.template_key);
      }
      const history = await api.getChatHistory(currentPage.template_key);
      chat.loadHistory(history);
    } catch { /* ignore */ }
  }

  let tree = $derived(flattenTree(page.currentStructure?.components || []));

  // Component tree collapse/drag state
  let collapsedIds = $state(new Set());
  let dragState = $state({ draggedId: null, overId: null, position: null });

  function toggleCollapse(id) {
    const next = new Set(collapsedIds);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    collapsedIds = next;
  }

  function expandToComponent(id) {
    const components = page.currentStructure?.components;
    if (!components) return;
    const path = getComponentPath(components, id);
    if (!path || path.length <= 1) return;
    const next = new Set(collapsedIds);
    for (let i = 0; i < path.length - 1; i++) next.delete(path[i]);
    collapsedIds = next;
  }

  function handleTreeDragStart(id) {
    dragState = { draggedId: id, overId: null, position: null };
  }

  function handleTreeDragOver(overId, position) {
    dragState = { ...dragState, overId, position };
  }

  function handleTreeDragLeave() {
    dragState = { ...dragState, overId: null, position: null };
  }

  function handleTreeDrop(draggedId, targetId, position) {
    dragState = { draggedId: null, overId: null, position: null };
    const components = page.currentStructure?.components;
    if (!components || draggedId === targetId) return;

    // Don't allow dropping into own descendants
    const path = getComponentPath(components, targetId);
    if (path && path.includes(draggedId)) return;

    // Figure out parent + index for the drop
    let targetParentId = null;
    let targetIndex = 0;

    if (position === 'inside') {
      // Drop as first child of target (must be container)
      if (!isContainerType(findById(components, targetId)?.type)) return;
      targetParentId = targetId;
      targetIndex = 0;
    } else {
      // Drop before/after target — find target's parent
      const findParent = (comps, id, parent = null) => {
        for (let i = 0; i < comps.length; i++) {
          if (comps[i].id === id) return { parentId: parent, index: i };
          if (comps[i].children?.length) {
            const found = findParent(comps[i].children, id, comps[i].id);
            if (found) return found;
          }
        }
        return null;
      };
      const loc = findParent(components, targetId);
      if (!loc) return;
      targetParentId = loc.parentId;
      targetIndex = position === 'before' ? loc.index : loc.index + 1;
    }

    page.moveComponent(draggedId, targetParentId, targetIndex);
    editor.markDirty();
  }

  let isResizingAny = $derived(isResizingDrawer || isResizingLeft || isResizingInspector);

  function startDrawerResize(e) {
    e.preventDefault();
    isResizingDrawer = true;
    const startX = e.clientX;
    const startWidth = drawerWidth;

    function onMove(ev) {
      const delta = startX - ev.clientX;
      drawerWidth = Math.min(600, Math.max(200, startWidth + delta));
    }
    function onUp() {
      isResizingDrawer = false;
      window.removeEventListener('mousemove', onMove);
      window.removeEventListener('mouseup', onUp);
    }
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
  }

  function startLeftResize(e) {
    e.preventDefault();
    isResizingLeft = true;
    const startX = e.clientX;
    const startWidth = leftPanelWidth;

    function onMove(ev) {
      const delta = ev.clientX - startX;
      leftPanelWidth = Math.min(700, Math.max(260, startWidth + delta));
    }
    function onUp() {
      isResizingLeft = false;
      window.removeEventListener('mousemove', onMove);
      window.removeEventListener('mouseup', onUp);
    }
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
  }

  function startInspectorResize(e) {
    e.preventDefault();
    isResizingInspector = true;
    const startX = e.clientX;
    const startWidth = inspectorWidth;

    function onMove(ev) {
      const delta = startX - ev.clientX;
      inspectorWidth = Math.min(500, Math.max(220, startWidth + delta));
    }
    function onUp() {
      isResizingInspector = false;
      window.removeEventListener('mousemove', onMove);
      window.removeEventListener('mouseup', onUp);
    }
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
  }

  const vw = { desktop: '100%', tablet: '768px', mobile: '375px' };

  const sidebarLabels = $derived({
    tree: t('component_tree', 'Component Tree'),
    versions: t('version_history', 'Version History'),
    fields: t('field_groups', 'Field Groups'),
    plugins: t('generated_plugins', 'Generated Plugins'),
    design: t('dt_design_tokens', 'Design Tokens'),
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

<svelte:window onkeydown={handleGlobalKeydown} />

<div class="tk-builder" style:user-select={isResizingAny ? 'none' : null}>
  <!-- Grain -->
  <div
    class="fixed inset-0 pointer-events-none z-[999] opacity-[0.022] mix-blend-overlay"
    style="background-image: url(&quot;data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E&quot;)"
  ></div>

  <!-- TOP BAR -->
  <TopToolbar
    {currentPage}
    structures={page.structures}
    bind:viewport
    bind:viewMode
    bind:editMode
    bind:sidebar
    {versions}
    {theme}
    {editor}
    {onBack}
    onSelectPage={selectPage}
    onCreateTemplate={createTemplate}
    onRequestDelete={requestDeleteTemplate}
    onPublish={handlePublish}
    onUnpublish={handleUnpublish}
    onPreview={handlePreview}
    onViewPage={viewPage}
  />

  <!-- MAIN -->
  <div class="flex flex-1 overflow-hidden">

    <!-- LEFT PANEL -->
    <div class="shrink-0 flex flex-col bg-background border-r border-border relative" style="width: {leftPanelWidth}px;">
      <!-- Resize handle -->
      <!-- svelte-ignore a11y_no_static_element_interactions -->
      <div class="tk-resize-handle tk-resize-right" onmousedown={startLeftResize}></div>
      <!-- Mode toggle -->
      <div class="flex border-b border-border shrink-0">
        <button
          class="flex-1 px-3 py-2 border-none cursor-pointer text-[12px] font-semibold font-body transition-colors {leftMode === 'ai' ? 'text-copper bg-copper/5 border-b-2 border-copper' : 'text-muted bg-transparent hover:text-muted-foreground'}"
          onclick={() => (leftMode = 'ai')}
        >{t('ai_chat', 'AI Chat')}</button>
        <button
          class="flex-1 px-3 py-2 border-none cursor-pointer text-[12px] font-semibold font-body transition-colors {leftMode === 'build' ? 'text-copper bg-copper/5 border-b-2 border-copper' : 'text-muted bg-transparent hover:text-muted-foreground'}"
          onclick={() => (leftMode = 'build')}
        >{t('build', 'Build')}</button>
      </div>

      {#if leftMode === 'ai'}
        <ChatPanel
          {chat}
          bind:input
          bind:attachedImages
          {imageWarning}
          {streamingDisplay}
          {isBuildingStructure}
          onSend={() => send()}
          onImageUpload={handleImageUpload}
          onRemoveImage={removeImage}
          onPaste={handlePaste}
          onClearChat={clearChat}
        />
      {:else}
        <BuildPanel
          {page}
          {editor}
          bind:selectedComp
          typeMap={TYPE_MAP}
          bind:collapsedIds
          bind:dragState
          onTreeSelect={(id) => { selectedComp = id; editor.selectComponent(id); expandToComponent(id); }}
          onTreeDblClick={(id) => { selectedComp = id; editor.selectComponent(id); editMode = true; }}
          onToggleCollapse={toggleCollapse}
          onTreeDragStart={handleTreeDragStart}
          onTreeDragOver={handleTreeDragOver}
          onTreeDragLeave={handleTreeDragLeave}
          onTreeDrop={handleTreeDrop}
          onSwitchToAI={(prompt) => { leftMode = 'ai'; input = prompt; send(prompt); }}
        />
      {/if}
    </div>

    <!-- Resize overlay: blocks iframe from stealing mouse events during drag -->
    {#if isResizingAny}
      <div class="fixed inset-0 z-50" style="cursor: col-resize;"></div>
    {/if}

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
              bind:this={iframeEl}
              srcdoc={previewHtml}
              title="Preview"
              class="w-full h-full border-none"
              sandbox="allow-same-origin allow-scripts"
              onload={handleIframeLoad}
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

    <!-- RIGHT PANEL: INSPECTOR (inline, shown when component selected in edit/build mode) -->
    {#if (editMode || leftMode === 'build') && editor.selectedComponentId}
      <div class="shrink-0 border-l border-border bg-card relative" style="width: {inspectorWidth}px;">
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div class="tk-resize-handle tk-resize-left" onmousedown={startInspectorResize}></div>
        <Inspector {editor} {page} />
      </div>
    {/if}

  </div>

  <!-- RIGHT DRAWER (overlay, slides in) -->
  <!-- svelte-ignore a11y_no_static_element_interactions -->
  <div
    class="tk-drawer-backdrop {sidebar ? 'tk-drawer-open' : ''}"
    onclick={() => (sidebar = null)}
  ></div>
  <div class="tk-drawer {sidebar ? 'tk-drawer-open' : ''}" style="width: {drawerWidth}px;">
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div class="tk-drawer-resize" onmousedown={startDrawerResize}></div>
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
        {#if !page.currentStructure?.components?.length}
          <div class="text-xs text-muted text-center py-4">{t('no_components_yet', 'No components yet.')}</div>
        {:else}
          <div class="flex items-center gap-1 mb-1.5 px-1">
            <button
              class="text-[10px] text-muted hover:text-muted-foreground bg-transparent border-none cursor-pointer px-1 py-0.5 rounded"
              onclick={() => { collapsedIds = new Set(); }}
            >{t('expand_all', 'Expand all')}</button>
            <span class="text-dim text-[10px]">·</span>
            <button
              class="text-[10px] text-muted hover:text-muted-foreground bg-transparent border-none cursor-pointer px-1 py-0.5 rounded"
              onclick={() => {
                const ids = new Set();
                const collect = (comps) => { for (const c of comps) { if (isContainerType(c.type) && c.children?.length) { ids.add(c.id); collect(c.children); } } };
                collect(page.currentStructure.components);
                collapsedIds = ids;
              }}
            >{t('collapse_all', 'Collapse all')}</button>
            <span class="ml-auto text-[10px] text-dim font-mono">{tree.length}</span>
          </div>
          <div role="tree" class="flex flex-col">
            {#each page.currentStructure.components as component (component.id)}
              <ComponentTreeNode
                {component}
                depth={0}
                selectedId={selectedComp}
                hoveredId={editor.hoveredComponentId}
                {collapsedIds}
                {dragState}
                typeMap={TYPE_MAP}
                onSelect={(id) => { selectedComp = id; editor.selectComponent(id); expandToComponent(id); }}
                onDblClick={(id) => { selectedComp = id; editor.selectComponent(id); editMode = true; sidebar = null; }}
                onHover={(id) => editor.hoverComponent(id)}
                onUnhover={() => editor.unhoverComponent()}
                onToggleCollapse={toggleCollapse}
                onDragStart={handleTreeDragStart}
                onDragOver={handleTreeDragOver}
                onDragLeave={handleTreeDragLeave}
                onDrop={handleTreeDrop}
              />
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

      <!-- Design Tokens -->
      {:else if sidebar === 'design'}
        <DesignTokensPanel store={designTokensStore} onTokensChanged={(tokens) => {
          if (bridge) {
            bridge.send('tekton:updateTokens', { tokens });
          }
        }} />
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

  /* WP style overrides within builder */
  .tk-builder :global(h1),
  .tk-builder :global(h2),
  .tk-builder :global(h3),
  .tk-builder :global(h4),
  .tk-builder :global(h5),
  .tk-builder :global(h6) {
    color: var(--color-foreground);
    font-family: var(--font-heading);
    font-weight: 700;
    line-height: 1.2;
    margin: 0;
    padding: 0;
  }

  .tk-builder :global(p) {
    color: inherit;
    font-size: 0.875rem;
    font-family: var(--font-body);
    margin: 0;
    line-height: 1.5;
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
  /* Code editor */
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
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s ease;
    box-shadow: -8px 0 40px rgba(0, 0, 0, 0);
  }
  .tk-drawer.tk-drawer-open {
    transform: translateX(0);
    box-shadow: -8px 0 40px rgba(0, 0, 0, 0.3);
  }

  /* Panel resize handles */
  .tk-resize-handle {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 6px;
    cursor: col-resize;
    z-index: 10;
  }
  .tk-resize-handle:hover,
  .tk-resize-handle:active {
    background: var(--copper);
    opacity: 0.3;
  }
  .tk-resize-right {
    right: -3px;
  }
  .tk-resize-left {
    left: -3px;
  }

  /* Drawer resize handle */
  .tk-drawer-resize {
    position: absolute;
    top: 0;
    left: -3px;
    width: 6px;
    bottom: 0;
    cursor: col-resize;
    z-index: 55;
  }
  .tk-drawer-resize:hover,
  .tk-drawer-resize:active {
    background: var(--copper);
    opacity: 0.3;
  }
</style>
