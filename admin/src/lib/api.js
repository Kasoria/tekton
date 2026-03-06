const getConfig = () => window.tektonData || {};

async function apiFetch(endpoint, options = {}) {
  const { restUrl, nonce } = getConfig();
  const url = `${restUrl}tekton/v1/${endpoint}`;
  const response = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
      ...options.headers,
    },
  });
  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: response.statusText }));
    throw new Error(error.message || 'API request failed');
  }
  return response.json();
}

export async function* streamGenerate(prompt, templateKey, type = 'generate_page', images = []) {
  const { restUrl, nonce } = getConfig();
  const url = `${restUrl}tekton/v1/ai/generate`;
  const body = { prompt, template_key: templateKey, type };
  if (images.length > 0) {
    body.images = images;
  }
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': nonce,
    },
    body: JSON.stringify(body),
  });

  if (!response.ok) {
    const err = await response.json().catch(() => ({ message: 'Stream failed' }));
    throw new Error(err.message || 'Stream failed');
  }

  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';

  while (true) {
    const { done, value } = await reader.read();
    if (done) break;

    buffer += decoder.decode(value, { stream: true });
    const lines = buffer.split('\n');
    buffer = lines.pop() || '';

    for (const line of lines) {
      if (line.startsWith('data: ')) {
        try {
          yield JSON.parse(line.slice(6));
        } catch {
          // Skip malformed JSON lines.
        }
      }
    }
  }
}

export const api = {
  getStructures: () => apiFetch('structures'),
  getStructure: (key) => apiFetch(`structures/${key}`),
  saveStructure: (data) => apiFetch('structures', { method: 'POST', body: JSON.stringify(data) }),
  deleteStructure: (key) => apiFetch(`structures/${key}`, { method: 'DELETE' }),
  getVersions: (key) => apiFetch(`structures/${key}/versions`),
  rollback: (key, version) =>
    apiFetch(`structures/${key}/rollback`, {
      method: 'POST',
      body: JSON.stringify({ version_number: version }),
    }),
  renameVersion: (key, version, label) =>
    apiFetch(`structures/${key}/versions/${version}/rename`, {
      method: 'POST',
      body: JSON.stringify({ label }),
    }),
  getChatHistory: (key) => apiFetch(`chat/${key}`),
  clearChat: (key) => apiFetch(`chat/${key}`, { method: 'DELETE' }),
  summarizeAndClearChat: (key) => apiFetch(`chat/${key}/summarize-clear`, { method: 'POST' }),
  getContext: () => apiFetch('context'),
  refreshContext: () => apiFetch('context/refresh', { method: 'POST' }),
  getDashboard: () => apiFetch('dashboard'),
  getFieldGroups: () => apiFetch('field-groups'),
  getPostTypes: () => apiFetch('post-types'),
  getActivity: () => apiFetch('activity'),
  getSettings: () => apiFetch('settings'),
  saveSettings: (data) => apiFetch('settings', { method: 'POST', body: JSON.stringify(data) }),
  getModels: (provider) => apiFetch(`ai/models?provider=${provider}`),
  preview: (components, templateKey) =>
    apiFetch('preview', {
      method: 'POST',
      body: JSON.stringify({ components, template_key: templateKey }),
    }),
};
