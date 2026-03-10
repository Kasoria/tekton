import { api } from '../api.js';
import {
  updateById,
  updateStylesById,
  updatePropsById,
  replaceContentById,
  moveComponent as moveComponentInTree,
  removeById,
  insertAt,
} from '../componentTree.js';
import { createHistoryStore } from './history.svelte.js';

export function createPageStore() {
  let structures = $state([]);
  let currentStructure = $state(null);
  let loading = $state(false);
  let structureVersion = $state(0);

  const history = createHistoryStore();

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
    history.clear();
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
    history.clear();
    structureVersion++;
  }

  // -- History helpers --

  function pushHistory() {
    if (currentStructure?.components) {
      history.push(currentStructure.components);
    }
  }

  function pushHistoryDebounced() {
    if (currentStructure?.components) {
      history.pushDebounced(currentStructure.components);
    }
  }

  function undo() {
    const components = history.undo();
    if (components && currentStructure) {
      currentStructure = { ...currentStructure, components };
      structureVersion++;
    }
  }

  function redo() {
    const components = history.redo();
    if (components && currentStructure) {
      currentStructure = { ...currentStructure, components };
      structureVersion++;
    }
  }

  // -- Component mutations --

  /**
   * Update a component via an updater function. Does NOT trigger preview re-render.
   */
  function updateComponent(id, updater) {
    if (!currentStructure?.components) return;
    pushHistoryDebounced();
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
    pushHistoryDebounced();
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
    pushHistoryDebounced();
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
    pushHistoryDebounced();
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
    pushHistory();
    currentStructure = {
      ...currentStructure,
      components: moveComponentInTree(currentStructure.components, componentId, targetParentId, targetIndex),
    };
    structureVersion++;
  }

  /**
   * Insert a new component into the tree.
   */
  function insertComponent(component, parentId, index) {
    if (!currentStructure?.components) return;
    pushHistory();
    currentStructure = {
      ...currentStructure,
      components: insertAt(currentStructure.components, component, parentId, index),
    };
    structureVersion++;
  }

  /**
   * Remove a component from the tree.
   */
  function deleteComponent(id) {
    if (!currentStructure?.components) return;
    pushHistory();
    currentStructure = {
      ...currentStructure,
      components: removeById(currentStructure.components, id),
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
    get canUndo() { return history.canUndo; },
    get canRedo() { return history.canRedo; },
    loadStructures,
    loadStructure,
    setStructure,
    updateComponent,
    updateComponentStyles,
    updateComponentProp,
    updateComponentContent,
    moveComponent,
    insertComponent,
    deleteComponent,
    undo,
    redo,
    saveCurrentStructure,
  };
}
