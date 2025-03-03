<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\PackageManager\Tests\integration;

use Flarum\Testing\integration\UsesTmpDir;

class SetupComposer
{
    use UsesTmpDir;

    private $config;

    public function __construct(array $config = null)
    {
        $this->config = $config;
    }

    public function run()
    {
        $composerJson = $this->tmpDir().'/composer.json';
        $composerLock = $this->tmpDir().'/composer.lock';
        $packages = $this->tmpDir().'/packages';

        file_put_contents($composerJson, json_encode($this->getConfig(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        if (! file_exists($packages)) {
            mkdir($packages);
        }

        if (file_exists($composerLock)) {
            unlink($composerLock);
        }
    }

    private function getConfig(): array
    {
        return array_merge([
            'require' => [
                'flarum/core' => '1.0.0',
                'flarum/tags' => '1.0.3',
                'flarum/lang-english' => '*',
            ],
            'config' => [
                'preferred-install' => 'dist',
                'sort-packages' => true,
            ],
            'repositories' => [
                [
                    'type' => 'path',
                    'url' => realpath($this->tmpDir()).'/packages/*',
                ]
            ]
        ], $this->config ?? []);
    }
}
