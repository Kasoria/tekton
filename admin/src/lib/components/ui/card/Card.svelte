<script>
  import { cn } from '$lib/utils.js';
  let { class: className = '', children, ...restProps } = $props();
</script>

<div class={cn('rounded-[10px] border border-border bg-card overflow-hidden', className)} {...restProps}>
  {@render children()}
</div>
