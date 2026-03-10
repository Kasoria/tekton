<script>
  import { t } from '$lib/i18n.svelte.js';
  import SpacingBoxModel from './SpacingBoxModel.svelte';

  let { editor, comp } = $props();

  let styles = $derived(editor.selectedStyles);

  let openSections = $state({
    typography: true,
    colors: false,
    spacing: false,
    size: false,
    borders: false,
    effects: false,
    position: false,
  });

  function toggle(key) {
    openSections = { ...openSections, [key]: !openSections[key] };
  }

  function getStyle(prop) {
    return styles[prop] || '';
  }

  function setStyle(prop, value) {
    editor.updateStyle(prop, value);
  }

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
  const textTransformOptions = ['none', 'uppercase', 'lowercase', 'capitalize'];
  const textDecorationOptions = ['none', 'underline', 'line-through', 'overline'];
  const borderStyles = ['solid', 'dashed', 'dotted', 'double', 'none'];
  const displayOptions = ['block', 'flex', 'grid', 'inline', 'inline-block', 'inline-flex', 'none'];
  const overflowOptions = ['visible', 'hidden', 'auto', 'scroll'];
  const positionOptions = ['static', 'relative', 'absolute', 'fixed', 'sticky'];
  const cursorOptions = ['auto', 'default', 'pointer', 'text', 'move', 'not-allowed', 'grab'];
</script>

<div class="flex flex-col gap-0 overflow-y-auto flex-1">
  <!-- TYPOGRAPHY -->
  <div class="tk-section">
    <button class="tk-section-header" onclick={() => toggle('typography')}>
      <span>{t('typography', 'Typography')}</span>
      <span class="tk-chevron {openSections.typography ? 'open' : ''}">&#9656;</span>
    </button>
    {#if openSections.typography}
      <div class="tk-section-body">
        <!-- Font Family -->
        <div>
          <label class="tk-inspector-label">{t('font_family', 'Font Family')}</label>
          <input
            type="text"
            value={getStyle('fontFamily')}
            oninput={(e) => setStyle('fontFamily', e.target.value)}
            placeholder="inherit"
            class="tk-inspector-input font-mono"
          />
        </div>

        <!-- Size + Weight -->
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="tk-inspector-label">{t('font_size', 'Size')}</label>
            <input
              type="text"
              value={getStyle('fontSize')}
              oninput={(e) => setStyle('fontSize', e.target.value)}
              placeholder="16px"
              class="tk-inspector-input font-mono"
            />
          </div>
          <div class="flex-1">
            <label class="tk-inspector-label">{t('font_weight', 'Weight')}</label>
            <select
              class="tk-inspector-input"
              value={getStyle('fontWeight') || ''}
              onchange={(e) => setStyle('fontWeight', e.target.value)}
            >
              <option value="">—</option>
              {#each fontWeights as fw}
                <option value={fw.value}>{fw.label}</option>
              {/each}
            </select>
          </div>
        </div>

        <!-- Line Height + Letter Spacing -->
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="tk-inspector-label">{t('line_height', 'Line Height')}</label>
            <input
              type="text"
              value={getStyle('lineHeight')}
              oninput={(e) => setStyle('lineHeight', e.target.value)}
              placeholder="1.5"
              class="tk-inspector-input font-mono"
            />
          </div>
          <div class="flex-1">
            <label class="tk-inspector-label">{t('letter_spacing', 'Spacing')}</label>
            <input
              type="text"
              value={getStyle('letterSpacing')}
              oninput={(e) => setStyle('letterSpacing', e.target.value)}
              placeholder="0px"
              class="tk-inspector-input font-mono"
            />
          </div>
        </div>

        <!-- Text Align -->
        <div>
          <label class="tk-inspector-label">{t('text_align', 'Align')}</label>
          <div class="flex gap-px bg-card-hover rounded-[5px] p-[2px]">
            {#each textAlignOptions as align}
              <button
                class="flex-1 px-1.5 py-[3px] border-none rounded-[3px] cursor-pointer text-[11px] font-medium font-body transition-colors capitalize {getStyle('textAlign') === align ? 'bg-border/60 text-foreground' : 'bg-transparent text-dim'}"
                onclick={() => setStyle('textAlign', align)}
              >{align.charAt(0).toUpperCase()}</button>
            {/each}
          </div>
        </div>

        <!-- Transform + Decoration -->
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="tk-inspector-label">{t('transform', 'Transform')}</label>
            <select
              class="tk-inspector-input"
              value={getStyle('textTransform') || ''}
              onchange={(e) => setStyle('textTransform', e.target.value)}
            >
              <option value="">—</option>
              {#each textTransformOptions as opt}
                <option value={opt}>{opt}</option>
              {/each}
            </select>
          </div>
          <div class="flex-1">
            <label class="tk-inspector-label">{t('decoration', 'Decoration')}</label>
            <select
              class="tk-inspector-input"
              value={getStyle('textDecoration') || ''}
              onchange={(e) => setStyle('textDecoration', e.target.value)}
            >
              <option value="">—</option>
              {#each textDecorationOptions as opt}
                <option value={opt}>{opt}</option>
              {/each}
            </select>
          </div>
        </div>
      </div>
    {/if}
  </div>

  <!-- COLORS -->
  <div class="tk-section">
    <button class="tk-section-header" onclick={() => toggle('colors')}>
      <span>{t('colors', 'Colors')}</span>
      <span class="tk-chevron {openSections.colors ? 'open' : ''}">&#9656;</span>
    </button>
    {#if openSections.colors}
      <div class="tk-section-body">
        <!-- Text Color -->
        <div>
          <label class="tk-inspector-label">{t('text_color', 'Text Color')}</label>
          <div class="flex items-center gap-2">
            <input
              type="color"
              class="tk-color-swatch"
              value={getStyle('color') || '#000000'}
              oninput={(e) => setStyle('color', e.target.value)}
            />
            <input
              type="text"
              value={getStyle('color')}
              oninput={(e) => setStyle('color', e.target.value)}
              placeholder="#000000"
              class="tk-inspector-input flex-1 font-mono"
            />
          </div>
        </div>

        <!-- Background Color -->
        <div>
          <label class="tk-inspector-label">{t('background', 'Background')}</label>
          <div class="flex items-center gap-2">
            <input
              type="color"
              class="tk-color-swatch"
              value={getStyle('backgroundColor') || '#ffffff'}
              oninput={(e) => setStyle('backgroundColor', e.target.value)}
            />
            <input
              type="text"
              value={getStyle('backgroundColor')}
              oninput={(e) => setStyle('backgroundColor', e.target.value)}
              placeholder="transparent"
              class="tk-inspector-input flex-1 font-mono"
            />
          </div>
        </div>

        <!-- Background Image -->
        <div>
          <label class="tk-inspector-label">{t('bg_image', 'Background Image')}</label>
          <input
            type="text"
            value={getStyle('backgroundImage')}
            oninput={(e) => setStyle('backgroundImage', e.target.value)}
            placeholder="url(...) or gradient"
            class="tk-inspector-input font-mono"
          />
        </div>

        <!-- Background Size + Position -->
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="tk-inspector-label">{t('bg_size', 'BG Size')}</label>
            <select
              class="tk-inspector-input"
              value={getStyle('backgroundSize') || ''}
              onchange={(e) => setStyle('backgroundSize', e.target.value)}
            >
              <option value="">—</option>
              <option value="cover">cover</option>
              <option value="contain">contain</option>
              <option value="auto">auto</option>
            </select>
          </div>
          <div class="flex-1">
            <label class="tk-inspector-label">{t('bg_position', 'BG Position')}</label>
            <input
              type="text"
              value={getStyle('backgroundPosition')}
              oninput={(e) => setStyle('backgroundPosition', e.target.value)}
              placeholder="center"
              class="tk-inspector-input font-mono"
            />
          </div>
        </div>
      </div>
    {/if}
  </div>

  <!-- SPACING -->
  <div class="tk-section">
    <button class="tk-section-header" onclick={() => toggle('spacing')}>
      <span>{t('spacing', 'Spacing')}</span>
      <span class="tk-chevron {openSections.spacing ? 'open' : ''}">&#9656;</span>
    </button>
    {#if openSections.spacing}
      <div class="tk-section-body">
        <SpacingBoxModel
          marginTop={getStyle('marginTop')}
          marginRight={getStyle('marginRight')}
          marginBottom={getStyle('marginBottom')}
          marginLeft={getStyle('marginLeft')}
          paddingTop={getStyle('paddingTop')}
          paddingRight={getStyle('paddingRight')}
          paddingBottom={getStyle('paddingBottom')}
          paddingLeft={getStyle('paddingLeft')}
          onStyleChange={setStyle}
        />

        <!-- Gap -->
        <div>
          <label class="tk-inspector-label">{t('gap', 'Gap')}</label>
          <input
            type="text"
            value={getStyle('gap')}
            oninput={(e) => setStyle('gap', e.target.value)}
            placeholder="0px"
            class="tk-inspector-input font-mono"
          />
        </div>
      </div>
    {/if}
  </div>

  <!-- SIZE & LAYOUT -->
  <div class="tk-section">
    <button class="tk-section-header" onclick={() => toggle('size')}>
      <span>{t('size_layout', 'Size & Layout')}</span>
      <span class="tk-chevron {openSections.size ? 'open' : ''}">&#9656;</span>
    </button>
    {#if openSections.size}
      <div class="tk-section-body">
        <!-- Width / Height -->
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="tk-inspector-label">{t('width', 'Width')}</label>
            <input type="text" value={getStyle('width')} oninput={(e) => setStyle('width', e.target.value)} placeholder="auto" class="tk-inspector-input font-mono" />
          </div>
          <div class="flex-1">
            <label class="tk-inspector-label">{t('height', 'Height')}</label>
            <input type="text" value={getStyle('height')} oninput={(e) => setStyle('height', e.target.value)} placeholder="auto" class="tk-inspector-input font-mono" />
          </div>
        </div>

        <!-- Min / Max Width -->
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="tk-inspector-label">{t('min_w', 'Min W')}</label>
            <input type="text" value={getStyle('minWidth')} oninput={(e) => setStyle('minWidth', e.target.value)} placeholder="0" class="tk-inspector-input font-mono" />
          </div>
          <div class="flex-1">
            <label class="tk-inspector-label">{t('max_w', 'Max W')}</label>
            <input type="text" value={getStyle('maxWidth')} oninput={(e) => setStyle('maxWidth', e.target.value)} placeholder="none" class="tk-inspector-input font-mono" />
          </div>
        </div>

        <!-- Min / Max Height -->
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="tk-inspector-label">{t('min_h', 'Min H')}</label>
            <input type="text" value={getStyle('minHeight')} oninput={(e) => setStyle('minHeight', e.target.value)} placeholder="0" class="tk-inspector-input font-mono" />
          </div>
          <div class="flex-1">
            <label class="tk-inspector-label">{t('max_h', 'Max H')}</label>
            <input type="text" value={getStyle('maxHeight')} oninput={(e) => setStyle('maxHeight', e.target.value)} placeholder="none" class="tk-inspector-input font-mono" />
          </div>
        </div>

        <!-- Display -->
        <div>
          <label class="tk-inspector-label">{t('display', 'Display')}</label>
          <select class="tk-inspector-input" value={getStyle('display') || ''} onchange={(e) => setStyle('display', e.target.value)}>
            <option value="">—</option>
            {#each displayOptions as opt}
              <option value={opt}>{opt}</option>
            {/each}
          </select>
        </div>

        <!-- Flex Direction (conditional) -->
        {#if ['flex', 'inline-flex'].includes(getStyle('display'))}
          <div class="flex gap-2">
            <div class="flex-1">
              <label class="tk-inspector-label">{t('direction', 'Direction')}</label>
              <select class="tk-inspector-input" value={getStyle('flexDirection') || ''} onchange={(e) => setStyle('flexDirection', e.target.value)}>
                <option value="">—</option>
                <option value="row">Row</option>
                <option value="column">Column</option>
                <option value="row-reverse">Row Rev</option>
                <option value="column-reverse">Col Rev</option>
              </select>
            </div>
            <div class="flex-1">
              <label class="tk-inspector-label">{t('wrap', 'Wrap')}</label>
              <select class="tk-inspector-input" value={getStyle('flexWrap') || ''} onchange={(e) => setStyle('flexWrap', e.target.value)}>
                <option value="">—</option>
                <option value="nowrap">No Wrap</option>
                <option value="wrap">Wrap</option>
              </select>
            </div>
          </div>

          <div class="flex gap-2">
            <div class="flex-1">
              <label class="tk-inspector-label">{t('justify', 'Justify')}</label>
              <select class="tk-inspector-input" value={getStyle('justifyContent') || ''} onchange={(e) => setStyle('justifyContent', e.target.value)}>
                <option value="">—</option>
                <option value="flex-start">Start</option>
                <option value="center">Center</option>
                <option value="flex-end">End</option>
                <option value="space-between">Between</option>
                <option value="space-around">Around</option>
                <option value="space-evenly">Evenly</option>
              </select>
            </div>
            <div class="flex-1">
              <label class="tk-inspector-label">{t('align_items', 'Align')}</label>
              <select class="tk-inspector-input" value={getStyle('alignItems') || ''} onchange={(e) => setStyle('alignItems', e.target.value)}>
                <option value="">—</option>
                <option value="flex-start">Start</option>
                <option value="center">Center</option>
                <option value="flex-end">End</option>
                <option value="stretch">Stretch</option>
                <option value="baseline">Baseline</option>
              </select>
            </div>
          </div>
        {/if}

        <!-- Grid columns (conditional) -->
        {#if getStyle('display') === 'grid'}
          <div>
            <label class="tk-inspector-label">{t('grid_cols', 'Grid Columns')}</label>
            <input type="text" value={getStyle('gridTemplateColumns')} oninput={(e) => setStyle('gridTemplateColumns', e.target.value)} placeholder="1fr 1fr" class="tk-inspector-input font-mono" />
          </div>
          <div>
            <label class="tk-inspector-label">{t('grid_rows', 'Grid Rows')}</label>
            <input type="text" value={getStyle('gridTemplateRows')} oninput={(e) => setStyle('gridTemplateRows', e.target.value)} placeholder="auto" class="tk-inspector-input font-mono" />
          </div>
        {/if}

        <!-- Overflow -->
        <div>
          <label class="tk-inspector-label">{t('overflow', 'Overflow')}</label>
          <select class="tk-inspector-input" value={getStyle('overflow') || ''} onchange={(e) => setStyle('overflow', e.target.value)}>
            <option value="">—</option>
            {#each overflowOptions as opt}
              <option value={opt}>{opt}</option>
            {/each}
          </select>
        </div>
      </div>
    {/if}
  </div>

  <!-- BORDERS -->
  <div class="tk-section">
    <button class="tk-section-header" onclick={() => toggle('borders')}>
      <span>{t('borders', 'Borders')}</span>
      <span class="tk-chevron {openSections.borders ? 'open' : ''}">&#9656;</span>
    </button>
    {#if openSections.borders}
      <div class="tk-section-body">
        <!-- Border Width + Style -->
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="tk-inspector-label">{t('border_width', 'Width')}</label>
            <input type="text" value={getStyle('borderWidth')} oninput={(e) => setStyle('borderWidth', e.target.value)} placeholder="0px" class="tk-inspector-input font-mono" />
          </div>
          <div class="flex-1">
            <label class="tk-inspector-label">{t('border_style', 'Style')}</label>
            <select class="tk-inspector-input" value={getStyle('borderStyle') || ''} onchange={(e) => setStyle('borderStyle', e.target.value)}>
              <option value="">—</option>
              {#each borderStyles as opt}
                <option value={opt}>{opt}</option>
              {/each}
            </select>
          </div>
        </div>

        <!-- Border Color -->
        <div>
          <label class="tk-inspector-label">{t('border_color', 'Color')}</label>
          <div class="flex items-center gap-2">
            <input type="color" class="tk-color-swatch" value={getStyle('borderColor') || '#000000'} oninput={(e) => setStyle('borderColor', e.target.value)} />
            <input type="text" value={getStyle('borderColor')} oninput={(e) => setStyle('borderColor', e.target.value)} placeholder="#000" class="tk-inspector-input flex-1 font-mono" />
          </div>
        </div>

        <!-- Border Radius -->
        <div>
          <label class="tk-inspector-label">{t('border_radius', 'Radius')}</label>
          <input type="text" value={getStyle('borderRadius')} oninput={(e) => setStyle('borderRadius', e.target.value)} placeholder="0px" class="tk-inspector-input font-mono" />
        </div>
      </div>
    {/if}
  </div>

  <!-- EFFECTS -->
  <div class="tk-section">
    <button class="tk-section-header" onclick={() => toggle('effects')}>
      <span>{t('effects', 'Effects')}</span>
      <span class="tk-chevron {openSections.effects ? 'open' : ''}">&#9656;</span>
    </button>
    {#if openSections.effects}
      <div class="tk-section-body">
        <!-- Opacity -->
        <div>
          <label class="tk-inspector-label">{t('opacity', 'Opacity')}</label>
          <div class="flex items-center gap-2">
            <input
              type="range"
              min="0" max="1" step="0.05"
              value={getStyle('opacity') || '1'}
              oninput={(e) => setStyle('opacity', e.target.value)}
              class="flex-1 accent-copper"
            />
            <span class="text-[11px] text-dim font-mono w-8 text-right">{getStyle('opacity') || '1'}</span>
          </div>
        </div>

        <!-- Box Shadow -->
        <div>
          <label class="tk-inspector-label">{t('box_shadow', 'Box Shadow')}</label>
          <input type="text" value={getStyle('boxShadow')} oninput={(e) => setStyle('boxShadow', e.target.value)} placeholder="0 2px 8px rgba(0,0,0,0.1)" class="tk-inspector-input font-mono" />
        </div>

        <!-- Transition -->
        <div>
          <label class="tk-inspector-label">{t('transition', 'Transition')}</label>
          <input type="text" value={getStyle('transition')} oninput={(e) => setStyle('transition', e.target.value)} placeholder="all 0.3s ease" class="tk-inspector-input font-mono" />
        </div>

        <!-- Cursor -->
        <div>
          <label class="tk-inspector-label">{t('cursor', 'Cursor')}</label>
          <select class="tk-inspector-input" value={getStyle('cursor') || ''} onchange={(e) => setStyle('cursor', e.target.value)}>
            <option value="">—</option>
            {#each cursorOptions as opt}
              <option value={opt}>{opt}</option>
            {/each}
          </select>
        </div>
      </div>
    {/if}
  </div>

  <!-- POSITION -->
  <div class="tk-section">
    <button class="tk-section-header" onclick={() => toggle('position')}>
      <span>{t('position', 'Position')}</span>
      <span class="tk-chevron {openSections.position ? 'open' : ''}">&#9656;</span>
    </button>
    {#if openSections.position}
      <div class="tk-section-body">
        <!-- Position Type -->
        <div>
          <label class="tk-inspector-label">{t('position_type', 'Type')}</label>
          <select class="tk-inspector-input" value={getStyle('position') || ''} onchange={(e) => setStyle('position', e.target.value)}>
            <option value="">—</option>
            {#each positionOptions as opt}
              <option value={opt}>{opt}</option>
            {/each}
          </select>
        </div>

        <!-- Z-Index -->
        <div>
          <label class="tk-inspector-label">{t('z_index', 'Z-Index')}</label>
          <input type="text" value={getStyle('zIndex')} oninput={(e) => setStyle('zIndex', e.target.value)} placeholder="auto" class="tk-inspector-input font-mono" />
        </div>

        <!-- Insets (only if positioned) -->
        {#if ['absolute', 'fixed', 'sticky', 'relative'].includes(getStyle('position'))}
          <div class="flex gap-2">
            <div class="flex-1">
              <label class="tk-inspector-label">{t('top', 'Top')}</label>
              <input type="text" value={getStyle('top')} oninput={(e) => setStyle('top', e.target.value)} placeholder="auto" class="tk-inspector-input font-mono" />
            </div>
            <div class="flex-1">
              <label class="tk-inspector-label">{t('right', 'Right')}</label>
              <input type="text" value={getStyle('right')} oninput={(e) => setStyle('right', e.target.value)} placeholder="auto" class="tk-inspector-input font-mono" />
            </div>
          </div>
          <div class="flex gap-2">
            <div class="flex-1">
              <label class="tk-inspector-label">{t('bottom', 'Bottom')}</label>
              <input type="text" value={getStyle('bottom')} oninput={(e) => setStyle('bottom', e.target.value)} placeholder="auto" class="tk-inspector-input font-mono" />
            </div>
            <div class="flex-1">
              <label class="tk-inspector-label">{t('left', 'Left')}</label>
              <input type="text" value={getStyle('left')} oninput={(e) => setStyle('left', e.target.value)} placeholder="auto" class="tk-inspector-input font-mono" />
            </div>
          </div>
        {/if}
      </div>
    {/if}
  </div>
</div>

<style>
  .tk-section {
    border-bottom: 1px solid var(--color-border-subtle, var(--color-border));
  }

  .tk-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 8px 12px;
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
  .tk-section-header:hover { background: var(--color-card-hover); }

  .tk-chevron {
    font-size: 10px;
    color: var(--color-dim);
    transition: transform 0.15s;
    display: inline-block;
  }
  .tk-chevron.open { transform: rotate(90deg); }

  .tk-section-body {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 2px 12px 12px;
  }

  .tk-inspector-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--color-muted);
    margin-bottom: 4px;
  }

  .tk-color-swatch {
    width: 26px;
    height: 26px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    padding: 1px;
    cursor: pointer;
    flex-shrink: 0;
    background: transparent;
  }
</style>
