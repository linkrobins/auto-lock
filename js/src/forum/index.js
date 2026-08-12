import app from 'flarum/forum/app';
import { override } from 'flarum/common/extend';
import AutoLockedPost from './AutoLockedPost';

// The global Flarum exposes, not an import: flarum-webpack-config does not
// externalize mithril, so importing it would bundle a second copy.
const m = window.m;

/**
 * How many whole days from now until `date`, never negative.
 *
 * Counted in whole days rather than hours because the copy talks in days. A
 * discussion 20 hours from locking reads as "locks today", which is true and
 * more useful than "locks in 0.83 days".
 */
function daysUntil(date) {
  const ms = date.getTime() - Date.now();

  return ms <= 0 ? 0 : Math.floor(ms / 86400000);
}

function countdownText(lockAt) {
  const days = daysUntil(lockAt);

  if (days === 0) return app.translator.trans('linkrobins-auto-lock.forum.countdown.today');
  if (days === 1) return app.translator.trans('linkrobins-auto-lock.forum.countdown.day');

  return app.translator.trans('linkrobins-auto-lock.forum.countdown.days', { count: days });
}

app.initializers.add('linkrobins/auto-lock', () => {
  // What Extend.PostTypes().add() does under the hood. Registered directly so
  // the stream can render the post type even on an install whose extenders
  // array is not read.
  app.postComponents['linkrobinsAutoLocked'] = AutoLockedPost;

  // override, NOT extend. extend() hands the callback the return value and
  // discards whatever the callback returns, so it can only mutate in place;
  // returning a new vnode from it is silently ignored and nothing renders.
  //
  // String path form so this applies whether ReplyPlaceholder sits in an eager
  // or a lazy loaded chunk, and `import type` is avoided entirely since the
  // component is never referenced as a value.
  override('flarum/forum/components/ReplyPlaceholder', 'view', function (original, ...args) {
    const vnode = original(...args);

    if (!app.forum.attribute('linkrobinsAutoLockEnabled')) return vnode;
    if (!app.forum.attribute('linkrobinsAutoLockShowCountdown')) return vnode;

    const discussion = this.attrs && this.attrs.discussion;
    if (!discussion) return vnode;

    // Serialized server side, and already null when the discussion is exempt,
    // already locked, or the feature is off. So there is no policy left to
    // duplicate here: a date means a countdown, no date means nothing.
    const raw = discussion.attribute('linkrobinsAutoLockAt');
    if (!raw) return vnode;

    const lockAt = new Date(raw);
    if (isNaN(lockAt.getTime())) return vnode;

    const notice = m(
      'div',
      {
        className: 'LinkRobinsAutoLock-notice',
        title: app.translator.trans('linkrobins-auto-lock.forum.countdown.title', {
          days: app.forum.attribute('linkrobinsAutoLockDays'),
        }),
      },
      [m('i', { className: 'icon fas fa-hourglass-end', 'aria-hidden': 'true' }), ' ', countdownText(lockAt)]
    );

    // Prepend rather than replace: the placeholder's own markup is whatever
    // core says it is, and this only adds a line above it.
    return [notice, vnode];
  });
});
