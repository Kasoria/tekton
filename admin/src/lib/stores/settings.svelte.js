import { api } from '../api.js';

export function createSettingsStore() {
  let settings = $state({});
  let models = $state([]);
  let loading = $state(false);

  async function load() {
    loading = true;
    try {
      settings = await api.getSettings();
    } finally {
      loading = false;
    }
  }

  async function save(data) {
    settings = { ...settings, ...data };
    await api.saveSettings(data);
  }

  async function loadModels(provider) {
    try {
      models = await api.getModels(provider);
    } catch {
      models = [];
    }
  }

  return {
    get settings() {
      return settings;
    },
    get models() {
      return models;
    },
    get loading() {
      return loading;
    },
    load,
    save,
    loadModels,
  };
}
