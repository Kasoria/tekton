<script>
  import { cn } from '$lib/utils.js';

  let { checked = $bindable(false), class: className = '', onchange, ...restProps } = $props();

  function toggle() {
    checked = !checked;
    onchange?.();
  }
</script>

<button
  type="button"
  role="switch"
  aria-checked={checked}
  class={cn(
    'relative inline-flex h-[18px] w-8 shrink-0 cursor-pointer items-center rounded-full p-0.5 transition-colors',
    checked ? 'bg-copper' : 'bg-border',
    className
  )}
  onclick={toggle}
  {...restProps}
>
  <span
    class={cn(
      'pointer-events-none block h-3.5 w-3.5 rounded-full transition-transform',
      checked ? 'translate-x-3.5 bg-background' : 'translate-x-0 bg-muted'
    )}
  ></span>
</button>
