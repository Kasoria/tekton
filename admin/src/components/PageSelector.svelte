<script>
  let { structures = [], onchange } = $props();

  let selected = $state('');
  let showNew = $state(false);
  let newKey = $state('');

  function handleChange(e) {
    selected = e.target.value;
    if (selected) {
      onchange?.(selected);
    }
  }

  function handleNewPage() {
    const key = newKey.trim().toLowerCase().replace(/[^a-z0-9-_]/g, '-');
    if (key) {
      selected = key;
      newKey = '';
      showNew = false;
      onchange?.(key);
    }
  }
</script>

<div class="page-selector">
  <select value={selected} onchange={handleChange}>
    <option value="" disabled>Select template...</option>
    <option value="front-page">Front Page</option>
    {#each structures as s}
      {#if s.template_key !== 'front-page'}
        <option value={s.template_key}>{s.title || s.template_key}</option>
      {/if}
    {/each}
  </select>

  <button class="new-btn" onclick={() => (showNew = !showNew)} title="New template">+</button>

  {#if showNew}
    <div class="new-input">
      <input
        bind:value={newKey}
        placeholder="template-key"
        onkeydown={(e) => e.key === 'Enter' && handleNewPage()}
      />
      <button onclick={handleNewPage}>Create</button>
    </div>
  {/if}
</div>

<style>
  .page-selector {
    display: flex;
    align-items: center;
    gap: 6px;
    position: relative;
  }

  select {
    background: #27272a;
    border: 1px solid #3f3f46;
    border-radius: 6px;
    padding: 4px 8px;
    color: #e4e4e7;
    font-size: 13px;
    cursor: pointer;
    outline: none;
  }

  .new-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: #27272a;
    border: 1px solid #3f3f46;
    color: #a78bfa;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .new-input {
    position: absolute;
    top: 100%;
    left: 0;
    margin-top: 4px;
    display: flex;
    gap: 4px;
    background: #27272a;
    border: 1px solid #3f3f46;
    border-radius: 6px;
    padding: 6px;
    z-index: 10;
  }

  .new-input input {
    background: #18181b;
    border: 1px solid #3f3f46;
    border-radius: 4px;
    padding: 4px 8px;
    color: #e4e4e7;
    font-size: 12px;
    width: 140px;
    outline: none;
  }

  .new-input button {
    background: #7c3aed;
    border: none;
    border-radius: 4px;
    padding: 4px 10px;
    color: white;
    font-size: 12px;
    cursor: pointer;
  }
</style>
