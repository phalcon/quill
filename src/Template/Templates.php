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

namespace Phalcon\Quill\Template;

use Phalcon\Quill\Exceptions\MissingTemplate;
use Phalcon\Quill\Exceptions\MissingTemplateDirectory;
use Phalcon\Quill\Exceptions\UnknownPlaceholder;

use function array_diff;
use function array_keys;
use function array_unique;
use function array_values;
use function basename;
use function dirname;
use function file_get_contents;
use function glob;
use function in_array;
use function is_dir;
use function is_file;
use function levenshtein;
use function preg_match_all;
use function str_ends_with;
use function strtr;
use function substr;

use const PHP_EOL;

/**
 * A template name resolved to a file and its `{{name}}` placeholders filled.
 *
 * A consumer's own directory is consulted before the shipped one, per name, so
 * overriding one template does not mean vendoring the other nineteen.
 *
 * Substitution is a single pass: a value that happens to contain `{{title}}`
 * is text, not an instruction. Loops, ordering and conditionals stay in PHP -
 * this deliberately is not a template language.
 */
final class Templates
{
    private const EXTENSION   = '.tpl';
    private const PLACEHOLDER = '/\{\{([a-zA-Z][a-zA-Z0-9]*)\}\}/';

    /** @var array<string, array{body: string, tokens: list<string>}> */
    private array $cache = [];

    private function __construct(
        private readonly string $format,
        private readonly string $customDir,
        private readonly string $shippedDir,
    ) {
    }

    public static function for(string $format, string $templatesDir): self
    {
        self::guard($templatesDir);

        return new self(
            $format,
            $templatesDir === '' ? '' : $templatesDir . '/' . $format,
            self::shipped($format)
        );
    }

    /**
     * @param array<string, string> $values
     */
    public function render(string $name, array $values): string
    {
        $template = $this->load($name);

        $missing = array_values(array_diff($template['tokens'], array_keys($values)));
        if ($missing !== []) {
            throw new UnknownPlaceholder($this->format . '/' . $name, $missing);
        }

        $pairs = [];
        foreach ($values as $key => $value) {
            $pairs['{{' . $key . '}}'] = $value;
        }

        return strtr($template['body'], $pairs);
    }

    /**
     * Files in the custom directory that no lookup will ever reach: a name
     * outside the shipped set, or a template sitting above the format
     * directory. Both otherwise produce a successful run that applied no
     * override, which reads as the feature working.
     *
     * @return list<string>
     */
    public static function unrecognized(string $format, string $templatesDir): array
    {
        self::guard($templatesDir);

        if ($templatesDir === '') {
            return [];
        }

        $known = [];
        foreach (glob(self::shipped($format) . '/*' . self::EXTENSION) ?: [] as $path) {
            $known[] = basename($path, self::EXTENSION);
        }

        // A format with no shipped templates has no set to be outside of, so a
        // json run with `templates` configured warns about nothing.
        if ($known === []) {
            return [];
        }

        $warnings = [];

        foreach (glob($templatesDir . '/' . $format . '/*' . self::EXTENSION) ?: [] as $path) {
            $name = basename($path, self::EXTENSION);
            if (in_array($name, $known, true)) {
                continue;
            }

            $warnings[] = "'" . basename($path) . "' matches no known template and was ignored."
                . PHP_EOL . '         Did you mean ' . "'" . self::nearest($name, $known) . ".tpl'?";
        }

        foreach (glob($templatesDir . '/*' . self::EXTENSION) ?: [] as $path) {
            $warnings[] = "'" . basename($path) . "' sits outside '" . $format . "/' and was ignored."
                . PHP_EOL . '         Move it to ' . $templatesDir . '/' . $format . '/' . basename($path);
        }

        return $warnings;
    }

    private static function guard(string $templatesDir): void
    {
        if ($templatesDir !== '' && !is_dir($templatesDir)) {
            throw new MissingTemplateDirectory($templatesDir);
        }
    }

    /**
     * @param list<string> $known
     */
    private static function nearest(string $name, array $known): string
    {
        $best     = $known[0];
        $distance = levenshtein($name, $best);

        foreach ($known as $candidate) {
            $current = levenshtein($name, $candidate);
            if ($current < $distance) {
                $best     = $candidate;
                $distance = $current;
            }
        }

        return $best;
    }

    private static function shipped(string $format): string
    {
        return dirname(__DIR__, 2) . '/resources/templates/' . $format;
    }

    /**
     * @return array{body: string, tokens: list<string>}
     */
    private function load(string $name): array
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $searched = [];
        $body     = null;

        foreach ([$this->customDir, $this->shippedDir] as $directory) {
            if ($directory === '') {
                continue;
            }

            $path       = $directory . '/' . $name . self::EXTENSION;
            $searched[] = $path;

            if (is_file($path)) {
                $body = (string) file_get_contents($path);

                break;
            }
        }

        if ($body === null) {
            throw new MissingTemplate($name, $searched);
        }

        // Exactly one. Editors and .editorconfig add a trailing newline, so a
        // template whose output must end in one is written with a blank line.
        if (str_ends_with($body, "\n")) {
            $body = substr($body, 0, -1);
        }

        preg_match_all(self::PLACEHOLDER, $body, $matches);

        return $this->cache[$name] = [
            'body'   => $body,
            'tokens' => array_values(array_unique($matches[1])),
        ];
    }
}
