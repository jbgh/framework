<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\PackageManager\Exception;

use Exception;

class ComposerCommandFailedException extends Exception
{
    public array $details = [];

    public function __construct(
        public string $packageName,
        string $output,
    ) {
        parent::__construct($output);
    }

    public function guessCause(): ?string
    {
        return null;
    }

    protected function getRawPackageName(): string
    {
        return preg_replace('/^([A-z0-9-_\/]+)(?::.*|)$/i', '$1', $this->packageName);
    }
}
