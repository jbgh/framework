<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\PackageManager\Job;

use Flarum\Bus\Dispatcher as Bus;
use Flarum\PackageManager\Command\AbstractActionCommand;
use Flarum\PackageManager\Task\Task;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\SyncQueue;

class Dispatcher
{
    /**
     * Overrides the user setting for execution mode if set.
     * Runs synchronously regardless of user setting if set true.
     * Asynchronously if set false.
     */
    protected ?bool $runSyncOverride;

    public function __construct(
        protected Bus $bus,
        protected Queue $queue,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function sync(): self
    {
        $this->runSyncOverride = true;

        return $this;
    }

    public function async(): self
    {
        $this->runSyncOverride = false;

        return $this;
    }

    public function dispatch(AbstractActionCommand $command): DispatcherResponse
    {
        $queueJobs = ($this->runSyncOverride === false) || ($this->runSyncOverride !== true && $this->settings->get('flarum-package-manager.queue_jobs'));

        if ($queueJobs && (! $this->queue instanceof SyncQueue)) {
            $task = Task::build($command->getOperationName(), $command->package ?? null);

            $command->task = $task;

            $this->queue->push(
                new ComposerCommandJob($command, PHP_VERSION)
            );
        } else {
            $data = $this->bus->dispatch($command);
        }

        return new DispatcherResponse($queueJobs, $data ?? null);
    }
}
