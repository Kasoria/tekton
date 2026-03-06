<script>
  import { Button } from '$lib/components/ui/button/index.js';
  import { Card } from '$lib/components/ui/card/index.js';
  import { Badge } from '$lib/components/ui/badge/index.js';
  import { Switch } from '$lib/components/ui/switch/index.js';
  import { ConfirmDialog } from '$lib/components/ui/dialog/index.js';
  import { createDashboardStore } from '$lib/stores/dashboard.svelte.js';
  import { createSettingsStore } from '$lib/stores/settings.svelte.js';
  import { api } from '$lib/api.js';

  let { onOpenBuilder, onOpenTemplate } = $props();

  const GLOBAL_TEMPLATES = ['header', 'footer'];

  let tab = $state('overview');
  let deleteConfirm = $state({ open: false, key: '' });

  const dashboard = createDashboardStore();
  const settingsStore = createSettingsStore();

  $effect(() => {
    dashboard.load();
    settingsStore.load();
  });

  const kindColor = {
    template: 'bg-copper',
    fullstack: 'bg-copper/70',
    plugin: 'bg-gold',
    tokens: 'bg-slate',
  };

  const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'templates', label: 'Templates' },
    { key: 'fields', label: 'Fields' },
    { key: 'cpts', label: 'Post Types' },
    { key: 'settings', label: 'Settings' },
  ];

  let publishedCount = $derived(dashboard.templates.filter(t => t.status === 'published').length);
  let totalFields = $derived(dashboard.fieldGroups.reduce((a, f) => a + (f.field_count || 0), 0));
  let totalEntries = $derived(dashboard.postTypes.reduce((a, c) => a + (c.entry_count || 0), 0));

  const stats = $derived([
    { n: dashboard.templates.length, label: 'Templates', sub: `${publishedCount} live` },
    { n: dashboard.fieldGroups.length, label: 'Field Groups', sub: `${totalFields} fields` },
    { n: dashboard.postTypes.length, label: 'Post Types', sub: `${totalEntries} entries` },
    { n: dashboard.pluginStats.count, label: 'Plugins', sub: `${dashboard.pluginStats.active} active` },
  ]);

  const quickActions = [
    { label: 'Build a Page', desc: 'Start from a natural language prompt', badge: 'AI' },
    { label: 'Full-Stack Generate', desc: 'CPT + Fields + Template in one shot', badge: 'AI' },
    { label: 'Create Plugin', desc: 'Generate a server-side feature', badge: 'Plugin Mode' },
  ];

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
    { title: 'AI', rows: [
      { key: 'tekton_ai_provider', label: 'Provider', type: 'text' },
      { key: 'tekton_ai_model', label: 'Model', type: 'text' },
      { key: 'tekton_ai_max_tokens', label: 'Max tokens', type: 'text' },
    ]},
    { title: 'Rendering', rows: [
      { key: 'tekton_override_theme', label: 'Override theme', type: 'bool' },
      { key: 'tekton_disable_gutenberg', label: 'Disable Gutenberg', type: 'bool' },
      { key: 'tekton_cache_enabled', label: 'Cache HTML', type: 'bool' },
      { key: 'tekton_minify_output', label: 'Minify output', type: 'bool' },
    ]},
    { title: 'Optional', rows: [
      { key: 'tekton_acf_compat', label: 'ACF compatibility', type: 'bool' },
      { key: 'tekton_plugin_mode_enabled', label: 'Plugin Mode', type: 'bool' },
      { key: 'tekton_debug_mode', label: 'Debug mode', type: 'bool' },
    ]},
  ]);

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
        {#if getMaskedKey(getSettingValue('tekton_ai_provider') || 'anthropic')}
          <div class="flex items-center gap-[5px] px-2.5 py-1 rounded-[5px] bg-green/5 border border-green/10">
            <div class="w-[5px] h-[5px] rounded-full bg-green"></div>
            <span class="text-[12px] text-green font-medium">API Connected</span>
          </div>
        {:else}
          <div class="flex items-center gap-[5px] px-2.5 py-1 rounded-[5px] bg-gold/5 border border-gold/10">
            <div class="w-[5px] h-[5px] rounded-full bg-gold"></div>
            <span class="text-[12px] text-gold font-medium">No API Key</span>
          </div>
        {/if}
        <Button onclick={onOpenBuilder}>Open Builder</Button>
      </div>
    </div>
  </header>

  <div class="max-w-[1120px] mx-auto px-10">
    <!-- Nav Tabs -->
    <nav class="flex gap-0 border-b border-border mb-8 pt-1">
      {#each tabs as t}
        <button
          class="px-5 py-3 bg-transparent border-none text-[13px] font-medium cursor-pointer font-body transition-all -mb-px {tab === t.key ? 'border-b-2 border-copper text-foreground' : 'border-b-2 border-transparent text-muted'}"
          onclick={() => (tab = t.key)}
        >
          {t.label}
        </button>
      {/each}
    </nav>

    {#if dashboard.loading}
      <div class="flex items-center justify-center py-20 text-muted text-sm">Loading...</div>
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
              <h2 class="font-heading text-xl font-bold tracking-tight">Templates</h2>
              {#if dashboard.templates.length > 0}
                <button class="bg-transparent border-none text-copper cursor-pointer text-xs font-medium font-body" onclick={() => (tab = 'templates')}>View all →</button>
              {/if}
            </div>
            {#if dashboard.templates.length === 0}
              <Card class="p-8 text-center">
                <div class="text-muted text-sm">No templates yet. Open the builder to create one.</div>
              </Card>
            {:else}
              <Card>
                {#each dashboard.templates.slice(0, 6) as t, i}
                  <!-- svelte-ignore a11y_no_static_element_interactions -->
                  <div class="flex items-center justify-between px-[18px] py-3 cursor-pointer transition-colors hover:bg-card-hover group {i < Math.min(dashboard.templates.length, 6) - 1 ? 'border-b border-border-subtle' : ''}" onclick={() => onOpenTemplate?.(t.template_key)}>
                    <div class="flex items-center gap-3.5">
                      <div class="w-[3px] h-7 rounded-sm {t.status === 'published' ? 'bg-copper' : 'bg-border'}"></div>
                      <div>
                        <div class="text-[13px] font-medium">{t.title || t.template_key}</div>
                        <div class="text-[12px] text-muted font-mono mt-px">{t.template_key}</div>
                      </div>
                    </div>
                    <div class="flex items-center gap-4">
                      <span class="text-[12px] text-dim">{t.component_count || 0} comp</span>
                      <span class="text-[12px] text-dim">{relativeTime(t.updated_at)}</span>
                      {#if GLOBAL_TEMPLATES.includes(t.template_key)}
                        <span class="text-[12px] font-semibold text-copper/70 uppercase tracking-wider">Global</span>
                      {:else}
                        <span class="text-[12px] font-semibold {t.status === 'published' ? 'text-green' : 'text-gold'}">
                          {t.status === 'published' ? 'LIVE' : 'DRAFT'}
                        </span>
                        <button
                          class="opacity-0 group-hover:opacity-100 text-dim hover:text-gold text-[12px] bg-transparent border-none cursor-pointer transition-opacity"
                          onclick={(e) => { e.stopPropagation(); requestDeleteTemplate(t.template_key); }}
                          title="Delete"
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
            <h2 class="font-heading text-xl font-bold tracking-tight mb-3.5">Activity</h2>
            {#if dashboard.activity.length === 0}
              <Card class="p-8 text-center">
                <div class="text-muted text-sm">No activity yet.</div>
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
          <h2 class="font-heading text-xl font-bold tracking-tight mb-3.5">Quick Actions</h2>
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
            <h2 class="font-heading text-2xl font-extrabold tracking-tight">Templates</h2>
            <p class="text-[13px] text-muted mt-1">Page and archive templates managed by Tekton</p>
          </div>
          <Button onclick={onOpenBuilder}>+ New Template</Button>
        </div>

        <div class="grid grid-cols-3 gap-3.5">
          {#each dashboard.templates as t}
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <Card class="cursor-pointer transition-colors hover:border-dim group relative {GLOBAL_TEMPLATES.includes(t.template_key) ? 'border-copper/20' : ''}" onclick={() => onOpenTemplate?.(t.template_key)}>
              <!-- Delete button (not for globals) -->
              {#if !GLOBAL_TEMPLATES.includes(t.template_key)}
                <button
                  class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 w-6 h-6 rounded-md bg-background/80 border border-border text-dim hover:text-gold hover:border-gold/30 cursor-pointer flex items-center justify-center text-xs transition-all z-10"
                  onclick={(e) => { e.stopPropagation(); requestDeleteTemplate(t.template_key); }}
                  title="Delete template"
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
                  <span class="text-sm font-semibold">{t.title || t.template_key}</span>
                  {#if GLOBAL_TEMPLATES.includes(t.template_key)}
                    <span class="text-[12px] font-semibold text-copper/70 uppercase tracking-wider">Global</span>
                  {:else}
                    <span class="text-[12px] font-semibold {t.status === 'published' ? 'text-green' : 'text-gold'}">
                      {t.status === 'published' ? 'LIVE' : 'DRAFT'}
                    </span>
                  {/if}
                </div>
                <div class="text-[12px] text-muted font-mono mt-[3px]">{t.template_key}</div>
                <div class="flex gap-2.5 mt-2 text-[12px] text-dim">
                  <span>v{t.version || 1}</span>
                  <span>·</span>
                  <span>{t.component_count || 0} components</span>
                  <span>·</span>
                  <span>{relativeTime(t.updated_at)}</span>
                </div>
              </div>
            </Card>
          {/each}
          <!-- New template card -->
          <button class="rounded-[10px] border border-dashed border-border min-h-[170px] flex flex-col items-center justify-center gap-2 cursor-pointer bg-transparent" onclick={onOpenBuilder}>
            <div class="w-9 h-9 rounded-lg bg-card-hover flex items-center justify-center text-muted text-lg">+</div>
            <span class="text-xs text-muted">New Template</span>
          </button>
        </div>
      </div>

    <!-- FIELDS TAB -->
    {:else if tab === 'fields'}
      <div class="pb-15">
        <div class="flex justify-between items-baseline mb-6">
          <div>
            <h2 class="font-heading text-2xl font-extrabold tracking-tight">Field Groups</h2>
            <p class="text-[13px] text-muted mt-1">Content structure — Tekton's built-in field engine</p>
          </div>
        </div>

        {#if dashboard.fieldGroups.length === 0}
          <Card class="p-8 text-center">
            <div class="text-muted text-sm">No field groups yet. They'll be created automatically when the AI generates templates that need custom fields.</div>
          </Card>
        {:else}
          <div class="flex flex-col gap-2">
            {#each dashboard.fieldGroups as fg}
              <Card class="flex items-center justify-between px-5 py-4 cursor-pointer transition-colors hover:border-dim">
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
                  <span class="text-xs text-muted">{fg.field_count || 0} fields</span>
                  <Badge variant={fg.source === 'ai' ? 'ai' : 'manual'}>{fg.source}</Badge>
                </div>
              </Card>
            {/each}
          </div>
        {/if}
      </div>

    <!-- POST TYPES TAB -->
    {:else if tab === 'cpts'}
      <div class="pb-15">
        <h2 class="font-heading text-2xl font-extrabold tracking-tight mb-6">Custom Post Types</h2>
        {#if dashboard.postTypes.length === 0}
          <Card class="p-8 text-center">
            <div class="text-muted text-sm">No custom post types yet. They'll be created when the AI generates full-stack features.</div>
          </Card>
        {:else}
          <div class="grid grid-cols-3 gap-3.5">
            {#each dashboard.postTypes as c}
              <Card class="p-[22px_20px]">
                <div class="flex items-center justify-between mb-3">
                  <span class="font-heading text-base font-bold">{c.config?.label || c.slug}</span>
                  <Badge variant={c.source === 'ai' ? 'ai' : 'manual'}>{c.source}</Badge>
                </div>
                <div class="text-[12px] text-muted font-mono mb-2.5">{c.slug}</div>
                <div class="flex gap-2 text-xs text-muted-foreground">
                  <span>{c.entry_count || 0} entries</span>
                  {#if c.taxonomies && c.taxonomies.length > 0}
                    <span class="text-dim">·</span>
                    <span>{c.taxonomies.map(t => t.slug || t).join(', ')}</span>
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
        <h2 class="font-heading text-2xl font-extrabold tracking-tight mb-6">Settings</h2>

        <!-- API Keys Section -->
        <Card class="mb-4">
          <div class="px-[18px] py-2.5 border-b border-border text-[12px] font-semibold uppercase tracking-[1.5px] text-muted">
            API Keys
          </div>
          {#each Object.entries(getProviders()) as [slug, providerInfo], i}
            <div class="flex items-center justify-between px-[18px] py-2.5 {i < Object.keys(getProviders()).length - 1 ? 'border-b border-border-subtle' : ''}">
              <span class="text-[13px] text-foreground/80">{providerInfo.name || slug}</span>
              <div class="flex items-center gap-2">
                {#if apiKeyEditing[slug]}
                  <input
                    type="password"
                    class="text-xs text-foreground font-mono px-2.5 py-1 bg-background rounded-[5px] border border-border w-48 outline-none focus:border-copper"
                    placeholder="Enter API key..."
                    bind:value={apiKeyInputs[slug]}
                    onkeydown={(e) => e.key === 'Enter' && saveApiKey(slug)}
                  />
                  <Button size="sm" onclick={() => saveApiKey(slug)}>Save</Button>
                  <Button size="sm" variant="ghost" onclick={() => (apiKeyEditing[slug] = false)}>Cancel</Button>
                {:else}
                  <span class="text-xs text-muted font-mono px-2.5 py-1 bg-background rounded-[5px] border border-border">
                    {getMaskedKey(slug) || 'Not set'}
                  </span>
                  <Button size="sm" variant="ghost" onclick={() => startEditingKey(slug)}>Edit</Button>
                {/if}
              </div>
            </div>
          {/each}
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
      </div>
    {/if}
  </div>
</div>

<ConfirmDialog
  open={deleteConfirm.open}
  title="Delete template"
  description="This will permanently delete &ldquo;{deleteConfirm.key}&rdquo; and all its versions. This cannot be undone."
  confirmLabel="Delete"
  onconfirm={confirmDeleteTemplate}
  oncancel={() => (deleteConfirm = { open: false, key: '' })}
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

  .tk-dashboard :global(label) {
    color: inherit;
  }
</style>
