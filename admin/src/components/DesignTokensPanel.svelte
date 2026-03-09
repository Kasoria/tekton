<script>
  import { t } from '$lib/i18n.svelte.js';

  let { store, onTokensChanged } = $props();

  let openSections = $state({ colors: true, fonts: true, typography: false, spacing: false, radii: false, shadows: false, meta: false, notes: false });

  function toggle(key) {
    openSections = { ...openSections, [key]: !openSections[key] };
  }

  const colorKeys = [
    { key: 'primary', i18n: 'dt_primary', fallback: 'Primary' },
    { key: 'secondary', i18n: 'dt_secondary', fallback: 'Secondary' },
    { key: 'accent', i18n: 'dt_accent', fallback: 'Accent' },
    { key: 'background', i18n: 'dt_background', fallback: 'Background' },
    { key: 'surface', i18n: 'dt_surface', fallback: 'Surface' },
    { key: 'text', i18n: 'dt_text', fallback: 'Text' },
    { key: 'text_muted', i18n: 'dt_text_muted', fallback: 'Text Muted' },
    { key: 'border', i18n: 'dt_border', fallback: 'Border' },
  ];

  const typographyKeys = [
    { key: 'size_xs', i18n: 'dt_size_xs', fallback: 'Size XS' },
    { key: 'size_sm', i18n: 'dt_size_sm', fallback: 'Size SM' },
    { key: 'size_base', i18n: 'dt_size_base', fallback: 'Size Base' },
    { key: 'size_lg', i18n: 'dt_size_lg', fallback: 'Size LG' },
    { key: 'size_xl', i18n: 'dt_size_xl', fallback: 'Size XL' },
    { key: 'size_2xl', i18n: 'dt_size_2xl', fallback: 'Size 2XL' },
    { key: 'size_3xl', i18n: 'dt_size_3xl', fallback: 'Size 3XL' },
    { key: 'size_4xl', i18n: 'dt_size_4xl', fallback: 'Size 4XL' },
    { key: 'size_5xl', i18n: 'dt_size_5xl', fallback: 'Size 5XL' },
    { key: 'line_height_tight', i18n: 'dt_lh_tight', fallback: 'Line Height Tight' },
    { key: 'line_height_base', i18n: 'dt_lh_base', fallback: 'Line Height Base' },
    { key: 'line_height_relaxed', i18n: 'dt_lh_relaxed', fallback: 'Line Height Relaxed' },
    { key: 'letter_spacing_tight', i18n: 'dt_ls_tight', fallback: 'Letter Spacing Tight' },
    { key: 'letter_spacing_normal', i18n: 'dt_ls_normal', fallback: 'Letter Spacing Normal' },
    { key: 'letter_spacing_wide', i18n: 'dt_ls_wide', fallback: 'Letter Spacing Wide' },
  ];

  const spacingKeys = [
    { key: 'xs', i18n: 'dt_sp_xs', fallback: 'XS' },
    { key: 'sm', i18n: 'dt_sp_sm', fallback: 'SM' },
    { key: 'md', i18n: 'dt_sp_md', fallback: 'MD' },
    { key: 'lg', i18n: 'dt_sp_lg', fallback: 'LG' },
    { key: 'xl', i18n: 'dt_sp_xl', fallback: 'XL' },
    { key: '2xl', i18n: 'dt_sp_2xl', fallback: '2XL' },
    { key: '3xl', i18n: 'dt_sp_3xl', fallback: '3XL' },
    { key: 'section_padding', i18n: 'dt_section_padding', fallback: 'Section Padding' },
    { key: 'content_gap', i18n: 'dt_content_gap', fallback: 'Content Gap' },
    { key: 'container_max_width', i18n: 'dt_max_width', fallback: 'Max Width' },
  ];

  const radiiKeys = [
    { key: 'sm', i18n: 'dt_rad_sm', fallback: 'SM' },
    { key: 'md', i18n: 'dt_rad_md', fallback: 'MD' },
    { key: 'lg', i18n: 'dt_rad_lg', fallback: 'LG' },
    { key: 'xl', i18n: 'dt_rad_xl', fallback: 'XL' },
    { key: 'full', i18n: 'dt_rad_full', fallback: 'Full' },
  ];

  const shadowKeys = [
    { key: 'sm', i18n: 'dt_sh_sm', fallback: 'SM' },
    { key: 'md', i18n: 'dt_sh_md', fallback: 'MD' },
    { key: 'lg', i18n: 'dt_sh_lg', fallback: 'LG' },
    { key: 'xl', i18n: 'dt_sh_xl', fallback: 'XL' },
  ];

  function getField(category, key) {
    return store.theme?.[category]?.[key] || '';
  }

  function setField(category, key, value) {
    store.updateField(category, key, value);
  }

  // Normalize hex for color input (needs #rrggbb format)
  function normalizeHex(hex) {
    if (!hex || !hex.startsWith('#')) return '#000000';
    const clean = hex.replace('#', '');
    if (clean.length === 3) {
      return '#' + clean[0] + clean[0] + clean[1] + clean[1] + clean[2] + clean[2];
    }
    if (clean.length === 6) return '#' + clean;
    return '#000000';
  }

  // Register callback for token changes
  $effect(() => {
    if (!onTokensChanged) return;
    const unsubscribe = store.onSave(onTokensChanged);
    return unsubscribe;
  });
</script>

<div class="tk-dt-panel">
  {#if !store.theme}
    <div class="tk-dt-empty">
      <span class="text-[12px] text-muted">{t('loading', 'Loading...')}</span>
    </div>
  {:else}
    <!-- Save indicator -->
    <div class="tk-dt-status">
      {#if store.saving}
        <span class="text-[10px] text-copper">{t('saving', 'Saving...')}</span>
      {:else if store.saved}
        <span class="text-[10px] text-green">{t('dt_saved', 'Saved')}</span>
      {/if}
    </div>

    <!-- Theme Meta -->
    <div class="tk-dt-section">
      <button class="tk-dt-section-header" onclick={() => toggle('meta')}>
        <span>{t('theme', 'Theme')}</span>
        <span class="tk-dt-chevron {openSections.meta ? 'open' : ''}">&#9656;</span>
      </button>
      {#if openSections.meta}
        <div class="tk-dt-section-body">
          <div>
            <label class="tk-dt-label">{t('name', 'Name')}</label>
            <input
              type="text"
              class="tk-dt-input"
              value={store.theme.name || ''}
              oninput={(e) => store.updateTopLevel('name', e.target.value)}
            />
          </div>
          <div>
            <label class="tk-dt-label">{t('description', 'Description')}</label>
            <input
              type="text"
              class="tk-dt-input"
              value={store.theme.description || ''}
              oninput={(e) => store.updateTopLevel('description', e.target.value)}
            />
          </div>
        </div>
      {/if}
    </div>

    <!-- Colors -->
    <div class="tk-dt-section">
      <button class="tk-dt-section-header" onclick={() => toggle('colors')}>
        <span>{t('colors', 'Colors')}</span>
        <span class="tk-dt-chevron {openSections.colors ? 'open' : ''}">&#9656;</span>
      </button>
      {#if openSections.colors}
        <div class="tk-dt-section-body">
          {#each colorKeys as c}
            <div>
              <label class="tk-dt-label">{t(c.i18n, c.fallback)}</label>
              <div class="tk-dt-color-row">
                <input
                  type="color"
                  class="tk-dt-color-picker"
                  value={normalizeHex(getField('colors', c.key))}
                  oninput={(e) => setField('colors', c.key, e.target.value)}
                />
                <input
                  type="text"
                  class="tk-dt-input tk-dt-color-text"
                  value={getField('colors', c.key)}
                  oninput={(e) => setField('colors', c.key, e.target.value)}
                  placeholder="#000000"
                />
              </div>
            </div>
          {/each}
        </div>
      {/if}
    </div>

    <!-- Fonts -->
    <div class="tk-dt-section">
      <button class="tk-dt-section-header" onclick={() => toggle('fonts')}>
        <span>{t('fonts', 'Fonts')}</span>
        <span class="tk-dt-chevron {openSections.fonts ? 'open' : ''}">&#9656;</span>
      </button>
      {#if openSections.fonts}
        <div class="tk-dt-section-body">
          <div>
            <label class="tk-dt-label">{t('dt_heading_font', 'Heading')}</label>
            <input
              type="text"
              class="tk-dt-input"
              value={getField('fonts', 'heading')}
              oninput={(e) => setField('fonts', 'heading', e.target.value)}
              placeholder="Playfair Display"
            />
            {#if getField('fonts', 'heading')}
              <div class="tk-dt-font-preview" style="font-family: {getField('fonts', 'heading')}, sans-serif; font-size: 16px; font-weight: 700;">
                {getField('fonts', 'heading')}
              </div>
            {/if}
          </div>
          <div>
            <label class="tk-dt-label">{t('dt_body_font', 'Body')}</label>
            <input
              type="text"
              class="tk-dt-input"
              value={getField('fonts', 'body')}
              oninput={(e) => setField('fonts', 'body', e.target.value)}
              placeholder="Inter"
            />
            {#if getField('fonts', 'body')}
              <div class="tk-dt-font-preview" style="font-family: {getField('fonts', 'body')}, sans-serif; font-size: 13px;">
                {t('dt_font_preview', 'The quick brown fox jumps over the lazy dog')}
              </div>
            {/if}
          </div>
        </div>
      {/if}
    </div>

    <!-- Typography -->
    <div class="tk-dt-section">
      <button class="tk-dt-section-header" onclick={() => toggle('typography')}>
        <span>{t('typography', 'Typography')}</span>
        <span class="tk-dt-chevron {openSections.typography ? 'open' : ''}">&#9656;</span>
      </button>
      {#if openSections.typography}
        <div class="tk-dt-section-body">
          <div class="tk-dt-grid">
            {#each typographyKeys as tk}
              <div class="tk-dt-grid-field">
                <label class="tk-dt-label">{t(tk.i18n, tk.fallback)}</label>
                <input
                  type="text"
                  class="tk-dt-input"
                  value={getField('typography', tk.key)}
                  oninput={(e) => setField('typography', tk.key, e.target.value)}
                />
              </div>
            {/each}
          </div>
        </div>
      {/if}
    </div>

    <!-- Spacing -->
    <div class="tk-dt-section">
      <button class="tk-dt-section-header" onclick={() => toggle('spacing')}>
        <span>{t('dt_spacing', 'Spacing')}</span>
        <span class="tk-dt-chevron {openSections.spacing ? 'open' : ''}">&#9656;</span>
      </button>
      {#if openSections.spacing}
        <div class="tk-dt-section-body">
          <div class="tk-dt-grid">
            {#each spacingKeys as sk}
              <div class="tk-dt-grid-field">
                <label class="tk-dt-label">{t(sk.i18n, sk.fallback)}</label>
                <input
                  type="text"
                  class="tk-dt-input"
                  value={getField('spacing', sk.key)}
                  oninput={(e) => setField('spacing', sk.key, e.target.value)}
                />
              </div>
            {/each}
          </div>
        </div>
      {/if}
    </div>

    <!-- Border Radii -->
    <div class="tk-dt-section">
      <button class="tk-dt-section-header" onclick={() => toggle('radii')}>
        <span>{t('dt_radii', 'Border Radii')}</span>
        <span class="tk-dt-chevron {openSections.radii ? 'open' : ''}">&#9656;</span>
      </button>
      {#if openSections.radii}
        <div class="tk-dt-section-body">
          <div class="tk-dt-grid">
            {#each radiiKeys as rk}
              <div class="tk-dt-grid-field">
                <label class="tk-dt-label">{t(rk.i18n, rk.fallback)}</label>
                <input
                  type="text"
                  class="tk-dt-input"
                  value={getField('radii', rk.key)}
                  oninput={(e) => setField('radii', rk.key, e.target.value)}
                />
              </div>
            {/each}
          </div>
        </div>
      {/if}
    </div>

    <!-- Shadows -->
    <div class="tk-dt-section">
      <button class="tk-dt-section-header" onclick={() => toggle('shadows')}>
        <span>{t('dt_shadows', 'Shadows')}</span>
        <span class="tk-dt-chevron {openSections.shadows ? 'open' : ''}">&#9656;</span>
      </button>
      {#if openSections.shadows}
        <div class="tk-dt-section-body">
          {#each shadowKeys as sk}
            <div>
              <label class="tk-dt-label">{t(sk.i18n, sk.fallback)}</label>
              <input
                type="text"
                class="tk-dt-input"
                value={getField('shadows', sk.key)}
                oninput={(e) => setField('shadows', sk.key, e.target.value)}
              />
            </div>
          {/each}
        </div>
      {/if}
    </div>

    <!-- Style Notes -->
    <div class="tk-dt-section">
      <button class="tk-dt-section-header" onclick={() => toggle('notes')}>
        <span>{t('dt_style_notes', 'Style Notes')}</span>
        <span class="tk-dt-chevron {openSections.notes ? 'open' : ''}">&#9656;</span>
      </button>
      {#if openSections.notes}
        <div class="tk-dt-section-body">
          <textarea
            class="tk-dt-input tk-dt-textarea"
            value={store.theme.style_notes || ''}
            oninput={(e) => store.updateTopLevel('style_notes', e.target.value)}
            placeholder={t('dt_style_notes_placeholder', 'Describe the visual style...')}
          ></textarea>
        </div>
      {/if}
    </div>
  {/if}
</div>

<style>
  .tk-dt-panel {
    display: flex;
    flex-direction: column;
  }

  .tk-dt-empty {
    padding: 16px;
    text-align: center;
  }

  .tk-dt-status {
    min-height: 20px;
    padding: 2px 16px;
    text-align: right;
  }

  .tk-dt-section {
    border-bottom: 1px solid var(--color-border-subtle, var(--color-border));
  }

  .tk-dt-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 10px 16px;
    background: transparent;
    border: none;
    color: var(--color-foreground);
    font-size: 12px !important;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    cursor: pointer;
    font-family: var(--font-body);
  }
  .tk-dt-section-header:hover { background: var(--color-card-hover); }

  .tk-dt-chevron {
    font-size: 10px;
    color: var(--color-dim);
    transition: transform 0.15s;
    display: inline-block;
  }
  .tk-dt-chevron.open { transform: rotate(90deg); }

  .tk-dt-section-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 4px 16px 16px;
  }

  .tk-dt-label {
    display: block;
    font-size: 10px !important;
    font-weight: 500;
    color: var(--color-dim);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-bottom: 2px;
    font-family: var(--font-body);
  }

  .tk-dt-input {
    width: 100%;
    padding: 7px 10px;
    font-size: 12px;
    border: 1px solid var(--color-border) !important;
    border-radius: 5px;
    background: var(--color-background) !important;
    color: var(--color-foreground) !important;
    font-family: var(--font-mono);
    box-sizing: border-box;
    transition: border-color 0.15s;
  }
  .tk-dt-input:focus {
    border-color: var(--color-copper) !important;
    outline: none !important;
  }

  .tk-dt-textarea {
    min-height: 60px;
    resize: vertical;
    font-family: var(--font-body);
  }

  .tk-dt-color-row {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .tk-dt-color-picker {
    width: 28px;
    height: 28px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    padding: 1px;
    cursor: pointer;
    flex-shrink: 0;
    background: transparent;
  }

  .tk-dt-color-text {
    flex: 1;
  }

  .tk-dt-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }

  .tk-dt-grid-field {
    min-width: 0;
  }

  .tk-dt-font-preview {
    margin-top: 6px;
    padding: 6px 8px;
    border-radius: 4px;
    background: var(--color-background);
    border: 1px solid var(--color-border-subtle, var(--color-border));
    color: var(--color-foreground);
    line-height: 1.4;
  }
</style>
