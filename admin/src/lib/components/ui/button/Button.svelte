<script>
  import { cn } from '$lib/utils.js';

  let {
    class: className = '',
    variant = 'default',
    size = 'default',
    children,
    ...restProps
  } = $props();

  const variants = {
    default: 'bg-copper text-white hover:bg-copper/90',
    secondary: 'bg-card text-muted-foreground hover:bg-card/80 border border-border',
    ghost: 'hover:bg-card text-muted-foreground',
    link: 'text-copper underline-offset-4 hover:underline',
  };

  const sizes = {
    default: 'h-9 px-5 text-sm',
    sm: 'h-8 px-3 text-xs',
    lg: 'h-10 px-6 text-base',
    icon: 'h-9 w-9',
  };
</script>

<button
  class={cn(
    'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors cursor-pointer disabled:opacity-50 disabled:pointer-events-none',
    variants[variant],
    sizes[size],
    className
  )}
  {...restProps}
>
  {@render children()}
</button>
