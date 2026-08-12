<?php

namespace LinkRobins\AutoLock;

use Flarum\Console\AbstractCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * The scheduled worker. Registered on Flarum's scheduler in extend.php, and
 * runnable by hand, which is how an admin checks what it would do before
 * turning it loose:
 *
 *     php flarum linkrobins:auto-lock --dry-run
 */
final class LockStaleDiscussionsCommand extends AbstractCommand
{
    public function __construct(
        protected Settings $settings,
        protected AutoLocker $locker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('linkrobins:auto-lock')
            ->setDescription('Lock discussions that have gone quiet for the configured number of days.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be locked, and lock nothing.');
    }

    protected function fire(): int
    {
        $dryRun = (bool) $this->input->getOption('dry-run');

        if (! $this->settings->enabled()) {
            $this->info('Auto Lock is switched off, so nothing was done.');

            return 0;
        }

        if (! $this->locker->available()) {
            $this->error('flarum/lock is not enabled, so discussions cannot be locked.');

            return 1;
        }

        $days = $this->settings->days();
        $cutoff = $this->locker->cutoff();

        $this->info(sprintf(
            '%s discussions with no activity since %s (%d days).',
            $dryRun ? 'Would lock' : 'Locking',
            $cutoff->toDateTimeString(),
            $days
        ));

        $count = $this->locker->run($dryRun);

        $this->info(sprintf('%s %d discussion%s.', $dryRun ? 'Would lock' : 'Locked', $count, $count === 1 ? '' : 's'));

        return 0;
    }
}
