/**
 * Pure utility functions for navigating and mutating the component JSON tree.
 * All mutation functions return new objects (immutable) for Svelte 5 reactivity.
 */

/**
 * Find a component by ID in a nested tree.
 * @param {Array} components
 * @param {string} id
 * @returns {object|null}
 */
export function findById(components, id) {
  for (const comp of components) {
    if (comp.id === id) return comp;
    if (comp.children?.length) {
      const found = findById(comp.children, id);
      if (found) return found;
    }
  }
  return null;
}

/**
 * Find the parent of a component by ID.
 * @param {Array} components
 * @param {string} id
 * @returns {{ parent: object|null, index: number }|null}
 */
export function findParentOf(components, id) {
  for (let i = 0; i < components.length; i++) {
    if (components[i].id === id) {
      return { parent: null, index: i };
    }
    if (components[i].children?.length) {
      for (let j = 0; j < components[i].children.length; j++) {
        if (components[i].children[j].id === id) {
          return { parent: components[i], index: j };
        }
      }
      const found = findParentOf(components[i].children, id);
      if (found) return found;
    }
  }
  return null;
}

/**
 * Return a new tree with the component at `id` replaced by `updater(component)`.
 * @param {Array} components
 * @param {string} id
 * @param {function} updater - receives component, returns new component
 * @returns {Array}
 */
export function updateById(components, id, updater) {
  return components.map(comp => {
    if (comp.id === id) return updater(comp);
    if (comp.children?.length) {
      const newChildren = updateById(comp.children, id, updater);
      if (newChildren !== comp.children) {
        return { ...comp, children: newChildren };
      }
    }
    return comp;
  });
}

/**
 * Merge styles at a specific breakpoint for a component.
 * @param {Array} components
 * @param {string} id
 * @param {string} breakpoint - 'desktop' | 'tablet' | 'mobile'
 * @param {object} styles - CSS properties to merge
 * @returns {Array}
 */
export function updateStylesById(components, id, breakpoint, styles) {
  return updateById(components, id, comp => ({
    ...comp,
    styles: {
      ...comp.styles,
      [breakpoint]: { ...(comp.styles?.[breakpoint] || {}), ...styles },
    },
  }));
}

/**
 * Merge props for a component.
 * @param {Array} components
 * @param {string} id
 * @param {object} props
 * @returns {Array}
 */
export function updatePropsById(components, id, props) {
  return updateById(components, id, comp => ({
    ...comp,
    props: { ...comp.props, ...props },
  }));
}

/**
 * Update a content prop, handling content source objects vs static strings.
 * If the current value is a content source, updates the fallback.
 * If it's a static string, replaces it.
 * @param {Array} components
 * @param {string} id
 * @param {string} prop - prop name (e.g. 'content', 'text')
 * @param {string} value - new text value
 * @returns {Array}
 */
export function replaceContentById(components, id, prop, value) {
  return updateById(components, id, comp => {
    const current = comp.props?.[prop];
    if (current && typeof current === 'object' && current.source) {
      return {
        ...comp,
        props: {
          ...comp.props,
          [prop]: { ...current, fallback: value },
        },
      };
    }
    return {
      ...comp,
      props: { ...comp.props, [prop]: value },
    };
  });
}

/**
 * Get the path of ancestor IDs from root to the component.
 * @param {Array} components
 * @param {string} id
 * @returns {string[]|null}
 */
export function getComponentPath(components, id) {
  for (const comp of components) {
    if (comp.id === id) return [id];
    if (comp.children?.length) {
      const path = getComponentPath(comp.children, id);
      if (path) return [comp.id, ...path];
    }
  }
  return null;
}

/**
 * Flatten a component tree into a list with depth info (for tree view).
 * @param {Array} components
 * @param {number} depth
 * @returns {Array<{ id: string, type: string, label: string, depth: number }>}
 */
export function flattenTree(components, depth = 0) {
  const result = [];
  for (const comp of components) {
    const label = getComponentLabel(comp);
    result.push({ id: comp.id, type: comp.type, label, depth });
    if (comp.children?.length) {
      result.push(...flattenTree(comp.children, depth + 1));
    }
  }
  return result;
}

/**
 * Derive a human-readable label from a component.
 */
function getComponentLabel(comp) {
  const content = comp.props?.content;
  if (content) {
    const text = typeof content === 'object' ? (content.fallback || content.field || '') : content;
    if (text) return text.length > 30 ? text.slice(0, 30) + '...' : text;
  }
  const text = comp.props?.text;
  if (text) {
    const str = typeof text === 'object' ? (text.fallback || '') : text;
    if (str) return str.length > 30 ? str.slice(0, 30) + '...' : str;
  }
  return comp.type;
}
