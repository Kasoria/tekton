<script>
  import { Button } from '$lib/components/ui/button/index.js';
  import { Card } from '$lib/components/ui/card/index.js';
  import { Switch } from '$lib/components/ui/switch/index.js';
  import { api } from '$lib/api.js';
  import { t } from '$lib/i18n.svelte.js';
  import { createThemeStore } from '$lib/stores/theme.svelte.js';

  const theme = createThemeStore();

  let { onBack, editId = null } = $props();

  let saving = $state(false);
  let loading = $state(!!editId);
  let saveMessage = $state('');
  let activeSection = $state('basic');

  // ─── Taxonomy editor ────────────────────────────────────────────
  let taxonomies = $state([]);

  function addTaxonomy() {
    taxonomies = [...taxonomies, {
      slug: '', label: '', singularLabel: '', hierarchical: true, public: true,
      showInMenu: true, showInRest: true, showTagcloud: true, showInQuickEdit: true,
      rewriteSlug: '',
    }];
  }

  function removeTaxonomy(i) {
    taxonomies = taxonomies.filter((_, idx) => idx !== i);
  }

  // ─── Post type config state ─────────────────────────────────────
  // Basic
  let slug = $state('');
  let label = $state('');
  let singularLabel = $state('');
  let description = $state('');
  let isNew = $derived(!editId);

  // Visibility
  let isPublic = $state(true);
  let publiclyQueryable = $state(true);
  let excludeFromSearch = $state(false);
  let showInNavMenus = $state(true);
  let showUi = $state(true);
  let showInMenu = $state(true);
  let showInMenuString = $state('');
  let showInAdminBar = $state(true);

  // URL / Archive
  let hasArchive = $state(true);
  let archiveSlug = $state('');
  let rewriteEnabled = $state(true);
  let rewriteSlug = $state('');
  let rewriteWithFront = $state(true);
  let rewriteFeeds = $state(false);
  let rewritePages = $state(true);
  let queryVar = $state(true);

  // Features
  let supports = $state(['title', 'thumbnail']);
  let hierarchical = $state(false);
  let canExport = $state(true);
  let deleteWithUser = $state(false);

  // REST API
  let showInRest = $state(true);
  let restBase = $state('');
  let restNamespace = $state('');
  let restControllerClass = $state('');

  // Menu
  let menuIcon = $state('dashicons-admin-post');
  let menuPosition = $state('');

  // Capabilities
  let capabilityType = $state('post');
  let mapMetaCap = $state(true);

  // Labels
  let labels = $state({
    addNew: '', addNewItem: '', editItem: '', newItem: '',
    viewItem: '', viewItems: '', searchItems: '', notFound: '',
    notFoundInTrash: '', parentItemColon: '', allItems: '',
    archives: '', attributes: '', insertIntoItem: '', uploadedToThisItem: '',
    filterItemsList: '', itemsListNavigation: '', itemsList: '',
    menuName: '',
  });

  const allSupports = [
    { key: 'title', label: 'Title' },
    { key: 'editor', label: 'Editor (content)' },
    { key: 'thumbnail', label: 'Featured Image' },
    { key: 'excerpt', label: 'Excerpt' },
    { key: 'trackbacks', label: 'Trackbacks' },
    { key: 'custom-fields', label: 'Custom Fields' },
    { key: 'comments', label: 'Comments' },
    { key: 'revisions', label: 'Revisions' },
    { key: 'page-attributes', label: 'Page Attributes' },
    { key: 'author', label: 'Author' },
    { key: 'post-formats', label: 'Post Formats' },
  ];

  const sections = [
    { key: 'basic', label: t('general', 'General') },
    { key: 'labels', label: t('labels', 'Labels') },
    { key: 'visibility', label: t('visibility', 'Visibility') },
    { key: 'urls', label: t('urls_archives', 'URLs & Archives') },
    { key: 'features', label: t('features', 'Features') },
    { key: 'rest', label: t('rest_api', 'REST API') },
    { key: 'admin', label: t('admin_menu', 'Admin Menu') },
    { key: 'capabilities', label: t('capabilities', 'Capabilities') },
    { key: 'taxonomies', label: t('taxonomies', 'Taxonomies') },
  ];

  const dashicons = [
    'dashicons-admin-post', 'dashicons-admin-page', 'dashicons-admin-media',
    'dashicons-admin-comments', 'dashicons-admin-users', 'dashicons-admin-tools',
    'dashicons-admin-settings', 'dashicons-admin-site', 'dashicons-admin-generic',
    'dashicons-admin-collapse', 'dashicons-admin-home', 'dashicons-admin-links',
    'dashicons-admin-appearance', 'dashicons-admin-plugins', 'dashicons-admin-network',
    'dashicons-format-standard', 'dashicons-format-image', 'dashicons-format-gallery',
    'dashicons-format-audio', 'dashicons-format-video', 'dashicons-format-chat',
    'dashicons-format-status', 'dashicons-format-aside', 'dashicons-format-quote',
    'dashicons-groups', 'dashicons-businessman', 'dashicons-id', 'dashicons-id-alt',
    'dashicons-products', 'dashicons-cart', 'dashicons-store', 'dashicons-portfolio',
    'dashicons-book', 'dashicons-book-alt', 'dashicons-calendar', 'dashicons-calendar-alt',
    'dashicons-location', 'dashicons-location-alt', 'dashicons-building',
    'dashicons-hammer', 'dashicons-heart', 'dashicons-star-filled', 'dashicons-flag',
    'dashicons-awards', 'dashicons-tickets-alt', 'dashicons-food', 'dashicons-shield',
    'dashicons-megaphone', 'dashicons-clipboard', 'dashicons-lightbulb',
  ];

  // ─── Load existing post type ───────────────────────────────────
  $effect(() => {
    if (editId) {
      loading = true;
      api.getPostType(editId).then((data) => {
        slug = data.slug || '';
        const c = data.config || {};
        label = c.label || '';
        singularLabel = c.labels?.singular_name || '';
        description = c.description || '';
        isPublic = c.public !== false;
        publiclyQueryable = c.publicly_queryable !== false;
        excludeFromSearch = c.exclude_from_search === true;
        showInNavMenus = c.show_in_nav_menus !== false;
        showUi = c.show_ui !== false;
        showInMenu = c.show_in_menu !== false;
        showInAdminBar = c.show_in_admin_bar !== false;
        hasArchive = c.has_archive !== false;
        archiveSlug = typeof c.has_archive === 'string' ? c.has_archive : '';
        if (c.rewrite === false) { rewriteEnabled = false; }
        else if (typeof c.rewrite === 'object') {
          rewriteSlug = c.rewrite.slug || '';
          rewriteWithFront = c.rewrite.with_front !== false;
          rewriteFeeds = c.rewrite.feeds === true;
          rewritePages = c.rewrite.pages !== false;
        }
        queryVar = c.query_var !== false;
        supports = c.supports || ['title', 'thumbnail'];
        hierarchical = c.hierarchical === true;
        canExport = c.can_export !== false;
        deleteWithUser = c.delete_with_user === true;
        showInRest = c.show_in_rest !== false;
        restBase = c.rest_base || '';
        restNamespace = c.rest_namespace || '';
        restControllerClass = c.rest_controller_class || '';
        menuIcon = c.menu_icon || 'dashicons-admin-post';
        menuPosition = c.menu_position != null ? String(c.menu_position) : '';
        capabilityType = c.capability_type || 'post';
        mapMetaCap = c.map_meta_cap !== false;

        // Labels
        if (c.labels) {
          for (const [k, v] of Object.entries(c.labels)) {
            const camel = k.replace(/_([a-z])/g, (_, c) => c.toUpperCase());
            if (camel in labels) labels[camel] = v || '';
          }
        }

        // Taxonomies
        taxonomies = (data.taxonomies || []).map(tx => ({
          slug: tx.slug || '',
          label: tx.config?.label || '',
          singularLabel: tx.config?.labels?.singular_name || '',
          hierarchical: tx.config?.hierarchical !== false,
          public: tx.config?.public !== false,
          showInMenu: tx.config?.show_in_menu !== false,
          showInRest: tx.config?.show_in_rest !== false,
          showTagcloud: tx.config?.show_tagcloud !== false,
          showInQuickEdit: tx.config?.show_in_quick_edit !== false,
          rewriteSlug: tx.config?.rewrite?.slug || '',
        }));

        loading = false;
      }).catch(() => { loading = false; });
    }
  });

  function toggleSupport(key) {
    if (supports.includes(key)) {
      supports = supports.filter(s => s !== key);
    } else {
      supports = [...supports, key];
    }
  }

  function generateDefaultLabels(plural, singular) {
    return {
      singular_name: singular,
      add_new: t('auto_label_add_new', 'Add New'),
      add_new_item: t('auto_label_add_new_item', 'Add New %s').replace('%s', singular),
      edit_item: t('auto_label_edit_item', 'Edit %s').replace('%s', singular),
      new_item: t('auto_label_new_item', 'New %s').replace('%s', singular),
      view_item: t('auto_label_view_item', 'View %s').replace('%s', singular),
      view_items: t('auto_label_view_items', 'View %s').replace('%s', plural),
      search_items: t('auto_label_search_items', 'Search %s').replace('%s', plural),
      not_found: t('auto_label_not_found', 'No %s found').replace('%s', plural.toLowerCase()),
      not_found_in_trash: t('auto_label_not_found_in_trash', 'No %s found in trash').replace('%s', plural.toLowerCase()),
      all_items: t('auto_label_all_items', 'All %s').replace('%s', plural),
      archives: t('auto_label_archives', '%s Archives').replace('%s', singular),
      menu_name: plural,
      parent_item_colon: t('auto_label_parent_item', 'Parent %s:').replace('%s', singular),
      attributes: t('auto_label_attributes', '%s Attributes').replace('%s', singular),
      insert_into_item: t('auto_label_insert_into_item', 'Insert into %s').replace('%s', singular.toLowerCase()),
      uploaded_to_this_item: t('auto_label_uploaded_to_item', 'Uploaded to this %s').replace('%s', singular.toLowerCase()),
      filter_items_list: t('auto_label_filter_items_list', 'Filter %s list').replace('%s', plural.toLowerCase()),
      items_list_navigation: t('auto_label_items_list_nav', '%s list navigation').replace('%s', plural),
      items_list: t('auto_label_items_list', '%s list').replace('%s', plural),
    };
  }

  function buildConfig() {
    const s = singularLabel || label;
    const defaultLabels = generateDefaultLabels(label, s);

    // Manual overrides: only use non-empty values
    const manualOverrides = Object.fromEntries(
      Object.entries(labels)
        .filter(([, v]) => v)
        .map(([k, v]) => [k.replace(/[A-Z]/g, m => '_' + m.toLowerCase()), v])
    );

    const config = {
      label,
      labels: {
        ...defaultLabels,
        ...manualOverrides,
      },
      description,
      public: isPublic,
      publicly_queryable: publiclyQueryable,
      exclude_from_search: excludeFromSearch,
      show_in_nav_menus: showInNavMenus,
      show_ui: showUi,
      show_in_menu: showInMenuString || showInMenu,
      show_in_admin_bar: showInAdminBar,
      has_archive: archiveSlug || hasArchive,
      query_var: queryVar,
      supports,
      hierarchical,
      can_export: canExport,
      delete_with_user: deleteWithUser,
      show_in_rest: showInRest,
      menu_icon: menuIcon,
      capability_type: capabilityType,
      map_meta_cap: mapMetaCap,
    };

    if (rewriteEnabled) {
      config.rewrite = { slug: rewriteSlug || slug, with_front: rewriteWithFront, feeds: rewriteFeeds, pages: rewritePages };
    } else {
      config.rewrite = false;
    }

    if (restBase) config.rest_base = restBase;
    if (restNamespace) config.rest_namespace = restNamespace;
    if (restControllerClass) config.rest_controller_class = restControllerClass;
    if (menuPosition) config.menu_position = parseInt(menuPosition);

    return config;
  }

  function buildTaxonomies() {
    return taxonomies.filter(tx => tx.slug && tx.label).map(tx => ({
      slug: tx.slug,
      config: {
        label: tx.label,
        labels: { singular_name: tx.singularLabel || tx.label },
        hierarchical: tx.hierarchical,
        public: tx.public,
        show_ui: true,
        show_in_menu: tx.showInMenu,
        show_in_rest: tx.showInRest,
        show_tagcloud: tx.showTagcloud,
        show_in_quick_edit: tx.showInQuickEdit,
        ...(tx.rewriteSlug ? { rewrite: { slug: tx.rewriteSlug } } : {}),
      },
    }));
  }

  async function save() {
    if (!slug || !label) return;
    saving = true;
    saveMessage = '';
    try {
      await api.savePostType({
        slug,
        config: buildConfig(),
        taxonomies: buildTaxonomies(),
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
        <span class="font-heading text-base font-bold">{isNew ? t('new_post_type', 'New Post Type') : `${t('edit', 'Edit')}: ${label || slug}`}</span>
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
        <Button onclick={save} disabled={saving || !slug || !label}>
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
                <div class="col-span-2 grid grid-cols-[1fr_1fr] gap-6">
                  <div>
                    <label class="tk-label">{t('post_type_key', 'Post Type Key (slug)')}</label>
                    <input type="text" class="tk-field font-mono" maxlength="20" placeholder="team_member" bind:value={slug} disabled={!isNew} oninput={() => { slug = slug.toLowerCase().replace(/[^a-z0-9_]/g, ''); }} />
                    <p class="tk-hint">{t('cpt_slug_hint', 'Max 20 characters. Lowercase, underscores only.')}{!isNew ? ' ' + t('slug_locked', 'Cannot be changed after creation.') : ''}</p>
                  </div>
                  <div>
                    <label class="tk-label">{t('description', 'Description')}</label>
                    <input type="text" class="tk-field" placeholder={t('cpt_desc_placeholder', 'A short description of the post type')} bind:value={description} />
                  </div>
                </div>
                <div>
                  <label class="tk-label">{t('plural_label', 'Plural Label')}</label>
                  <input type="text" class="tk-field" placeholder="Team Members" bind:value={label} />
                </div>
                <div>
                  <label class="tk-label">{t('singular_label', 'Singular Label')}</label>
                  <input type="text" class="tk-field" placeholder="Team Member" bind:value={singularLabel} />
                </div>
              </div>
            </Card>

          <!-- LABELS -->
          {:else if activeSection === 'labels'}
            <Card class="p-6">
              <h3 class="font-heading text-base font-bold mb-1">{t('labels', 'Labels')}</h3>
              <p class="text-[12px] text-muted mb-6">{t('labels_desc', 'Fine-tune how this post type appears throughout the admin. Leave blank for WordPress defaults.')}</p>
              <div class="grid grid-cols-2 gap-x-6 gap-y-5">
                {#each [
                  ['addNew', t('label_add_new', 'Add New'), t('label_add_new_ph', 'e.g. Add New')],
                  ['addNewItem', t('label_add_new_item', 'Add New Item'), t('label_add_new_item_ph', 'e.g. Add New Team Member')],
                  ['editItem', t('label_edit_item', 'Edit Item'), t('label_edit_item_ph', 'e.g. Edit Team Member')],
                  ['newItem', t('label_new_item', 'New Item'), t('label_new_item_ph', 'e.g. New Team Member')],
                  ['viewItem', t('label_view_item', 'View Item'), t('label_view_item_ph', 'e.g. View Team Member')],
                  ['viewItems', t('label_view_items', 'View Items'), t('label_view_items_ph', 'e.g. View Team Members')],
                  ['searchItems', t('label_search_items', 'Search Items'), t('label_search_items_ph', 'e.g. Search Team Members')],
                  ['notFound', t('label_not_found', 'Not Found'), t('label_not_found_ph', 'e.g. No team members found')],
                  ['notFoundInTrash', t('label_not_found_in_trash', 'Not Found in Trash'), t('label_not_found_in_trash_ph', 'e.g. No team members found in trash')],
                  ['allItems', t('label_all_items', 'All Items'), t('label_all_items_ph', 'e.g. All Team Members')],
                  ['archives', t('label_archives', 'Archives'), t('label_archives_ph', 'e.g. Team Member Archives')],
                  ['menuName', t('label_menu_name', 'Menu Name'), t('label_menu_name_ph', 'e.g. Team')],
                  ['parentItemColon', t('label_parent_item', 'Parent Item:'), t('label_parent_item_ph', 'For hierarchical types')],
                  ['attributes', t('label_attributes', 'Attributes'), t('label_attributes_ph', 'e.g. Team Member Attributes')],
                  ['insertIntoItem', t('label_insert_into_item', 'Insert into Item'), t('label_insert_into_item_ph', 'e.g. Insert into team member')],
                  ['uploadedToThisItem', t('label_uploaded_to_item', 'Uploaded to Item'), t('label_uploaded_to_item_ph', 'e.g. Uploaded to this team member')],
                  ['filterItemsList', t('label_filter_items_list', 'Filter Items List'), t('label_filter_items_list_ph', 'e.g. Filter team members list')],
                  ['itemsListNavigation', t('label_items_list_nav', 'Items List Navigation'), t('label_items_list_nav_ph', 'e.g. Team members list navigation')],
                  ['itemsList', t('label_items_list', 'Items List'), t('label_items_list_ph', 'e.g. Team members list')],
                ] as [key, lbl, ph]}
                  <div>
                    <label class="tk-label">{lbl}</label>
                    <input type="text" class="tk-field" placeholder={ph} bind:value={labels[key]} />
                  </div>
                {/each}
              </div>
            </Card>

          <!-- VISIBILITY -->
          {:else if activeSection === 'visibility'}
            <Card class="p-6">
              <h3 class="font-heading text-base font-bold mb-5">{t('visibility', 'Visibility')}</h3>
              <div class="flex flex-col gap-4">
                {#each [
                  ['isPublic', t('cpt_public', 'Public'), t('cpt_public_desc', 'Whether this post type is visible on the front end and in search results.')],
                  ['publiclyQueryable', t('cpt_publicly_queryable', 'Publicly Queryable'), t('cpt_publicly_queryable_desc', 'Whether queries for this post type can be performed from the front end.')],
                  ['excludeFromSearch', t('cpt_exclude_search', 'Exclude from Search'), t('cpt_exclude_search_desc', 'Whether to exclude posts from front-end search results.')],
                  ['showUi', t('cpt_show_ui', 'Show UI'), t('cpt_show_ui_desc', 'Whether to generate a default admin UI for this post type.')],
                  ['showInNavMenus', t('cpt_show_nav_menus', 'Show in Nav Menus'), t('cpt_show_nav_menus_desc', 'Whether this post type is available for selection in navigation menus.')],
                  ['showInAdminBar', t('cpt_show_admin_bar', 'Show in Admin Bar'), t('cpt_show_admin_bar_desc', 'Whether to show the post type in the admin bar "New" menu.')],
                ] as [key, lbl, desc]}
                  <div class="flex items-center justify-between py-2 border-b border-border-subtle last:border-0">
                    <div>
                      <div class="text-[13px] font-medium">{lbl}</div>
                      <div class="text-[12px] text-muted mt-0.5">{desc}</div>
                    </div>
                    {#if key === 'isPublic'}
                      <Switch checked={isPublic} onchange={() => (isPublic = !isPublic)} />
                    {:else if key === 'publiclyQueryable'}
                      <Switch checked={publiclyQueryable} onchange={() => (publiclyQueryable = !publiclyQueryable)} />
                    {:else if key === 'excludeFromSearch'}
                      <Switch checked={excludeFromSearch} onchange={() => (excludeFromSearch = !excludeFromSearch)} />
                    {:else if key === 'showUi'}
                      <Switch checked={showUi} onchange={() => (showUi = !showUi)} />
                    {:else if key === 'showInNavMenus'}
                      <Switch checked={showInNavMenus} onchange={() => (showInNavMenus = !showInNavMenus)} />
                    {:else if key === 'showInAdminBar'}
                      <Switch checked={showInAdminBar} onchange={() => (showInAdminBar = !showInAdminBar)} />
                    {/if}
                  </div>
                {/each}
                <div class="flex items-center justify-between py-2">
                  <div>
                    <div class="text-[13px] font-medium">{t('cpt_show_in_menu', 'Show in Menu')}</div>
                    <div class="text-[12px] text-muted mt-0.5">{t('cpt_show_in_menu_desc', 'Show as top-level menu, or enter a parent menu slug (e.g. "edit.php" or "tools.php").')}</div>
                  </div>
                  <div class="flex items-center gap-2">
                    <Switch checked={showInMenu} onchange={() => (showInMenu = !showInMenu)} />
                    {#if showInMenu}
                      <input type="text" class="tk-field !w-40 text-[12px] font-mono" placeholder="top-level" bind:value={showInMenuString} />
                    {/if}
                  </div>
                </div>
              </div>
            </Card>

          <!-- URLS & ARCHIVES -->
          {:else if activeSection === 'urls'}
            <Card class="p-6">
              <h3 class="font-heading text-base font-bold mb-5">{t('urls_archives', 'URLs & Archives')}</h3>
              <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between py-2 border-b border-border-subtle">
                  <div>
                    <div class="text-[13px] font-medium">{t('cpt_has_archive', 'Has Archive')}</div>
                    <div class="text-[12px] text-muted mt-0.5">{t('cpt_has_archive_desc', 'Enable an archive page for this post type.')}</div>
                  </div>
                  <Switch checked={hasArchive} onchange={() => (hasArchive = !hasArchive)} />
                </div>
                {#if hasArchive}
                  <div class="pl-4 border-l-2 border-copper/20">
                    <label class="tk-label">{t('custom_archive_slug', 'Custom Archive Slug')}</label>
                    <input type="text" class="tk-field !w-64 font-mono" placeholder={t('same_as_slug', 'Same as post type slug')} bind:value={archiveSlug} />
                    <p class="tk-hint">{t('archive_slug_hint', 'Leave blank to use the post type slug.')}</p>
                  </div>
                {/if}
                <div class="flex items-center justify-between py-2 border-b border-border-subtle">
                  <div>
                    <div class="text-[13px] font-medium">{t('cpt_url_rewrites', 'URL Rewrites')}</div>
                    <div class="text-[12px] text-muted mt-0.5">{t('cpt_url_rewrites_desc', 'Whether to enable pretty permalinks for this post type.')}</div>
                  </div>
                  <Switch checked={rewriteEnabled} onchange={() => (rewriteEnabled = !rewriteEnabled)} />
                </div>
                {#if rewriteEnabled}
                  <div class="pl-4 border-l-2 border-copper/20 flex flex-col gap-3">
                    <div>
                      <label class="tk-label">{t('custom_rewrite_slug', 'Custom Rewrite Slug')}</label>
                      <input type="text" class="tk-field !w-64 font-mono" placeholder={slug || 'post-type-slug'} bind:value={rewriteSlug} />
                    </div>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                      {#each [
                        ['rewriteWithFront', t('with_front', 'With Front'), rewriteWithFront],
                        ['rewriteFeeds', t('feeds', 'Feeds'), rewriteFeeds],
                        ['rewritePages', t('pagination', 'Pagination'), rewritePages],
                      ] as [key, lbl, val]}
                        <label class="flex items-center gap-1.5 text-[13px] cursor-pointer">
                          <input type="checkbox" checked={val} onchange={() => {
                            if (key === 'rewriteWithFront') rewriteWithFront = !rewriteWithFront;
                            else if (key === 'rewriteFeeds') rewriteFeeds = !rewriteFeeds;
                            else if (key === 'rewritePages') rewritePages = !rewritePages;
                          }} />
                          {lbl}
                        </label>
                      {/each}
                    </div>
                  </div>
                {/if}
                <div class="flex items-center justify-between py-2">
                  <div>
                    <div class="text-[13px] font-medium">{t('cpt_query_var', 'Query Var')}</div>
                    <div class="text-[12px] text-muted mt-0.5">{t('cpt_query_var_desc', 'Whether to allow ?post_type=slug queries.')}</div>
                  </div>
                  <Switch checked={queryVar} onchange={() => (queryVar = !queryVar)} />
                </div>
              </div>
            </Card>

          <!-- FEATURES -->
          {:else if activeSection === 'features'}
            <Card class="p-6">
              <h3 class="font-heading text-base font-bold mb-1">{t('supported_features', 'Supported Features')}</h3>
              <p class="text-[12px] text-muted mb-5">{t('supported_features_desc', 'Which core features this post type supports. Note: Tekton replaces the block editor, so "Editor" enables the classic editor fallback only.')}</p>
              <div class="grid grid-cols-3 gap-3 mb-6">
                {#each allSupports as s}
                  <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors
                    {supports.includes(s.key) ? 'border-copper/40 bg-copper/5' : 'border-border hover:border-dim'}">
                    <input type="checkbox" checked={supports.includes(s.key)} onchange={() => toggleSupport(s.key)}
                      class="accent-[var(--color-copper)]" />
                    <span class="text-[13px]">{s.label}</span>
                  </label>
                {/each}
              </div>
              <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between py-2 border-t border-border-subtle">
                  <div>
                    <div class="text-[13px] font-medium">{t('cpt_hierarchical', 'Hierarchical')}</div>
                    <div class="text-[12px] text-muted mt-0.5">{t('cpt_hierarchical_desc', 'Allow parent/child relationships (like Pages).')}</div>
                  </div>
                  <Switch checked={hierarchical} onchange={() => (hierarchical = !hierarchical)} />
                </div>
                <div class="flex items-center justify-between py-2 border-t border-border-subtle">
                  <div>
                    <div class="text-[13px] font-medium">{t('cpt_can_export', 'Can Export')}</div>
                    <div class="text-[12px] text-muted mt-0.5">{t('cpt_can_export_desc', 'Allow this post type to be exported via WordPress export tools.')}</div>
                  </div>
                  <Switch checked={canExport} onchange={() => (canExport = !canExport)} />
                </div>
                <div class="flex items-center justify-between py-2 border-t border-border-subtle">
                  <div>
                    <div class="text-[13px] font-medium">{t('cpt_delete_with_user', 'Delete with User')}</div>
                    <div class="text-[12px] text-muted mt-0.5">{t('cpt_delete_with_user_desc', 'Delete posts of this type when the author user is deleted.')}</div>
                  </div>
                  <Switch checked={deleteWithUser} onchange={() => (deleteWithUser = !deleteWithUser)} />
                </div>
              </div>
            </Card>

          <!-- REST API -->
          {:else if activeSection === 'rest'}
            <Card class="p-6">
              <h3 class="font-heading text-base font-bold mb-5">{t('rest_api', 'REST API')}</h3>
              <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between py-2 border-b border-border-subtle">
                  <div>
                    <div class="text-[13px] font-medium">{t('cpt_show_in_rest', 'Show in REST')}</div>
                    <div class="text-[12px] text-muted mt-0.5">{t('cpt_show_in_rest_desc', 'Expose this post type in the WordPress REST API. Required for Tekton.')}</div>
                  </div>
                  <Switch checked={showInRest} onchange={() => (showInRest = !showInRest)} />
                </div>
                <div>
                  <label class="tk-label">{t('rest_base', 'REST Base')}</label>
                  <input type="text" class="tk-field !w-64 font-mono" placeholder={slug || 'auto'} bind:value={restBase} />
                  <p class="tk-hint">{t('rest_base_hint', 'Custom base URL for REST routes. Defaults to the post type slug.')}</p>
                </div>
                <div>
                  <label class="tk-label">{t('rest_namespace', 'REST Namespace')}</label>
                  <input type="text" class="tk-field !w-64 font-mono" placeholder="wp/v2" bind:value={restNamespace} />
                  <p class="tk-hint">{t('rest_namespace_hint', 'Custom namespace. Defaults to wp/v2.')}</p>
                </div>
                <div>
                  <label class="tk-label">{t('rest_controller_class', 'REST Controller Class')}</label>
                  <input type="text" class="tk-field !w-80 font-mono" placeholder="WP_REST_Posts_Controller" bind:value={restControllerClass} />
                  <p class="tk-hint">{t('rest_controller_hint', 'Custom controller class. For advanced use only.')}</p>
                </div>
              </div>
            </Card>

          <!-- ADMIN MENU -->
          {:else if activeSection === 'admin'}
            <Card class="p-6">
              <h3 class="font-heading text-base font-bold mb-5">{t('admin_menu', 'Admin Menu')}</h3>
              <div class="flex flex-col gap-5">
                <div>
                  <label class="tk-label">{t('menu_position', 'Menu Position')}</label>
                  <input type="number" class="tk-field !w-32" placeholder={t('auto', 'Auto')} bind:value={menuPosition} />
                  <p class="tk-hint">{t('menu_position_hint', '5=below Posts, 10=below Media, 20=below Pages, 25=below Comments, 60=below first separator, 65=below Plugins, 70=below Users, 80=below Settings')}</p>
                </div>
                <div>
                  <label class="tk-label">{t('menu_icon', 'Menu Icon')}</label>
                  <div class="flex flex-wrap gap-2 mt-2 mb-2">
                    {#each dashicons as icon}
                      <button
                        class="w-9 h-9 rounded-md border flex items-center justify-center cursor-pointer transition-colors text-[18px]
                          {menuIcon === icon ? 'border-copper bg-copper/10 text-copper' : 'border-border bg-card text-muted hover:border-dim'}"
                        onclick={() => (menuIcon = icon)}
                        title={icon}
                      >
                        <span class="dashicons {icon}" style="font-size:18px;width:18px;height:18px;line-height:18px"></span>
                      </button>
                    {/each}
                  </div>
                  <div class="flex items-center gap-2 mt-2">
                    <label class="tk-label !mb-0">{t('or_enter_custom', 'Or enter custom:')}</label>
                    <input type="text" class="tk-field !w-64 font-mono text-[12px]" placeholder="dashicons-admin-post or SVG URL" bind:value={menuIcon} />
                  </div>
                </div>
              </div>
            </Card>

          <!-- CAPABILITIES -->
          {:else if activeSection === 'capabilities'}
            <Card class="p-6">
              <h3 class="font-heading text-base font-bold mb-5">{t('capabilities', 'Capabilities')}</h3>
              <div class="flex flex-col gap-4">
                <div>
                  <label class="tk-label">{t('capability_type', 'Capability Type')}</label>
                  <input type="text" class="tk-field !w-48 font-mono" placeholder="post" bind:value={capabilityType} />
                  <p class="tk-hint">{t('capability_type_hint', 'Use "post" for standard capabilities, or a custom string to create unique capabilities for this post type.')}</p>
                </div>
                <div class="flex items-center justify-between py-2">
                  <div>
                    <div class="text-[13px] font-medium">{t('map_meta_cap', 'Map Meta Cap')}</div>
                    <div class="text-[12px] text-muted mt-0.5">{t('map_meta_cap_desc', 'Map meta capabilities to primitive capabilities. Usually should be enabled.')}</div>
                  </div>
                  <Switch checked={mapMetaCap} onchange={() => (mapMetaCap = !mapMetaCap)} />
                </div>
              </div>
            </Card>

          <!-- TAXONOMIES -->
          {:else if activeSection === 'taxonomies'}
            <Card class="p-6">
              <h3 class="font-heading text-base font-bold mb-1">{t('taxonomies', 'Taxonomies')}</h3>
              <p class="text-[12px] text-muted mb-5">{t('taxonomies_desc', 'Create and manage taxonomies associated with this post type.')}</p>

              {#if taxonomies.length === 0}
                <div class="text-sm text-muted text-center py-6 border border-dashed border-border rounded-lg mb-4">{t('no_taxonomies_yet', 'No taxonomies yet.')}</div>
              {:else}
                <div class="flex flex-col gap-4 mb-4">
                  {#each taxonomies as tx, i}
                    <div class="p-4 rounded-lg border border-border bg-background">
                      <div class="flex items-center justify-between mb-3">
                        <span class="font-heading text-sm font-bold">{tx.label || `Taxonomy ${i + 1}`}</span>
                        <button class="text-dim hover:text-gold text-sm bg-transparent border-none cursor-pointer" onclick={() => removeTaxonomy(i)}>{t('remove', 'Remove')}</button>
                      </div>
                      <div class="grid grid-cols-3 gap-3 mb-3">
                        <div>
                          <label class="tk-label">{t('slug', 'Slug')}</label>
                          <input type="text" class="tk-field font-mono" placeholder="department" bind:value={tx.slug} oninput={() => { tx.slug = tx.slug.toLowerCase().replace(/[^a-z0-9_]/g, ''); }} />
                        </div>
                        <div>
                          <label class="tk-label">{t('plural_label', 'Plural Label')}</label>
                          <input type="text" class="tk-field" placeholder="Departments" bind:value={tx.label} />
                        </div>
                        <div>
                          <label class="tk-label">{t('singular_label', 'Singular Label')}</label>
                          <input type="text" class="tk-field" placeholder="Department" bind:value={tx.singularLabel} />
                        </div>
                      </div>
                      <div>
                        <label class="tk-label">{t('rewrite_slug', 'Rewrite Slug')}</label>
                        <input type="text" class="tk-field !w-48 font-mono text-[12px]" placeholder={t('same_as_slug', 'Same as slug')} bind:value={tx.rewriteSlug} />
                      </div>
                      <div class="flex flex-wrap gap-x-5 gap-y-2 mt-3">
                        {#each [
                          ['hierarchical', t('hierarchical', 'Hierarchical')],
                          ['public', t('public', 'Public')],
                          ['showInMenu', t('show_in_menu', 'Show in Menu')],
                          ['showInRest', t('rest_api', 'REST API')],
                          ['showTagcloud', t('tag_cloud', 'Tag Cloud')],
                          ['showInQuickEdit', t('quick_edit', 'Quick Edit')],
                        ] as [key, lbl]}
                          <label class="flex items-center gap-1.5 text-[12px] cursor-pointer">
                            <input type="checkbox" bind:checked={tx[key]} />
                            {lbl}
                          </label>
                        {/each}
                      </div>
                    </div>
                  {/each}
                </div>
              {/if}

              <Button variant="ghost" onclick={addTaxonomy}>+ {t('add_taxonomy', 'Add Taxonomy')}</Button>
            </Card>
          {/if}

          <!-- Bottom save bar -->
          <div class="flex justify-end gap-3 pt-2 pb-8">
            <Button variant="ghost" onclick={onBack}>{t('cancel', 'Cancel')}</Button>
            <Button onclick={save} disabled={saving || !slug || !label}>
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
</style>
