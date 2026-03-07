<script>
  let { open = false, onclose, children } = $props();

  function handleBackdrop(e) {
    if (e.target === e.currentTarget) {
      onclose?.();
    }
  }

  function handleKeydown(e) {
    if (e.key === 'Escape') {
      onclose?.();
    }
  }
</script>

{#if open}
  <!-- svelte-ignore a11y_no_static_element_interactions -->
  <div
    class="fixed inset-0 z-[10000] flex items-center justify-center"
    onkeydown={handleKeydown}
  >
    <!-- Backdrop -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div
      class="absolute inset-0 bg-black/60 backdrop-blur-[2px]"
      onclick={handleBackdrop}
    ></div>

    <!-- Content -->
    <div class="relative z-10 w-full max-w-[400px] mx-4 bg-card border border-border rounded-xl shadow-[0_24px_80px_var(--color-shadow)] p-5">
      {@render children()}
    </div>
  </div>
{/if}
