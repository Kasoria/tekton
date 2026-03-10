<script>
  import { t } from '$lib/i18n.svelte.js';
  import { TYPE_MAP } from '$lib/elementCategories.js';
  import ContentTab from './inspector/ContentTab.svelte';
  import StyleTab from './inspector/StyleTab.svelte';
  import SettingsTab from './inspector/SettingsTab.svelte';

  let { editor, page } = $props();

  let activeTab = $state('content'); // 'content' | 'style' | 'settings'

  let comp = $derived(editor.selectedComponent);
  let compType = $derived(comp?.type || '');
  let typeInfo = $derived(TYPE_MAP[compType] || { letter: '?', hue: '#666', label: compType });

  const tabs = [
    { key: 'content', label: 'Content' },
    { key: 'style', label: 'Style' },
    { key: 'settings', label: 'Settings' },
  ];

  // Breakpoint selector
  const breakpoints = [
    { key: 'desktop', icon: '🖥', label: 'Desktop' },
    { key: 'tablet', icon: '📱', label: 'Tablet' },
    { key: 'mobile', icon: '📲', label: 'Mobile' },
  ];
</script>

<div class="tk-inspector">
  {#if comp}
    <!-- Header: type badge + label -->
    <div class="flex items-center gap-2 px-3 py-2.5 border-b border-border shrink-0">
      <span
        class="w-5 h-5 rounded flex items-center justify-center text-[9px] font-bold font-mono shrink-0"
        style="background: {typeInfo.hue}15; color: {typeInfo.hue};"
      >{typeInfo.letter}</span>
      <span class="text-[12px] font-semibold uppercase tracking-[1.5px] text-muted-foreground truncate">{typeInfo.label}</span>

      <!-- Dirty indicator -->
      {#if editor.dirty}
        <span class="ml-auto text-[9px] text-copper animate-pulse">{t('saving', 'Saving...')}</span>
      {/if}
    </div>

    <!-- Breakpoint selector (compact) -->
    <div class="flex items-center justify-center gap-1 px-3 py-1.5 border-b border-border shrink-0">
      {#each breakpoints as bp}
        <button
          class="px-2.5 py-[3px] border-none rounded-[3px] cursor-pointer text-[11px] font-medium font-body transition-colors {editor.activeBreakpoint === bp.key ? 'bg-copper text-white' : 'bg-transparent text-dim hover:text-muted-foreground'}"
          onclick={() => editor.setBreakpoint(bp.key)}
          title={bp.label}
        >{bp.label}</button>
      {/each}
    </div>

    <!-- Tab bar -->
    <div class="flex border-b border-border shrink-0">
      {#each tabs as tab}
        <button
          class="flex-1 px-2 py-2 border-none cursor-pointer text-[11px] font-semibold font-body uppercase tracking-[0.8px] transition-colors {activeTab === tab.key ? 'text-copper border-b-2 border-copper -mb-px bg-copper/5' : 'text-dim bg-transparent hover:text-muted-foreground'}"
          onclick={() => (activeTab = tab.key)}
        >{t(`tab_${tab.key}`, tab.label)}</button>
      {/each}
    </div>

    <!-- Tab content -->
    <div class="flex-1 overflow-hidden flex flex-col">
      {#if activeTab === 'content'}
        <ContentTab {editor} {comp} {compType} />
      {:else if activeTab === 'style'}
        <StyleTab {editor} {comp} />
      {:else if activeTab === 'settings'}
        <SettingsTab {editor} {comp} />
      {/if}
    </div>
  {:else}
    <!-- Empty state -->
    <div class="flex-1 flex flex-col items-center justify-center px-6 text-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-card-hover flex items-center justify-center">
        <span class="text-[18px] text-dim">⬚</span>
      </div>
      <div>
        <p class="text-[13px] text-muted-foreground font-medium mb-1">{t('no_selection', 'No component selected')}</p>
        <p class="text-[11px] text-dim leading-relaxed">{t('select_hint', 'Click a component in the preview or the structure tree to inspect and edit its properties.')}</p>
      </div>
    </div>
  {/if}
</div>

<style>
  .tk-inspector {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    background: var(--color-card);
  }
</style>
