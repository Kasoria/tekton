<script>
  import Dashboard from './components/Dashboard.svelte';
  import Builder from './components/Builder.svelte';
  import { createThemeStore } from '$lib/stores/theme.svelte.js';

  const theme = createThemeStore();

  let view = $state('dashboard');
  let initialTemplateKey = $state(null);

  function openBuilder(templateKey = null) {
    initialTemplateKey = templateKey;
    view = 'builder';
  }
</script>

{#if view === 'dashboard'}
  <Dashboard onOpenBuilder={() => openBuilder()} onOpenTemplate={(key) => openBuilder(key)} />
{:else}
  <Builder onBack={() => (view = 'dashboard')} {initialTemplateKey} />
{/if}
