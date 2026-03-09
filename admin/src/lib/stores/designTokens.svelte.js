import { api } from '../api.js';

let theme = $state(null);
let tokens = $state(null);
let dirty = $state(false);
let saving = $state(false);
let saved = $state(false);
let saveTimer = null;
let saveCallbacks = [];

async function load() {
  try {
    const data = await api.getTheme();
    if (data.theme) {
      theme = data.theme;
    }
  } catch {
    // ignore
  }
}

function updateField(category, key, value) {
  if (!theme) return;
  theme = { ...theme, [category]: { ...theme[category], [key]: value } };
  markDirty();
}

function updateTopLevel(key, value) {
  if (!theme) return;
  theme = { ...theme, [key]: value };
  markDirty();
}

function setTheme(newTheme) {
  theme = newTheme;
  dirty = false;
}

function markDirty() {
  dirty = true;
  saved = false;
  clearTimeout(saveTimer);
  saveTimer = setTimeout(save, 800);
}

async function save() {
  if (!dirty || !theme) return;
  saving = true;
  dirty = false;
  try {
    const result = await api.saveTheme($state.snapshot(theme));
    if (result.design_tokens) {
      tokens = result.design_tokens;
      for (const cb of saveCallbacks) cb(tokens);
    }
    saved = true;
    setTimeout(() => { saved = false; }, 2000);
  } catch {
    dirty = true;
  } finally {
    saving = false;
  }
}

function onSave(callback) {
  saveCallbacks.push(callback);
  return () => {
    saveCallbacks = saveCallbacks.filter(cb => cb !== callback);
  };
}

export const designTokensStore = {
  get theme() { return theme; },
  get tokens() { return tokens; },
  get dirty() { return dirty; },
  get saving() { return saving; },
  get saved() { return saved; },
  load,
  updateField,
  updateTopLevel,
  setTheme,
  save,
  onSave,
};
