import { isContainerType } from './componentTree.js';

function randomId() {
  const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
  let id = '';
  for (let i = 0; i < 8; i++) {
    id += chars[Math.floor(Math.random() * chars.length)];
  }
  return 'comp_' + id;
}

const DEFAULT_PROPS = {
  section: {},
  container: { maxWidth: '1200px' },
  div: {},
  heading: { level: 2, content: { source: 'static', value: 'Heading' } },
  text: { content: { source: 'static', value: 'Text content' } },
  image: { src: '', alt: '' },
  button: { text: { source: 'static', value: 'Button' }, href: '' },
  grid: { columns: 3, gap: '20px' },
  'flex-row': { gap: '16px', alignItems: 'center' },
  'flex-column': { gap: '16px' },
  link: { text: { source: 'static', value: 'Link' }, href: '#' },
  list: { ordered: false, items: [] },
  spacer: { height: '40px' },
  divider: { color: 'var(--tekton-border)', thickness: '1px' },
  video: { src: '', type: 'embed' },
  icon: { name: 'star', size: '24px' },
  'post-loop': { query: { post_type: 'post', posts_per_page: 6 } },
  'post-title': { tagName: 'h2', link: true },
  'post-content': {},
  'post-meta': { showDate: true, showAuthor: true, showCategories: false },
  'featured-image': { size: 'medium', link: true },
  menu: { location: 'primary' },
  'tekton-field': { group: '', field: '' },
  'search-form': {},
};

/**
 * Create a new component with a unique ID and default props for its type.
 */
export function createComponent(type) {
  return {
    id: randomId(),
    type,
    props: { ...(DEFAULT_PROPS[type] || {}) },
    styles: { desktop: {}, tablet: {}, mobile: {} },
    children: isContainerType(type) ? [] : undefined,
  };
}
