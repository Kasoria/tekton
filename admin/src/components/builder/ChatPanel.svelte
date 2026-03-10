<script>
  import { t } from '$lib/i18n.svelte.js';

  let {
    chat,
    input = $bindable(''),
    attachedImages = $bindable([]),
    imageWarning = '',
    streamingDisplay,
    isBuildingStructure,
    onSend,
    onImageUpload,
    onRemoveImage,
    onPaste,
    onClearChat,
  } = $props();

  let messagesEnd;
  let textareaEl;
  let fileInputEl;
  let showClearMenu = $state(false);
  let isClearing = $state(false);

  // Auto-scroll messages
  $effect(() => {
    if (chat.messages.length || chat.isStreaming) {
      messagesEnd?.scrollIntoView({ behavior: 'smooth' });
    }
  });

  function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      onSend?.();
    }
  }

  function autoResize(e) {
    e.target.style.height = '20px';
    e.target.style.height = e.target.scrollHeight + 'px';
  }

  async function handleClear(withSummary) {
    isClearing = true;
    showClearMenu = false;
    try {
      await onClearChat?.(withSummary);
    } finally {
      isClearing = false;
    }
  }
</script>

<!-- Messages -->
<div class="flex-1 overflow-auto p-4 pb-2">
  <div class="flex flex-col gap-5">
    {#each chat.messages as m}
      <div class="flex flex-col gap-[5px]">
        <span class="text-[12px] font-semibold uppercase tracking-[1.2px] pl-0.5 {m.role === 'user' ? 'text-muted' : 'text-copper'}">
          {m.role === 'user' ? t('you', 'You') : t('tekton', 'Tekton')}
          {#if m.is_summary}<span class="text-[12px] text-muted-foreground font-normal normal-case tracking-normal ml-1">· {t('summary_of_previous', 'summary of previous session')}</span>{/if}
        </span>
        <div class="rounded-[10px] text-[13.5px] leading-[1.65] px-3.5 py-3 {m.role === 'user' ? 'bg-card-hover text-foreground border-l-2 border-dim' : 'bg-card text-foreground/75 border-l-2 border-copper/20'}">
          {#if m.images?.length}
            <div class="flex gap-1.5 mb-2 flex-wrap">
              {#each m.images as img}
                <img src={img.preview} alt="" class="w-10 h-10 object-cover rounded-[5px] border border-border/50 opacity-80" />
              {/each}
            </div>
          {/if}
          <div class="whitespace-pre-wrap">{m.content}</div>

          {#if m.structure}
            <div class="inline-flex items-center gap-[5px] mt-2.5 px-2.5 py-1 rounded-[5px] bg-green/5 border border-green/10">
              <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 5.5L4 7.5L8 3" stroke="#7dab6e" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <span class="text-[12px] text-green font-medium">{t('preview_updated', 'Preview updated')}</span>
            </div>
          {/if}
        </div>
      </div>
    {/each}

    <!-- Streaming indicator -->
    {#if chat.isStreaming}
      <div class="flex flex-col gap-[5px]">
        <span class="text-[12px] font-semibold uppercase tracking-[1.2px] pl-0.5 text-copper">{t('tekton', 'Tekton')}</span>
        <div class="rounded-[10px] text-[13.5px] leading-[1.65] px-3.5 py-3 bg-card text-foreground/75 border-l-2 border-copper/20">
          {#if streamingDisplay?.()}
            <div class="whitespace-pre-wrap">{streamingDisplay()}</div>
          {/if}
          {#if isBuildingStructure?.()}
            <div class="flex items-center gap-2.5 {streamingDisplay?.() ? 'mt-3 pt-3 border-t border-border/30' : ''}">
              <div class="tk-cooking flex gap-[3px]">
                <span></span><span></span><span></span>
              </div>
              <span class="text-[12px] text-copper/80 font-medium">{t('generating_structure', 'Generating structure…')}</span>
            </div>
          {:else if !streamingDisplay?.()}
            <div class="flex items-center gap-2">
              <div class="tk-ember w-[7px] h-[7px] rounded-full bg-copper shrink-0"></div>
              <span class="text-muted">{t('thinking', 'Thinking...')}</span>
            </div>
          {/if}
        </div>
      </div>
    {/if}
    <div bind:this={messagesEnd}></div>
  </div>
</div>

<!-- Input area -->
<div class="p-4 pt-2 border-t border-border">
  <!-- Image warning -->
  {#if imageWarning}
    <div class="text-[12px] text-red-400 mb-1.5 px-1">{imageWarning}</div>
  {/if}
  <!-- Image thumbnails -->
  {#if attachedImages.length > 0}
    <div class="flex gap-1.5 mb-2 flex-wrap">
      {#each attachedImages as img, i}
        <div class="relative group">
          <img
            src={img.preview}
            alt={img.name}
            class="w-12 h-12 object-cover rounded-[6px] border border-border"
          />
          <button
            class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-background border border-border text-dim text-[12px] leading-none flex items-center justify-center cursor-pointer opacity-0 group-hover:opacity-100 transition-opacity hover:text-foreground"
            onclick={() => onRemoveImage?.(i)}
          >×</button>
        </div>
      {/each}
    </div>
  {/if}

  <div class="flex gap-2 items-start bg-card rounded-[10px] border border-border px-3 py-2.5 focus-within:border-dim transition-colors">
    <button
      onclick={() => fileInputEl?.click()}
      class="w-[30px] h-[30px] rounded-[7px] border-none cursor-pointer flex items-center justify-center transition-all shrink-0 bg-transparent opacity-70 hover:opacity-100"
      aria-label="Attach image"
      title="Attach image"
    >
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
        <rect x="2" y="2" width="12" height="12" rx="2" stroke="#8a847d" stroke-width="1.3"/>
        <circle cx="5.5" cy="5.5" r="1.2" fill="#8a847d"/>
        <path d="M2 11l3-3.5 2.5 2.5L10 7.5l4 4.5" stroke="#8a847d" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
    <input
      bind:this={fileInputEl}
      type="file"
      accept="image/jpeg,image/png,image/gif,image/webp"
      multiple
      onchange={onImageUpload}
      class="hidden"
    />
    <textarea
      bind:this={textareaEl}
      bind:value={input}
      onkeydown={handleKeydown}
      oninput={autoResize}
      onpaste={onPaste}
      placeholder={t('describe_prompt', 'Describe what to build or change...')}
      rows="3"
      class="flex-1 bg-transparent border-none text-foreground text-[13px] leading-[1.5] resize-none outline-none font-body min-h-[54px] max-h-[150px] placeholder:text-dim"
    ></textarea>
    <button
      onclick={() => onSend?.()}
      disabled={(!input.trim() && attachedImages.length === 0) || chat.isStreaming}
      class="w-[30px] h-[30px] rounded-[7px] border-none cursor-pointer flex items-center justify-center transition-all shrink-0 {input.trim() || attachedImages.length > 0 ? 'bg-copper opacity-100' : 'bg-transparent opacity-60'}"
      aria-label="Send message"
    >
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
        <path d="M7 11V3M7 3L4 6M7 3l3 3" stroke={input.trim() || attachedImages.length > 0 ? '#ffffff' : 'var(--color-muted)'} stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
  </div>
  <div class="flex items-center justify-start gap-1.5 mt-1.5 pl-0.5">
    {#each ['/fullstack', '/plugin', '/undo'] as cmd}
      <button
        class="px-[7px] py-[2px] bg-transparent rounded cursor-pointer text-[12px] font-mono transition-colors"
        style="border: 1px solid var(--color-muted); color: var(--color-muted-foreground);"
        onclick={() => { input = cmd + ' '; textareaEl?.focus(); }}
      >{cmd}</button>
    {/each}

    <!-- Clear chat -->
    {#if chat.messages.length > 0}
      <div class="relative ml-auto">
        <button
          class="px-[7px] py-[2px] bg-transparent border border-border/50 rounded text-dim cursor-pointer text-[12px] font-body transition-colors hover:text-muted hover:border-dim {isClearing ? 'opacity-50 pointer-events-none' : ''}"
          onclick={() => (showClearMenu = !showClearMenu)}
        >{isClearing ? t('clearing', 'Clearing...') : t('clear_chat', 'Clear chat')}</button>
        {#if showClearMenu}
          <!-- svelte-ignore a11y_no_static_element_interactions -->
          <div class="fixed inset-0 z-[29]" onclick={() => (showClearMenu = false)}></div>
          <div class="absolute bottom-[calc(100%+6px)] right-0 w-[200px] z-30 bg-card-hover border border-dim rounded-[8px] p-1 shadow-[0_12px_40px_var(--color-shadow)]">
            <button
              class="flex flex-col items-start w-full px-2.5 py-2 border-none rounded-[5px] cursor-pointer text-left bg-transparent hover:bg-border/20 transition-colors"
              onclick={() => handleClear(true)}
            >
              <span class="text-[12px] text-foreground font-medium">{t('clear_with_summary', 'Clear with summary')}</span>
              <span class="text-[12px] text-muted leading-tight mt-0.5">{t('clear_with_summary_desc', 'AI summarizes the conversation, then clears')}</span>
            </button>
            <button
              class="flex flex-col items-start w-full px-2.5 py-2 border-none rounded-[5px] cursor-pointer text-left bg-transparent hover:bg-border/20 transition-colors"
              onclick={() => handleClear(false)}
            >
              <span class="text-[12px] text-foreground font-medium">{t('clear_all', 'Clear all')}</span>
              <span class="text-[12px] text-muted leading-tight mt-0.5">{t('clear_all_desc', 'Remove entire chat history')}</span>
            </button>
          </div>
        {/if}
      </div>
    {:else}
      <span class="ml-auto text-[12px] text-muted">{t('shift_enter_newline', 'shift+enter for newline')}</span>
    {/if}
  </div>
</div>

<style>
  @keyframes ember {
    0%, 100% { opacity: 0.35; transform: scale(0.85); }
    50% { opacity: 1; transform: scale(1.15); }
  }

  :global(.tk-ember) {
    animation: ember 1.6s ease-in-out infinite;
  }

  .tk-cooking {
    display: flex;
    align-items: center;
    gap: 3px;
  }
  .tk-cooking span {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--color-copper, #c97d3c);
    animation: cooking 1.4s ease-in-out infinite;
  }
  .tk-cooking span:nth-child(2) { animation-delay: 0.15s; }
  .tk-cooking span:nth-child(3) { animation-delay: 0.3s; }

  @keyframes cooking {
    0%, 80%, 100% { opacity: 0.25; transform: scale(0.75); }
    40% { opacity: 1; transform: scale(1.1); }
  }
</style>
