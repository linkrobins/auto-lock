import app from 'flarum/forum/app';
import EventPost from 'flarum/forum/components/EventPost';

/**
 * The "locked automatically" line in the post stream.
 *
 * Extends core's EventPost so it inherits the native look: the avatar slot
 * becomes an icon, and the row sits in the stream like a rename or a tag
 * change. The description deliberately never renders `data.username`, because
 * this post has no actor. EventPost would otherwise fall back to the deleted
 * user placeholder and invent a moderator who did nothing.
 */
export default class AutoLockedPost extends EventPost {
  icon() {
    return 'fas fa-hourglass-end';
  }

  description(data) {
    // The threshold is read off the post, not the current setting, so an admin
    // who later changes the number does not rewrite history on old locks.
    const content = this.attrs.post.content();
    const days = (content && content.days) || app.forum.attribute('linkrobinsAutoLockDays');

    return app.translator.trans('linkrobins-auto-lock.forum.post.locked', { ...data, count: days });
  }
}
