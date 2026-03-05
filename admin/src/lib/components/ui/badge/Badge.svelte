<script>
  import { cn } from '$lib/utils.js';

  let { class: className = '', variant = 'default', children, ...restProps } = $props();

  const variants = {
    default: 'bg-copper/10 text-copper',
    success: 'bg-green/10 text-green',
    warning: 'bg-gold/10 text-gold',
    muted: 'bg-muted/10 text-muted',
    ai: 'bg-copper/10 text-copper',
    manual: 'bg-slate/10 text-slate',
  };
</script>

<span
  class={cn(
    'inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider',
    variants[variant],
    className
  )}
  {...restProps}
>
  {@render children()}
</span>
