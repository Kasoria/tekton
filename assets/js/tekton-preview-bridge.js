/**
 * Tekton Preview Bridge
 *
 * Vanilla JS injected into the preview iframe. Handles hover highlighting,
 * component selection, inline text editing, and PostMessage communication
 * with the parent builder UI.
 *
 * @package Tekton
 * @since   1.0.0
 */
(function () {
  'use strict';

  var state = {
    selectedId: null,
    hoveredId: null,
    editingId: null,
  };

  // ─── Overlay Elements ────────────────────────────────────────────

  var hoverOverlay = createOverlay('tekton-hover-overlay', '1px dashed rgba(184, 112, 47, 0.4)');
  var selectOverlay = createOverlay('tekton-select-overlay', '2px solid rgba(184, 112, 47, 0.8)');

  // Label that shows component type on selection
  var selectLabel = document.createElement('div');
  selectLabel.id = 'tekton-select-label';
  setStyles(selectLabel, {
    position: 'fixed',
    zIndex: '99999',
    background: '#b8702f',
    color: '#fff',
    fontSize: '10px',
    fontFamily: 'system-ui, sans-serif',
    fontWeight: '600',
    padding: '2px 6px',
    borderRadius: '0 0 4px 4px',
    pointerEvents: 'none',
    display: 'none',
    lineHeight: '1.4',
    letterSpacing: '0.3px',
    whiteSpace: 'nowrap',
  });
  document.body.appendChild(selectLabel);

  // Badge shown during content-source editing
  var sourceBadge = document.createElement('div');
  sourceBadge.id = 'tekton-source-badge';
  setStyles(sourceBadge, {
    position: 'fixed',
    zIndex: '100000',
    background: '#1a1816',
    color: '#e8c496',
    fontSize: '10px',
    fontFamily: 'system-ui, sans-serif',
    padding: '3px 8px',
    borderRadius: '4px',
    pointerEvents: 'none',
    display: 'none',
    lineHeight: '1.3',
    whiteSpace: 'nowrap',
  });
  document.body.appendChild(sourceBadge);

  function createOverlay(id, border) {
    var el = document.createElement('div');
    el.id = id;
    setStyles(el, {
      position: 'fixed',
      zIndex: '99998',
      border: border,
      pointerEvents: 'none',
      display: 'none',
      borderRadius: '2px',
      boxSizing: 'border-box',
    });
    document.body.appendChild(el);
    return el;
  }

  function setStyles(el, styles) {
    for (var key in styles) {
      el.style[key] = styles[key];
    }
  }

  // ─── DOM Helpers ─────────────────────────────────────────────────

  function getComponentEl(target) {
    var el = target;
    while (el && el !== document.body) {
      if (el.hasAttribute && el.hasAttribute('data-component-type')) {
        return el;
      }
      el = el.parentElement;
    }
    return null;
  }

  function isInsidePostLoop(el) {
    var parent = el.parentElement;
    while (parent && parent !== document.body) {
      if (parent.hasAttribute('data-component-type') && parent.getAttribute('data-component-type') === 'post-loop') {
        return true;
      }
      parent = parent.parentElement;
    }
    return false;
  }

  function positionOverlay(overlay, el) {
    var rect = el.getBoundingClientRect();
    overlay.style.top = rect.top + 'px';
    overlay.style.left = rect.left + 'px';
    overlay.style.width = rect.width + 'px';
    overlay.style.height = rect.height + 'px';
    overlay.style.display = 'block';
  }

  function hideOverlay(overlay) {
    overlay.style.display = 'none';
  }

  // ─── PostMessage ─────────────────────────────────────────────────

  function sendToParent(type, payload) {
    if (!window.parent || window.parent === window) return;
    window.parent.postMessage({ type: type, payload: payload || {} }, '*');
  }

  // ─── Hover ───────────────────────────────────────────────────────

  var hoverRaf = null;

  document.addEventListener('mouseover', function (e) {
    if (state.editingId) return;
    if (hoverRaf) return;
    hoverRaf = requestAnimationFrame(function () {
      hoverRaf = null;
      var compEl = getComponentEl(e.target);
      if (!compEl) {
        if (state.hoveredId) {
          state.hoveredId = null;
          hideOverlay(hoverOverlay);
          sendToParent('tekton:componentLeave');
        }
        return;
      }
      var id = compEl.id;
      if (id === state.hoveredId) return;
      if (id === state.selectedId) {
        hideOverlay(hoverOverlay);
        state.hoveredId = null;
        return;
      }
      state.hoveredId = id;
      positionOverlay(hoverOverlay, compEl);
      sendToParent('tekton:componentHover', {
        componentId: id,
        componentType: compEl.getAttribute('data-component-type'),
      });
    });
  });

  document.addEventListener('mouseout', function (e) {
    if (!e.relatedTarget || e.relatedTarget === document.documentElement) {
      state.hoveredId = null;
      hideOverlay(hoverOverlay);
      sendToParent('tekton:componentLeave');
    }
  });

  // ─── Selection ───────────────────────────────────────────────────

  document.addEventListener('click', function (e) {
    // Intercept links and buttons
    var link = e.target.closest('a, button');
    if (link) e.preventDefault();

    if (state.editingId) return;

    var compEl = getComponentEl(e.target);
    if (!compEl) {
      deselectCurrent();
      sendToParent('tekton:componentClick', { componentId: null, componentType: null });
      return;
    }

    selectElement(compEl);
    sendToParent('tekton:componentClick', {
      componentId: compEl.id,
      componentType: compEl.getAttribute('data-component-type'),
    });
  });

  function selectElement(compEl) {
    state.selectedId = compEl.id;
    positionOverlay(selectOverlay, compEl);
    hideOverlay(hoverOverlay);

    // Position label
    var rect = compEl.getBoundingClientRect();
    var type = compEl.getAttribute('data-component-type') || '';
    selectLabel.textContent = type;
    selectLabel.style.top = Math.max(0, rect.top) + 'px';
    selectLabel.style.left = rect.left + 'px';
    selectLabel.style.display = 'block';
  }

  function deselectCurrent() {
    state.selectedId = null;
    hideOverlay(selectOverlay);
    selectLabel.style.display = 'none';
    sourceBadge.style.display = 'none';
    stopEditing();
  }

  // ─── Inline Editing ──────────────────────────────────────────────

  document.addEventListener('dblclick', function (e) {
    var target = e.target;
    // Walk up to find an editable element
    while (target && target !== document.body) {
      if (target.hasAttribute && target.getAttribute('data-tekton-editable') === 'true') break;
      target = target.parentElement;
    }
    if (!target || target === document.body) return;

    // Don't allow inline editing inside post loops
    if (isInsidePostLoop(target)) return;

    var compEl = getComponentEl(target);
    if (!compEl) return;

    e.preventDefault();
    startEditing(target, compEl);
  });

  function startEditing(editableEl, compEl) {
    if (state.editingId) stopEditing();

    state.editingId = compEl.id;
    editableEl.setAttribute('contenteditable', 'true');
    editableEl.focus();

    // Select all text
    var selection = window.getSelection();
    var range = document.createRange();
    range.selectNodeContents(editableEl);
    selection.removeAllRanges();
    selection.addRange(range);

    // Show content source badge if applicable
    var sourceData = editableEl.getAttribute('data-tekton-source');
    if (sourceData) {
      try {
        var source = JSON.parse(sourceData);
        var label = 'Dynamic: ' + source.source;
        if (source.group) label += '.' + source.group;
        if (source.field) label += '.' + source.field;
        sourceBadge.textContent = label + ' (editing fallback)';
        var rect = editableEl.getBoundingClientRect();
        sourceBadge.style.top = (rect.bottom + 4) + 'px';
        sourceBadge.style.left = rect.left + 'px';
        sourceBadge.style.display = 'block';
      } catch (_) { /* ignore parse errors */ }
    }

    editableEl.style.outline = '2px solid rgba(184, 112, 47, 0.5)';
    editableEl.style.outlineOffset = '2px';
    editableEl.style.borderRadius = '2px';

    // Store reference for cleanup
    editableEl._tektonEditing = true;

    sendToParent('tekton:editStart', { componentId: compEl.id });

    editableEl.addEventListener('blur', handleEditBlur);
    editableEl.addEventListener('keydown', handleEditKeydown);
    editableEl.addEventListener('input', handleEditInput);
  }

  function stopEditing() {
    if (!state.editingId) return;

    var editableEl = document.querySelector('[contenteditable="true"]._tektonEditing, [contenteditable="true"]');
    if (!editableEl) {
      // Fallback: find any contenteditable we set
      var allEditable = document.querySelectorAll('[contenteditable="true"]');
      for (var i = 0; i < allEditable.length; i++) {
        if (allEditable[i]._tektonEditing) {
          editableEl = allEditable[i];
          break;
        }
      }
    }

    if (editableEl) {
      editableEl.removeAttribute('contenteditable');
      editableEl.style.outline = '';
      editableEl.style.outlineOffset = '';
      editableEl._tektonEditing = false;
      editableEl.removeEventListener('blur', handleEditBlur);
      editableEl.removeEventListener('keydown', handleEditKeydown);
      editableEl.removeEventListener('input', handleEditInput);
    }

    sourceBadge.style.display = 'none';

    sendToParent('tekton:editEnd', { componentId: state.editingId });
    state.editingId = null;
  }

  function handleEditBlur() {
    // Delay to allow click events to fire first
    setTimeout(function () {
      if (state.editingId) {
        emitContentEdit(this);
        stopEditing();
      }
    }.bind(this), 100);
  }

  function handleEditKeydown(e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      emitContentEdit(this);
      stopEditing();
    }
    // Enter finishes editing for headings, Shift+Enter for new line in text
    if (e.key === 'Enter' && !e.shiftKey) {
      var compEl = getComponentEl(this);
      var type = compEl ? compEl.getAttribute('data-component-type') : '';
      if (type === 'heading') {
        e.preventDefault();
        emitContentEdit(this);
        stopEditing();
      }
    }
  }

  var inputDebounce = null;
  function handleEditInput() {
    var el = this;
    clearTimeout(inputDebounce);
    inputDebounce = setTimeout(function () {
      emitContentEdit(el);
    }, 300);
  }

  function emitContentEdit(editableEl) {
    var compEl = getComponentEl(editableEl);
    if (!compEl) return;
    var prop = editableEl.getAttribute('data-tekton-prop') || 'content';
    var isContentSource = editableEl.hasAttribute('data-tekton-source');
    sendToParent('tekton:contentEdit', {
      componentId: compEl.id,
      prop: prop,
      value: editableEl.textContent || '',
      isContentSource: isContentSource,
    });
  }

  // ─── Incoming Messages from Parent ───────────────────────────────

  window.addEventListener('message', function (e) {
    var data = e.data;
    if (!data || typeof data.type !== 'string' || !data.type.startsWith('tekton:')) return;

    var type = data.type;
    var payload = data.payload || {};

    switch (type) {
      case 'tekton:select':
        handleSelect(payload.componentId);
        break;
      case 'tekton:deselect':
        deselectCurrent();
        break;
      case 'tekton:hover':
        handleParentHover(payload.componentId);
        break;
      case 'tekton:unhover':
        hideOverlay(hoverOverlay);
        state.hoveredId = null;
        break;
      case 'tekton:updateStyle':
        handleStyleUpdate(payload);
        break;
      case 'tekton:updateContent':
        handleContentUpdate(payload);
        break;
      case 'tekton:scrollTo':
        handleScrollTo(payload.componentId);
        break;
    }
  });

  function handleSelect(componentId) {
    if (!componentId) {
      deselectCurrent();
      return;
    }
    var el = document.getElementById(componentId);
    if (!el) return;
    selectElement(el);
  }

  function handleParentHover(componentId) {
    if (!componentId || componentId === state.selectedId) return;
    var el = document.getElementById(componentId);
    if (!el) return;
    state.hoveredId = componentId;
    positionOverlay(hoverOverlay, el);
  }

  function handleStyleUpdate(payload) {
    var el = document.getElementById(payload.componentId);
    if (!el) return;
    var prop = payload.property;
    var value = payload.value;
    // Convert camelCase to kebab for CSS
    var cssProp = prop.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
    el.style.setProperty(cssProp, value);
    // Reposition overlays if the selected element changed size
    if (payload.componentId === state.selectedId) {
      requestAnimationFrame(function () {
        positionOverlay(selectOverlay, el);
        var rect = el.getBoundingClientRect();
        selectLabel.style.top = Math.max(0, rect.top) + 'px';
        selectLabel.style.left = rect.left + 'px';
      });
    }
  }

  function handleContentUpdate(payload) {
    var el = document.getElementById(payload.componentId);
    if (!el) return;
    // Find the editable child or use element directly
    var editable = el.querySelector('[data-tekton-editable="true"]') || el;
    if (state.editingId !== payload.componentId) {
      editable.textContent = payload.content;
    }
  }

  function handleScrollTo(componentId) {
    var el = document.getElementById(componentId);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  // ─── Resize Observer ─────────────────────────────────────────────

  // Reposition overlays on scroll/resize
  var repositionRaf = null;
  function repositionOverlays() {
    if (repositionRaf) return;
    repositionRaf = requestAnimationFrame(function () {
      repositionRaf = null;
      if (state.selectedId) {
        var el = document.getElementById(state.selectedId);
        if (el) {
          positionOverlay(selectOverlay, el);
          var rect = el.getBoundingClientRect();
          selectLabel.style.top = Math.max(0, rect.top) + 'px';
          selectLabel.style.left = rect.left + 'px';
        }
      }
      if (state.hoveredId) {
        var hovEl = document.getElementById(state.hoveredId);
        if (hovEl) positionOverlay(hoverOverlay, hovEl);
      }
    });
  }

  window.addEventListener('scroll', repositionOverlays, { passive: true });
  window.addEventListener('resize', repositionOverlays, { passive: true });

  // ─── Init ────────────────────────────────────────────────────────

  sendToParent('tekton:ready');
})();
