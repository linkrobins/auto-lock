<?php

namespace LinkRobins\AutoLock;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Illuminate\Database\Eloquent\Builder;
use Psr\Log\LoggerInterface;

/**
 * Decides which discussions have gone quiet, and locks them.
 *
 * Shared by the scheduled command and by the forum countdown, so the date a
 * member is shown is produced by the same code that will actually do the
 * locking. Two implementations would drift, and the countdown would start
 * lying.
 */
final class AutoLocker
{
    public function __construct(
        protected Settings $settings,
        protected DiscussionTags $tags,
        protected LoggerInterface $log,
    ) {
    }

    /**
     * Is the feature usable at all?
     *
     * `is_locked` belongs to flarum/lock. With that extension disabled the
     * column does not exist, so this extension has nothing to write to and
     * quietly does nothing rather than erroring on every run.
     */
    public function available(): bool
    {
        static $available = null;

        if ($available === null) {
            try {
                $available = (new Discussion())->getConnection()
                    ->getSchemaBuilder()
                    ->hasColumn('discussions', 'is_locked');
            } catch (\Throwable $e) {
                $available = false;
            }
        }

        return $available;
    }

    /** The cutoff: anything last posted to before this is stale. */
    public function cutoff(?Carbon $now = null): Carbon
    {
        return ($now ?? Carbon::now())->copy()->subDays($this->settings->days());
    }

    /**
     * When this discussion is due to be locked, or null when it never will be.
     *
     * Null covers every reason: the feature is off, flarum/lock is not
     * installed, the discussion is already locked, it carries an exempt tag,
     * or it has no activity date to count from.
     */
    public function lockAtFor(Discussion $discussion): ?Carbon
    {
        if (! $this->settings->enabled() || ! $this->available()) {
            return null;
        }

        // getAttribute rather than the magic property: is_locked is not
        // declared on Discussion, it is added by flarum/lock, which this
        // package deliberately does not depend on.
        if ($discussion->getAttribute('is_locked')) {
            return null;
        }

        $last = $discussion->last_posted_at ?? $discussion->created_at;

        if (! $last instanceof Carbon) {
            return null;
        }

        if ($this->isExempt($discussion)) {
            return null;
        }

        return $last->copy()->addDays($this->settings->days());
    }

    /** Does this discussion carry a tag that is exempt from locking? */
    public function isExempt(Discussion $discussion): bool
    {
        $exempt = $this->settings->exemptTagIds();

        if (! $exempt) {
            return false;
        }

        return (bool) array_intersect($exempt, $this->tags->idsFor($discussion));
    }

    /**
     * Every discussion currently due to be locked.
     *
     * Tag exemption is applied in PHP rather than SQL so this keeps working
     * when flarum/tags is not installed, which is the same reason
     * DiscussionTags reads the pivot table directly.
     *
     * @return \Illuminate\Support\LazyCollection<int, Discussion>
     */
    public function due(?Carbon $now = null): \Illuminate\Support\LazyCollection
    {
        $cutoff = $this->cutoff($now);

        return Discussion::query()
            ->where('is_locked', false)
            ->whereNull('hidden_at')
            ->where(function (Builder $query) use ($cutoff) {
                $query->where('last_posted_at', '<', $cutoff)
                    ->orWhere(function (Builder $q) use ($cutoff) {
                        // A discussion with no replies has no last_posted_at on
                        // some installs, so fall back to when it was started.
                        $q->whereNull('last_posted_at')->where('created_at', '<', $cutoff);
                    });
            })
            ->orderBy('id')
            ->cursor()
            ->reject(fn (Discussion $discussion) => $this->isExempt($discussion));
    }

    /**
     * Lock everything that is due. Returns the number locked.
     *
     * Deliberately does NOT dispatch DiscussionWasLocked. That event exists for
     * a person locking a thread: it writes a "locked this discussion" post into
     * the stream attributed to them, and notifies the author. Nobody performed
     * this action, so attributing it to an admin would be a small lie, and the
     * first run over an old forum would post into and notify every stale
     * discussion at once. The lock state is what members actually experience.
     */
    public function run(bool $dryRun = false, ?Carbon $now = null): int
    {
        if (! $this->settings->enabled() || ! $this->available()) {
            return 0;
        }

        $locked = 0;

        foreach ($this->due($now) as $discussion) {
            $locked++;

            if ($dryRun) {
                continue;
            }

            try {
                $discussion->setAttribute('is_locked', true);
                $discussion->save();
            } catch (\Throwable $e) {
                $locked--;
                $this->log->error(
                    '[linkrobins/auto-lock] could not lock discussion '.$discussion->id.': '.$e->getMessage()
                );
            }
        }

        return $locked;
    }
}
