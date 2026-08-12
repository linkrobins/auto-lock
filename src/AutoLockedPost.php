<?php

namespace LinkRobins\AutoLock;

use Carbon\Carbon;
use Flarum\Post\AbstractEventPost;

/**
 * The "locked automatically" line in the post stream.
 *
 * A first-class event post rather than flarum/lock's own DiscussionLockedPost,
 * for two reasons. That one renders as "<someone> locked this discussion", and
 * nobody did: attributing an unattended sweep to a person is a small lie that
 * a moderator will act on. And it carries no reason, where the whole point of
 * this line is to say why the discussion closed.
 *
 * `user_id` is deliberately left null. The frontend component replaces the
 * avatar with an icon and never renders a username, so there is no actor to
 * misattribute.
 */
final class AutoLockedPost extends AbstractEventPost
{
    public static string $type = 'linkrobinsAutoLocked';

    /**
     * The days threshold is stored ON the post, not read from settings at
     * render time. An admin who later changes 30 to 90 must not silently
     * rewrite the stated reason on every discussion already locked under the
     * old rule.
     */
    public static function reply(int $discussionId, int $days): static
    {
        $post = new static();

        $post->content = ['days' => $days];
        $post->created_at = Carbon::now();
        $post->discussion_id = $discussionId;
        $post->user_id = null;

        return $post;
    }
}
