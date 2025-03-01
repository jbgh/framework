<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\PackageManager\Command;

use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\Paths;
use Flarum\Foundation\ValidationException;
use Flarum\PackageManager\Composer\ComposerAdapter;
use Flarum\PackageManager\Exception\ComposerUpdateFailedException;
use Flarum\PackageManager\Exception\ExtensionNotInstalledException;
use Flarum\PackageManager\Extension\Event\Updated;
use Flarum\PackageManager\Settings\LastUpdateCheck;
use Flarum\PackageManager\UpdateExtensionValidator;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Component\Console\Input\StringInput;

class UpdateExtensionHandler
{
    public function __construct(
        public ComposerAdapter $composer,
        public ExtensionManager $extensions,
        public UpdateExtensionValidator $validator,
        public LastUpdateCheck $lastUpdateCheck,
        public Dispatcher $events,
        public Paths $paths
    ) {
    }

    public function handle(UpdateExtension $command): void
    {
        $command->actor->assertAdmin();

        $this->validator->assertValid(['extensionId' => $command->extensionId]);

        $extension = $this->extensions->getExtension($command->extensionId);

        if (empty($extension)) {
            throw new ExtensionNotInstalledException($command->extensionId);
        }

        $rootComposer = json_decode(file_get_contents("{$this->paths->base}/composer.json"), true);

        // If this was installed as a requirement for another extension,
        // don't update it directly.
        // @TODO communicate this in the UI.
        if (! isset($rootComposer['require'][$extension->name]) && ! empty($extension->getExtensionDependencyIds())) {
            throw new ValidationException([
                'message' => "Cannot update $extension->name. It was installed as a requirement for other extensions: ".implode(', ', $extension->getExtensionDependencyIds()).'. Update those extensions instead.'
            ]);
        }

        $output = $this->composer->run(
            new StringInput("require $extension->name:*"),
            $command->task ?? null
        );

        if ($output->getExitCode() !== 0) {
            throw new ComposerUpdateFailedException($extension->name, $output->getContents());
        }

        $this->events->dispatch(
            new Updated($command->actor, $extension)
        );
    }
}
