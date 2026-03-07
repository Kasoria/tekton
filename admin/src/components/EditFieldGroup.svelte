<script>
  import { Button } from '$lib/components/ui/button/index.js';
  import { Card } from '$lib/components/ui/card/index.js';
  import { Switch } from '$lib/components/ui/switch/index.js';
  import { Select } from '$lib/components/ui/select/index.js';
  import { api } from '$lib/api.js';
  import { t } from '$lib/i18n.svelte.js';
  import { createThemeStore } from '$lib/stores/theme.svelte.js';

  const theme = createThemeStore();

  let { onBack, editId = null } = $props();

  let saving = $state(false);
  let loading = $state(!!editId);
  let saveMessage = $state('');
  let activeSection = $state('basic');

  // ─── Field group config ─────────────────────────────────────────
  let title = $state('');
  let slug = $state('');
  let description = $state('');
  let position = $state('normal');
  let priority = $state('high');
  let isActive = $state(true);
  let isNew = $derived(!editId);

  // ─── Post type options (loaded from WP) ────────────────────────
  let postTypeOptions = $state([]);

  $effect(() => {
    api.getWpPostTypes().then((types) => {
      postTypeOptions = types;
    }).catch(() => {
      postTypeOptions = [
        { value: 'post', label: 'Post' },
        { value: 'page', label: 'Page' },
      ];
    });
  });

  // ─── Location rules ────────────────────────────────────────────
  let locationRules = $state([[{ param: 'post_type', operator: '==', value: 'post' }]]);

  const locationParams = [
    { value: 'post_type', label: t('post_type', 'Post Type') },
  ];

  const operatorOptions = [
    { value: '==', label: t('op_equals', 'is equal to') },
    { value: '!=', label: t('op_not_equals', 'is not equal to') },
  ];

  function addRuleGroup() {
    locationRules = [...locationRules, [{ param: 'post_type', operator: '==', value: '' }]];
  }

  function removeRuleGroup(gi) {
    locationRules = locationRules.filter((_, i) => i !== gi);
  }

  function addRule(gi) {
    locationRules[gi] = [...locationRules[gi], { param: 'post_type', operator: '==', value: '' }];
  }

  function removeRule(gi, ri) {
    locationRules[gi] = locationRules[gi].filter((_, i) => i !== ri);
    if (locationRules[gi].length === 0) removeRuleGroup(gi);
  }

  // ─── Fields ─────────────────────────────────────────────────────
  let fields = $state([]);
  let expandedField = $state(null);

  const fieldTypes = [
    { value: 'text', label: 'Text' },
    { value: 'textarea', label: 'Textarea' },
    { value: 'wysiwyg', label: 'WYSIWYG Editor' },
    { value: 'number', label: 'Number' },
    { value: 'email', label: 'Email' },
    { value: 'url', label: 'URL' },
    { value: 'password', label: 'Password' },
    { value: 'select', label: 'Select' },
    { value: 'checkbox', label: 'Checkbox' },
    { value: 'radio', label: 'Radio' },
    { value: 'true_false', label: 'True / False' },
    { value: 'image', label: 'Image' },
    { value: 'gallery', label: 'Gallery' },
    { value: 'file', label: 'File' },
    { value: 'date', label: 'Date' },
    { value: 'datetime', label: 'Date & Time' },
    { value: 'time', label: 'Time' },
    { value: 'color', label: 'Color' },
    { value: 'range', label: 'Range' },
    { value: 'repeater', label: 'Repeater' },
    { value: 'group', label: 'Group' },
    { value: 'flexible_content', label: 'Flexible Content' },
    { value: 'relationship', label: 'Relationship' },
    { value: 'post_object', label: 'Post Object' },
    { value: 'taxonomy', label: 'Taxonomy' },
    { value: 'code', label: 'Code' },
  ];

  function addField() {
    const newField = {
      name: '',
      type: 'text',
      label: '',
      required: false,
      placeholder: '',
      default_value: '',
      instructions: '',
      // Type-specific
      maxlength: '',
      rows: '',
      min: '',
      max: '',
      step: '',
      choices: '',
      return_format: 'value',
      multiple: false,
      preview_size: 'medium',
      post_type_filter: '',
      min_rows: '',
      max_rows: '',
      sub_fields: '',
    };
    fields = [...fields, newField];
    expandedField = fields.length - 1;
  }

  function removeField(index) {
    fields = fields.filter((_, i) => i !== index);
    if (expandedField === index) expandedField = null;
    else if (expandedField > index) expandedField--;
  }

  function moveField(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= fields.length) return;
    const arr = [...fields];
    [arr[index], arr[target]] = [arr[target], arr[index]];
    fields = arr;
    expandedField = target;
  }

  function autoSlug(text) {
    return text.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
  }

  // Type-specific config helpers
  function hasChoices(type) {
    return ['select', 'checkbox', 'radio'].includes(type);
  }

  function hasMaxlength(type) {
    return ['text', 'textarea', 'email', 'url', 'password'].includes(type);
  }

  function hasRows(type) {
    return ['textarea', 'wysiwyg', 'code'].includes(type);
  }

  function hasMinMax(type) {
    return ['number', 'range'].includes(type);
  }

  function hasPreviewSize(type) {
    return ['image', 'gallery'].includes(type);
  }

  function hasReturnFormat(type) {
    return ['select', 'checkbox', 'radio', 'image', 'gallery', 'file', 'relationship', 'post_object', 'taxonomy'].includes(type);
  }

  function hasPostTypeFilter(type) {
    return ['relationship', 'post_object'].includes(type);
  }

  function hasRepeaterConfig(type) {
    return ['repeater', 'group', 'flexible_content'].includes(type);
  }

  const sections = [
    { key: 'basic', label: t('general', 'General') },
    { key: 'fields', label: t('fields', 'Fields') },
    { key: 'location', label: t('location_rules', 'Location Rules') },
    { key: 'presentation', label: t('presentation', 'Presentation') },
  ];

  // ─── Load existing field group ──────────────────────────────────
  $effect(() => {
    if (editId) {
      loading = true;
      api.getFieldGroup(editId).then((data) => {
        title = data.title || '';
        slug = data.slug || '';
        description = data.description || '';
        position = data.position || 'normal';
        priority = data.priority || 'high';
        isActive = data.active !== false;

        if (data.location_rules && data.location_rules.length > 0) {
          locationRules = data.location_rules.map(group =>
            Array.isArray(group) ? group.map(r => ({ ...r })) : [{ ...group }]
          );
        }

        fields = (data.fields || []).map(f => ({
          name: f.name || '',
          type: f.type || 'text',
          label: f.label || '',
          required: f.required === true,
          placeholder: f.placeholder || '',
          default_value: f.default_value || '',
          instructions: f.instructions || '',
          maxlength: f.maxlength != null ? String(f.maxlength) : '',
          rows: f.rows != null ? String(f.rows) : '',
          min: f.min != null ? String(f.min) : '',
          max: f.max != null ? String(f.max) : '',
          step: f.step != null ? String(f.step) : '',
          choices: Array.isArray(f.choices) ? f.choices.join('\n') : (f.choices || ''),
          return_format: f.return_format || 'value',
          multiple: f.multiple === true,
          preview_size: f.preview_size || 'medium',
          post_type_filter: Array.isArray(f.post_type_filter) ? f.post_type_filter.join(', ') : (f.post_type_filter || ''),
          min_rows: f.min_rows != null ? String(f.min_rows) : '',
          max_rows: f.max_rows != null ? String(f.max_rows) : '',
          sub_fields: Array.isArray(f.sub_fields) ? JSON.stringify(f.sub_fields, null, 2) : (f.sub_fields || ''),
        }));

        loading = false;
      }).catch(() => { loading = false; });
    }
  });

  function buildFields() {
    return fields.filter(f => f.name && f.label).map(f => {
      const field = {
        name: f.name,
        type: f.type,
        label: f.label,
        required: f.required,
      };

      if (f.placeholder) field.placeholder = f.placeholder;
      if (f.default_value) field.default_value = f.default_value;
      if (f.instructions) field.instructions = f.instructions;

      if (hasMaxlength(f.type) && f.maxlength) field.maxlength = parseInt(f.maxlength);
      if (hasRows(f.type) && f.rows) field.rows = parseInt(f.rows);
      if (hasMinMax(f.type)) {
        if (f.min) field.min = parseFloat(f.min);
        if (f.max) field.max = parseFloat(f.max);
        if (f.step) field.step = parseFloat(f.step);
      }

      if (hasChoices(f.type) && f.choices) {
        field.choices = f.choices.split('\n').map(c => c.trim()).filter(Boolean);
      }

      if (hasReturnFormat(f.type)) field.return_format = f.return_format;
      if (f.type === 'select' || f.type === 'checkbox') field.multiple = f.multiple;
      if (hasPreviewSize(f.type)) field.preview_size = f.preview_size;

      if (hasPostTypeFilter(f.type) && f.post_type_filter) {
        field.post_type_filter = f.post_type_filter.split(',').map(s => s.trim()).filter(Boolean);
      }

      if (hasRepeaterConfig(f.type)) {
        if (f.min_rows) field.min_rows = parseInt(f.min_rows);
        if (f.max_rows) field.max_rows = parseInt(f.max_rows);
        if (f.sub_fields) {
          try { field.sub_fields = JSON.parse(f.sub_fields); } catch {}
        }
      }

      return field;
    });
  }

  async function save() {
    if (!slug || !title) return;
    saving = true;
    saveMessage = '';
    try {
      const builtFields = buildFields();
      if (builtFields.length === 0) {
        saveMessage = t('error_no_fields', 'Error: At least one valid field is required.');
        saving = false;
        return;
      }

      await api.saveFieldGroup({
        title,
        slug,
        description,
        fields: builtFields,
        location_rules: locationRules.filter(g => g.length > 0 && g.some(r => r.value)),
        position,
        priority,
        active: isActive,
        source: 'manual',
      });
      saveMessage = t('saved_successfully', 'Saved successfully.');
      setTimeout(() => (saveMessage = ''), 3000);
    } catch (e) {
      saveMessage = t('error_prefix', 'Error:') + ' ' + e.message;
    } finally {
      saving = false;
    }
  }
</script>

<div class="tk-editor">
  <!-- Header -->
  <header class="border-b border-border bg-card sticky top-0 z-10">
    <div class="max-w-[1120px] mx-auto h-[60px] flex items-center justify-between px-10">
      <div class="flex items-center gap-3">
        <button class="bg-transparent border-none text-muted hover:text-foreground cursor-pointer text-sm font-body" onclick={onBack}>&larr; {t('back', 'Back')}</button>
        <span class="text-dim">/</span>
        <span class="font-heading text-base font-bold">{isNew ? t('new_field_group', 'New Field Group') : `${t('edit', 'Edit')}: ${title || slug}`}</span>
      </div>
      <div class="flex items-center gap-3">
        {#if saveMessage}
          <span class="text-[12px] {saveMessage.startsWith('Error') ? 'text-gold' : 'text-green'}">{saveMessage}</span>
        {/if}
        <button
          class="w-8 h-8 flex items-center justify-center rounded-lg bg-transparent border border-border text-muted-foreground hover:text-foreground hover:border-dim cursor-pointer transition-colors"
          onclick={() => theme.toggle()}
        >
          {#if theme.resolved === 'light'}
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="3" stroke="currentColor" stroke-width="1.3"/><path d="M8 2v1.5M8 12.5V14M2 8h1.5M12.5 8H14M3.75 3.75l1.06 1.06M11.19 11.19l1.06 1.06M12.25 3.75l-1.06 1.06M4.81 11.19l-1.06 1.06" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
          {:else}
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.5 9.5a5.5 5.5 0 01-7-7 5.5 5.5 0 107 7z" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
          {/if}
        </button>
        <Button onclick={save} disabled={saving || !slug || !title}>
          {saving ? t('saving', 'Saving...') : t('save', 'Save')}
        </Button>
      </div>
    </div>
  </header>

  {#if loading}
    <div class="flex items-center justify-center py-20 text-muted text-sm">{t('loading', 'Loading...')}</div>
  {:else}
    <div class="max-w-[1120px] mx-auto px-10 py-8">
      <div class="grid grid-cols-[200px_1fr] gap-8">
        <!-- Section nav -->
        <nav class="flex flex-col gap-0.5 sticky top-[76px] self-start">
          {#each sections as sec}
            <button
              class="text-left text-[13px] px-3 py-2 rounded-md bg-transparent border-none cursor-pointer font-body transition-colors
                {activeSection === sec.key ? 'bg-copper/10 text-copper font-semibold' : 'text-muted-foreground hover:text-foreground hover:bg-card-hover'}"
              onclick={() => (activeSection = sec.key)}
            >
              {sec.label}
            </button>
          {/each}
        </nav>

        <!-- Form content -->
        <div class="flex flex-col gap-6">

          <!-- GENERAL -->
          {#if activeSection === 'basic'}
            <Card class="p-6">
              <h3 class="font-heading text-base font-bold mb-6">{t('general_settings', 'General Settings')}</h3>
              <div class="grid grid-cols-2 gap-x-6 gap-y-5">
                <div>
                  <label class="tk-label">{t('title', 'Title')}</label>
                  <input type="text" class="tk-field" placeholder="e.g. Team Member Details" bind:value={title}
                    oninput={() => { if (isNew && (!slug || slug === autoSlug(title.slice(0, -1)))) slug = autoSlug(title); }} />
                </div>
                <div>
                  <label class="tk-label">{t('slug', 'Slug')}</label>
                  <input type="text" class="tk-field font-mono" placeholder="team_member_details" bind:value={slug} disabled={!isNew}
                    oninput={() => { slug = slug.toLowerCase().replace(/[^a-z0-9_]/g, ''); }} />
                  <p class="tk-hint">{!isNew ? t('slug_locked', 'Cannot be changed after creation.') : t('slug_hint', 'Auto-generated from title. Lowercase, underscores only.')}</p>
                </div>
                <div class="col-span-2">
                  <label class="tk-label">{t('description', 'Description')}</label>
                  <input type="text" class="tk-field" placeholder={t('fg_description_placeholder', 'Optional description for this field group')} bind:value={description} />
                </div>
                <div class="col-span-2 flex items-center justify-between py-2">
                  <div>
                    <div class="text-[13px] font-medium">{t('active', 'Active')}</div>
                    <div class="text-[12px] text-muted mt-0.5">{t('active_desc', 'Whether this field group is active and its meta boxes are shown.')}</div>
                  </div>
                  <Switch checked={isActive} onchange={() => (isActive = !isActive)} />
                </div>
              </div>
            </Card>

          <!-- FIELDS -->
          {:else if activeSection === 'fields'}
            <Card class="p-6">
              <div class="flex items-center justify-between mb-5">
                <div>
                  <h3 class="font-heading text-base font-bold">{t('fields', 'Fields')}</h3>
                  <p class="text-[12px] text-muted mt-1">{fields.length} {fields.length !== 1 ? t('fields', 'fields') : t('field', 'field')} {t('configured', 'configured')}</p>
                </div>
                <Button onclick={addField}>+ {t('add_field', 'Add Field')}</Button>
              </div>

              {#if fields.length === 0}
                <div class="text-sm text-muted text-center py-8 border border-dashed border-border rounded-lg">
                  {t('no_fields_yet', 'No fields yet. Click "Add Field" to start.')}
                </div>
              {:else}
                <div class="flex flex-col gap-2">
                  {#each fields as field, i}
                    <div class="rounded-lg border transition-colors {expandedField === i ? 'border-copper/40 bg-copper/[0.02]' : 'border-border hover:border-dim'}">
                      <!-- Field header -->
                      <!-- svelte-ignore a11y_no_static_element_interactions -->
                      <div class="flex items-center justify-between px-4 py-3 cursor-pointer" onclick={() => expandedField = expandedField === i ? null : i}>
                        <div class="flex items-center gap-3">
                          <span class="text-dim text-xs w-5 text-center">{i + 1}</span>
                          <div>
                            <div class="text-[13px] font-semibold">{field.label || t('untitled_field', 'Untitled field')}</div>
                            <div class="flex gap-2 items-center mt-0.5">
                              <span class="text-[11px] text-muted font-mono">{field.name || '...'}</span>
                              <span class="text-[11px] text-copper font-medium uppercase">{field.type}</span>
                              {#if field.required}<span class="text-[11px] text-gold">{t('required', 'Required')}</span>{/if}
                            </div>
                          </div>
                        </div>
                        <div class="flex items-center gap-1">
                          <button class="w-6 h-6 rounded bg-transparent border-none text-dim hover:text-foreground cursor-pointer text-xs" onclick={(e) => { e.stopPropagation(); moveField(i, -1); }} title={t('move_up', 'Move up')} disabled={i === 0}>&#9650;</button>
                          <button class="w-6 h-6 rounded bg-transparent border-none text-dim hover:text-foreground cursor-pointer text-xs" onclick={(e) => { e.stopPropagation(); moveField(i, 1); }} title={t('move_down', 'Move down')} disabled={i === fields.length - 1}>&#9660;</button>
                          <button class="w-6 h-6 rounded bg-transparent border-none text-dim hover:text-gold cursor-pointer text-xs" onclick={(e) => { e.stopPropagation(); removeField(i); }} title={t('remove', 'Remove')}>&#10005;</button>
                          <span class="text-dim text-xs ml-1">{expandedField === i ? '&#9662;' : '&#9656;'}</span>
                        </div>
                      </div>

                      <!-- Field config (expanded) -->
                      {#if expandedField === i}
                        <div class="border-t border-border-subtle px-4 py-4">
                          <div class="grid grid-cols-2 gap-x-6 gap-y-5">
                            <!-- Label -->
                            <div>
                              <label class="tk-label">{t('field_label', 'Field Label')}</label>
                              <input type="text" class="tk-field" placeholder="e.g. Full Name" bind:value={field.label}
                                oninput={() => { if (!field.name || field.name === autoSlug(field.label.slice(0, -1))) field.name = autoSlug(field.label); }} />
                            </div>

                            <!-- Name -->
                            <div>
                              <label class="tk-label">{t('field_name', 'Field Name')}</label>
                              <input type="text" class="tk-field font-mono" placeholder="full_name" bind:value={field.name}
                                oninput={() => { field.name = field.name.toLowerCase().replace(/[^a-z0-9_]/g, ''); }} />
                              <p class="tk-hint">{t('field_name_hint', 'Used as the meta key suffix. Lowercase, underscores only.')}</p>
                            </div>

                            <!-- Type -->
                            <div>
                              <label class="tk-label">{t('field_type', 'Field Type')}</label>
                              <Select
                                value={field.type}
                                options={fieldTypes}
                                searchable
                                fullWidth
                                onchange={(val) => { field.type = val; }}
                              />
                            </div>

                            <!-- Required -->
                            <div class="flex items-center gap-3 pt-5">
                              <Switch checked={field.required} onchange={() => (field.required = !field.required)} />
                              <span class="text-[13px]">{t('required', 'Required')}</span>
                            </div>

                            <!-- Instructions -->
                            <div class="col-span-2">
                              <label class="tk-label">{t('instructions', 'Instructions')}</label>
                              <input type="text" class="tk-field" placeholder={t('instructions_placeholder', 'Help text shown below the field')} bind:value={field.instructions} />
                            </div>

                            <!-- Default value -->
                            <div>
                              <label class="tk-label">{t('default_value', 'Default Value')}</label>
                              <input type="text" class="tk-field" placeholder="" bind:value={field.default_value} />
                            </div>

                            <!-- Placeholder -->
                            <div>
                              <label class="tk-label">{t('placeholder', 'Placeholder')}</label>
                              <input type="text" class="tk-field" placeholder="" bind:value={field.placeholder} />
                            </div>
                          </div>

                          <!-- Type-specific settings -->
                          <div class="mt-5 pt-4 border-t border-border-subtle">
                            <div class="text-[11px] font-semibold text-muted uppercase tracking-wider mb-3">{t('type_specific_settings', 'Type-Specific Settings')}</div>

                            <div class="grid grid-cols-2 gap-x-6 gap-y-5">
                              {#if hasMaxlength(field.type)}
                                <div>
                                  <label class="tk-label">{t('max_length', 'Max Length')}</label>
                                  <input type="number" class="tk-field !w-32" placeholder={t('no_limit', 'No limit')} bind:value={field.maxlength} />
                                </div>
                              {/if}

                              {#if hasRows(field.type)}
                                <div>
                                  <label class="tk-label">{t('rows', 'Rows')}</label>
                                  <input type="number" class="tk-field !w-32" placeholder="8" bind:value={field.rows} />
                                </div>
                              {/if}

                              {#if hasMinMax(field.type)}
                                <div>
                                  <label class="tk-label">{t('min_value', 'Min Value')}</label>
                                  <input type="number" class="tk-field !w-32" placeholder="" bind:value={field.min} />
                                </div>
                                <div>
                                  <label class="tk-label">{t('max_value', 'Max Value')}</label>
                                  <input type="number" class="tk-field !w-32" placeholder="" bind:value={field.max} />
                                </div>
                                <div>
                                  <label class="tk-label">{t('step', 'Step')}</label>
                                  <input type="number" class="tk-field !w-32" placeholder="1" bind:value={field.step} />
                                </div>
                              {/if}

                              {#if hasChoices(field.type)}
                                <div class="col-span-2">
                                  <label class="tk-label">{t('choices', 'Choices')}</label>
                                  <textarea class="tk-field !h-24 font-mono text-[12px]" placeholder={t('choices_placeholder', 'One choice per line')} bind:value={field.choices}></textarea>
                                  <p class="tk-hint">{t('choices_hint', 'Enter one choice per line. Use "value : Label" format for different values and labels.')}</p>
                                </div>
                                {#if field.type === 'select' || field.type === 'checkbox'}
                                  <div class="flex items-center gap-3">
                                    <Switch checked={field.multiple} onchange={() => (field.multiple = !field.multiple)} />
                                    <span class="text-[13px]">{t('allow_multiple', 'Allow Multiple Selections')}</span>
                                  </div>
                                {/if}
                              {/if}

                              {#if hasReturnFormat(field.type)}
                                <div>
                                  <label class="tk-label">{t('return_format', 'Return Format')}</label>
                                  <select class="tk-field !w-48" bind:value={field.return_format}>
                                    <option value="value">{t('return_value', 'Value')}</option>
                                    <option value="label">{t('return_label', 'Label')}</option>
                                    <option value="array">{t('return_array', 'Array (value + label)')}</option>
                                    {#if field.type === 'image'}
                                      <option value="id">{t('image_id', 'Image ID')}</option>
                                      <option value="url">{t('image_url', 'Image URL')}</option>
                                    {/if}
                                    {#if field.type === 'relationship'}
                                      <option value="id">{t('post_id', 'Post ID')}</option>
                                      <option value="object">{t('post_object', 'Post Object')}</option>
                                    {/if}
                                  </select>
                                </div>
                              {/if}

                              {#if hasPreviewSize(field.type)}
                                <div>
                                  <label class="tk-label">{t('preview_size', 'Preview Size')}</label>
                                  <select class="tk-field !w-48" bind:value={field.preview_size}>
                                    <option value="thumbnail">{t('size_thumbnail', 'Thumbnail')}</option>
                                    <option value="medium">{t('size_medium', 'Medium')}</option>
                                    <option value="large">{t('size_large', 'Large')}</option>
                                    <option value="full">{t('size_full', 'Full')}</option>
                                  </select>
                                </div>
                              {/if}

                              {#if hasPostTypeFilter(field.type)}
                                <div>
                                  <label class="tk-label">{t('filter_by_post_type', 'Filter by Post Type')}</label>
                                  <input type="text" class="tk-field font-mono" placeholder="post, page" bind:value={field.post_type_filter} />
                                  <p class="tk-hint">{t('post_type_filter_hint', 'Comma-separated post type slugs. Leave empty for all.')}</p>
                                </div>
                              {/if}

                              {#if hasRepeaterConfig(field.type)}
                                <div>
                                  <label class="tk-label">{t('min_rows', 'Min Rows')}</label>
                                  <input type="number" class="tk-field !w-32" placeholder="0" bind:value={field.min_rows} />
                                </div>
                                <div>
                                  <label class="tk-label">{t('max_rows', 'Max Rows')}</label>
                                  <input type="number" class="tk-field !w-32" placeholder={t('no_limit', 'No limit')} bind:value={field.max_rows} />
                                </div>
                                <div class="col-span-2">
                                  <label class="tk-label">{t('sub_fields_json', 'Sub Fields (JSON)')}</label>
                                  <textarea class="tk-field !h-32 font-mono text-[12px]" placeholder={'[{"name":"title","type":"text","label":"Title"}]'} bind:value={field.sub_fields}></textarea>
                                  <p class="tk-hint">{t('sub_fields_hint', 'JSON array of sub-field definitions. Each must have name, type, and label.')}</p>
                                </div>
                              {/if}
                            </div>
                          </div>
                        </div>
                      {/if}
                    </div>
                  {/each}
                </div>

                <div class="mt-4">
                  <Button variant="ghost" onclick={addField}>+ {t('add_field', 'Add Field')}</Button>
                </div>
              {/if}
            </Card>

          <!-- LOCATION RULES -->
          {:else if activeSection === 'location'}
            <Card class="p-6">
              <h3 class="font-heading text-base font-bold mb-1">{t('location_rules', 'Location Rules')}</h3>
              <p class="text-[12px] text-muted mb-5">{t('location_rules_desc', 'Define where this field group appears. Rule groups use OR logic between them, AND logic within each group.')}</p>

              <div class="flex flex-col gap-4">
                {#each locationRules as ruleGroup, gi}
                  {#if gi > 0}
                    <div class="text-center text-[12px] font-semibold text-copper uppercase tracking-wider">or</div>
                  {/if}
                  <div class="p-4 rounded-lg border border-border bg-background">
                    <div class="flex flex-col gap-2">
                      {#each ruleGroup as rule, ri}
                        {#if ri > 0}
                          <div class="text-[11px] font-semibold text-muted uppercase tracking-wider pl-1">and</div>
                        {/if}
                        <div class="flex items-center gap-2">
                          <div class="w-40 shrink-0">
                            <Select
                              value={rule.param}
                              options={locationParams}
                              fullWidth
                              onchange={(val) => { rule.param = val; }}
                            />
                          </div>
                          <div class="w-40 shrink-0">
                            <Select
                              value={rule.operator}
                              options={operatorOptions}
                              fullWidth
                              onchange={(val) => { rule.operator = val; }}
                            />
                          </div>
                          <div class="flex-1">
                            <Select
                              value={rule.value}
                              options={postTypeOptions}
                              searchable
                              fullWidth
                              placeholder={t('select_post_type', 'Select...')}
                              onchange={(val) => { rule.value = val; }}
                            />
                          </div>
                          <button class="w-6 h-6 rounded bg-transparent border-none text-dim hover:text-gold cursor-pointer text-xs flex items-center justify-center" onclick={() => removeRule(gi, ri)} title={t('remove_rule', 'Remove rule')}>&#10005;</button>
                        </div>
                      {/each}
                    </div>
                    <div class="flex items-center gap-2 mt-3">
                      <button class="text-[12px] text-copper bg-transparent border-none cursor-pointer font-body font-medium" onclick={() => addRule(gi)}>+ {t('add_rule', 'Add Rule')}</button>
                      {#if locationRules.length > 1}
                        <button class="text-[12px] text-dim hover:text-gold bg-transparent border-none cursor-pointer font-body" onclick={() => removeRuleGroup(gi)}>{t('remove_group', 'Remove Group')}</button>
                      {/if}
                    </div>
                  </div>
                {/each}
              </div>

              <div class="mt-4">
                <Button variant="ghost" onclick={addRuleGroup}>+ {t('add_rule_group', 'Add Rule Group')}</Button>
              </div>
            </Card>

          <!-- PRESENTATION -->
          {:else if activeSection === 'presentation'}
            <Card class="p-6">
              <h3 class="font-heading text-base font-bold mb-6">{t('presentation', 'Presentation')}</h3>
              <div class="grid grid-cols-2 gap-x-6 gap-y-5">
                <div>
                  <label class="tk-label">{t('position', 'Position')}</label>
                  <select class="tk-field" bind:value={position}>
                    <option value="normal">{t('position_normal', 'Normal (below content)')}</option>
                    <option value="side">{t('position_side', 'Side (sidebar)')}</option>
                    <option value="advanced">{t('position_advanced', 'Advanced (below normal)')}</option>
                  </select>
                  <p class="tk-hint">{t('position_hint', 'Where the meta box appears on the edit screen.')}</p>
                </div>
                <div>
                  <label class="tk-label">{t('priority', 'Priority')}</label>
                  <select class="tk-field" bind:value={priority}>
                    <option value="high">{t('priority_high', 'High')}</option>
                    <option value="default">{t('priority_default', 'Default')}</option>
                    <option value="low">{t('priority_low', 'Low')}</option>
                  </select>
                  <p class="tk-hint">{t('priority_hint', 'Order within the chosen position.')}</p>
                </div>
              </div>
            </Card>
          {/if}

          <!-- Bottom save bar -->
          <div class="flex justify-end gap-3 pt-2 pb-8">
            <Button variant="ghost" onclick={onBack}>{t('cancel', 'Cancel')}</Button>
            <Button onclick={save} disabled={saving || !slug || !title}>
              {saving ? t('saving', 'Saving...') : (isNew ? t('create', 'Create') : t('save', 'Save'))}
            </Button>
          </div>
        </div>
      </div>
    </div>
  {/if}
</div>

<style>
  .tk-editor {
    min-height: 100vh;
    background: var(--color-background);
    color: var(--color-foreground);
    font-family: var(--font-body);
  }

  .tk-editor :global(h1), .tk-editor :global(h2), .tk-editor :global(h3),
  .tk-editor :global(h4), .tk-editor :global(h5), .tk-editor :global(h6) {
    color: var(--color-foreground); font-size: inherit; font-weight: inherit;
    margin: 0; padding: 0; line-height: inherit;
  }
  .tk-editor :global(p) { color: inherit; font-size: inherit; margin: 0; line-height: inherit; }
  .tk-editor :global(button) { font-family: var(--font-body); line-height: inherit; }
  .tk-editor :global(input), .tk-editor :global(select), .tk-editor :global(textarea) {
    font-family: var(--font-body); color: var(--color-foreground);
  }
  .tk-editor :global(label) { color: inherit; }

  .tk-editor :global(.tk-label) {
    display: block; font-size: 12px; font-weight: 600; color: var(--color-muted-foreground);
    margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;
  }

  .tk-editor :global(.tk-field) {
    display: block !important; width: 100% !important; padding: 8px 12px !important;
    background: var(--color-background) !important; border: 1px solid var(--color-border) !important;
    border-radius: 8px !important; color: var(--color-foreground) !important;
    font-size: 13px !important; font-family: var(--font-body) !important;
    line-height: 1.5 !important; outline: none !important; box-shadow: none !important;
    height: auto !important; min-height: 0 !important; margin: 0 !important;
    transition: border-color 0.15s !important;
  }
  .tk-editor :global(.tk-field:focus) { border-color: var(--color-copper) !important; }
  .tk-editor :global(.tk-field:disabled) { opacity: 0.5; cursor: not-allowed; }
  .tk-editor :global(.tk-field::placeholder) { color: var(--color-dim) !important; }

  .tk-editor :global(.tk-hint) {
    font-size: 11px; color: var(--color-dim); margin-top: 4px; line-height: 1.4;
  }

  .tk-editor :global(textarea.tk-field) {
    resize: vertical !important;
  }

  .tk-editor :global(select.tk-field) {
    appearance: auto !important;
    cursor: pointer !important;
  }
</style>
