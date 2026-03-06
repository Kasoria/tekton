import { streamGenerate } from '../api.js';

export function createChatStore() {
  let messages = $state([]);
  let isStreaming = $state(false);
  let currentStream = $state('');
  let templateKey = $state('front-page');

  async function sendMessage(prompt, type = 'generate_page', images = []) {
    messages.push({
      role: 'user',
      content: prompt,
      images: images.length > 0 ? images : undefined,
      timestamp: Date.now(),
    });
    isStreaming = true;
    currentStream = '';

    try {
      for await (const event of streamGenerate(prompt, templateKey, type, images)) {
        if (event.type === 'chunk') {
          currentStream += event.content;
        } else if (event.type === 'complete') {
          // Use the parsed natural language message from the backend,
          // falling back to extracting it from the stream.
          const displayMessage = event.message || extractMessage(currentStream);

          messages.push({
            role: 'assistant',
            content: displayMessage,
            structure: event.structure || null,
            timestamp: Date.now(),
          });
          currentStream = '';
          return event.structure || null;
        } else if (event.type === 'error') {
          messages.push({
            role: 'assistant',
            content: `Error: ${event.message}`,
            timestamp: Date.now(),
          });
          currentStream = '';
        }
      }
    } catch (err) {
      messages.push({
        role: 'assistant',
        content: `Error: ${err.message}`,
        timestamp: Date.now(),
      });
    } finally {
      isStreaming = false;
      currentStream = '';
    }
    return null;
  }

  /**
   * Extract natural language from a streamed response that may contain JSON.
   * Strips out ```json code fences and raw JSON objects.
   */
  function extractMessage(raw) {
    if (!raw) return 'Changes applied to the preview.';

    // If it starts with { or [, it's raw JSON — no natural language.
    const trimmed = raw.trim();
    if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
      return 'Changes applied to the preview.';
    }

    // Extract text before the first code fence.
    const fenceIdx = raw.indexOf('```');
    if (fenceIdx > 0) {
      const before = raw.substring(0, fenceIdx).trim();
      if (before) return before;
    }

    // If no code fence, return as-is (might be a plain text response).
    return trimmed;
  }

  function loadHistory(history) {
    messages = history.map((msg) => ({
      role: msg.role,
      content: msg.content,
      timestamp: new Date(msg.created_at).getTime(),
    }));
  }

  function clear() {
    messages = [];
  }

  function setTemplateKey(key) {
    templateKey = key;
    messages = [];
  }

  return {
    get messages() {
      return messages;
    },
    get isStreaming() {
      return isStreaming;
    },
    get currentStream() {
      return currentStream;
    },
    get templateKey() {
      return templateKey;
    },
    sendMessage,
    loadHistory,
    clear,
    setTemplateKey,
  };
}
