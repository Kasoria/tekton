<script>
  import { Button } from '$lib/components/ui/button/index.js';
  import { Card } from '$lib/components/ui/card/index.js';
  import { Select } from '$lib/components/ui/select/index.js';
  import { createSettingsStore } from '$lib/stores/settings.svelte.js';
  import { api, streamThemeGenerate } from '$lib/api.js';
  import { t } from '$lib/i18n.svelte.js';

  let { oncomplete } = $props();

  const settingsStore = createSettingsStore();

  let step = $state(1);
  let description = $state('');
  let theme = $state(null);
  let generating = $state(false);
  let streamText = $state('');
  let error = $state('');

  // Step 1 state
  let aiProvider = $state('');
  let apiKeyInput = $state('');
  let savingKey = $state(false);

  let checkedApiKey = false;

  $effect(() => {
    settingsStore.load();
  });

  $effect(() => {
    const s = settingsStore.settings;
    if (checkedApiKey || !s.tekton_available_providers) return;
    checkedApiKey = true;
    const provider = s.tekton_ai_provider || '';
    if (provider && s[`tekton_api_key_${provider}`]) {
      aiProvider = provider;
      step = 2;
    }
  });

  let providerOptions = $derived(
    Object.entries(settingsStore.settings.tekton_available_providers || {}).map(([slug, info]) => ({
      value: slug,
      label: info.name || slug,
    }))
  );

  $effect(() => {
    const s = settingsStore.settings;
    if (s.tekton_ai_provider && !aiProvider) aiProvider = s.tekton_ai_provider;
  });

  async function saveApiKeyAndProvider() {
    if (!aiProvider || !apiKeyInput.trim()) return;
    savingKey = true;
    error = '';
    try {
      await settingsStore.save({
        tekton_ai_provider: aiProvider,
        [`tekton_api_key_${aiProvider}`]: apiKeyInput.trim(),
      });
      await settingsStore.load();
      step = 2;
    } catch (e) {
      error = e.message || 'Failed to save';
    } finally {
      savingKey = false;
    }
  }

  function skipSetup() {
    oncomplete?.();
  }

  async function generateTheme() {
    if (!description.trim()) return;
    generating = true;
    streamText = '';
    error = '';
    theme = null;

    try {
      for await (const chunk of streamThemeGenerate(description.trim())) {
        if (chunk.type === 'text' || chunk.text) {
          streamText += chunk.text || chunk.content || '';
        } else if (chunk.type === 'complete' || chunk.type === 'done') {
          // Final chunk may contain the theme directly
          if (chunk.theme) {
            theme = chunk.theme;
          }
        } else if (chunk.content) {
          streamText += chunk.content;
        }
      }

      // If we didn't get a theme object directly, parse from accumulated text
      if (!theme && streamText) {
        const jsonMatch = streamText.match(/```json\s*([\s\S]*?)```/) || streamText.match(/(\{[\s\S]*\})/);
        if (jsonMatch) {
          theme = JSON.parse(jsonMatch[1]);
        }
      }

      if (theme) {
        step = 3;
      } else {
        error = 'Could not parse theme from response. Please try again.';
      }
    } catch (e) {
      error = e.message || 'Theme generation failed';
    } finally {
      generating = false;
    }
  }

  async function useTheme() {
    if (!theme) return;
    savingKey = true;
    error = '';
    try {
      await api.saveTheme({ ...theme, onboarding_complete: true });
      oncomplete?.();
    } catch (e) {
      error = e.message || 'Failed to save theme';
    } finally {
      savingKey = false;
    }
  }

  function regenerate() {
    step = 2;
    theme = null;
    streamText = '';
    error = '';
  }

  // Color swatch labels
  const colorLabels = [
    { key: 'primary', label: 'Primary' },
    { key: 'secondary', label: 'Secondary' },
    { key: 'accent', label: 'Accent' },
    { key: 'background', label: 'Background' },
    { key: 'surface', label: 'Surface' },
    { key: 'text', label: 'Text' },
  ];
</script>

<div class="tk-onboarding">
  <!-- Grain overlay -->
  <div
    class="fixed inset-0 pointer-events-none z-[999] opacity-[0.022] mix-blend-overlay"
    style="background-image: url(&quot;data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E&quot;)"
  ></div>

  <div class="tk-onboarding-center">
    <!-- Logo -->
    <div class="flex items-center gap-3 mb-8">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
        <rect x="2" y="6" width="20" height="2" rx="1" fill="#c97d3c"/>
        <rect x="5" y="10" width="14" height="2" rx="1" fill="#c97d3c" opacity="0.6"/>
        <rect x="8" y="14" width="8" height="2" rx="1" fill="#c97d3c" opacity="0.35"/>
        <rect x="10" y="18" width="4" height="2" rx="1" fill="#c97d3c" opacity="0.2"/>
      </svg>
      <span class="font-heading text-lg font-extrabold tracking-tight">tekton</span>
    </div>

    <!-- Step 1: Welcome + API Key -->
    {#if step === 1}
      <Card class="tk-onboarding-card">
        <div class="p-8">
          <h1 class="font-heading text-2xl font-extrabold tracking-tight mb-2">
            {t('onboarding_welcome', 'Welcome to Tekton')}
          </h1>
          <p class="text-[13px] text-muted leading-relaxed mb-8">
            {t('onboarding_welcome_sub', "AI-first WordPress site builder. Let's get you set up.")}
          </p>

          <div class="flex flex-col gap-5">
            <!-- Provider -->
            <div class="flex flex-col gap-1.5">
              <label class="text-[12px] font-semibold uppercase tracking-[1.5px] text-muted">
                {t('provider', 'Provider')}
              </label>
              <Select
                bind:value={aiProvider}
                options={providerOptions}
                placeholder={t('select_provider', 'Select provider...')}
              />
            </div>

            <!-- API Key -->
            <div class="flex flex-col gap-1.5">
              <label class="text-[12px] font-semibold uppercase tracking-[1.5px] text-muted">
                {t('api_key', 'API Key')}
              </label>
              <input
                type="password"
                class="tk-onboarding-input"
                placeholder={t('enter_api_key', 'Enter API key...')}
                bind:value={apiKeyInput}
                onkeydown={(e) => e.key === 'Enter' && saveApiKeyAndProvider()}
              />
            </div>

            {#if error}
              <p class="text-[12px] text-gold">{error}</p>
            {/if}

            <Button onclick={saveApiKeyAndProvider} disabled={savingKey || !aiProvider || !apiKeyInput.trim()}>
              {savingKey ? t('saving', 'Saving...') : t('continue', 'Continue')}
            </Button>
          </div>

          <div class="mt-6 text-center">
            <button class="bg-transparent border-none text-muted text-[12px] cursor-pointer hover:text-foreground transition-colors font-body" onclick={skipSetup}>
              {t('skip_setup', 'Skip setup')}
            </button>
          </div>
        </div>
      </Card>

    <!-- Step 2: Describe Your Site -->
    {:else if step === 2}
      <Card class="tk-onboarding-card">
        <div class="p-8">
          <h1 class="font-heading text-2xl font-extrabold tracking-tight mb-2">
            {t('onboarding_describe', 'Tell us about your site')}
          </h1>
          <p class="text-[13px] text-muted leading-relaxed mb-8">
            {t('onboarding_describe_sub', 'A sentence or two is enough. The AI will design a fitting theme.')}
          </p>

          <div class="flex flex-col gap-5">
            <textarea
              class="tk-onboarding-textarea"
              rows="4"
              placeholder={t('onboarding_describe_placeholder', 'e.g. A craft coffee roastery in Portland with an earthy, artisanal vibe...')}
              bind:value={description}
              disabled={generating}
            ></textarea>

            {#if generating}
              <div class="tk-stream-preview">
                <div class="text-[12px] text-muted mb-2 font-semibold uppercase tracking-[1.5px]">
                  {t('generating', 'Generating...')}
                </div>
                <pre class="text-[12px] text-muted-foreground font-mono whitespace-pre-wrap leading-relaxed max-h-[200px] overflow-y-auto">{streamText || '...'}</pre>
              </div>
            {/if}

            {#if error}
              <p class="text-[12px] text-gold">{error}</p>
            {/if}

            <div class="flex gap-3">
              <Button variant="ghost" onclick={() => { step = 1; error = ''; }} disabled={generating}>
                {t('back', 'Back')}
              </Button>
              <Button onclick={generateTheme} disabled={generating || !description.trim()}>
                {generating ? t('generating_theme', 'Generating...') : t('generate_theme', 'Generate Theme')}
              </Button>
            </div>
          </div>
        </div>
      </Card>

    <!-- Step 3: Theme Preview -->
    {:else if step === 3 && theme}
      <Card class="tk-onboarding-card">
        <div class="p-8">
          <h1 class="font-heading text-2xl font-extrabold tracking-tight mb-2">
            {t('onboarding_theme', 'Your Theme')}
          </h1>
          {#if theme.name}
            <p class="text-[13px] text-muted-foreground mb-6">{theme.name}</p>
          {/if}

          <!-- Color Swatches -->
          <div class="mb-6">
            <div class="text-[12px] font-semibold uppercase tracking-[1.5px] text-muted mb-3">
              {t('colors', 'Colors')}
            </div>
            <div class="flex gap-3 flex-wrap">
              {#each colorLabels as cl}
                {#if theme.colors?.[cl.key]}
                  <div class="flex flex-col items-center gap-1.5">
                    <div
                      class="w-10 h-10 rounded-full border border-border"
                      style="background-color: {theme.colors[cl.key]}"
                    ></div>
                    <span class="text-[11px] text-muted">{cl.label}</span>
                  </div>
                {/if}
              {/each}
            </div>
          </div>

          <!-- Font Pairing -->
          {#if theme.fonts}
            <div class="mb-6">
              <div class="text-[12px] font-semibold uppercase tracking-[1.5px] text-muted mb-3">
                {t('typography', 'Typography')}
              </div>
              <div class="p-4 rounded-lg bg-background border border-border-subtle">
                {#if theme.fonts.heading}
                  <div class="text-xl font-bold mb-2" style="font-family: {theme.fonts.heading}, sans-serif">
                    {theme.fonts.heading}
                  </div>
                {/if}
                {#if theme.fonts.body}
                  <p class="text-[13px] text-muted-foreground leading-relaxed" style="font-family: {theme.fonts.body}, sans-serif">
                    The quick brown fox jumps over the lazy dog. {theme.fonts.body}
                  </p>
                {/if}
              </div>
            </div>
          {/if}

          <!-- Style Notes -->
          {#if theme.style_notes || theme.description}
            <div class="mb-6">
              <p class="text-[13px] text-muted italic leading-relaxed">
                {theme.style_notes || theme.description}
              </p>
            </div>
          {/if}

          {#if error}
            <p class="text-[12px] text-gold mb-4">{error}</p>
          {/if}

          <div class="flex gap-3">
            <Button variant="ghost" onclick={regenerate}>
              {t('regenerate', 'Regenerate')}
            </Button>
            <Button onclick={useTheme} disabled={savingKey}>
              {savingKey ? t('saving', 'Saving...') : t('use_this_theme', 'Use This Theme')}
            </Button>
          </div>
        </div>
      </Card>
    {/if}

    <!-- Step indicators -->
    <div class="flex justify-center gap-2 mt-6">
      {#each [1, 2, 3] as s}
        <div class="w-1.5 h-1.5 rounded-full transition-colors {step === s ? 'bg-copper' : 'bg-border'}"></div>
      {/each}
    </div>
  </div>
</div>

<style>
  .tk-onboarding {
    min-height: 100vh;
    background: var(--color-background);
    color: var(--color-foreground);
    font-family: var(--font-body);
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .tk-onboarding-center {
    width: 100%;
    max-width: 480px;
    padding: 2rem;
  }

  .tk-onboarding :global(.tk-onboarding-card) {
    border-color: var(--color-dim);
  }

  .tk-onboarding-input {
    width: 100%;
    padding: 8px 12px;
    background: var(--color-background);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    color: var(--color-foreground);
    font-size: 13px;
    font-family: var(--font-mono, monospace);
    outline: none;
    transition: border-color 0.15s;
    box-sizing: border-box;
  }

  .tk-onboarding-input:focus {
    border-color: var(--color-copper);
  }

  .tk-onboarding-textarea {
    width: 100%;
    padding: 10px 12px;
    background: var(--color-background);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    color: var(--color-foreground);
    font-size: 13px;
    font-family: var(--font-body);
    outline: none;
    resize: vertical;
    transition: border-color 0.15s;
    box-sizing: border-box;
    line-height: 1.6;
  }

  .tk-onboarding-textarea:focus {
    border-color: var(--color-copper);
  }

  .tk-onboarding-textarea::placeholder {
    color: var(--color-muted);
  }

  .tk-stream-preview {
    padding: 12px;
    background: var(--color-background);
    border: 1px solid var(--color-border);
    border-radius: 6px;
  }

  /* Override WordPress admin styles */
  .tk-onboarding :global(h1) {
    color: var(--color-foreground);
    font-size: inherit;
    font-weight: inherit;
    margin: 0;
    padding: 0;
    line-height: inherit;
  }

  .tk-onboarding :global(p) {
    color: inherit;
    font-size: inherit;
    margin: 0;
    line-height: inherit;
  }

  .tk-onboarding :global(label) {
    color: inherit;
  }

  .tk-onboarding :global(input) {
    font-family: var(--font-body);
    color: var(--color-foreground);
  }

  .tk-onboarding :global(textarea) {
    font-family: var(--font-body);
    color: var(--color-foreground);
  }

  .tk-onboarding :global(button) {
    font-family: var(--font-body);
    line-height: inherit;
  }
</style>
