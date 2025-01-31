<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\PackageManager\Command;

use Flarum\PackageManager\Composer\ComposerAdapter;
use Flarum\PackageManager\Composer\ComposerJson;
use Flarum\PackageManager\Event\FlarumUpdated;
use Flarum\PackageManager\Exception\MajorUpdateFailedException;
use Flarum\PackageManager\Exception\NoNewMajorVersionException;
use Flarum\PackageManager\Settings\LastUpdateCheck;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Component\Console\Input\ArrayInput;

class MajorUpdateHandler
{
    public function __construct(
        protected ComposerAdapter $composer,
        protected LastUpdateCheck $lastUpdateCheck,
        protected Dispatcher $events,
        protected ComposerJson $composerJson
    ) {
    }

    /**
     * Set the version constraint for all directly required packages in the root composer.json to *.
     * Set flarum/core version constraint to new major version.
     * Run composer update --prefer-dist --no-plugins --no-dev -a --with-all-dependencies.
     * Clear cache.
     * Run migrations.
     *
     * @throws \Flarum\User\Exception\PermissionDeniedException
     * @throws NoNewMajorVersionException|MajorUpdateFailedException
     */
    public function handle(MajorUpdate $command): void
    {
        $command->actor->assertAdmin();

        $majorVersion = $this->lastUpdateCheck->getNewMajorVersion();

        if (! $majorVersion) {
            throw new NoNewMajorVersionException();
        }

        $this->updateComposerJson($majorVersion);

        $this->runCommand($command, $majorVersion);

        if ($command->dryRun) {
            $this->composerJson->revert();

            return;
        }

        $this->events->dispatch(
            new FlarumUpdated($command->actor, FlarumUpdated::MAJOR)
        );
    }

    /**
     * @todo change minimum stability to 'stable' and any other similar params
     */
    protected function updateComposerJson(string $majorVersion): void
    {
        $versionNumber = str_replace('v', '', $majorVersion);

        $this->composerJson->require('*', '*');
        $this->composerJson->require('flarum/core', '^'.$versionNumber);
    }

    /**
     * @throws MajorUpdateFailedException
     */
    protected function runCommand(MajorUpdate $command, string $majorVersion): void
    {
        $input = [
            'command' => 'update',
            '--prefer-dist' => true,
            '--no-plugins' => true,
            '--no-dev' => true,
            '-a' => true,
            '--with-all-dependencies' => true,
        ];

        if ($command->dryRun) {
            $input['--dry-run'] = true;
        }

        $output = $this->composer->run(new ArrayInput($input), $command->task ?? null);

        if ($output->getExitCode() !== 0) {
            throw new MajorUpdateFailedException('*', $output->getContents(), $majorVersion);
        }
    }
}
