<script>
  import Dashboard from './components/Dashboard.svelte';
  import Builder from './components/Builder.svelte';

  let view = $state('dashboard');
</script>

{#if view === 'dashboard'}
  <Dashboard onOpenBuilder={() => (view = 'builder')} />
{:else}
  <Builder onBack={() => (view = 'dashboard')} />
{/if}
