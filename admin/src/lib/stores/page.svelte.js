import { api } from '../api.js';
import {
  updateById,
  updateStylesById,
  updatePropsById,
  replaceContentById,
  moveComponent as moveComponentInTree,
} from '../componentTree.js';

export function createPageStore() {
  let structures = $state([]);
  let currentStructure = $state(null);
  let loading = $state(false);
  let structureVersion = $state(0);

  async function loadStructures() {
    loading = true;
    try {
      structures = await api.getStructures();
    } finally {
      loading = false;
    }
  }

  async function loadStructure(templateKey) {
    loading = true;
    try {
      currentStructure = await api.getStructure(templateKey);
      structureVersion++;
    } catch {
      currentStructure = null;
    } finally {
      loading = false;
    }
  }

  function setStructure(structure) {
    currentStructure = structure;
    structureVersion++;
  }

  /**
   * Update a component via an updater function. Does NOT trigger preview re-render.
   */
  function updateComponent(id, updater) {
    if (!currentStructure?.components) return;
    currentStructure = {
      ...currentStructure,
      components: updateById(currentStructure.components, id, updater),
    };
  }

  /**
   * Merge styles at a breakpoint for a component. Does NOT trigger preview re-render.
   */
  function updateComponentStyles(id, breakpoint, styles) {
    if (!currentStructure?.components) return;
    currentStructure = {
      ...currentStructure,
      components: updateStylesById(currentStructure.components, id, breakpoint, styles),
    };
  }

  /**
   * Merge props for a component. Does NOT trigger preview re-render.
   */
  function updateComponentProp(id, prop, value) {
    if (!currentStructure?.components) return;
    currentStructure = {
      ...currentStructure,
      components: updatePropsById(currentStructure.components, id, { [prop]: value }),
    };
  }

  /**
   * Update content, handling content source fallback.
   */
  function updateComponentContent(id, prop, value) {
    if (!currentStructure?.components) return;
    currentStructure = {
      ...currentStructure,
      components: replaceContentById(currentStructure.components, id, prop, value),
    };
  }

  /**
   * Move a component to a new position in the tree.
   */
  function moveComponent(componentId, targetParentId, targetIndex) {
    if (!currentStructure?.components) return;
    currentStructure = {
      ...currentStructure,
      components: moveComponentInTree(currentStructure.components, componentId, targetParentId, targetIndex),
    };
    structureVersion++;
  }

  /**
   * Save the current structure to the backend.
   */
  async function saveCurrentStructure(changeType = 'manual', changeSummary = '', statusOverride = null) {
    if (!currentStructure) return;
    const snap = $state.snapshot(currentStructure);
    const data = {
      template_key: snap.template_key,
      title: snap.title || '',
      components: snap.components || [],
      styles: snap.styles || {},
      keyframes: snap.keyframes || {},
      scripts: snap.scripts || [],
      wrapper_styles: snap.wrapper_styles || {},
      status: statusOverride || snap.status || 'draft',
      change_type: changeType,
      change_summary: changeSummary,
    };
    return api.saveStructure(data);
  }

  return {
    get structures() { return structures; },
    get currentStructure() { return currentStructure; },
    get loading() { return loading; },
    get structureVersion() { return structureVersion; },
    loadStructures,
    loadStructure,
    setStructure,
    updateComponent,
    updateComponentStyles,
    updateComponentProp,
    updateComponentContent,
    moveComponent,
    saveCurrentStructure,
  };
}
