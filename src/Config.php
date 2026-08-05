<?php

/**
 * This file is part of the Phalcon Quill.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\Quill;

use Phalcon\Quill\Exceptions\MalformedConfiguration;
use Phalcon\Quill\Exceptions\MissingConfiguration;
use Phalcon\Quill\Exceptions\MissingConfigurationKey;

use function dirname;
use function is_array;
use function is_file;
use function is_string;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * Everything the readers and formatters need that is project-specific.
 * Nothing about a particular repository is compiled into quill.
 *
 * Projects declare it in a `quill.php` at their root, the same shape talon
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
        'namespace',
    ];

    private readonly string $assetsDir;
    private readonly string $outputDir;
    private readonly string $sourceRoot;
    private readonly string $templatesDir;

    /**
     * `$assetsDir` defaults to the output directory, which puts a formatter's
     * assets beside the documents they style. A destination that separates the
     * two - as an mkdocs site separates `docs/api` from `docs/assets/css` -
     * names it instead.
     */
    public function __construct(
        private readonly string $language,
        string $sourceRoot,
        string $outputDir,
        private readonly string $repository,
        private readonly string $branch,
        private readonly string $sourcePrefix,
        private readonly string $extension,
        private readonly string $rootNamespace,
        string $assetsDir = '',
        string $templatesDir = '',
    ) {
        $this->sourceRoot   = rtrim($sourceRoot, '/');
        $this->outputDir    = rtrim($outputDir, '/');
        $this->assetsDir    = $assetsDir === '' ? $this->outputDir : rtrim($assetsDir, '/');
        $this->templatesDir = rtrim($templatesDir, '/');
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
                throw new MissingConfigurationKey($key);
            }

            $values[$key] = $value;
        }

        // Absent from KEYS on purpose: a project that keeps assets with its
        // documents says nothing and takes the constructor's default.
        /** @var mixed $assets */
        $assets = $config['assets'] ?? null;

        // Absent from KEYS on purpose: a project using the shipped templates
        // says nothing and takes the constructor's default.
        /** @var mixed $templates */
        $templates = $config['templates'] ?? null;

        return new self(
            $values['language'],
            self::absolute($values['source'], $root),
            self::absolute($values['output'], $root),
            $values['repository'],
            $values['branch'],
            $values['prefix'],
            $values['extension'],
            trim($values['namespace'], '\\'),
            is_string($assets) && $assets !== '' ? self::absolute($assets, $root) : '',
            is_string($templates) && $templates !== '' ? self::absolute($templates, $root) : '',
        );
    }

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new MissingConfiguration($path);
        }

        /** @var mixed $config */
        $config = require $path;
        if (!is_array($config)) {
            throw new MalformedConfiguration($path);
        }

        /** @var array<string, mixed> $config */
        return self::fromArray($config, dirname($path));
    }

    private static function absolute(string $path, string $root): string
    {
        return str_starts_with($path, '/') ? $path : rtrim($root, '/') . '/' . $path;
    }

    public function assetsDir(): string
    {
        return $this->assetsDir;
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

    /**
     * What a generated page file is named before its own segment - the root
     * namespace lowercased, which is where `phalcon_acl.md` comes from.
     */
    public function pagePrefix(): string
    {
        return strtolower($this->rootNamespace) . '_';
    }

    public function repository(): string
    {
        return $this->repository;
    }

    /**
     * The namespace every documented declaration sits under. Headings drop it,
     * page names carry it lowercased, and short-name resolution falls back to
     * it.
     */
    public function rootNamespace(): string
    {
        return $this->rootNamespace;
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
     * Where a consumer's own templates live, or '' when they use the shipped
     * set. The formatter resolves each template name here first.
     */
    public function templatesDir(): string
    {
        return $this->templatesDir;
    }

    /**
     * A copy writing somewhere else, for one-off runs that must not touch the
     * configured destination. Normal use never needs it.
     *
     * The assets directory is not carried over - a redirected run wants
     * everything it produces in the one place. The templates directory is,
     * because templates are input: a redirected run that fell back to the
     * shipped set would not be the same run, and a diff against it would come
     * back clean having proved nothing.
     */
    public function withOutputDir(string $outputDir): self
    {
        // Assets are passed empty explicitly in order to reach templates
        // positionally - dropping the one and carrying the other in one call.
        return new self(
            $this->language,
            $this->sourceRoot,
            $outputDir,
            $this->repository,
            $this->branch,
            $this->sourcePrefix,
            $this->extension,
            $this->rootNamespace,
            '',
            $this->templatesDir,
        );
    }
}
