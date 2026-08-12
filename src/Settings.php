<?php

namespace LinkRobins\AutoLock;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Typed access to the extension's settings, so the rest of the code never
 * parses raw setting strings.
 */
final class Settings
{
    public const PREFIX = 'linkrobins-auto-lock.';

    public const ENABLED = self::PREFIX.'enabled';
    public const DAYS = self::PREFIX.'days';
    public const EXEMPT_TAGS = self::PREFIX.'exempt_tags';
    public const SHOW_COUNTDOWN = self::PREFIX.'show_countdown';
    public const POST_NOTICE = self::PREFIX.'post_notice';

    public const DEFAULT_DAYS = 30;

    /**
     * Below this, a forum could lock a conversation that is merely paused
     * overnight. It is a floor on the setting, not a default.
     */
    public const MIN_DAYS = 1;

    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * The kill switch. Defaults to OFF, unlike most of our settings.
     *
     * Locking is destructive in the sense that it changes what members may do,
     * and enabling an extension should never silently start rewriting old
     * discussions. An admin has to ask for it.
     */
    public function enabled(): bool
    {
        return (bool) $this->settings->get(self::ENABLED, false);
    }

    /** Days of silence before a discussion is locked. */
    public function days(): int
    {
        $days = (int) $this->settings->get(self::DAYS, self::DEFAULT_DAYS);

        return max(self::MIN_DAYS, $days ?: self::DEFAULT_DAYS);
    }

    public function showCountdown(): bool
    {
        return (bool) $this->settings->get(self::SHOW_COUNTDOWN, true);
    }

    /**
     * Leave a line in the discussion saying it was locked, and why.
     *
     * Default on: a thread that closes with no explanation reads as
     * moderation, and members ask about it. One row per locked discussion is
     * the cost, which a first sweep over an old forum does pay in bulk.
     */
    public function postNotice(): bool
    {
        return (bool) $this->settings->get(self::POST_NOTICE, true);
    }

    /**
     * Tag ids that are never auto locked.
     *
     * Stored as a comma separated list because that is what the admin tag
     * picker produces, and it keeps the setting readable in the database.
     *
     * @return list<int>
     */
    public function exemptTagIds(): array
    {
        $raw = (string) $this->settings->get(self::EXEMPT_TAGS, '');

        $ids = array_map('intval', array_filter(
            array_map('trim', explode(',', $raw)),
            static fn ($value) => $value !== '' && is_numeric($value)
        ));

        return array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
    }
}
