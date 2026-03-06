<script>
  import { api } from '../lib/api.js';

  let { structure = null, mode = 'desktop' } = $props();

  let iframeRef = $state(null);
  let loading = $state(false);
  let previewHtml = $state('');

  let iframeWidth = $derived(
    mode === 'desktop' ? '100%' : mode === 'tablet' ? '768px' : '375px'
  );

  $effect(() => {
    if (structure?.components) {
      loadPreview(structure.components, structure.template_key);
    }
  });

  async function loadPreview(components, templateKey) {
    loading = true;
    try {
      const result = await api.preview(components, templateKey || 'preview');
      previewHtml = result.html;
      if (iframeRef) {
        iframeRef.srcdoc = previewHtml;
      }
    } catch (err) {
      previewHtml = `<html><body style="font-family:system-ui;color:#999;display:flex;align-items:center;justify-content:center;height:100vh"><p>Preview error: ${err.message}</p></body></html>`;
      if (iframeRef) {
        iframeRef.srcdoc = previewHtml;
      }
    } finally {
      loading = false;
    }
  }
</script>

<div class="preview-panel">
  {#if loading}
    <div class="preview-loading">
      <div class="spinner"></div>
    </div>
  {/if}

  {#if !structure}
    <div class="preview-empty">
      <p>No preview yet. Start by describing a page in the chat.</p>
    </div>
  {:else}
    <div class="iframe-wrapper" style="max-width: {iframeWidth}">
      <iframe
        bind:this={iframeRef}
        title="Page Preview"
        sandbox="allow-same-origin allow-scripts"
      ></iframe>
    </div>
  {/if}
</div>

<style>
  .preview-panel {
    width: 100%;
    height: 100%;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: auto;
    background: #1a1a1e;
  }

  .preview-empty {
    color: #52525b;
    font-size: 14px;
    text-align: center;
    padding: 40px;
  }

  .preview-loading {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 15, 20, 0.7);
    z-index: 10;
  }

  .spinner {
    width: 28px;
    height: 28px;
    border: 3px solid #3f3f46;
    border-top-color: #a78bfa;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  .iframe-wrapper {
    width: 100%;
    height: 100%;
    margin: 0 auto;
    transition: max-width 0.3s ease;
    background: #ffffff;
  }

  iframe {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
  }
</style>
