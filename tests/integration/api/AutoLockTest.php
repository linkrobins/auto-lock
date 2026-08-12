<?php

/*
 * This file is part of linkrobins/auto-lock.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace LinkRobins\AutoLock\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use LinkRobins\AutoLock\AutoLocker;
use PHPUnit\Framework\Attributes\Test;

class AutoLockTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('linkrobins-auto-lock', 'flarum-lock', 'flarum-tags');

        $stale = Carbon::now()->subDays(90)->toDateTimeString();
        $fresh = Carbon::now()->subDay()->toDateTimeString();

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(), // id 2
            ],
            'tags' => [
                ['id' => 1, 'name' => 'General', 'slug' => 'general', 'position' => 0],
                ['id' => 2, 'name' => 'Extensions', 'slug' => 'extensions', 'position' => 1],
            ],
            'discussions' => [
                // 1: stale, no exempt tag -> should lock
                ['id' => 1, 'title' => 'Stale', 'created_at' => $stale, 'last_posted_at' => $stale, 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'slug' => 'stale', 'is_private' => 0, 'is_locked' => 0],
                // 2: recent -> must stay open
                ['id' => 2, 'title' => 'Fresh', 'created_at' => $fresh, 'last_posted_at' => $fresh, 'user_id' => 1, 'first_post_id' => 2, 'comment_count' => 1, 'slug' => 'fresh', 'is_private' => 0, 'is_locked' => 0],
                // 3: stale but tagged Extensions, which is exempt
                ['id' => 3, 'title' => 'Stale exempt', 'created_at' => $stale, 'last_posted_at' => $stale, 'user_id' => 1, 'first_post_id' => 3, 'comment_count' => 1, 'slug' => 'stale-exempt', 'is_private' => 0, 'is_locked' => 0],
                // 4: stale and already locked -> must not be counted again
                ['id' => 4, 'title' => 'Already locked', 'created_at' => $stale, 'last_posted_at' => $stale, 'user_id' => 1, 'first_post_id' => 4, 'comment_count' => 1, 'slug' => 'already-locked', 'is_private' => 0, 'is_locked' => 1],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 3, 'tag_id' => 2],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => $stale, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>one</p></t>', 'is_private' => 0],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'created_at' => $fresh, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>two</p></t>', 'is_private' => 0],
                ['id' => 3, 'discussion_id' => 3, 'number' => 1, 'created_at' => $stale, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>three</p></t>', 'is_private' => 0],
                ['id' => 4, 'discussion_id' => 4, 'number' => 1, 'created_at' => $stale, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>four</p></t>', 'is_private' => 0],
            ],
        ]);

        // Settings go through the helper, not prepareDatabase: the settings
        // repository is memory-cached at boot, before database seeding runs.
        $this->setting('linkrobins-auto-lock.enabled', '1');
        $this->setting('linkrobins-auto-lock.days', '30');
        $this->setting('linkrobins-auto-lock.exempt_tags', '2');
    }

    private function locker(): AutoLocker
    {
        return $this->app()->getContainer()->make(AutoLocker::class);
    }

    #[Test]
    public function it_locks_only_the_stale_unexempt_discussion(): void
    {
        $this->assertSame(1, $this->locker()->run());

        $this->assertTrue((bool) Discussion::find(1)->getAttribute('is_locked'), 'the stale discussion should be locked');
        $this->assertFalse((bool) Discussion::find(2)->getAttribute('is_locked'), 'a recent discussion must stay open');
        $this->assertFalse((bool) Discussion::find(3)->getAttribute('is_locked'), 'an exempt tag must keep it open');
    }

    #[Test]
    public function a_dry_run_changes_nothing(): void
    {
        $this->assertSame(1, $this->locker()->run(true));

        $this->assertFalse((bool) Discussion::find(1)->getAttribute('is_locked'), 'a dry run must not lock anything');
    }

    #[Test]
    public function it_does_nothing_while_switched_off(): void
    {
        $this->setting('linkrobins-auto-lock.enabled', '0');

        $this->assertSame(0, $this->locker()->run());
        $this->assertFalse((bool) Discussion::find(1)->getAttribute('is_locked'));
    }

    #[Test]
    public function running_twice_locks_nothing_the_second_time(): void
    {
        $this->assertSame(1, $this->locker()->run());
        $this->assertSame(0, $this->locker()->run(), 'an already locked discussion must not be picked up again');
    }

    #[Test]
    public function the_lock_date_is_serialized_for_the_countdown(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/discussions/2', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $lockAt = $body['data']['attributes']['linkrobinsAutoLockAt'] ?? null;

        $this->assertNotNull($lockAt, 'an open discussion should carry its lock date');
        $this->assertSame(
            Carbon::now()->subDay()->addDays(30)->toDateString(),
            Carbon::parse($lockAt)->toDateString(),
            'the date shown must be last activity plus the configured days'
        );
    }

    #[Test]
    public function an_exempt_discussion_carries_no_lock_date(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/discussions/3', ['authenticatedAs' => 2])
        );

        $body = json_decode($response->getBody()->getContents(), true);

        $this->assertNull(
            $body['data']['attributes']['linkrobinsAutoLockAt'] ?? null,
            'an exempt discussion must not advertise a countdown it will never reach'
        );
    }
}
