import { streamGenerate } from '../api.js';

export function createChatStore() {
  let messages = $state([]);
  let isStreaming = $state(false);
  let currentStream = $state('');
  let templateKey = $state('front-page');

  async function sendMessage(prompt, type = 'generate_page') {
    messages.push({ role: 'user', content: prompt, timestamp: Date.now() });
    isStreaming = true;
    currentStream = '';

    try {
      for await (const event of streamGenerate(prompt, templateKey, type)) {
        if (event.type === 'chunk') {
          currentStream += event.content;
        } else if (event.type === 'complete') {
          messages.push({
            role: 'assistant',
            content: currentStream,
            structure: event.structure,
            timestamp: Date.now(),
          });
          currentStream = '';
          return event.structure;
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
