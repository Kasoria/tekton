<script>
  let { store } = $props();

  let provider = $state('anthropic');
  let model = $state('');
  let apiKeys = $state({
    anthropic: '',
    openai: '',
    google: '',
    openrouter: '',
  });
  let maxTokens = $state(8192);
  let saving = $state(false);
  let message = $state('');

  const providers = [
    { id: 'anthropic', name: 'Anthropic' },
    { id: 'openai', name: 'OpenAI' },
    { id: 'google', name: 'Google Gemini' },
    { id: 'openrouter', name: 'OpenRouter' },
  ];

  $effect(() => {
    const s = store.settings;
    if (s.tekton_ai_provider) provider = s.tekton_ai_provider;
    if (s.tekton_ai_model) model = s.tekton_ai_model;
    if (s.tekton_ai_max_tokens) maxTokens = s.tekton_ai_max_tokens;
  });

  $effect(() => {
    store.loadModels(provider);
  });

  async function handleSave() {
    saving = true;
    message = '';
    try {
      const data = {
        tekton_ai_provider: provider,
        tekton_ai_model: model,
        tekton_ai_max_tokens: maxTokens,
      };

      for (const [key, value] of Object.entries(apiKeys)) {
        if (value && !value.includes('...')) {
          data[`tekton_api_key_${key}`] = value;
        }
      }

      await store.save(data);
      message = 'Settings saved.';
      apiKeys = { anthropic: '', openai: '', google: '', openrouter: '' };
    } catch (err) {
      message = `Error: ${err.message}`;
    } finally {
      saving = false;
    }
  }
</script>

<div class="settings-panel">
  <h2>Settings</h2>

  <div class="field">
    <label for="provider">AI Provider</label>
    <select id="provider" bind:value={provider}>
      {#each providers as p}
        <option value={p.id}>{p.name}</option>
      {/each}
    </select>
  </div>

  <div class="field">
    <label for="apikey">API Key ({providers.find((p) => p.id === provider)?.name})</label>
    <input
      id="apikey"
      type="password"
      bind:value={apiKeys[provider]}
      placeholder={store.settings[`tekton_api_key_${provider}`] || 'Enter API key...'}
    />
  </div>

  <div class="field">
    <label for="model">Model</label>
    <select id="model" bind:value={model}>
      {#each store.models as m}
        <option value={m.id}>{m.name}</option>
      {/each}
    </select>
    <small>Select provider and enter API key to load models</small>
  </div>

  <div class="field">
    <label for="tokens">Max Tokens: {maxTokens}</label>
    <input
      id="tokens"
      type="range"
      min="1024"
      max="32768"
      step="1024"
      bind:value={maxTokens}
    />
  </div>

  {#if message}
    <div class="message" class:error={message.startsWith('Error')}>{message}</div>
  {/if}

  <button class="save-btn" onclick={handleSave} disabled={saving}>
    {saving ? 'Saving...' : 'Save Settings'}
  </button>

  <div class="info-box">
    <p>API keys are encrypted and stored in your WordPress database. They are never sent to the frontend.</p>
    <p>If your WordPress security salts are regenerated, you will need to re-enter your API keys.</p>
  </div>
</div>

<style>
  .settings-panel {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    overflow-y: auto;
    height: 100%;
  }

  h2 {
    font-size: 16px;
    font-weight: 600;
    color: #e4e4e7;
    margin: 0;
  }

  .field {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  label {
    font-size: 12px;
    font-weight: 600;
    color: #a1a1aa;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  select, input[type="password"] {
    background: #27272a;
    border: 1px solid #3f3f46;
    border-radius: 6px;
    padding: 8px 10px;
    color: #e4e4e7;
    font-size: 13px;
    outline: none;
  }

  select:focus, input:focus {
    border-color: #7c3aed;
  }

  input[type="range"] {
    accent-color: #7c3aed;
  }

  small {
    font-size: 11px;
    color: #52525b;
  }

  .save-btn {
    background: #7c3aed;
    border: none;
    border-radius: 6px;
    padding: 10px 16px;
    color: white;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s;
  }

  .save-btn:hover:not(:disabled) {
    background: #6d28d9;
  }

  .save-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  .message {
    font-size: 13px;
    padding: 8px 12px;
    border-radius: 6px;
    background: #064e3b;
    color: #6ee7b7;
  }

  .message.error {
    background: #7f1d1d;
    color: #fca5a5;
  }

  .info-box {
    margin-top: 8px;
    padding: 12px;
    background: #1c1c22;
    border-radius: 8px;
    border: 1px solid #27272a;
  }

  .info-box p {
    font-size: 12px;
    color: #71717a;
    line-height: 1.5;
    margin: 0 0 6px;
  }

  .info-box p:last-child {
    margin: 0;
  }
</style>
