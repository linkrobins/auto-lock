<?php

namespace LinkRobins\AutoLock;

use Flarum\Api\Context;
use Flarum\Api\Schema;

/**
 * Serializes the lock date onto the discussion, which is what the forum
 * countdown renders.
 *
 * Only on the single discussion response: a listing would pay the tag lookup
 * for every row to render something no listing shows.
 */
final class AddAutoLockAttributes
{
    public function __construct(
        protected AutoLocker $locker,
        protected Settings $settings,
    ) {
    }

    /**
     * @return array<Schema\DateTime>
     */
    public function __invoke(): array
    {
        return [
            Schema\DateTime::make('linkrobinsAutoLockAt')
                ->visible(fn ($model, Context $context) => $context->showing())
                ->get(function ($discussion, Context $context) {
                    // Fail closed. A throw here would break every discussion
                    // page, and the countdown is decoration: the worst
                    // acceptable outcome is that it does not render.
                    try {
                        if (! $this->settings->showCountdown()) {
                            return null;
                        }

                        return $this->locker->lockAtFor($discussion);
                    } catch (\Throwable $e) {
                        return null;
                    }
                }),
        ];
    }
}
