import { findById } from '../componentTree.js';

/**
 * Svelte 5 runes store for inline editor state.
 *
 * @param {object} pageStore - The page store instance (for reading current structure).
 * @param {object|null} bridge - The PostMessage bridge instance (set after iframe loads).
 */
export function createEditorStore(pageStore, getBridge) {
  let selectedComponentId = $state(null);
  let hoveredComponentId = $state(null);
  let isEditing = $state(false);
  let activeBreakpoint = $state('desktop');
  let dirty = $state(false);

  let saveTimer = null;

  let selectedComponent = $derived.by(() => {
    if (!selectedComponentId || !pageStore.currentStructure?.components) return null;
    return findById(pageStore.currentStructure.components, selectedComponentId);
  });

  let selectedStyles = $derived.by(() => {
    if (!selectedComponent?.styles) return {};
    return selectedComponent.styles[activeBreakpoint] || {};
  });

  function selectComponent(id) {
    selectedComponentId = id;
    const bridge = getBridge();
    if (bridge && id) {
      bridge.send('tekton:select', { componentId: id });
    }
  }

  function deselectComponent() {
    selectedComponentId = null;
    isEditing = false;
    const bridge = getBridge();
    if (bridge) {
      bridge.send('tekton:deselect');
    }
  }

  function hoverComponent(id) {
    hoveredComponentId = id;
    const bridge = getBridge();
    if (bridge && id) {
      bridge.send('tekton:hover', { componentId: id });
    }
  }

  function unhoverComponent() {
    hoveredComponentId = null;
    const bridge = getBridge();
    if (bridge) {
      bridge.send('tekton:unhover');
    }
  }

  function startEditing(id) {
    isEditing = true;
  }

  function stopEditing() {
    isEditing = false;
  }

  function setBreakpoint(bp) {
    activeBreakpoint = bp;
  }

  function markDirty() {
    dirty = true;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      if (dirty) commitChanges();
    }, 2000);
  }

  /**
   * Update a style property — live preview via bridge + update store.
   */
  function updateStyle(property, value) {
    if (!selectedComponentId) return;
    pageStore.updateComponentStyles(selectedComponentId, activeBreakpoint, { [property]: value });
    const bridge = getBridge();
    if (bridge) {
      bridge.send('tekton:updateStyle', { componentId: selectedComponentId, property, value });
    }
    markDirty();
  }

  /**
   * Update a prop value on the selected component.
   */
  function updateProp(propName, value) {
    if (!selectedComponentId) return;
    pageStore.updateComponentProp(selectedComponentId, propName, value);
    markDirty();
  }

  /**
   * Update text content, handling content sources.
   */
  function updateContent(prop, value, isContentSource) {
    if (!selectedComponentId) return;
    if (isContentSource) {
      pageStore.updateComponentContent(selectedComponentId, prop, value);
    } else {
      pageStore.updateComponentProp(selectedComponentId, prop, value);
    }
    markDirty();
  }

  /**
   * Persist changes to backend.
   */
  async function commitChanges() {
    if (!dirty || !pageStore.currentStructure) return;
    dirty = false;
    clearTimeout(saveTimer);
    try {
      await pageStore.saveCurrentStructure('manual', 'Inline edit');
    } catch (err) {
      console.error('[Tekton] Save failed:', err);
      dirty = true;
    }
  }

  function destroy() {
    clearTimeout(saveTimer);
    if (dirty) commitChanges();
  }

  return {
    get selectedComponentId() { return selectedComponentId; },
    get hoveredComponentId() { return hoveredComponentId; },
    get isEditing() { return isEditing; },
    get activeBreakpoint() { return activeBreakpoint; },
    get dirty() { return dirty; },
    get selectedComponent() { return selectedComponent; },
    get selectedStyles() { return selectedStyles; },
    selectComponent,
    deselectComponent,
    hoverComponent,
    unhoverComponent,
    startEditing,
    stopEditing,
    setBreakpoint,
    updateStyle,
    updateProp,
    updateContent,
    markDirty,
    commitChanges,
    destroy,
  };
}
