/**
 * Parent-side PostMessage bridge for communicating with the preview iframe.
 */

/**
 * Create a bridge to communicate with the preview iframe.
 * @param {HTMLIFrameElement} iframeEl
 * @returns {{ send: Function, on: Function, off: Function, destroy: Function }}
 */
export function createBridge(iframeEl) {
  const handlers = new Map();

  function onMessage(event) {
    const data = event.data;
    if (!data || typeof data.type !== 'string' || !data.type.startsWith('tekton:')) return;
    const listeners = handlers.get(data.type);
    if (listeners) {
      listeners.forEach(cb => cb(data.payload || {}));
    }
  }

  window.addEventListener('message', onMessage);

  return {
    /**
     * Send a message to the iframe.
     * @param {string} type - e.g. 'tekton:select'
     * @param {object} [payload]
     */
    send(type, payload = {}) {
      const win = iframeEl?.contentWindow;
      if (!win) return;
      win.postMessage({ type, payload }, '*');
    },

    /**
     * Register a handler for messages from the iframe.
     * @param {string} type - e.g. 'tekton:componentClick'
     * @param {function} callback
     */
    on(type, callback) {
      if (!handlers.has(type)) handlers.set(type, new Set());
      handlers.get(type).add(callback);
    },

    /**
     * Remove a handler.
     * @param {string} type
     * @param {function} callback
     */
    off(type, callback) {
      const listeners = handlers.get(type);
      if (listeners) listeners.delete(callback);
    },

    /**
     * Clean up all listeners.
     */
    destroy() {
      window.removeEventListener('message', onMessage);
      handlers.clear();
    },
  };
}
