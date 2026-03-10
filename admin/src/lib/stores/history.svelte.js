const MAX_HISTORY = 50;

export function createHistoryStore() {
  let stack = $state([]);
  let index = $state(-1);
  let batchTimer = null;
  let batchSnapshot = null;

  function snapshot(components) {
    return JSON.parse(JSON.stringify(components));
  }

  /**
   * Push a snapshot onto the history stack.
   * Trims any redo entries beyond the current index.
   */
  function push(components) {
    // Trim redo future
    stack = stack.slice(0, index + 1);
    stack = [...stack, snapshot(components)];
    if (stack.length > MAX_HISTORY) {
      stack = stack.slice(stack.length - MAX_HISTORY);
    }
    index = stack.length - 1;
  }

  /**
   * Push with debounce — groups rapid changes (e.g. typing) into one entry.
   * Call with the components state BEFORE the change.
   */
  function pushDebounced(components, delay = 500) {
    if (!batchTimer) {
      // First change in batch — capture the "before" state
      batchSnapshot = snapshot(components);
    }
    clearTimeout(batchTimer);
    batchTimer = setTimeout(() => {
      if (batchSnapshot) {
        stack = stack.slice(0, index + 1);
        stack = [...stack, batchSnapshot];
        if (stack.length > MAX_HISTORY) {
          stack = stack.slice(stack.length - MAX_HISTORY);
        }
        index = stack.length - 1;
      }
      batchTimer = null;
      batchSnapshot = null;
    }, delay);
  }

  /**
   * Flush any pending debounced snapshot immediately.
   */
  function flush() {
    if (batchTimer && batchSnapshot) {
      clearTimeout(batchTimer);
      stack = stack.slice(0, index + 1);
      stack = [...stack, batchSnapshot];
      if (stack.length > MAX_HISTORY) {
        stack = stack.slice(stack.length - MAX_HISTORY);
      }
      index = stack.length - 1;
      batchTimer = null;
      batchSnapshot = null;
    }
  }

  function undo() {
    flush();
    if (index < 0) return null;
    const restored = stack[index];
    index--;
    return restored ? JSON.parse(JSON.stringify(restored)) : null;
  }

  function redo() {
    if (index >= stack.length - 1) return null;
    index++;
    const restored = stack[index];
    return restored ? JSON.parse(JSON.stringify(restored)) : null;
  }

  function clear() {
    stack = [];
    index = -1;
    batchTimer = null;
    batchSnapshot = null;
  }

  return {
    get canUndo() { return index >= 0; },
    get canRedo() { return index < stack.length - 1; },
    push,
    pushDebounced,
    flush,
    undo,
    redo,
    clear,
  };
}
