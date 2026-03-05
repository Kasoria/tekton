<script>
  import { cn } from '$lib/utils.js';
  let { class: className = '', children, ...restProps } = $props();
</script>

<h3 class={cn('font-heading text-lg font-semibold leading-none tracking-tight', className)} {...restProps}>
  {@render children()}
</h3>
