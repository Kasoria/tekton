<script>
  import Dialog from './Dialog.svelte';
  import { Button } from '$lib/components/ui/button/index.js';

  let {
    open = false,
    title = 'Are you sure?',
    description = '',
    confirmLabel = 'Delete',
    cancelLabel = 'Cancel',
    variant = 'destructive',
    onconfirm,
    oncancel,
  } = $props();

  function handleConfirm() {
    onconfirm?.();
  }

  function handleCancel() {
    oncancel?.();
  }
</script>

<Dialog {open} onclose={handleCancel}>
  <div class="flex flex-col gap-4">
    <div class="flex flex-col gap-1.5">
      <h3 class="text-[15px] font-semibold text-foreground font-heading m-0 p-0">{title}</h3>
      {#if description}
        <p class="text-[13px] text-muted-foreground leading-[1.5] m-0 p-0">{description}</p>
      {/if}
    </div>
    <div class="flex justify-end gap-2">
      <button
        class="px-3.5 py-[7px] bg-transparent border border-border rounded-lg text-muted-foreground text-[13px] font-medium font-body cursor-pointer hover:border-dim transition-colors"
        onclick={handleCancel}
      >{cancelLabel}</button>
      {#if variant === 'destructive'}
        <button
          class="px-3.5 py-[7px] bg-red-500/90 border-none rounded-lg text-white text-[13px] font-medium font-body cursor-pointer hover:bg-red-500 transition-colors"
          onclick={handleConfirm}
        >{confirmLabel}</button>
      {:else}
        <Button onclick={handleConfirm}>{confirmLabel}</Button>
      {/if}
    </div>
  </div>
</Dialog>
