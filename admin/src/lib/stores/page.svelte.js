import { api } from '../api.js';

export function createPageStore() {
  let structures = $state([]);
  let currentStructure = $state(null);
  let loading = $state(false);

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
    } catch {
      currentStructure = null;
    } finally {
      loading = false;
    }
  }

  function setStructure(structure) {
    currentStructure = structure;
  }

  return {
    get structures() {
      return structures;
    },
    get currentStructure() {
      return currentStructure;
    },
    get loading() {
      return loading;
    },
    loadStructures,
    loadStructure,
    setStructure,
  };
}
