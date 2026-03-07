<script>
  import { Button } from '$lib/components/ui/button/index.js';
  import { Card } from '$lib/components/ui/card/index.js';
  import { Badge } from '$lib/components/ui/badge/index.js';
  import { Switch } from '$lib/components/ui/switch/index.js';
  import { Select } from '$lib/components/ui/select/index.js';
  import { ConfirmDialog } from '$lib/components/ui/dialog/index.js';
  import { createDashboardStore } from '$lib/stores/dashboard.svelte.js';
  import { createSettingsStore } from '$lib/stores/settings.svelte.js';
  import { api } from '$lib/api.js';
  import { t } from '$lib/i18n.svelte.js';
  import { createThemeStore } from '$lib/stores/theme.svelte.js';
  import OnboardingFlow from './OnboardingFlow.svelte';

  const theme = createThemeStore();

  let { onOpenBuilder, onOpenTemplate, onEditPostType, onEditFieldGroup } = $props();

  const GLOBAL_TEMPLATES = ['header', 'footer'];

  let tab = $state('overview');
  let deleteConfirm = $state({ open: false, key: '' });
  let deleteFgConfirm = $state({ open: false, id: 0 });
  let deleteCptConfirm = $state({ open: false, id: 0 });

  let showOnboarding = $state(false);
  let currentTheme = $state(null);

  const dashboard = createDashboardStore();
  const settingsStore = createSettingsStore();

  $effect(() => {
    dashboard.load();
    settingsStore.load();
    api.getTheme().then((themeData) => {
      if (!themeData.onboarding_complete) {
        showOnboarding = true;
      }
      if (themeData.theme) {
        currentTheme = themeData.theme;
      }
    }).catch(() => {
      showOnboarding = true;
    });
  });

  const kindColor = {
    template: 'bg-copper',
    fullstack: 'bg-copper/70',
    plugin: 'bg-gold',
    tokens: 'bg-slate',
  };

  const tabs = $derived([
    { key: 'overview', label: t('overview', 'Overview') },
    { key: 'templates', label: t('templates', 'Templates') },
    { key: 'fields', label: t('fields', 'Fields') },
    { key: 'cpts', label: t('post_types', 'Post Types') },
    { key: 'settings', label: t('settings', 'Settings') },
  ]);

  let publishedCount = $derived(dashboard.templates.filter(t => t.status === 'published').length);
  let totalFields = $derived(dashboard.fieldGroups.reduce((a, f) => a + (f.field_count || 0), 0));
  let totalEntries = $derived(dashboard.postTypes.reduce((a, c) => a + (c.entry_count || 0), 0));

  const stats = $derived([
    { n: dashboard.templates.length, label: t('templates', 'Templates'), sub: `${publishedCount} ${t('live', 'live')}` },
    { n: dashboard.fieldGroups.length, label: t('field_groups', 'Field Groups'), sub: `${totalFields} ${t('fields', 'fields')}` },
    { n: dashboard.postTypes.length, label: t('post_types', 'Post Types'), sub: `${totalEntries} ${t('entries', 'entries')}` },
    { n: dashboard.pluginStats.count, label: t('plugins', 'Plugins'), sub: `${dashboard.pluginStats.active} ${t('active', 'active')}` },
  ]);

  const quickActions = $derived([
    { label: t('build_a_page', 'Build a Page'), desc: t('build_a_page_desc', 'Start from a natural language prompt'), badge: 'AI' },
    { label: t('fullstack_generate', 'Full-Stack Generate'), desc: t('fullstack_generate_desc', 'CPT + Fields + Template in one shot'), badge: 'AI' },
    { label: t('create_plugin', 'Create Plugin'), desc: t('create_plugin_desc', 'Generate a server-side feature'), badge: t('plugin_mode', 'Plugin Mode') },
  ]);

  function relativeTime(dateStr) {
    if (!dateStr) return '';
    if (dateStr.includes('ago')) return dateStr;
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return `${days}d ago`;
  }

  function locationLabel(fg) {
    const rules = fg.location_rules;
    if (!rules || !Array.isArray(rules) || rules.length === 0) return '';
    const first = rules[0];
    if (Array.isArray(first) && first.length > 0) {
      const rule = first[0];
      return `${rule.param || ''}: ${rule.value || ''}`;
    }
    if (first.param) return `${first.param}: ${first.value || ''}`;
    return '';
  }

  // ─── Settings ──────────────────────────────────────────────────────

  let saving = $state(false);
  let saveMessage = $state('');

  const settingSections = $derived([
    { title: t('rendering', 'Rendering'), rows: [
      { key: 'tekton_override_theme', label: t('override_theme', 'Override theme'), type: 'bool' },
      { key: 'tekton_disable_gutenberg', label: t('disable_gutenberg', 'Disable Gutenberg'), type: 'bool' },
      { key: 'tekton_cache_enabled', label: t('cache_html', 'Cache HTML'), type: 'bool' },
      { key: 'tekton_minify_output', label: t('minify_output', 'Minify output'), type: 'bool' },
    ]},
    { title: t('optional', 'Optional'), rows: [
      { key: 'tekton_acf_compat', label: t('acf_compatibility', 'ACF compatibility'), type: 'bool' },
      { key: 'tekton_plugin_mode_enabled', label: t('plugin_mode', 'Plugin Mode'), type: 'bool' },
      { key: 'tekton_debug_mode', label: t('debug_mode', 'Debug mode'), type: 'bool' },
    ]},
  ]);

  // AI settings state
  let aiProvider = $state('');
  let aiModel = $state('');
  let useCustomModel = $state(false);
  let customModelId = $state('');

  $effect(() => {
    const s = settingsStore.settings;
    if (s.tekton_ai_provider && !aiProvider) aiProvider = s.tekton_ai_provider;
    if (s.tekton_ai_model && !aiModel) aiModel = s.tekton_ai_model;
  });

  $effect(() => {
    if (aiProvider) {
      settingsStore.loadModels(aiProvider);
    }
  });

  let providerOptions = $derived(
    Object.entries(getProviders()).map(([slug, info]) => ({ value: slug, label: info.name || slug }))
  );

  let modelOptions = $derived([
    ...settingsStore.models.map(m => ({ value: m.id, label: m.name })),
    { value: '__custom__', label: t('custom_model', 'Custom model...') },
  ]);

  const languageOptions = [
    { value: '', label: t('language_auto', 'Auto (WordPress default)') },
    { value: 'en_US', label: 'English' },
    { value: 'de_DE', label: 'Deutsch' },
  ];

  async function handleProviderChange(val) {
    aiProvider = val;
    useCustomModel = false;
    customModelId = '';
    await updateSetting('tekton_ai_provider', aiProvider);
    await settingsStore.loadModels(val);
    // Auto-select first model for the new provider
    if (settingsStore.models.length > 0) {
      aiModel = settingsStore.models[0].id;
      await updateSetting('tekton_ai_model', aiModel);
    } else {
      aiModel = '';
    }
  }

  async function handleModelChange(val) {
    if (val === '__custom__') {
      useCustomModel = true;
      customModelId = '';
    } else {
      useCustomModel = false;
      customModelId = '';
      aiModel = val;
      await updateSetting('tekton_ai_model', val);
    }
  }

  async function handleCustomModelSave() {
    const id = customModelId.trim();
    if (!id) return;
    aiModel = id;
    await updateSetting('tekton_ai_model', id);
    // Stay in custom mode since this model won't be in the dropdown list
  }

  function handleCustomModelCancel() {
    useCustomModel = false;
    customModelId = '';
    // Revert to first model in list if available
    if (settingsStore.models.length > 0) {
      aiModel = settingsStore.models[0].id;
      updateSetting('tekton_ai_model', aiModel);
    }
  }

  async function handleLocaleChange(val) {
    await updateSetting('tekton_locale', val);
    // Reload the page to apply the new locale translations
    window.location.reload();
  }

  function requestDeleteTemplate(templateKey) {
    if (GLOBAL_TEMPLATES.includes(templateKey)) return;
    deleteConfirm = { open: true, key: templateKey };
  }

  async function confirmDeleteTemplate() {
    const templateKey = deleteConfirm.key;
    deleteConfirm = { open: false, key: '' };
    await api.deleteStructure(templateKey);
    await dashboard.load();
  }

  async function confirmDeleteFieldGroup() {
    const id = deleteFgConfirm.id;
    deleteFgConfirm = { open: false, id: 0 };
    await api.deleteFieldGroup(id);
    await dashboard.load();
  }

  async function confirmDeletePostType() {
    const id = deleteCptConfirm.id;
    deleteCptConfirm = { open: false, id: 0 };
    await api.deletePostType(id);
    await dashboard.load();
  }

  function getSettingValue(key) {
    return settingsStore.settings[key] ?? '';
  }

  async function updateSetting(key, value) {
    await settingsStore.save({ [key]: value });
  }

  async function handleSettingInput(key, event) {
    const value = event.target.value;
    await updateSetting(key, value);
  }

  // API key state
  let apiKeyInputs = $state({});
  let apiKeyEditing = $state({});

  function getProviders() {
    return settingsStore.settings.tekton_available_providers || {};
  }

  function getMaskedKey(slug) {
    return settingsStore.settings[`tekton_api_key_${slug}`] || '';
  }

  function startEditingKey(slug) {
    apiKeyEditing[slug] = true;
    apiKeyInputs[slug] = '';
  }

  async function saveApiKey(slug) {
    if (apiKeyInputs[slug]) {
      await settingsStore.save({ [`tekton_api_key_${slug}`]: apiKeyInputs[slug] });
      await settingsStore.load();
    }
    apiKeyEditing[slug] = false;
  }
</script>

{#if showOnboarding}
  <OnboardingFlow oncomplete={() => { showOnboarding = false; dashboard.load(); api.getTheme().then(d => { if (d.theme) currentTheme = d.theme; }).catch(() => {}); }} />
{:else}
<div class="tk-dashboard">
  <!-- Grain overlay -->
  <div
    class="fixed inset-0 pointer-events-none z-[999] opacity-[0.022] mix-blend-overlay"
    style="background-image: url(&quot;data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E&quot;)"
  ></div>

  <!-- Header -->
  <header class="border-b border-border bg-card sticky top-0 z-10">
    <div class="max-w-[1120px] mx-auto h-[60px] flex items-center justify-between px-10">
      <div class="flex items-center gap-3">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
          <rect x="2" y="6" width="20" height="2" rx="1" fill="#c97d3c"/>
          <rect x="5" y="10" width="14" height="2" rx="1" fill="#c97d3c" opacity="0.6"/>
          <rect x="8" y="14" width="8" height="2" rx="1" fill="#c97d3c" opacity="0.35"/>
          <rect x="10" y="18" width="4" height="2" rx="1" fill="#c97d3c" opacity="0.2"/>
        </svg>
        <div>
          <span class="font-heading text-lg font-extrabold tracking-tight">tekton</span>
          <span class="text-[12px] text-muted ml-2 font-normal">v{window.tektonData?.version || '1.0.0'}</span>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <button
          class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border border-border text-muted-foreground hover:text-foreground hover:border-dim cursor-pointer transition-colors"
          onclick={() => theme.toggle()}
          title={theme.mode === 'system' ? 'Theme: System' : theme.mode === 'light' ? 'Theme: Light' : 'Theme: Dark'}
        >
          {#if theme.mode === 'system'}
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="10" rx="1.5" stroke="currentColor" stroke-width="1.3"/><line x1="5" y1="15" x2="11" y2="15" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
          {:else if theme.resolved === 'light'}
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="1.3"/><path d="M8 2v1.5M8 12.5V14M2 8h1.5M12.5 8H14M3.75 3.75l1.06 1.06M11.19 11.19l1.06 1.06M12.25 3.75l-1.06 1.06M4.81 11.19l-1.06 1.06" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
          {:else}
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.5 9.5a5.5 5.5 0 01-7-7 5.5 5.5 0 107 7z" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {/if}
        </button>
        {#if getMaskedKey(getSettingValue('tekton_ai_provider') || 'anthropic')}
          <div class="flex items-center gap-[5px] px-2.5 py-1 rounded-[5px] bg-green/5 border border-green/10">
            <div class="w-[5px] h-[5px] rounded-full bg-green"></div>
            <span class="text-[12px] text-green font-medium">{t('api_connected', 'API Connected')}</span>
          </div>
        {:else}
          <div class="flex items-center gap-[5px] px-2.5 py-1 rounded-[5px] bg-gold/5 border border-gold/10">
            <div class="w-[5px] h-[5px] rounded-full bg-gold"></div>
            <span class="text-[12px] text-gold font-medium">{t('no_api_key', 'No API Key')}</span>
          </div>
        {/if}
        <Button onclick={onOpenBuilder}>{t('open_builder', 'Open Builder')}</Button>
      </div>
    </div>
  </header>

  <div class="max-w-[1120px] mx-auto px-10">
    <!-- Nav Tabs -->
    <nav class="flex gap-0 border-b border-border mb-8 pt-1">
      {#each tabs as tb}
        <button
          class="px-5 py-3 bg-transparent border-none text-[13px] font-medium cursor-pointer font-body transition-all -mb-px {tab === tb.key ? 'border-b-2 border-copper text-foreground' : 'border-b-2 border-transparent text-muted'}"
          onclick={() => (tab = tb.key)}
        >
          {tb.label}
        </button>
      {/each}
    </nav>

    {#if dashboard.loading}
      <div class="flex items-center justify-center py-20 text-muted text-sm">{t('loading', 'Loading...')}</div>
    {:else if dashboard.error}
      <div class="flex items-center justify-center py-20 text-gold text-sm">{dashboard.error}</div>

    <!-- OVERVIEW TAB -->
    {:else if tab === 'overview'}
      <div class="pb-15">
        <!-- Stat cards -->
        <div class="grid grid-cols-4 gap-3.5 mb-10">
          {#each stats as s}
            <Card class="relative p-[22px_24px]">
              <div class="absolute top-0 right-0 w-20 h-20 bg-[radial-gradient(circle_at_top_right,#c97d3c08,transparent_70%)]"></div>
              <div class="font-heading text-[40px] font-extrabold leading-none tracking-[-2px]">{s.n}</div>
              <div class="text-xs text-muted-foreground mt-1.5 font-medium">{s.label}</div>
              <div class="text-[12px] text-dim mt-0.5">{s.sub}</div>
            </Card>
          {/each}
        </div>

        <!-- Templates + Activity -->
        <div class="grid grid-cols-[1fr_340px] gap-5 mb-8">
          <div>
            <div class="flex items-baseline justify-between mb-3.5">
              <h2 class="font-heading text-xl font-bold tracking-tight">{t('templates', 'Templates')}</h2>
              {#if dashboard.templates.length > 0}
                <button class="bg-transparent border-none text-copper cursor-pointer text-xs font-medium font-body" onclick={() => (tab = 'templates')}>{t('view_all', 'View all →')}</button>
              {/if}
            </div>
            {#if dashboard.templates.length === 0}
              <Card class="p-8 text-center">
                <div class="text-muted text-sm">{t('no_templates_yet', 'No templates yet. Open the builder to create one.')}</div>
              </Card>
            {:else}
              <Card>
                {#each dashboard.templates.slice(0, 6) as tpl, i}
                  <!-- svelte-ignore a11y_no_static_element_interactions -->
                  <div class="flex items-center justify-between px-[18px] py-3 cursor-pointer transition-colors hover:bg-card-hover group {i < Math.min(dashboard.templates.length, 6) - 1 ? 'border-b border-border-subtle' : ''}" onclick={() => onOpenTemplate?.(tpl.template_key)}>
                    <div class="flex items-center gap-3.5">
                      <div class="w-[3px] h-7 rounded-sm {tpl.status === 'published' ? 'bg-copper' : 'bg-border'}"></div>
                      <div>
                        <div class="text-[13px] font-medium">{tpl.title || tpl.template_key}</div>
                        <div class="text-[12px] text-muted font-mono mt-px">{tpl.template_key}</div>
                      </div>
                    </div>
                    <div class="flex items-center gap-4">
                      <span class="text-[12px] text-dim">{tpl.component_count || 0} {t('comp', 'comp')}</span>
                      <span class="text-[12px] text-dim">{relativeTime(tpl.updated_at)}</span>
                      {#if GLOBAL_TEMPLATES.includes(tpl.template_key)}
                        <span class="text-[12px] font-semibold text-copper/70 uppercase tracking-wider">{t('global', 'Global')}</span>
                      {:else}
                        <span class="text-[12px] font-semibold {tpl.status === 'published' ? 'text-green' : 'text-gold'}">
                          {tpl.status === 'published' ? t('status_live', 'LIVE') : t('status_draft', 'DRAFT')}
                        </span>
                        <button
                          class="opacity-0 group-hover:opacity-100 text-dim hover:text-gold text-[12px] bg-transparent border-none cursor-pointer transition-opacity"
                          onclick={(e) => { e.stopPropagation(); requestDeleteTemplate(tpl.template_key); }}
                          title={t('delete', 'Delete')}
                        >×</button>
                      {/if}
                    </div>
                  </div>
                {/each}
              </Card>
            {/if}
          </div>

          <!-- Activity -->
          <div>
            <h2 class="font-heading text-xl font-bold tracking-tight mb-3.5">{t('activity', 'Activity')}</h2>
            {#if dashboard.activity.length === 0}
              <Card class="p-8 text-center">
                <div class="text-muted text-sm">{t('no_activity_yet', 'No activity yet.')}</div>
              </Card>
            {:else}
              <Card class="py-2">
                {#each dashboard.activity as a}
                  <div class="flex gap-3 px-[18px] py-2.5 items-start">
                    <div class="w-[3px] h-[3px] rounded-sm mt-[7px] shrink-0 {kindColor[a.kind] || 'bg-muted'}"></div>
                    <div class="flex-1">
                      <div class="text-xs text-muted-foreground leading-relaxed">
                        <span class="text-muted">{a.action}</span>
                        {' '}
                        <span class="text-foreground/80 font-medium">{a.target}</span>
                      </div>
                      <div class="text-[12px] text-dim mt-px">{a.time}</div>
                    </div>
                  </div>
                {/each}
              </Card>
            {/if}
          </div>
        </div>

        <!-- Quick Actions -->
        <div>
          <h2 class="font-heading text-xl font-bold tracking-tight mb-3.5">{t('quick_actions', 'Quick Actions')}</h2>
          <div class="grid grid-cols-3 gap-3.5">
            {#each quickActions as a}
              <button class="p-[24px_22px] rounded-[10px] text-left border border-border bg-card cursor-pointer transition-colors hover:border-dim relative overflow-hidden" onclick={onOpenBuilder}>
                <div class="absolute -bottom-5 -right-5 w-[100px] h-[100px] bg-[radial-gradient(circle,#c97d3c06,transparent_70%)]"></div>
                <div class="flex items-center gap-2 mb-2">
                  <span class="font-heading text-base font-bold text-foreground">{a.label}</span>
                  <Badge variant="ai">{a.badge}</Badge>
                </div>
                <div class="text-xs text-muted leading-relaxed">{a.desc}</div>
              </button>
            {/each}
          </div>
        </div>
      </div>

    <!-- TEMPLATES TAB -->
    {:else if tab === 'templates'}
      <div class="pb-15">
        <div class="flex justify-between items-baseline mb-6">
          <div>
            <h2 class="font-heading text-2xl font-extrabold tracking-tight">{t('templates_heading', 'Templates')}</h2>
            <p class="text-[13px] text-muted mt-1">{t('templates_desc', 'Page and archive templates managed by Tekton')}</p>
          </div>
          <Button onclick={onOpenBuilder}>{t('new_template_btn', '+ New Template')}</Button>
        </div>

        <div class="grid grid-cols-3 gap-3.5">
          {#each dashboard.templates as tpl}
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <Card class="cursor-pointer transition-colors hover:border-dim group relative {GLOBAL_TEMPLATES.includes(tpl.template_key) ? 'border-copper/20' : ''}" onclick={() => onOpenTemplate?.(tpl.template_key)}>
              <!-- Delete button (not for globals) -->
              {#if !GLOBAL_TEMPLATES.includes(tpl.template_key)}
                <button
                  class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 w-6 h-6 rounded-md bg-background/80 border border-border text-dim hover:text-gold hover:border-gold/30 cursor-pointer flex items-center justify-center text-xs transition-all z-10"
                  onclick={(e) => { e.stopPropagation(); requestDeleteTemplate(tpl.template_key); }}
                  title={t('delete_template', 'Delete template')}
                >×</button>
              {/if}
              <!-- Skeleton preview -->
              <div class="h-[90px] bg-background m-2.5 rounded-md flex flex-col p-2 gap-1 border border-border-subtle">
                <div class="h-[5px] w-3/5 bg-border rounded-sm"></div>
                <div class="flex-1 flex gap-1">
                  <div class="flex-[2] bg-border/25 rounded"></div>
                  <div class="flex-1 flex flex-col gap-[3px]">
                    <div class="flex-1 bg-border/20 rounded-sm"></div>
                    <div class="flex-1 bg-border/20 rounded-sm"></div>
                  </div>
                </div>
              </div>
              <div class="px-3.5 pb-3.5 pt-2">
                <div class="flex items-center justify-between">
                  <span class="text-sm font-semibold">{tpl.title || tpl.template_key}</span>
                  {#if GLOBAL_TEMPLATES.includes(tpl.template_key)}
                    <span class="text-[12px] font-semibold text-copper/70 uppercase tracking-wider">{t('global', 'Global')}</span>
                  {:else}
                    <span class="text-[12px] font-semibold {tpl.status === 'published' ? 'text-green' : 'text-gold'}">
                      {tpl.status === 'published' ? t('status_live', 'LIVE') : t('status_draft', 'DRAFT')}
                    </span>
                  {/if}
                </div>
                <div class="text-[12px] text-muted font-mono mt-[3px]">{tpl.template_key}</div>
                <div class="flex gap-2.5 mt-2 text-[12px] text-dim">
                  <span>v{tpl.version || 1}</span>
                  <span>·</span>
                  <span>{tpl.component_count || 0} {t('components', 'components')}</span>
                  <span>·</span>
                  <span>{relativeTime(tpl.updated_at)}</span>
                </div>
              </div>
            </Card>
          {/each}
          <!-- New template card -->
          <button class="rounded-[10px] border border-dashed border-border min-h-[170px] flex flex-col items-center justify-center gap-2 cursor-pointer bg-transparent" onclick={onOpenBuilder}>
            <div class="w-9 h-9 rounded-lg bg-card-hover flex items-center justify-center text-muted text-lg">+</div>
            <span class="text-xs text-muted">{t('new_template', 'New Template')}</span>
          </button>
        </div>
      </div>

    <!-- FIELDS TAB -->
    {:else if tab === 'fields'}
      <div class="pb-15">
        <div class="flex justify-between items-baseline mb-6">
          <div>
            <h2 class="font-heading text-2xl font-extrabold tracking-tight">{t('field_groups_heading', 'Field Groups')}</h2>
            <p class="text-[13px] text-muted mt-1">{t('field_groups_desc', "Content structure — Tekton's built-in field engine")}</p>
          </div>
          <Button onclick={() => onEditFieldGroup?.(null)}>+ New Field Group</Button>
        </div>

        {#if dashboard.fieldGroups.length === 0}
          <Card class="p-8 text-center">
            <div class="text-muted text-sm">{t('no_field_groups_yet', "No field groups yet. They'll be created automatically when the AI generates templates that need custom fields.")}</div>
          </Card>
        {:else}
          <div class="flex flex-col gap-2">
            {#each dashboard.fieldGroups as fg}
              <Card class="transition-colors hover:border-dim">
                <!-- svelte-ignore a11y_no_static_element_interactions -->
                <div class="flex items-center justify-between px-5 py-4 cursor-pointer" onclick={() => onEditFieldGroup?.(fg.id)}>
                  <div class="flex items-center gap-4">
                    <div class="w-[3px] h-8 rounded-sm {fg.source === 'ai' ? 'bg-copper' : 'bg-slate'}"></div>
                    <div>
                      <div class="text-sm font-semibold">{fg.title}</div>
                      <div class="flex gap-2 mt-[3px] items-center">
                        <span class="text-[12px] text-muted font-mono">{fg.slug}</span>
                        {#if locationLabel(fg)}
                          <span class="text-dim">·</span>
                          <span class="text-[12px] text-muted">{locationLabel(fg)}</span>
                        {/if}
                      </div>
                    </div>
                  </div>
                  <div class="flex items-center gap-3">
                    <span class="text-xs text-muted">{fg.field_count || 0} {t('fields', 'fields')}</span>
                    <Badge variant={fg.source === 'ai' ? 'ai' : 'manual'}>{fg.source}</Badge>
                    <button
                      class="w-6 h-6 rounded-md bg-transparent border border-transparent text-dim hover:text-gold hover:border-gold/30 cursor-pointer flex items-center justify-center text-xs transition-all"
                      onclick={(e) => { e.stopPropagation(); deleteFgConfirm = { open: true, id: fg.id }; }}
                      title={t('delete', 'Delete')}
                    >×</button>
                    <span class="text-dim text-xs">&#9656;</span>
                  </div>
                </div>
              </Card>
            {/each}
          </div>
        {/if}
      </div>

    <!-- POST TYPES TAB -->
    {:else if tab === 'cpts'}
      <div class="pb-15">
        <div class="flex justify-between items-baseline mb-6">
          <h2 class="font-heading text-2xl font-extrabold tracking-tight">{t('custom_post_types', 'Custom Post Types')}</h2>
          <Button onclick={() => onEditPostType?.(null)}>+ New Post Type</Button>
        </div>
        {#if dashboard.postTypes.length === 0}
          <Card class="p-8 text-center">
            <div class="text-muted text-sm">{t('no_post_types_yet', "No custom post types yet. They'll be created when the AI generates full-stack features.")}</div>
          </Card>
        {:else}
          <div class="grid grid-cols-3 gap-3.5">
            {#each dashboard.postTypes as c}
              <!-- svelte-ignore a11y_no_static_element_interactions -->
              <Card class="p-[22px_20px] relative group cursor-pointer" onclick={() => onEditPostType?.(c.id)}>
                <button
                  class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 w-6 h-6 rounded-md bg-background/80 border border-border text-dim hover:text-gold hover:border-gold/30 cursor-pointer flex items-center justify-center text-xs transition-all z-10"
                  onclick={(e) => { e.stopPropagation(); deleteCptConfirm = { open: true, id: c.id }; }}
                  title={t('delete', 'Delete')}
                >×</button>
                <div class="flex items-center justify-between mb-3">
                  <span class="font-heading text-base font-bold">{c.config?.label || c.slug}</span>
                  <Badge variant={c.source === 'ai' ? 'ai' : 'manual'}>{c.source}</Badge>
                </div>
                <div class="text-[12px] text-muted font-mono mb-2.5">{c.slug}</div>
                <div class="flex gap-2 text-xs text-muted-foreground">
                  <span>{c.entry_count || 0} {t('entries', 'entries')}</span>
                  {#if c.taxonomies && c.taxonomies.length > 0}
                    <span class="text-dim">·</span>
                    <span>{c.taxonomies.map(tx => tx.slug || tx).join(', ')}</span>
                  {/if}
                </div>
              </Card>
            {/each}
          </div>
        {/if}
      </div>

    <!-- SETTINGS TAB -->
    {:else if tab === 'settings'}
      <div class="max-w-[560px] pb-15">
        <h2 class="font-heading text-2xl font-extrabold tracking-tight mb-6">{t('settings', 'Settings')}</h2>

        <!-- API Keys Section -->
        <Card class="mb-4">
          <div class="px-[18px] py-2.5 border-b border-border text-[12px] font-semibold uppercase tracking-[1.5px] text-muted">
            {t('api_keys', 'API Keys')}
          </div>
          {#each Object.entries(getProviders()) as [slug, providerInfo], i}
            <div class="flex items-center justify-between px-[18px] py-2.5 {i < Object.keys(getProviders()).length - 1 ? 'border-b border-border-subtle' : ''}">
              <span class="text-[13px] text-foreground/80">{providerInfo.name || slug}</span>
              <div class="flex items-center gap-2">
                {#if apiKeyEditing[slug] || !getMaskedKey(slug)}
                  <input
                    type="password"
                    class="text-xs text-foreground font-mono px-2.5 py-1 bg-background rounded-[5px] border border-border w-48 outline-none focus:border-copper"
                    placeholder={t('enter_api_key', 'Enter API key...')}
                    bind:value={apiKeyInputs[slug]}
                    onkeydown={(e) => e.key === 'Enter' && saveApiKey(slug)}
                  />
                  <Button size="sm" onclick={() => saveApiKey(slug)}>{t('save', 'Save')}</Button>
                  {#if getMaskedKey(slug)}
                    <Button size="sm" variant="ghost" onclick={() => (apiKeyEditing[slug] = false)}>{t('cancel', 'Cancel')}</Button>
                  {/if}
                {:else}
                  <span class="text-xs text-muted font-mono px-2.5 py-1 bg-background rounded-[5px] border border-border">
                    {getMaskedKey(slug)}
                  </span>
                  <Button size="sm" variant="ghost" onclick={() => startEditingKey(slug)}>{t('edit', 'Edit')}</Button>
                {/if}
              </div>
            </div>
          {/each}
        </Card>

        <!-- AI Settings -->
        <Card class="mb-4">
          <div class="px-[18px] py-2.5 border-b border-border text-[12px] font-semibold uppercase tracking-[1.5px] text-muted">
            {t('ai', 'AI')}
          </div>
          <!-- Provider -->
          <div class="flex items-center justify-between px-[18px] py-2.5 border-b border-border-subtle">
            <span class="text-[13px] text-foreground/80">{t('provider', 'Provider')}</span>
            <Select
              bind:value={aiProvider}
              options={providerOptions}
              onchange={handleProviderChange}
            />
          </div>
          <!-- Model -->
          <div class="flex items-center justify-between px-[18px] py-2.5 border-b border-border-subtle">
            <span class="text-[13px] text-foreground/80">{t('model', 'Model')}</span>
            <div class="flex items-center gap-2">
              {#if useCustomModel}
                <input
                  type="text"
                  class="text-xs text-foreground font-mono px-2.5 py-1 bg-background rounded-[5px] border border-border w-48 outline-none focus:border-copper text-right"
                  placeholder={t('custom_model_id', 'Custom model ID')}
                  bind:value={customModelId}
                  onkeydown={(e) => e.key === 'Enter' && handleCustomModelSave()}
                />
                <Button size="sm" onclick={handleCustomModelSave}>{t('save', 'Save')}</Button>
                <Button size="sm" variant="ghost" onclick={handleCustomModelCancel}>{t('cancel', 'Cancel')}</Button>
              {:else}
                <Select
                  bind:value={aiModel}
                  options={modelOptions}
                  searchable={aiProvider === 'openrouter'}
                  onchange={handleModelChange}
                />
              {/if}
            </div>
          </div>
          <!-- Max tokens -->
          <div class="flex items-center justify-between px-[18px] py-2.5">
            <span class="text-[13px] text-foreground/80">{t('max_tokens', 'Max tokens')}</span>
            <input
              type="number"
              class="tk-input"
              value={getSettingValue('tekton_ai_max_tokens')}
              onchange={(e) => handleSettingInput('tekton_ai_max_tokens', e)}
            />
          </div>
        </Card>

        {#each settingSections as sec}
          <Card class="mb-4">
            <div class="px-[18px] py-2.5 border-b border-border text-[12px] font-semibold uppercase tracking-[1.5px] text-muted">
              {sec.title}
            </div>
            {#each sec.rows as r, ri}
              <div class="flex items-center justify-between px-[18px] py-2.5 {ri < sec.rows.length - 1 ? 'border-b border-border-subtle' : ''}">
                <span class="text-[13px] text-foreground/80">{r.label}</span>
                {#if r.type === 'bool'}
                  <Switch
                    checked={!!getSettingValue(r.key)}
                    onchange={() => updateSetting(r.key, !getSettingValue(r.key))}
                  />
                {:else}
                  <input
                    type="text"
                    class="text-xs text-muted font-body px-2.5 py-1 bg-background rounded-[5px] border border-border outline-none focus:border-copper w-48 text-right"
                    value={getSettingValue(r.key)}
                    onchange={(e) => handleSettingInput(r.key, e)}
                  />
                {/if}
              </div>
            {/each}
          </Card>
        {/each}

        <!-- Language -->
        <Card class="mb-4">
          <div class="px-[18px] py-2.5 border-b border-border text-[12px] font-semibold uppercase tracking-[1.5px] text-muted">
            {t('language', 'Language')}
          </div>
          <div class="flex items-center justify-between px-[18px] py-2.5">
            <span class="text-[13px] text-foreground/80">{t('language', 'Language')}</span>
            <Select
              value={getSettingValue('tekton_locale') || ''}
              options={languageOptions}
              onchange={handleLocaleChange}
            />
          </div>
        </Card>

        <!-- Theme -->
        <Card class="mb-4">
          <div class="px-[18px] py-2.5 border-b border-border text-[12px] font-semibold uppercase tracking-[1.5px] text-muted">
            {t('theme', 'Theme')}
          </div>
          <div class="px-[18px] py-4">
            {#if currentTheme}
              {#if currentTheme.name}
                <div class="text-[13px] font-semibold mb-0.5">{currentTheme.name}</div>
              {/if}
              {#if currentTheme.description}
                <p class="text-[12px] text-muted mb-4">{currentTheme.description}</p>
              {/if}

              <!-- Color swatches -->
              {#if currentTheme.colors}
                <div class="flex gap-2.5 flex-wrap mb-4">
                  {#each [['primary','Primary'],['secondary','Secondary'],['accent','Accent'],['background','Bg'],['surface','Surface'],['text','Text']] as [key, label]}
                    {#if currentTheme.colors[key]}
                      <div class="flex flex-col items-center gap-1">
                        <div class="w-7 h-7 rounded-full border border-border" style="background-color: {currentTheme.colors[key]}"></div>
                        <span class="text-[10px] text-dim">{label}</span>
                      </div>
                    {/if}
                  {/each}
                </div>
              {/if}

              <!-- Font pairing -->
              {#if currentTheme.fonts}
                <div class="p-3 rounded-md bg-background border border-border-subtle mb-4">
                  {#if currentTheme.fonts.heading}
                    <div class="text-base font-bold mb-1" style="font-family: {currentTheme.fonts.heading}, sans-serif">{currentTheme.fonts.heading}</div>
                  {/if}
                  {#if currentTheme.fonts.body}
                    <p class="text-[12px] text-muted-foreground leading-relaxed" style="font-family: {currentTheme.fonts.body}, sans-serif">{currentTheme.fonts.body}</p>
                  {/if}
                </div>
              {/if}

              <!-- Style notes -->
              {#if currentTheme.style_notes}
                <p class="text-[12px] text-muted italic leading-relaxed mb-4">{currentTheme.style_notes}</p>
              {/if}
            {:else}
              <p class="text-[12px] text-muted mb-3">
                {t('theme_desc', 'Regenerate your site theme with AI based on a description of your site.')}
              </p>
            {/if}

            <div class="mt-4">
              <Button onclick={() => { showOnboarding = true; }}>
                {t('regenerate_theme', 'Regenerate Theme')}
              </Button>
            </div>
          </div>
        </Card>
      </div>
    {/if}
  </div>
</div>
{/if}

<ConfirmDialog
  open={deleteConfirm.open}
  title={t('delete_template', 'Delete template')}
  description={t('delete_template_desc', 'This will permanently delete the template and all its versions. This cannot be undone.')}
  confirmLabel={t('delete', 'Delete')}
  onconfirm={confirmDeleteTemplate}
  oncancel={() => (deleteConfirm = { open: false, key: '' })}
/>

<ConfirmDialog
  open={deleteFgConfirm.open}
  title="Delete field group"
  description="This will permanently delete the field group. Field values in existing posts will not be removed."
  confirmLabel={t('delete', 'Delete')}
  onconfirm={confirmDeleteFieldGroup}
  oncancel={() => (deleteFgConfirm = { open: false, id: 0 })}
/>

<ConfirmDialog
  open={deleteCptConfirm.open}
  title="Delete post type"
  description="This will unregister the post type. Existing posts will remain in the database but won't be accessible until re-registered."
  confirmLabel={t('delete', 'Delete')}
  onconfirm={confirmDeletePostType}
  oncancel={() => (deleteCptConfirm = { open: false, id: 0 })}
/>


<style>
  .tk-dashboard {
    min-height: 100vh;
    background: var(--color-background);
    color: var(--color-foreground);
    font-family: var(--font-body);
  }

  /* Override WordPress admin styles within our dashboard */
  .tk-dashboard :global(h1),
  .tk-dashboard :global(h2),
  .tk-dashboard :global(h3),
  .tk-dashboard :global(h4),
  .tk-dashboard :global(h5),
  .tk-dashboard :global(h6) {
    color: var(--color-foreground);
    font-size: inherit;
    font-weight: inherit;
    margin: 0;
    padding: 0;
    line-height: inherit;
  }

  .tk-dashboard :global(p) {
    color: inherit;
    font-size: inherit;
    margin: 0;
    line-height: inherit;
  }

  .tk-dashboard :global(a) {
    color: inherit;
    text-decoration: none;
  }

  .tk-dashboard :global(button) {
    font-family: var(--font-body);
    line-height: inherit;
  }

  .tk-dashboard :global(input) {
    font-family: var(--font-body);
    color: var(--color-foreground);
  }

  .tk-dashboard :global(input.tk-input) {
    display: inline-flex !important;
    align-items: center !important;
    padding: 5px 10px !important;
    background: var(--color-background) !important;
    border: 1px solid var(--color-border) !important;
    border-radius: 6px !important;
    color: var(--color-foreground) !important;
    font-size: 13px !important;
    font-family: var(--font-body) !important;
    line-height: 1.4 !important;
    text-align: right !important;
    outline: none !important;
    box-shadow: none !important;
    height: auto !important;
    width: auto !important;
    min-height: 0 !important;
    margin: 0 !important;
    transition: border-color 0.15s, background 0.15s !important;
    -moz-appearance: textfield !important;
  }

  .tk-dashboard :global(input.tk-input:hover) {
    border-color: var(--color-dim) !important;
    background: var(--color-card-hover) !important;
  }

  .tk-dashboard :global(input.tk-input:focus) {
    border-color: var(--color-copper) !important;
    box-shadow: none !important;
  }

  .tk-dashboard :global(input.tk-input::-webkit-inner-spin-button),
  .tk-dashboard :global(input.tk-input::-webkit-outer-spin-button) {
    -webkit-appearance: none !important;
    margin: 0 !important;
  }

  .tk-dashboard :global(label) {
    color: inherit;
  }

</style>
