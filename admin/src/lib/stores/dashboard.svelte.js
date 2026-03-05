import { api } from '../api.js';

export function createDashboardStore() {
  let templates = $state([]);
  let fieldGroups = $state([]);
  let postTypes = $state([]);
  let activity = $state([]);
  let pluginStats = $state({ count: 0, active: 0 });
  let loading = $state(false);
  let error = $state(null);

  async function load() {
    loading = true;
    error = null;
    try {
      const data = await api.getDashboard();
      templates = data.templates || [];
      fieldGroups = data.field_groups || [];
      postTypes = data.post_types || [];
      activity = data.activity || [];
      pluginStats = data.plugins || { count: 0, active: 0 };
    } catch (e) {
      error = e.message;
    } finally {
      loading = false;
    }
  }

  return {
    get templates() { return templates; },
    get fieldGroups() { return fieldGroups; },
    get postTypes() { return postTypes; },
    get activity() { return activity; },
    get pluginStats() { return pluginStats; },
    get loading() { return loading; },
    get error() { return error; },
    load,
  };
}
