<script>
  import { isContainerType, getComponentLabel } from '$lib/componentTree.js';

  let {
    component,
    depth = 0,
    selectedId = null,
    hoveredId = null,
    collapsedIds,
    dragState,
    typeMap,
    onSelect,
    onHover,
    onUnhover,
    onToggleCollapse,
    onDragStart,
    onDragOver,
    onDragLeave,
    onDrop,
    onDblClick,
  } = $props();

  const isContainer = $derived(isContainerType(component.type));
  const hasChildren = $derived(isContainer && component.children?.length > 0);
  const isCollapsed = $derived(collapsedIds.has(component.id));
  const isSelected = $derived(selectedId === component.id);
  const isHovered = $derived(hoveredId === component.id);
  const label = $derived(getComponentLabel(component));
  const typeMeta = $derived(typeMap[component.type] || { letter: '?', hue: '#7a746e' });

  // Drop target state — 'before' | 'inside' | 'after' | null
  let dropPosition = $state(null);
  // Whether this node is being dragged
  let isDragging = $state(false);

  function handleDragStart(e) {
    isDragging = true;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', component.id);
    onDragStart(component.id);
    // Minimal drag image
    e.dataTransfer.setDragImage(e.currentTarget, 0, 0);
  }

  function handleDragEnd() {
    isDragging = false;
  }

  function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.dataTransfer.dropEffect = 'move';

    const rect = e.currentTarget.getBoundingClientRect();
    const y = e.clientY - rect.top;
    const height = rect.height;
    const zone = height / 3;

    if (y < zone) {
      dropPosition = 'before';
    } else if (y > height - zone && !isContainer) {
      dropPosition = 'after';
    } else if (y > height - zone && isContainer) {
      dropPosition = isCollapsed || !hasChildren ? 'inside' : 'after';
    } else {
      dropPosition = isContainer ? 'inside' : 'after';
    }

    onDragOver(component.id, dropPosition);
  }

  function handleDragLeave(e) {
    // Only reset if we actually left the node (not entering a child)
    if (!e.currentTarget.contains(e.relatedTarget)) {
      dropPosition = null;
      onDragLeave();
    }
  }

  function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    const draggedId = e.dataTransfer.getData('text/plain');
    if (draggedId && draggedId !== component.id) {
      onDrop(draggedId, component.id, dropPosition || 'after');
    }
    dropPosition = null;
  }
</script>

<div
  class="tree-node"
  class:tree-node-dragging={isDragging}
  class:tree-node-drop-before={dropPosition === 'before' && !isDragging}
  class:tree-node-drop-after={dropPosition === 'after' && !isDragging}
  class:tree-node-drop-inside={dropPosition === 'inside' && !isDragging}
>
  <!-- The node row -->
  <div
    class="tree-row"
    class:tree-row-selected={isSelected}
    class:tree-row-hovered={isHovered && !isSelected}
    style="padding-left: {4 + depth * 14}px;"
    role="treeitem"
    tabindex="0"
    aria-selected={isSelected}
    aria-expanded={hasChildren ? !isCollapsed : undefined}
    draggable="true"
    ondragstart={handleDragStart}
    ondragend={handleDragEnd}
    ondragover={handleDragOver}
    ondragleave={handleDragLeave}
    ondrop={handleDrop}
    onclick={() => onSelect(component.id)}
    ondblclick={() => onDblClick(component.id)}
    onmouseenter={() => onHover(component.id)}
    onmouseleave={() => onUnhover()}
    onkeydown={(e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onSelect(component.id); }
      if (e.key === 'ArrowRight' && hasChildren && isCollapsed) { e.preventDefault(); onToggleCollapse(component.id); }
      if (e.key === 'ArrowLeft' && hasChildren && !isCollapsed) { e.preventDefault(); onToggleCollapse(component.id); }
    }}
  >
    <!-- Collapse toggle -->
    {#if hasChildren}
      <button
        class="tree-collapse-btn"
        onclick={(e) => { e.stopPropagation(); onToggleCollapse(component.id); }}
        tabindex="-1"
        aria-label={isCollapsed ? 'Expand' : 'Collapse'}
      >
        <svg width="10" height="10" viewBox="0 0 10 10" class="tree-chevron" class:tree-chevron-collapsed={isCollapsed}>
          <path d="M3 2 L7 5 L3 8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    {:else}
      <span class="tree-collapse-placeholder"></span>
    {/if}

    <!-- Type badge -->
    <span
      class="tree-type-badge"
      style="background: {typeMeta.hue}15; color: {typeMeta.hue};"
    >{typeMeta.letter}</span>

    <!-- Label -->
    <span class="tree-label" class:tree-label-selected={isSelected}>{label}</span>

    <!-- Type name -->
    <span class="tree-type-name">{component.type}</span>
  </div>

  <!-- Children (recursive) -->
  {#if hasChildren && !isCollapsed}
    <div class="tree-children" role="group" style="--guide-left: {18 + depth * 14}px;">
      {#each component.children as child (child.id)}
        <svelte:self
          component={child}
          depth={depth + 1}
          {selectedId}
          {hoveredId}
          {collapsedIds}
          {dragState}
          {typeMap}
          {onSelect}
          {onHover}
          {onUnhover}
          {onToggleCollapse}
          {onDragStart}
          {onDragOver}
          {onDragLeave}
          {onDrop}
          {onDblClick}
        />
      {/each}
    </div>
  {/if}
</div>

<style>
  .tree-node {
    position: relative;
  }

  .tree-node-dragging {
    opacity: 0.35;
  }

  .tree-node-drop-before::before {
    content: '';
    position: absolute;
    top: 0;
    left: 8px;
    right: 8px;
    height: 2px;
    background: var(--copper);
    border-radius: 1px;
    z-index: 5;
    pointer-events: none;
  }

  .tree-node-drop-after::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 8px;
    right: 8px;
    height: 2px;
    background: var(--copper);
    border-radius: 1px;
    z-index: 5;
    pointer-events: none;
  }

  .tree-node-drop-inside > .tree-row {
    outline: 1.5px solid var(--copper);
    outline-offset: -1.5px;
    border-radius: 5px;
  }

  .tree-row {
    position: relative;
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 3px 6px;
    border-radius: 5px;
    cursor: pointer;
    border: 1px solid transparent;
    min-height: 26px;
    user-select: none;
    transition: background 0.1s, border-color 0.1s;
  }

  .tree-row:hover {
    background: var(--surface-alt, rgba(127, 127, 127, 0.06));
  }

  .tree-row-selected {
    background: color-mix(in srgb, var(--copper) 6%, transparent);
    border-color: color-mix(in srgb, var(--copper) 12%, transparent);
  }

  .tree-row-hovered {
    background: color-mix(in srgb, var(--copper) 4%, transparent);
  }

  .tree-collapse-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 14px;
    height: 14px;
    padding: 0;
    border: none;
    background: transparent;
    color: var(--muted);
    cursor: pointer;
    flex-shrink: 0;
    border-radius: 3px;
    transition: background 0.1s;
  }

  .tree-collapse-btn:hover {
    background: var(--surface-alt, rgba(127, 127, 127, 0.1));
    color: var(--muted-foreground);
  }

  .tree-chevron {
    transition: transform 0.15s ease;
  }

  .tree-chevron-collapsed {
    transform: rotate(0deg);
  }

  .tree-chevron:not(.tree-chevron-collapsed) {
    transform: rotate(90deg);
  }

  .tree-collapse-placeholder {
    width: 14px;
    flex-shrink: 0;
  }

  .tree-type-badge {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    flex-shrink: 0;
    font-size: 8px;
    font-weight: 700;
    font-family: var(--font-mono);
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .tree-label {
    font-size: 11.5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--muted-foreground);
    font-weight: 400;
    min-width: 0;
  }

  .tree-label-selected {
    color: var(--foreground);
    font-weight: 600;
  }

  .tree-type-name {
    margin-left: auto;
    font-size: 10px;
    color: var(--dim);
    font-family: var(--font-mono);
    flex-shrink: 0;
    white-space: nowrap;
  }

  .tree-children {
    position: relative;
  }

  .tree-children::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: var(--guide-left, 18px);
    width: 1px;
    background: var(--border, rgba(127, 127, 127, 0.15));
  }
</style>
