<script>
  import { t } from '$lib/i18n.svelte.js';
  import { ELEMENT_CATEGORIES, TYPE_MAP } from '$lib/elementCategories.js';

  let { onInsert } = $props();

  let search = $state('');
  let expandedCats = $state(ELEMENT_CATEGORIES.map(c => c.name));

  function toggleCat(name) {
    if (expandedCats.includes(name)) {
      expandedCats = expandedCats.filter(n => n !== name);
    } else {
      expandedCats = [...expandedCats, name];
    }
  }

  let filteredCats = $derived(
    ELEMENT_CATEGORIES.map(cat => ({
      ...cat,
      items: cat.items.filter(item => {
        if (!search.trim()) return true;
        const q = search.toLowerCase();
        const tm = TYPE_MAP[item.type];
        return item.type.includes(q) || item.desc.toLowerCase().includes(q) || (tm?.label || '').toLowerCase().includes(q);
      }),
    })).filter(cat => cat.items.length > 0)
  );
</script>

<div class="flex flex-col h-full">
  <!-- Search -->
  <div class="px-3 py-2.5 border-b border-border shrink-0">
    <input
      type="text"
      bind:value={search}
      placeholder={t('search_elements', 'Search elements...')}
      class="w-full px-2.5 py-[6px] bg-card-hover border border-border/50 rounded-[6px] text-[12px] font-body text-foreground outline-none placeholder:text-dim focus:border-dim transition-colors"
    />
  </div>

  <!-- Categories -->
  <div class="flex-1 overflow-auto px-2 py-1.5">
    {#each filteredCats as cat}
      <div class="mb-1">
        <button
          class="flex items-center justify-between w-full px-2 py-1.5 bg-transparent border-none text-[11px] font-semibold uppercase tracking-[1.5px] text-muted cursor-pointer hover:text-muted-foreground transition-colors"
          onclick={() => toggleCat(cat.name)}
        >
          <span>{cat.name}</span>
          <svg width="10" height="10" viewBox="0 0 10 10" class="transition-transform {expandedCats.includes(cat.name) ? 'rotate-180' : ''}">
            <path d="M2.5 4L5 6.5L7.5 4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
          </svg>
        </button>

        {#if expandedCats.includes(cat.name)}
          <div class="flex flex-col gap-px">
            {#each cat.items as item}
              {@const tm = TYPE_MAP[item.type] || { letter: '?', hue: '#666', label: item.type }}
              <button
                class="tk-palette-item flex items-center gap-2.5 w-full px-2.5 py-[7px] bg-transparent border-none rounded-[5px] cursor-pointer text-left transition-colors hover:bg-card-hover group"
                onclick={() => onInsert?.(item.type)}
                title={item.desc}
              >
                <span
                  class="w-[22px] h-[22px] rounded-[4px] flex items-center justify-center text-[10px] font-bold shrink-0"
                  style="background: {tm.hue}22; color: {tm.hue}; border: 1px solid {tm.hue}33;"
                >{tm.letter}</span>
                <div class="flex flex-col min-w-0">
                  <span class="text-[12.5px] text-foreground font-medium leading-tight">{tm.label}</span>
                  <span class="text-[11px] text-dim leading-tight truncate">{item.desc}</span>
                </div>
              </button>
            {/each}
          </div>
        {/if}
      </div>
    {/each}
  </div>
</div>
