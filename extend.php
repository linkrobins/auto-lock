<?php

use Flarum\Api\Resource\DiscussionResource;
use Flarum\Extend;
use Illuminate\Console\Scheduling\Event;
use LinkRobins\AutoLock\AddAutoLockAttributes;
use LinkRobins\AutoLock\AutoLockedPost;
use LinkRobins\AutoLock\LockStaleDiscussionsCommand;
use LinkRobins\AutoLock\Settings;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Console())
        ->command(LockStaleDiscussionsCommand::class)
        // Hourly rather than daily so a discussion locks close to the moment
        // the countdown said it would. Daily could leave it open most of an
        // extra day, and a countdown that reaches zero and does nothing is
        // worse than no countdown.
        //
        // This only runs if the host actually runs `php flarum schedule:run`
        // every minute. Without that cron entry the extension does nothing at
        // all, which the README says plainly.
        ->schedule(LockStaleDiscussionsCommand::class, function (Event $event) {
            $event->hourly()->withoutOverlapping();
        }),

    (new Extend\Settings())
        // Off by default. Enabling an extension should not silently start
        // locking a forum's back catalogue; an admin has to ask for it.
        ->default(Settings::ENABLED, false)
        ->default(Settings::DAYS, (string) Settings::DEFAULT_DAYS)
        ->default(Settings::EXEMPT_TAGS, '')
        ->default(Settings::SHOW_COUNTDOWN, true)
        ->default(Settings::POST_NOTICE, true)
        // The forum frontend needs these to decide whether to render the
        // countdown at all. The exempt tag list stays server side: it is
        // already accounted for in the serialized date.
        ->serializeToForum('linkrobinsAutoLockEnabled', Settings::ENABLED, 'boolval')
        ->serializeToForum('linkrobinsAutoLockDays', Settings::DAYS, 'intval')
        ->serializeToForum('linkrobinsAutoLockShowCountdown', Settings::SHOW_COUNTDOWN, 'boolval'),

    (new Extend\ApiResource(DiscussionResource::class))
        ->fields(AddAutoLockAttributes::class),

    // The "locked automatically" line in the post stream.
    (new Extend\Post())
        ->type(AutoLockedPost::class),
];
