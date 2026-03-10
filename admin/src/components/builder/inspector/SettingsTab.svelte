<script>
  import { t } from '$lib/i18n.svelte.js';

  let { editor, comp } = $props();

  let compType = $derived(comp?.type || '');

  // Device visibility — stored as props
  let hideDesktop = $derived(comp?.props?.hideDesktop || false);
  let hideTablet = $derived(comp?.props?.hideTablet || false);
  let hideMobile = $derived(comp?.props?.hideMobile || false);

  // Visibility condition
  let visibilityCondition = $derived(comp?.props?.visibilityCondition || '');

  // Custom CSS
  let customCss = $derived(comp?.props?.customCss || '');
</script>

<div class="flex flex-col gap-0 overflow-y-auto flex-1">
  <!-- COMPONENT INFO -->
  <div class="p-3 border-b border-border-subtle">
    <label class="tk-inspector-label">{t('component_id', 'Component ID')}</label>
    <div class="text-[11px] text-dim font-mono bg-card-hover rounded px-2 py-1.5 select-all">{comp?.id || '—'}</div>
  </div>

  <!-- DEVICE VISIBILITY -->
  <div class="p-3 border-b border-border-subtle">
    <label class="tk-inspector-label mb-2">{t('device_visibility', 'Device Visibility')}</label>
    <div class="flex flex-col gap-2">
      {#each [
        { key: 'hideDesktop', label: 'Desktop', checked: hideDesktop },
        { key: 'hideTablet', label: 'Tablet', checked: hideTablet },
        { key: 'hideMobile', label: 'Mobile', checked: hideMobile },
      ] as device}
        <label class="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            checked={!device.checked}
            onchange={(e) => editor.updateProp(device.key, !e.target.checked)}
            class="tk-checkbox"
          />
          <span class="text-[12px] text-muted-foreground">{t(`show_on_${device.label.toLowerCase()}`, `Show on ${device.label}`)}</span>
        </label>
      {/each}
    </div>
  </div>

  <!-- VISIBILITY CONDITIONS -->
  <div class="p-3 border-b border-border-subtle">
    <label class="tk-inspector-label">{t('visibility_condition', 'Visibility Condition')}</label>
    <select
      class="tk-inspector-input"
      value={visibilityCondition}
      onchange={(e) => editor.updateProp('visibilityCondition', e.target.value)}
    >
      <option value="">{t('always_visible', 'Always visible')}</option>
      <option value="logged_in">{t('logged_in_only', 'Logged-in users only')}</option>
      <option value="logged_out">{t('logged_out_only', 'Logged-out users only')}</option>
      <option value="admin">{t('admins_only', 'Administrators only')}</option>
      <option value="editor">{t('editors_up', 'Editors and above')}</option>
    </select>
  </div>

  <!-- HTML TAG (for containers) -->
  {#if ['section', 'container', 'div', 'flex-row', 'flex-column'].includes(compType)}
    <div class="p-3 border-b border-border-subtle">
      <label class="tk-inspector-label">{t('html_tag', 'HTML Tag')}</label>
      <select
        class="tk-inspector-input"
        value={comp?.props?.tag || 'div'}
        onchange={(e) => editor.updateProp('tag', e.target.value)}
      >
        <option value="div">div</option>
        <option value="section">section</option>
        <option value="article">article</option>
        <option value="aside">aside</option>
        <option value="header">header</option>
        <option value="footer">footer</option>
        <option value="main">main</option>
        <option value="nav">nav</option>
      </select>
    </div>
  {/if}

  <!-- CUSTOM CSS -->
  <div class="p-3 border-b border-border-subtle">
    <label class="tk-inspector-label">{t('custom_css', 'Custom CSS')}</label>
    <p class="text-[10px] text-dim mb-2 mt-0">{t('custom_css_hint', 'Use %self% to target this element')}</p>
    <textarea
      value={customCss}
      oninput={(e) => editor.updateProp('customCss', e.target.value)}
      placeholder={`%self% {\n  /* your styles */\n}`}
      rows="5"
      class="tk-inspector-input font-mono"
      style="resize: vertical; min-height: 80px; line-height: 1.5; tab-size: 2;"
    ></textarea>
  </div>

  <!-- ARIA / ACCESSIBILITY -->
  <div class="p-3">
    <label class="tk-inspector-label">{t('accessibility', 'Accessibility')}</label>
    <div class="flex flex-col gap-2 mt-1">
      <div>
        <label class="text-[10px] text-dim uppercase tracking-[0.5px]">{t('aria_label', 'ARIA Label')}</label>
        <input
          type="text"
          value={comp?.props?.ariaLabel || ''}
          oninput={(e) => editor.updateProp('ariaLabel', e.target.value)}
          placeholder={t('aria_label_placeholder', 'Accessible label...')}
          class="tk-inspector-input"
        />
      </div>
      <div>
        <label class="text-[10px] text-dim uppercase tracking-[0.5px]">{t('aria_role', 'Role')}</label>
        <input
          type="text"
          value={comp?.props?.role || ''}
          oninput={(e) => editor.updateProp('role', e.target.value)}
          placeholder="banner, navigation, etc."
          class="tk-inspector-input"
        />
      </div>
    </div>
  </div>
</div>

<style>
  .tk-inspector-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--color-muted);
    margin-bottom: 4px;
  }

  .tk-checkbox {
    width: 14px;
    height: 14px;
    accent-color: var(--color-copper);
    cursor: pointer;
  }

  :global(.border-border-subtle) {
    border-color: var(--color-border-subtle, var(--color-border));
  }
</style>
