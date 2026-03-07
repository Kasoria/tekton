const STORAGE_KEY = 'tekton-theme';

let mode = $state(localStorage.getItem(STORAGE_KEY) || 'system');
let resolved = $state(resolveMode());

function resolveMode() {
  if (mode === 'system') {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  return mode;
}

function applyTheme() {
  resolved = resolveMode();
  const el = document.getElementById('tekton-app');
  if (!el) return;
  el.classList.toggle('dark', resolved === 'dark');
}

// Apply on load
applyTheme();

// Listen for OS preference changes when in system mode
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
  if (mode === 'system') applyTheme();
});

export function createThemeStore() {
  return {
    get mode() { return mode; },
    get resolved() { return resolved; },
    toggle() {
      const next = mode === 'system' ? 'light' : mode === 'light' ? 'dark' : 'system';
      mode = next;
      localStorage.setItem(STORAGE_KEY, next);
      applyTheme();
    },
    setMode(m) {
      mode = m;
      localStorage.setItem(STORAGE_KEY, m);
      applyTheme();
    },
  };
}
