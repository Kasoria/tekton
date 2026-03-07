<script>
  import Dashboard from './components/Dashboard.svelte';
  import Builder from './components/Builder.svelte';
  import EditPostType from './components/EditPostType.svelte';
  import EditFieldGroup from './components/EditFieldGroup.svelte';
  import { createThemeStore } from '$lib/stores/theme.svelte.js';

  const theme = createThemeStore();

  let view = $state('dashboard');
  let initialTemplateKey = $state(null);
  let editId = $state(null);

  function openBuilder(templateKey = null) {
    initialTemplateKey = templateKey;
    view = 'builder';
  }

  function goToDashboard() {
    editId = null;
    view = 'dashboard';
  }
</script>

{#if view === 'dashboard'}
  <Dashboard
    onOpenBuilder={() => openBuilder()}
    onOpenTemplate={(key) => openBuilder(key)}
    onEditPostType={(id) => { editId = id; view = 'editPostType'; }}
    onEditFieldGroup={(id) => { editId = id; view = 'editFieldGroup'; }}
  />
{:else if view === 'builder'}
  <Builder onBack={goToDashboard} {initialTemplateKey} />
{:else if view === 'editPostType'}
  <EditPostType onBack={goToDashboard} {editId} />
{:else if view === 'editFieldGroup'}
  <EditFieldGroup onBack={goToDashboard} {editId} />
{/if}
