<script>
  import { cn } from '$lib/utils.js';

  let {
    value = $bindable(''),
    options = [],
    placeholder = '',
    searchable = false,
    fullWidth = false,
    class: className = '',
    onchange,
    ...restProps
  } = $props();

  let open = $state(false);
  let triggerEl = $state(null);
  let searchInputEl = $state(null);
  let dropdownPos = $state({ top: 0, left: 0, minWidth: 0 });
  let openUpward = $state(false);
  let searchQuery = $state('');

  let selectedLabel = $derived(
    options.find(o => o.value === value)?.label || placeholder || value
  );

  let filteredOptions = $derived(
    searchable && searchQuery
      ? options.filter(o => o.label.toLowerCase().includes(searchQuery.toLowerCase()) || o.value.toLowerCase().includes(searchQuery.toLowerCase()))
      : options
  );

  function updatePosition() {
    if (!triggerEl) return;
    const rect = triggerEl.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const estimatedHeight = Math.min(filteredOptions.length * 32 + (searchable ? 42 : 0) + 10, 300);
    openUpward = spaceBelow < estimatedHeight && rect.top > estimatedHeight;
    dropdownPos = {
      top: openUpward ? rect.top - 4 : rect.bottom + 4,
      left: rect.right,
      minWidth: Math.max(rect.width, searchable ? 220 : rect.width),
    };
  }

  function toggleOpen() {
    if (!open) {
      updatePosition();
      open = true;
      searchQuery = '';
      if (searchable) {
        requestAnimationFrame(() => searchInputEl?.focus());
      }
    } else {
      open = false;
    }
  }

  function select(val) {
    value = val;
    open = false;
    searchQuery = '';
    onchange?.(val);
  }

  function handleKeydown(e) {
    if (e.key === 'Escape') { open = false; searchQuery = ''; }
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      toggleOpen();
    }
  }

  function handleOptionKeydown(e, val) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      select(val);
    }
  }

  function handleSearchKeydown(e) {
    if (e.key === 'Escape') { open = false; searchQuery = ''; }
    if (e.key === 'Enter' && filteredOptions.length === 1) {
      select(filteredOptions[0].value);
    }
  }
</script>

<div class={cn('tk-select', fullWidth && 'tk-select-full', className)} {...restProps}>
  <button
    bind:this={triggerEl}
    type="button"
    class="tk-select-trigger {fullWidth ? 'tk-select-trigger-full' : ''}"
    onclick={toggleOpen}
    onkeydown={handleKeydown}
  >
    <span class="tk-select-value {value ? '' : 'tk-select-placeholder'} {fullWidth ? 'tk-select-value-full' : ''}">{selectedLabel}</span>
    <svg class="tk-select-chevron {open ? 'tk-select-chevron-open' : ''}" width="12" height="12" viewBox="0 0 12 12" fill="none">
      <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </button>

  {#if open}
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div class="fixed inset-0 z-[99998]" onclick={() => { open = false; searchQuery = ''; }}></div>
    <div
      class="tk-select-dropdown"
      role="listbox"
      style="position:fixed; {openUpward ? `bottom:${window.innerHeight - dropdownPos.top}px` : `top:${dropdownPos.top}px`}; left:auto; right:{window.innerWidth - dropdownPos.left}px; min-width:{dropdownPos.minWidth}px;"
    >
      {#if searchable}
        <div class="tk-select-search">
          <input
            bind:this={searchInputEl}
            type="text"
            class="tk-select-search-input"
            placeholder="Search..."
            bind:value={searchQuery}
            onkeydown={handleSearchKeydown}
          />
        </div>
      {/if}
      {#each filteredOptions as opt}
        <button
          type="button"
          role="option"
          aria-selected={opt.value === value}
          class="tk-select-option {opt.value === value ? 'tk-select-option-active' : ''}"
          onclick={() => select(opt.value)}
          onkeydown={(e) => handleOptionKeydown(e, opt.value)}
        >
          <span>{opt.label}</span>
          {#if opt.value === value}
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
              <path d="M2.5 6.5L5 9L9.5 3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          {/if}
        </button>
      {:else}
        <div class="tk-select-empty">No results</div>
      {/each}
    </div>
  {/if}
</div>

<style>
  .tk-select {
    display: inline-flex;
  }

  .tk-select-trigger {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 5px 10px;
    background: var(--color-background);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    color: var(--color-foreground);
    font-size: 13px;
    font-family: var(--font-body);
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    min-width: 0;
    line-height: 1.4;
  }

  .tk-select-trigger:hover {
    border-color: var(--color-dim);
    background: var(--color-card-hover);
  }

  .tk-select-trigger:focus-visible {
    border-color: var(--color-copper);
    outline: none;
  }

  .tk-select-value {
    flex: 1;
    text-align: right;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .tk-select-placeholder {
    color: var(--color-muted);
  }

  .tk-select-chevron {
    color: var(--color-muted);
    flex-shrink: 0;
    transition: transform 0.15s;
  }

  .tk-select-chevron-open {
    transform: rotate(180deg);
  }

  .tk-select-dropdown {
    z-index: 99999;
    max-height: 300px;
    overflow-y: auto;
    background: var(--color-card-hover);
    border: 1px solid var(--color-dim);
    border-radius: 8px;
    padding: 3px;
    box-shadow: 0 12px 40px var(--color-shadow);
  }

  .tk-select-search {
    padding: 4px 4px 2px;
    position: sticky;
    top: 0;
    background: var(--color-card-hover);
    z-index: 1;
  }

  .tk-select-search-input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid var(--color-border);
    border-radius: 5px;
    background: var(--color-background);
    color: var(--color-foreground);
    font-size: 12px;
    font-family: var(--font-body);
    outline: none;
    box-sizing: border-box;
  }

  .tk-select-search-input:focus {
    border-color: var(--color-copper);
  }

  .tk-select-search-input::placeholder {
    color: var(--color-muted);
  }

  .tk-select-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
    padding: 6px 10px;
    border: none;
    border-radius: 5px;
    background: transparent;
    color: var(--color-muted-foreground);
    font-size: 13px;
    font-family: var(--font-body);
    cursor: pointer;
    text-align: left;
    transition: background 0.1s, color 0.1s;
    white-space: nowrap;
    line-height: 1.4;
  }

  .tk-select-option:hover {
    background: var(--color-card-hover);
    color: var(--color-foreground);
  }

  .tk-select-option-active {
    color: var(--color-copper);
  }

  .tk-select-option-active:hover {
    color: var(--color-copper);
  }

  .tk-select-empty {
    padding: 8px 10px;
    color: var(--color-muted);
    font-size: 12px;
    text-align: center;
  }

  .tk-select-dropdown::-webkit-scrollbar {
    width: 4px;
  }
  .tk-select-dropdown::-webkit-scrollbar-track {
    background: transparent;
  }
  .tk-select-dropdown::-webkit-scrollbar-thumb {
    background: var(--color-border);
    border-radius: 2px;
  }

  /* Full-width variant for form fields */
  .tk-select-full {
    display: flex;
    width: 100%;
  }

  .tk-select-trigger-full {
    flex: 1;
    width: 100%;
    padding: 8px 12px;
    border-radius: 8px;
  }

  .tk-select-value-full {
    text-align: left;
  }
</style>
