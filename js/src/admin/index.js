import app from 'flarum/admin/app';

// The global Flarum exposes, not an import: flarum-webpack-config does not
// externalize mithril, so importing it would bundle a second copy.
const m = window.m;

const EXT_ID = 'linkrobins-auto-lock';
const PREFIX = EXT_ID + '.';

const trans = (key, args) => app.translator.trans(PREFIX + 'admin.settings.' + key, args);

// Loaded once, lazily, the first time the tag picker draws. A forum without
// flarum/tags (or an admin without permission to list them) simply gets no tag
// section rather than an error.
const tags = { status: 'idle', list: [] };

function loadTags() {
  if (tags.status !== 'idle') return;
  tags.status = 'loading';

  app
    .request({ method: 'GET', url: app.forum.attribute('apiUrl') + '/tags' })
    .then((doc) => {
      tags.list = ((doc && doc.data) || []).map((tag) => ({
        id: Number(tag.id),
        name: (tag.attributes && tag.attributes.name) || '#' + tag.id,
      }));
      tags.status = 'ready';
      m.redraw();
    })
    .catch(() => {
      tags.status = 'unavailable';
      m.redraw();
    });
}

/** The comma separated setting, as a list of numeric ids. */
function selectedIds(page) {
  const raw = String(page.setting(PREFIX + 'exempt_tags')() || '');

  return raw
    .split(',')
    .map((part) => Number(String(part).trim()))
    .filter((id) => Number.isFinite(id) && id > 0);
}

function toggleTag(page, id, on) {
  const next = selectedIds(page).filter((existing) => existing !== id);
  if (on) next.push(id);

  // Sorted so the stored value is stable, and the Save button does not light
  // up just because two admins ticked the same boxes in a different order.
  next.sort((a, b) => a - b);
  page.setting(PREFIX + 'exempt_tags')(next.join(','));
}

function tagPicker() {
  loadTags();

  if (tags.status === 'loading') {
    return m('div', { className: 'helpText' }, trans('exempt_tags_loading'));
  }

  // No tags extension, or no tags yet: nothing to exempt, so say nothing.
  if (tags.status !== 'ready' || !tags.list.length) return null;

  const page = this;
  const selected = selectedIds(page);

  return m('div', { className: 'Form-group LinkRobinsAutoLock-tags' }, [
    m('label', trans('exempt_tags_label')),
    m('div', { className: 'helpText' }, trans('exempt_tags_help')),
    m(
      'div',
      { className: 'LinkRobinsAutoLock-tagList' },
      tags.list.map((tag) =>
        m('label', { className: 'checkbox LinkRobinsAutoLock-tag' }, [
          m('input', {
            type: 'checkbox',
            checked: selected.indexOf(tag.id) !== -1,
            onchange: (event) => toggleTag(page, tag.id, event.target.checked),
          }),
          ' ',
          tag.name,
        ])
      )
    ),
  ]);
}

app.initializers.add('linkrobins/auto-lock', () => {
  const registry = app.registry.for(EXT_ID);

  registry.registerSetting({
    setting: PREFIX + 'enabled',
    type: 'boolean',
    label: trans('enabled_label'),
    help: trans('enabled_help'),
  });

  registry.registerSetting({
    setting: PREFIX + 'days',
    type: 'number',
    min: 1,
    label: trans('days_label'),
    help: trans('days_help'),
  });

  registry.registerSetting({
    setting: PREFIX + 'show_countdown',
    type: 'boolean',
    label: trans('show_countdown_label'),
    help: trans('show_countdown_help'),
  });

  registry.registerSetting({
    setting: PREFIX + 'post_notice',
    type: 'boolean',
    label: trans('post_notice_label'),
    help: trans('post_notice_help'),
  });

  // A function entry is invoked with `this` = the settings page, whose
  // setting() streams feed the page's own Save button. That is how the tag
  // picker participates in saving without managing its own state.
  registry.registerSetting(tagPicker);

  // The extension does nothing at all unless the host runs the scheduler, so
  // say so on the page rather than leaving an admin to wonder why a correctly
  // configured extension never locks anything.
  // Plain text, not m.trust: the string carries no markup, because Flarum's
  // translator parses angle brackets in locale values as rich-text tags and an
  // unknown one throws, which renders the raw key and can blank this page.
  registry.registerSetting(() => m('div', { className: 'Form-group helpText LinkRobinsAutoLock-notice' }, trans('scheduler_notice')));
});
