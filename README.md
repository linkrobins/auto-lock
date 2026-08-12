# Link Robins Auto Lock

Locks discussions that have gone quiet, so old threads stop collecting replies
nobody is waiting for. Runs on Flarum's own scheduler, with tags you can exempt
and a countdown that tells members a discussion is about to close while they can
still reply to it.

Flarum 2.x only.

## What it does

Once a day is too coarse for a countdown, so the job runs hourly. Every run it
looks for discussions whose most recent post is older than the number of days
you set, and locks them. A discussion is skipped when it carries one of your
exempt tags, when it is already locked, or when it is hidden.

While a discussion is still open, members see a line above the reply box:

> This discussion locks in 12 days if nobody replies.

Replying resets the clock, because the countdown is measured from the most
recent post. That is the point of showing it: someone who still cares can keep
the thread alive, rather than finding out it closed after the fact.

## What it does NOT do

- **It does not post "locked this discussion" into the thread, and nobody is
  notified.** That event belongs to a person locking a thread by hand. Nobody
  performed this action, so attributing it to an admin would be a small lie, and
  the first run over an established forum would post into and notify every stale
  discussion at once. Members simply find the thread closed, which is what
  locking means.
- It never unlocks anything. Nothing here reverses a lock, whether this
  extension applied it or you did.
- It does not delete, archive, or hide discussions.
- It does not touch discussions that are already locked, so a thread you locked
  by hand for your own reasons stays exactly as you left it.

## Settings

Admin, then Extensions, then Link Robins Auto Lock.

| Setting | Default | What it does |
|---|---|---|
| Lock quiet discussions automatically | **off** | The master switch. Nothing is locked while this is off. |
| Days of silence before locking | 30 | Counted from the most recent post. Minimum 1. |
| Tags that are never locked | none | Discussions with any of these tags stay open however quiet they get. |
| Show a countdown above the reply box | on | Turn off to lock silently. |
| Leave a note in the discussion when it locks | on | Adds one line to the post stream saying the discussion was locked automatically, and after how long. |

It ships switched off. Enabling an extension should not silently start locking a
forum's back catalogue, so it waits to be asked.

The note left in a locked discussion records the threshold that was in force at
the time, so changing 30 days to 90 later does not rewrite the stated reason on
discussions already locked under the old rule. The note is an event post, the
same kind of row as a rename or a tag change, so it does not bump the discussion
back to the top of the list and does not notify anyone.

## Requirements

- Flarum **2.0** or later
- PHP **8.3+**
- **flarum/lock** enabled. That extension owns the lock state; without it there
  is nothing to write to, and this one reports that and does nothing.
- **A working scheduler.** This runs as a scheduled job, so it needs the usual
  Flarum cron entry:

      * * * * * cd /path/to/flarum && php flarum schedule:run >> /dev/null 2>&1

  Without it the extension is inert. That is the single most likely reason for
  "I turned it on and nothing happened".

`flarum/tags` is optional. Without it there are simply no tags to exempt.

## Trying it before you trust it

The command is safe to run by hand, and `--dry-run` reports what it would lock
without touching anything:

    php flarum linkrobins:auto-lock --dry-run

Worth doing once on an established forum, where the first real run can lock a
lot of threads at once.

## Before you turn it on

Locking is not free. A support thread earns its value from the replies it
collects over time, and search engines send people to old threads for exactly
that reason. Locking one means the next person with the same question starts a
new thread instead of adding to the answer.

On a young or small forum, necroposting is usually not yet a problem worth
solving this way. It is worth having when threads are old enough that reviving
them does more harm than good.
