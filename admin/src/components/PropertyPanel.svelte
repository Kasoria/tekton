<script>
  import { t } from '$lib/i18n.svelte.js';

  let { editor, page, typeMap = {}, onclose } = $props();

  // Collapsible sections
  let openSections = $state({ content: true, typography: true, spacing: false, background: false, size: false });

  function toggle(key) {
    openSections = { ...openSections, [key]: !openSections[key] };
  }

  let comp = $derived(editor.selectedComponent);
  let styles = $derived(editor.selectedStyles);
  let compType = $derived(comp?.type || '');

  // Whether this component has text content
  let hasContent = $derived(['heading', 'text', 'button', 'link'].includes(compType));
  let contentProp = $derived(compType === 'button' || compType === 'link' ? 'text' : 'content');
  let contentValue = $derived.by(() => {
    if (!comp?.props) return '';
    const val = comp.props[contentProp];
    if (val && typeof val === 'object' && val.source) return val.fallback || '';
    return val || '';
  });
  let isContentSource = $derived.by(() => {
    if (!comp?.props) return false;
    const val = comp.props[contentProp];
    return val && typeof val === 'object' && !!val.source;
  });
  let contentSourceLabel = $derived.by(() => {
    if (!comp?.props) return '';
    const val = comp.props[contentProp];
    if (!val || typeof val !== 'object' || !val.source) return '';
    let label = val.source;
    if (val.group) label += '.' + val.group;
    if (val.field) label += '.' + val.field;
    return label;
  });

  // Has link props
  let hasHref = $derived(['button', 'link'].includes(compType));

  // Style helpers
  function getStyle(prop) {
    return styles[prop] || '';
  }

  function setStyle(prop, value) {
    editor.updateStyle(prop, value);
  }

  function setContent(value) {
    editor.updateContent(contentProp, value, isContentSource);
  }

  function setProp(name, value) {
    editor.updateProp(name, value);
  }

  const breakpoints = [
    { key: 'desktop', label: 'Desktop', icon: 'D' },
    { key: 'tablet', label: 'Tablet', icon: 'T' },
    { key: 'mobile', label: 'Mobile', icon: 'M' },
  ];

  const fontWeights = [
    { value: '100', label: 'Thin' },
    { value: '200', label: 'Extra Light' },
    { value: '300', label: 'Light' },
    { value: '400', label: 'Normal' },
    { value: '500', label: 'Medium' },
    { value: '600', label: 'Semibold' },
    { value: '700', label: 'Bold' },
    { value: '800', label: 'Extra Bold' },
    { value: '900', label: 'Black' },
  ];

  const textAlignOptions = ['left', 'center', 'right', 'justify'];
</script>

<div class="tk-property-panel">
  <!-- Header -->
  <div class="tk-pp-header">
    <div class="flex items-center gap-2">
      {#if typeMap[compType]}
        <span
          class="w-5 h-5 rounded flex items-center justify-center text-[9px] font-bold font-mono"
          style="background: {typeMap[compType].hue}15; color: {typeMap[compType].hue};"
        >{typeMap[compType].letter}</span>
      {/if}
      <span class="text-[12px] font-semibold uppercase tracking-[1.5px] text-muted-foreground">{compType}</span>
    </div>
    <button class="tk-pp-close" onclick={onclose}>x</button>
  </div>

  <!-- Breakpoint selector -->
  <div class="tk-pp-breakpoints">
    {#each breakpoints as bp}
      <button
        class="tk-pp-bp-btn {editor.activeBreakpoint === bp.key ? 'active' : ''}"
        onclick={() => editor.setBreakpoint(bp.key)}
        title={bp.label}
      >{bp.label}</button>
    {/each}
  </div>

  <div class="tk-pp-body">
    <!-- Component ID -->
    <div class="tk-pp-id">
      <span class="text-dim text-[10px] font-mono">{comp?.id || ''}</span>
    </div>

    <!-- Content Section -->
    {#if hasContent}
      <div class="tk-pp-section">
        <button class="tk-pp-section-header" onclick={() => toggle('content')}>
          <span>{t('content', 'Content')}</span>
          <span class="tk-pp-chevron {openSections.content ? 'open' : ''}">&#9656;</span>
        </button>
        {#if openSections.content}
          <div class="tk-pp-section-body">
            {#if isContentSource}
              <div class="tk-pp-source-badge">
                Dynamic: {contentSourceLabel}
              </div>
            {/if}
            <div>
              <label class="tk-pp-label">{isContentSource ? t('fallback', 'Fallback') : t('text', 'Text')}</label>
              {#if compType === 'text'}
                <textarea
                  class="tk-pp-input tk-pp-textarea"
                  value={contentValue}
                  oninput={(e) => setContent(e.target.value)}
                ></textarea>
              {:else}
                <input
                  type="text"
                  class="tk-pp-input"
                  value={contentValue}
                  oninput={(e) => setContent(e.target.value)}
                />
              {/if}
            </div>

            {#if hasHref}
              <div>
                <label class="tk-pp-label">{t('link_url', 'Link URL')}</label>
                <input
                  type="text"
                  class="tk-pp-input"
                  value={comp?.props?.href || ''}
                  oninput={(e) => setProp('href', e.target.value)}
                  placeholder="https://..."
                />
              </div>
              <div>
                <label class="tk-pp-label">{t('target', 'Target')}</label>
                <select
                  class="tk-pp-select"
                  value={comp?.props?.target || '_self'}
                  onchange={(e) => setProp('target', e.target.value)}
                >
                  <option value="_self">Same window</option>
                  <option value="_blank">New tab</option>
                </select>
              </div>
            {/if}
          </div>
        {/if}
      </div>
    {/if}

    <!-- Typography Section -->
    <div class="tk-pp-section">
      <button class="tk-pp-section-header" onclick={() => toggle('typography')}>
        <span>{t('typography', 'Typography')}</span>
        <span class="tk-pp-chevron {openSections.typography ? 'open' : ''}">&#9656;</span>
      </button>
      {#if openSections.typography}
        <div class="tk-pp-section-body">
          <div class="tk-pp-row">
            <div class="tk-pp-field">
              <label class="tk-pp-label">{t('font_size', 'Size')}</label>
              <input
                type="text"
                class="tk-pp-input"
                value={getStyle('fontSize')}
                oninput={(e) => setStyle('fontSize', e.target.value)}
                placeholder="16px"
              />
            </div>
            <div class="tk-pp-field">
              <label class="tk-pp-label">{t('font_weight', 'Weight')}</label>
              <select
                class="tk-pp-select"
                value={getStyle('fontWeight') || ''}
                onchange={(e) => setStyle('fontWeight', e.target.value)}
              >
                <option value="">-</option>
                {#each fontWeights as fw}
                  <option value={fw.value}>{fw.label}</option>
                {/each}
              </select>
            </div>
          </div>

          <div class="tk-pp-row">
            <div class="tk-pp-field">
              <label class="tk-pp-label">{t('line_height', 'Line H.')}</label>
              <input
                type="text"
                class="tk-pp-input"
                value={getStyle('lineHeight')}
                oninput={(e) => setStyle('lineHeight', e.target.value)}
                placeholder="1.5"
              />
            </div>
            <div class="tk-pp-field">
              <label class="tk-pp-label">{t('letter_spacing', 'Spacing')}</label>
              <input
                type="text"
                class="tk-pp-input"
                value={getStyle('letterSpacing')}
                oninput={(e) => setStyle('letterSpacing', e.target.value)}
                placeholder="0px"
              />
            </div>
          </div>

          <div>
            <label class="tk-pp-label">{t('text_align', 'Align')}</label>
            <div class="tk-pp-align-group">
              {#each textAlignOptions as align}
                <button
                  class="tk-pp-align-btn {getStyle('textAlign') === align ? 'active' : ''}"
                  onclick={() => setStyle('textAlign', align)}
                  title={align}
                >{align[0].toUpperCase()}</button>
              {/each}
            </div>
          </div>

          <div>
            <label class="tk-pp-label">{t('color', 'Color')}</label>
            <div class="tk-pp-color-row">
            <input
              type="color"
              class="tk-pp-color-picker"
              value={getStyle('color') || '#000000'}
              oninput={(e) => setStyle('color', e.target.value)}
            />
            <input
              type="text"
              class="tk-pp-input tk-pp-color-text"
              value={getStyle('color')}
              oninput={(e) => setStyle('color', e.target.value)}
              placeholder="#000000"
            />
            </div>
          </div>
        </div>
      {/if}
    </div>

    <!-- Spacing Section -->
    <div class="tk-pp-section">
      <button class="tk-pp-section-header" onclick={() => toggle('spacing')}>
        <span>{t('spacing', 'Spacing')}</span>
        <span class="tk-pp-chevron {openSections.spacing ? 'open' : ''}">&#9656;</span>
      </button>
      {#if openSections.spacing}
        <div class="tk-pp-section-body">
          <div>
            <label class="tk-pp-label">{t('padding', 'Padding')}</label>
            <div class="tk-pp-spacing-grid">
              <input type="text" class="tk-pp-input tk-pp-spacing-input" value={getStyle('paddingTop')} oninput={(e) => setStyle('paddingTop', e.target.value)} placeholder="T" title="Padding Top" />
              <input type="text" class="tk-pp-input tk-pp-spacing-input" value={getStyle('paddingRight')} oninput={(e) => setStyle('paddingRight', e.target.value)} placeholder="R" title="Padding Right" />
              <input type="text" class="tk-pp-input tk-pp-spacing-input" value={getStyle('paddingBottom')} oninput={(e) => setStyle('paddingBottom', e.target.value)} placeholder="B" title="Padding Bottom" />
              <input type="text" class="tk-pp-input tk-pp-spacing-input" value={getStyle('paddingLeft')} oninput={(e) => setStyle('paddingLeft', e.target.value)} placeholder="L" title="Padding Left" />
            </div>
          </div>
          <div>
            <label class="tk-pp-label">{t('padding_shorthand', 'All sides')}</label>
            <input type="text" class="tk-pp-input" value={getStyle('padding')} oninput={(e) => setStyle('padding', e.target.value)} placeholder="e.g. 20px 40px" />
          </div>

          <div>
            <label class="tk-pp-label">{t('margin', 'Margin')}</label>
            <div class="tk-pp-spacing-grid">
              <input type="text" class="tk-pp-input tk-pp-spacing-input" value={getStyle('marginTop')} oninput={(e) => setStyle('marginTop', e.target.value)} placeholder="T" title="Margin Top" />
              <input type="text" class="tk-pp-input tk-pp-spacing-input" value={getStyle('marginRight')} oninput={(e) => setStyle('marginRight', e.target.value)} placeholder="R" title="Margin Right" />
              <input type="text" class="tk-pp-input tk-pp-spacing-input" value={getStyle('marginBottom')} oninput={(e) => setStyle('marginBottom', e.target.value)} placeholder="B" title="Margin Bottom" />
              <input type="text" class="tk-pp-input tk-pp-spacing-input" value={getStyle('marginLeft')} oninput={(e) => setStyle('marginLeft', e.target.value)} placeholder="L" title="Margin Left" />
            </div>
          </div>
          <div>
            <label class="tk-pp-label">{t('margin_shorthand', 'All sides')}</label>
            <input type="text" class="tk-pp-input" value={getStyle('margin')} oninput={(e) => setStyle('margin', e.target.value)} placeholder="e.g. 0 auto" />
          </div>

          <div>
            <label class="tk-pp-label">{t('gap', 'Gap')}</label>
            <input type="text" class="tk-pp-input" value={getStyle('gap')} oninput={(e) => setStyle('gap', e.target.value)} placeholder="16px" />
          </div>
        </div>
      {/if}
    </div>

    <!-- Background & Border Section -->
    <div class="tk-pp-section">
      <button class="tk-pp-section-header" onclick={() => toggle('background')}>
        <span>{t('background_border', 'Background & Border')}</span>
        <span class="tk-pp-chevron {openSections.background ? 'open' : ''}">&#9656;</span>
      </button>
      {#if openSections.background}
        <div class="tk-pp-section-body">
          <div>
            <label class="tk-pp-label">{t('background_color', 'Background')}</label>
            <div class="tk-pp-color-row">
              <input
                type="color"
                class="tk-pp-color-picker"
                value={getStyle('backgroundColor') || '#ffffff'}
                oninput={(e) => setStyle('backgroundColor', e.target.value)}
              />
              <input
                type="text"
                class="tk-pp-input tk-pp-color-text"
                value={getStyle('backgroundColor')}
                oninput={(e) => setStyle('backgroundColor', e.target.value)}
                placeholder="transparent"
              />
            </div>
          </div>

          <div>
            <label class="tk-pp-label">{t('border', 'Border')}</label>
            <div class="tk-pp-row">
              <div class="tk-pp-field">
                <input type="text" class="tk-pp-input" value={getStyle('borderWidth')} oninput={(e) => setStyle('borderWidth', e.target.value)} placeholder="0px" />
              </div>
              <div class="tk-pp-field">
                <select class="tk-pp-select" value={getStyle('borderStyle') || ''} onchange={(e) => setStyle('borderStyle', e.target.value)}>
                  <option value="">-</option>
                  <option value="solid">Solid</option>
                  <option value="dashed">Dashed</option>
                  <option value="dotted">Dotted</option>
                  <option value="none">None</option>
                </select>
              </div>
            </div>
            <div class="tk-pp-color-row" style="margin-top: 8px;">
              <input type="color" class="tk-pp-color-picker" value={getStyle('borderColor') || '#000000'} oninput={(e) => setStyle('borderColor', e.target.value)} />
              <input type="text" class="tk-pp-input tk-pp-color-text" value={getStyle('borderColor')} oninput={(e) => setStyle('borderColor', e.target.value)} placeholder="#000" />
            </div>
          </div>

          <div>
            <label class="tk-pp-label">{t('border_radius', 'Radius')}</label>
            <input type="text" class="tk-pp-input" value={getStyle('borderRadius')} oninput={(e) => setStyle('borderRadius', e.target.value)} placeholder="0px" />
          </div>
        </div>
      {/if}
    </div>

    <!-- Size Section -->
    <div class="tk-pp-section">
      <button class="tk-pp-section-header" onclick={() => toggle('size')}>
        <span>{t('size_layout', 'Size & Layout')}</span>
        <span class="tk-pp-chevron {openSections.size ? 'open' : ''}">&#9656;</span>
      </button>
      {#if openSections.size}
        <div class="tk-pp-section-body">
          <div class="tk-pp-row">
            <div class="tk-pp-field">
              <label class="tk-pp-label">{t('width', 'Width')}</label>
              <input type="text" class="tk-pp-input" value={getStyle('width')} oninput={(e) => setStyle('width', e.target.value)} placeholder="auto" />
            </div>
            <div class="tk-pp-field">
              <label class="tk-pp-label">{t('height', 'Height')}</label>
              <input type="text" class="tk-pp-input" value={getStyle('height')} oninput={(e) => setStyle('height', e.target.value)} placeholder="auto" />
            </div>
          </div>
          <div class="tk-pp-row">
            <div class="tk-pp-field">
              <label class="tk-pp-label">{t('min_width', 'Min W')}</label>
              <input type="text" class="tk-pp-input" value={getStyle('minWidth')} oninput={(e) => setStyle('minWidth', e.target.value)} placeholder="0" />
            </div>
            <div class="tk-pp-field">
              <label class="tk-pp-label">{t('max_width', 'Max W')}</label>
              <input type="text" class="tk-pp-input" value={getStyle('maxWidth')} oninput={(e) => setStyle('maxWidth', e.target.value)} placeholder="none" />
            </div>
          </div>

          <div>
            <label class="tk-pp-label">{t('overflow', 'Overflow')}</label>
            <select class="tk-pp-select" value={getStyle('overflow') || ''} onchange={(e) => setStyle('overflow', e.target.value)}>
              <option value="">-</option>
              <option value="visible">Visible</option>
              <option value="hidden">Hidden</option>
              <option value="auto">Auto</option>
              <option value="scroll">Scroll</option>
            </select>
          </div>

          <div>
            <label class="tk-pp-label">{t('opacity', 'Opacity')}</label>
            <div class="flex items-center gap-2">
              <input
                type="range"
                min="0" max="1" step="0.05"
                value={getStyle('opacity') || '1'}
                oninput={(e) => setStyle('opacity', e.target.value)}
                class="tk-pp-range"
              />
              <span class="text-[11px] text-dim font-mono w-8 text-right">{getStyle('opacity') || '1'}</span>
            </div>
          </div>
        </div>
      {/if}
    </div>
  </div>

  <!-- Dirty indicator -->
  {#if editor.dirty}
    <div class="tk-pp-dirty">
      <span class="text-[10px] text-copper">{t('unsaved_changes', 'Saving...')}</span>
    </div>
  {/if}
</div>

<style>
  .tk-property-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
  }

  .tk-pp-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    border-bottom: 1px solid var(--color-border);
    flex-shrink: 0;
  }

  .tk-pp-close {
    background: transparent;
    border: none;
    color: var(--color-dim);
    cursor: pointer;
    font-size: 15px;
    line-height: 1;
    padding: 2px;
  }
  .tk-pp-close:hover { color: var(--color-muted); }

  .tk-pp-breakpoints {
    display: flex;
    gap: 4px;
    padding: 8px 16px;
    border-bottom: 1px solid var(--color-border);
    flex-shrink: 0;
  }

  .tk-pp-bp-btn {
    flex: 1;
    padding: 5px 0;
    background: transparent;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    color: var(--color-dim);
    font-size: 11px;
    font-weight: 600;
    font-family: var(--font-body);
    cursor: pointer;
    transition: all 0.15s;
  }
  .tk-pp-bp-btn:hover { color: var(--color-muted-foreground); }
  .tk-pp-bp-btn.active {
    background: var(--color-copper);
    border-color: var(--color-copper);
    color: #fff;
  }

  .tk-pp-body {
    flex: 1;
    overflow-y: auto;
    padding: 4px 0;
  }

  .tk-pp-id {
    padding: 4px 16px 8px;
  }

  .tk-pp-section {
    border-bottom: 1px solid var(--color-border-subtle, var(--color-border));
  }

  .tk-pp-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 10px 16px;
    background: transparent;
    border: none;
    color: var(--color-foreground);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    cursor: pointer;
    font-family: var(--font-body);
  }
  .tk-pp-section-header:hover { background: var(--color-card-hover); }

  .tk-pp-chevron {
    font-size: 10px;
    color: var(--color-dim);
    transition: transform 0.15s;
    display: inline-block;
  }
  .tk-pp-chevron.open { transform: rotate(90deg); }

  .tk-pp-section-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 4px 16px 16px;
  }

  .tk-pp-label {
    display: block;
    font-size: 9px;
    font-weight: 500;
    color: var(--color-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 3px;
    font-family: var(--font-body);
  }

  .tk-pp-input {
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
  .tk-pp-input:focus {
    border-color: var(--color-copper) !important;
    outline: none !important;
  }

  .tk-pp-textarea {
    min-height: 60px;
    resize: vertical;
    font-family: var(--font-body);
  }

  .tk-pp-select {
    width: 100%;
    padding: 7px 10px;
    font-size: 12px;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    background: var(--color-background);
    color: var(--color-foreground);
    font-family: var(--font-body);
    cursor: pointer;
    appearance: auto;
  }

  .tk-pp-row {
    display: flex;
    gap: 10px;
  }
  .tk-pp-field {
    flex: 1;
  }

  .tk-pp-color-row {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .tk-pp-color-picker {
    width: 28px;
    height: 28px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    padding: 1px;
    cursor: pointer;
    flex-shrink: 0;
    background: transparent;
  }

  .tk-pp-color-text {
    flex: 1;
  }

  .tk-pp-align-group {
    display: flex;
    gap: 2px;
  }

  .tk-pp-align-btn {
    flex: 1;
    padding: 4px 0;
    background: transparent;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    color: var(--color-dim);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    font-family: var(--font-mono);
    transition: all 0.15s;
  }
  .tk-pp-align-btn:hover { color: var(--color-muted-foreground); }
  .tk-pp-align-btn.active {
    background: var(--color-copper);
    border-color: var(--color-copper);
    color: #fff;
  }

  .tk-pp-spacing-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 4px;
  }
  .tk-pp-spacing-input {
    text-align: center;
    padding: 5px 2px;
  }

  .tk-pp-source-badge {
    font-size: 10px;
    color: #e8c496;
    background: #1a1816;
    padding: 3px 8px;
    border-radius: 4px;
    margin-bottom: 6px;
    font-family: var(--font-mono);
  }

  .tk-pp-range {
    flex: 1;
    accent-color: var(--color-copper);
  }

  .tk-pp-dirty {
    padding: 4px 14px;
    border-top: 1px solid var(--color-border);
    text-align: center;
    flex-shrink: 0;
  }
</style>
