<?php

/**
 * This file is part of the Phalcon Scribe.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Scribe;

use Phalcon\Scribe\Exceptions\InvalidConfiguration;

use function dirname;
use function is_array;
use function is_file;
use function is_string;
use function rtrim;
use function str_replace;
use function str_starts_with;

/**
 * Everything the readers and formatters need that is project-specific.
 * Nothing about a particular repository is compiled into scribe.
 *
 * Projects declare it in a `scribe.php` at their root, the same shape talon
 * uses for `talon.php`.
 */
final class Config
{
    /** @var array<int, string> */
    private const KEYS = [
        'language',
        'source',
        'output',
        'repository',
        'branch',
        'prefix',
        'extension',
    ];

    private readonly string $outputDir;
    private readonly string $sourceRoot;

    public function __construct(
        private readonly string $language,
        string $sourceRoot,
        string $outputDir,
        private readonly string $repository,
        private readonly string $branch,
        private readonly string $sourcePrefix,
        private readonly string $extension,
    ) {
        $this->sourceRoot = rtrim($sourceRoot, '/');
        $this->outputDir  = rtrim($outputDir, '/');
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config, string $root): self
    {
        $values = [];
        foreach (self::KEYS as $key) {
            /** @var mixed $value */
            $value = $config[$key] ?? null;
            if (!is_string($value) || $value === '') {
                throw new InvalidConfiguration(
                    "scribe configuration key '{$key}' is required and must be a non-empty string"
                );
            }

            $values[$key] = $value;
        }

        return new self(
            $values['language'],
            self::absolute($values['source'], $root),
            self::absolute($values['output'], $root),
            $values['repository'],
            $values['branch'],
            $values['prefix'],
            $values['extension'],
        );
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new InvalidConfiguration("scribe configuration: no such file '{$path}'");
        }

        /** @var mixed $config */
        $config = require $path;
        if (!is_array($config)) {
            throw new InvalidConfiguration("'{$path}' must return an array");
        }

        /** @var array<string, mixed> $config */
        return self::fromArray($config, dirname($path));
    }

    public function branch(): string
    {
        return $this->branch;
    }

    public function extension(): string
    {
        return $this->extension;
    }

    public function language(): string
    {
        return $this->language;
    }

    public function outputDir(): string
    {
        return $this->outputDir;
    }

    public function repository(): string
    {
        return $this->repository;
    }

    public function sourcePrefix(): string
    {
        return $this->sourcePrefix;
    }

    public function sourceRoot(): string
    {
        return $this->sourceRoot;
    }

    public function sourceUrl(string $relativePath): string
    {
        return 'https://github.com/' . $this->repository
            . '/blob/' . $this->branch
            . '/' . $this->sourcePrefix
            . '/' . str_replace('\\', '/', $relativePath);
    }

    /**
     * A copy writing somewhere else, for one-off runs that must not touch the
     * configured destination. Normal use never needs it.
     */
    public function withOutputDir(string $outputDir): self
    {
        return new self(
            $this->language,
            $this->sourceRoot,
            $outputDir,
            $this->repository,
            $this->branch,
            $this->sourcePrefix,
            $this->extension,
        );
    }

    /**
     * Mirrors Talon\Cli\SuiteMap::absolute() - a leading slash wins, anything
     * else is relative to the configuration file's directory.
     */
    private static function absolute(string $path, string $root): string
    {
        return str_starts_with($path, '/') ? $path : rtrim($root, '/') . '/' . $path;
    }
}
