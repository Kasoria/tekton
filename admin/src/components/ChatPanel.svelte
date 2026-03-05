<script>
  let { messages = [], isStreaming = false, currentStream = '', onsend } = $props();

  let input = $state('');
  let messagesContainer = $state(null);

  $effect(() => {
    if (messagesContainer && (messages.length || currentStream)) {
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
  });

  function handleSubmit(e) {
    e.preventDefault();
    const prompt = input.trim();
    if (!prompt || isStreaming) return;
    input = '';
    onsend?.(prompt);
  }

  function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      handleSubmit(e);
    }
  }
</script>

<div class="chat-panel">
  <div class="chat-messages" bind:this={messagesContainer}>
    {#if messages.length === 0 && !currentStream}
      <div class="chat-empty">
        <div class="empty-icon">T</div>
        <p class="empty-title">Tekton Builder</p>
        <p class="empty-hint">Describe the page you want to build.</p>
        <p class="empty-example">Try: "Create a landing page with a hero section, features grid, and call to action"</p>
      </div>
    {/if}

    {#each messages as msg}
      <div class="chat-message {msg.role}">
        <div class="message-label">{msg.role === 'user' ? 'You' : 'Tekton'}</div>
        <div class="message-content">{msg.content}</div>
      </div>
    {/each}

    {#if currentStream}
      <div class="chat-message assistant">
        <div class="message-label">Tekton</div>
        <div class="message-content streaming">{currentStream}<span class="cursor"></span></div>
      </div>
    {/if}

    {#if isStreaming && !currentStream}
      <div class="chat-message assistant">
        <div class="message-label">Tekton</div>
        <div class="message-content thinking">
          <span class="dot"></span><span class="dot"></span><span class="dot"></span>
        </div>
      </div>
    {/if}
  </div>

  <form class="chat-input" onsubmit={handleSubmit}>
    <textarea
      bind:value={input}
      onkeydown={handleKeydown}
      placeholder="Describe what you want to build..."
      rows="1"
      disabled={isStreaming}
    ></textarea>
    <button type="submit" disabled={isStreaming || !input.trim()}>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </button>
  </form>
</div>

<style>
  .chat-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
  }

  .chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .chat-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    text-align: center;
    gap: 8px;
    opacity: 0.7;
  }

  .empty-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #7c3aed, #a78bfa);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 800;
    color: white;
    margin-bottom: 8px;
  }

  .empty-title {
    font-size: 18px;
    font-weight: 600;
    color: #e4e4e7;
  }

  .empty-hint {
    font-size: 14px;
    color: #71717a;
  }

  .empty-example {
    font-size: 12px;
    color: #52525b;
    max-width: 280px;
    margin-top: 8px;
  }

  .chat-message {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .message-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #71717a;
  }

  .message-content {
    font-size: 14px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
  }

  .chat-message.user .message-content {
    background: #27272a;
    padding: 10px 14px;
    border-radius: 10px;
    color: #e4e4e7;
  }

  .chat-message.assistant .message-content {
    color: #d4d4d8;
  }

  .streaming .cursor {
    display: inline-block;
    width: 2px;
    height: 1em;
    background: #a78bfa;
    margin-left: 2px;
    animation: blink 0.8s infinite;
    vertical-align: text-bottom;
  }

  .thinking {
    display: flex;
    gap: 4px;
    padding: 8px 0;
  }

  .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #71717a;
    animation: bounce 1.4s infinite ease-in-out both;
  }

  .dot:nth-child(1) { animation-delay: -0.32s; }
  .dot:nth-child(2) { animation-delay: -0.16s; }

  @keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0; }
  }

  @keyframes bounce {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
  }

  .chat-input {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid #27272a;
    background: #18181b;
  }

  .chat-input textarea {
    flex: 1;
    background: #27272a;
    border: 1px solid #3f3f46;
    border-radius: 8px;
    padding: 10px 14px;
    color: #e4e4e7;
    font-size: 14px;
    resize: none;
    outline: none;
    font-family: inherit;
    min-height: 40px;
    max-height: 120px;
  }

  .chat-input textarea:focus {
    border-color: #7c3aed;
  }

  .chat-input textarea::placeholder {
    color: #52525b;
  }

  .chat-input button {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: #7c3aed;
    border: none;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 0.15s;
  }

  .chat-input button:hover:not(:disabled) {
    background: #6d28d9;
  }

  .chat-input button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
  }
</style>
