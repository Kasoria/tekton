<script>
  import ChatPanel from './components/ChatPanel.svelte';
  import PreviewPanel from './components/PreviewPanel.svelte';
  import PageSelector from './components/PageSelector.svelte';
  import SettingsPanel from './components/SettingsPanel.svelte';
  import { createChatStore } from './lib/stores/chat.svelte.js';
  import { createPageStore } from './lib/stores/page.svelte.js';
  import { createSettingsStore } from './lib/stores/settings.svelte.js';
  import { api } from './lib/api.js';

  const chat = createChatStore();
  const page = createPageStore();
  const settingsStore = createSettingsStore();

  let activeTab = $state('chat');
  let previewMode = $state('desktop');
  let chatPanelWidth = $state(420);
  let isResizing = $state(false);

  $effect(() => {
    page.loadStructures();
    settingsStore.load();
  });

  async function handleSend(prompt) {
    const structure = await chat.sendMessage(prompt);
    if (structure) {
      page.setStructure(structure);
    }
  }

  function handleTemplateChange(templateKey) {
    chat.setTemplateKey(templateKey);
    page.loadStructure(templateKey);
    api.getChatHistory(templateKey).then((history) => {
      chat.loadHistory(history);
    });
  }

  function handleMouseDown(e) {
    isResizing = true;
    const startX = e.clientX;
    const startWidth = chatPanelWidth;

    function onMouseMove(e) {
      const delta = e.clientX - startX;
      chatPanelWidth = Math.max(300, Math.min(800, startWidth + delta));
    }

    function onMouseUp() {
      isResizing = false;
      window.removeEventListener('mousemove', onMouseMove);
      window.removeEventListener('mouseup', onMouseUp);
    }

    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
  }
</script>

<div class="tekton-builder">
  <!-- Top Bar -->
  <header class="tekton-topbar">
    <div class="topbar-left">
      <span class="tekton-logo">Tekton</span>
      <PageSelector
        structures={page.structures}
        onchange={handleTemplateChange}
      />
    </div>
    <div class="topbar-center">
      <div class="preview-modes">
        <button
          class:active={previewMode === 'desktop'}
          onclick={() => (previewMode = 'desktop')}
          title="Desktop"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        </button>
        <button
          class:active={previewMode === 'tablet'}
          onclick={() => (previewMode = 'tablet')}
          title="Tablet"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18"/></svg>
        </button>
        <button
          class:active={previewMode === 'mobile'}
          onclick={() => (previewMode = 'mobile')}
          title="Mobile"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18"/></svg>
        </button>
      </div>
    </div>
    <div class="topbar-right">
      <button class="tab-btn" class:active={activeTab === 'chat'} onclick={() => (activeTab = 'chat')}>Chat</button>
      <button class="tab-btn" class:active={activeTab === 'settings'} onclick={() => (activeTab = 'settings')}>Settings</button>
    </div>
  </header>

  <!-- Main Content -->
  <div class="tekton-main">
    <!-- Left Panel -->
    <aside class="tekton-sidebar" style="width: {chatPanelWidth}px">
      {#if activeTab === 'chat'}
        <ChatPanel
          messages={chat.messages}
          isStreaming={chat.isStreaming}
          currentStream={chat.currentStream}
          onsend={handleSend}
        />
      {:else if activeTab === 'settings'}
        <SettingsPanel store={settingsStore} />
      {/if}
    </aside>

    <!-- Resize Handle -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div class="resize-handle" onmousedown={handleMouseDown}></div>

    <!-- Preview -->
    <main class="tekton-preview">
      <PreviewPanel
        structure={page.currentStructure}
        mode={previewMode}
      />
    </main>
  </div>
</div>

<style>
  .tekton-builder {
    display: flex;
    flex-direction: column;
    height: 100vh;
    background: #0f0f14;
    color: #e4e4e7;
    font-family: system-ui, -apple-system, sans-serif;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99999;
  }

  .tekton-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 48px;
    padding: 0 16px;
    background: #18181b;
    border-bottom: 1px solid #27272a;
    flex-shrink: 0;
  }

  .topbar-left {
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .tekton-logo {
    font-size: 16px;
    font-weight: 700;
    color: #a78bfa;
    letter-spacing: -0.02em;
  }

  .topbar-center {
    display: flex;
    align-items: center;
  }

  .preview-modes {
    display: flex;
    gap: 2px;
    background: #27272a;
    border-radius: 6px;
    padding: 2px;
  }

  .preview-modes button {
    padding: 4px 8px;
    background: transparent;
    border: none;
    color: #71717a;
    cursor: pointer;
    border-radius: 4px;
    display: flex;
    align-items: center;
  }

  .preview-modes button.active {
    background: #3f3f46;
    color: #e4e4e7;
  }

  .topbar-right {
    display: flex;
    gap: 4px;
  }

  .tab-btn {
    padding: 6px 12px;
    background: transparent;
    border: none;
    color: #71717a;
    cursor: pointer;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
  }

  .tab-btn.active {
    background: #27272a;
    color: #e4e4e7;
  }

  .tekton-main {
    display: flex;
    flex: 1;
    overflow: hidden;
  }

  .tekton-sidebar {
    flex-shrink: 0;
    background: #18181b;
    border-right: 1px solid #27272a;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }

  .resize-handle {
    width: 4px;
    cursor: col-resize;
    background: transparent;
    flex-shrink: 0;
    transition: background 0.15s;
  }

  .resize-handle:hover {
    background: #a78bfa;
  }

  .tekton-preview {
    flex: 1;
    overflow: hidden;
    background: #27272a;
  }
</style>
