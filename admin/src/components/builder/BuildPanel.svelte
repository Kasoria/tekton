<script>
  import { t } from '$lib/i18n.svelte.js';
  import { isContainerType, findParentOf } from '$lib/componentTree.js';
  import { createComponent } from '$lib/componentFactory.js';
  import ElementPalette from './ElementPalette.svelte';
  import ComponentTreeNode from '../ComponentTreeNode.svelte';

  let {
    page,
    editor,
    selectedComp = $bindable(null),
    typeMap = {},
    // Tree state
    collapsedIds = $bindable(new Set()),
    dragState = $bindable({ draggedId: null, overId: null, position: null }),
    // Tree event handlers
    onTreeSelect,
    onTreeDblClick,
    onTreeDragStart,
    onTreeDragOver,
    onTreeDragLeave,
    onTreeDrop,
    onToggleCollapse,
    // Quick AI
    onSwitchToAI,
  } = $props();

  let buildTab = $state('elements'); // 'elements' | 'tree'
  let quickAiInput = $state('');

  function handleInsert(type) {
    const comp = createComponent(type);
    const components = page.currentStructure?.components;
    if (!components) return;

    if (selectedComp) {
      const selectedEl = findById(components, selectedComp);
      if (selectedEl && isContainerType(selectedEl.type)) {
        // Insert as last child of selected container
        page.insertComponent(comp, selectedComp, -1);
      } else {
        // Insert as sibling after selected
        const parent = findParentOf(components, selectedComp);
        if (parent) {
          page.insertComponent(comp, parent.parentId, parent.index + 1);
        } else {
          // Selected is at root level
          const rootIdx = components.findIndex(c => c.id === selectedComp);
          page.insertComponent(comp, null, rootIdx + 1);
        }
      }
    } else {
      // Nothing selected — insert at root level
      page.insertComponent(comp, null, -1);
    }

    // Select the new component
    selectedComp = comp.id;
    editor.selectComponent(comp.id);
    editor.markDirty();
  }

  // Import findById locally since it's used in handleInsert
  function findById(comps, id) {
    for (const c of comps) {
      if (c.id === id) return c;
      if (c.children?.length) {
        const found = findById(c.children, id);
        if (found) return found;
      }
    }
    return null;
  }

  function handleQuickAI(e) {
    if (e.key === 'Enter' && !e.shiftKey && quickAiInput.trim()) {
      e.preventDefault();
      onSwitchToAI?.(quickAiInput.trim());
      quickAiInput = '';
    }
  }
</script>

<div class="flex flex-col h-full">
  <!-- Sub-tabs -->
  <div class="flex border-b border-border shrink-0">
    {#each [
      { key: 'elements', label: t('elements', 'Elements') },
      { key: 'tree', label: t('structure', 'Structure') },
    ] as tab}
      <button
        class="flex-1 px-3 py-2 border-none cursor-pointer text-[12px] font-semibold font-body uppercase tracking-[1px] transition-colors {buildTab === tab.key ? 'text-foreground border-b-2 border-copper bg-transparent' : 'text-muted bg-transparent hover:text-muted-foreground'}"
        onclick={() => (buildTab = tab.key)}
      >{tab.label}</button>
    {/each}
  </div>

  <!-- Tab content -->
  <div class="flex-1 overflow-hidden flex flex-col">
    {#if buildTab === 'elements'}
      <ElementPalette onInsert={handleInsert} />
    {:else}
      <!-- Structure tree -->
      <div class="flex-1 overflow-auto p-2">
        {#if !page.currentStructure?.components?.length}
          <div class="text-xs text-muted text-center py-4">{t('no_components_yet', 'No components yet.')}</div>
        {:else}
          <div role="tree" class="flex flex-col">
            {#each page.currentStructure.components as component (component.id)}
              <ComponentTreeNode
                {component}
                depth={0}
                selectedId={selectedComp}
                hoveredId={editor.hoveredComponentId}
                {collapsedIds}
                {dragState}
                {typeMap}
                onSelect={onTreeSelect}
                onDblClick={onTreeDblClick}
                onHover={(id) => editor.hoverComponent(id)}
                onUnhover={() => editor.unhoverComponent()}
                onToggleCollapse={onToggleCollapse}
                onDragStart={onTreeDragStart}
                onDragOver={onTreeDragOver}
                onDragLeave={onTreeDragLeave}
                onDrop={onTreeDrop}
              />
            {/each}
          </div>
        {/if}
      </div>
    {/if}
  </div>

  <!-- Quick AI bar -->
  <div class="shrink-0 p-2.5 border-t border-border">
    <div class="flex gap-1.5 items-center bg-card-hover rounded-[7px] border border-border/50 px-2.5 py-1.5 focus-within:border-dim transition-colors">
      <svg width="12" height="12" viewBox="0 0 16 16" fill="none" class="shrink-0 text-dim">
        <path d="M8 2v4M8 10v4M2 8h4M10 8h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
      </svg>
      <input
        type="text"
        bind:value={quickAiInput}
        onkeydown={handleQuickAI}
        placeholder={t('quick_ai', 'Ask AI...')}
        class="flex-1 bg-transparent border-none text-[12px] text-foreground outline-none font-body placeholder:text-dim"
      />
    </div>
  </div>
</div>
