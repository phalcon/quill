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

namespace Phalcon\Quill\Formatter;

use Phalcon\Quill\Config;
use Phalcon\Quill\Contracts\Formatter;
use Phalcon\Quill\Exceptions\MissingAsset;
use Phalcon\Quill\Formatter\Markdown\ClassPage;
use Phalcon\Quill\Formatter\Markdown\Html;
use Phalcon\Quill\Formatter\Markdown\Naming;
use Phalcon\Quill\Formatter\Markdown\Presentation;
use Phalcon\Quill\Formatter\Markdown\Signature;
use Phalcon\Quill\Model\Registry;
use Phalcon\Quill\Selection;
use Phalcon\Quill\Template\Templates;

use function array_keys;
use function dirname;
use function file_get_contents;
use function ksort;
use function sort;
use function str_replace;
use function stripos;
use function ucfirst;

/**
 * Emits the mkdocs Markdown the documentation repositories consume.
 *
 * The exact whitespace and markup is load-bearing. These documents are diffed
 * between the two Phalcon implementations, so an incidental formatting change
 * reads as an API change. It lives in `resources/templates/markdown`, which a
 * project can override a file at a time; alter it deliberately, never for
 * tidiness.
 *
 * Naming, escaping and signature rendering live in the Markdown namespace, and
 * a class's own section is ClassPage's. What stays here is which pages exist,
 * what goes on each, and the index.
 */
final class MarkdownFormatter implements Formatter
{
    public const STYLESHEET = 'api.css';

    private readonly Html $html;
    private readonly Naming $naming;
    private readonly Signature $signature;

    public function __construct()
    {
        $this->html      = new Html();
        $this->naming    = new Naming();
        $this->signature = new Signature($this->html);
    }

    /**
     * The stylesheet the emitted markup depends on: selectors only. Colors come
     * from `--api-*` custom properties it reads but does not define, leaving the
     * palette - and light and dark - to whichever site renders the pages.
     *
     * @return array<string, string>
     */
    public function assets(): array
    {
        $path  = dirname(__DIR__, 2) . '/resources/' . self::STYLESHEET;
        $sheet = @file_get_contents($path);

        if ($sheet === false) {
            throw new MissingAsset($path);
        }

        return [self::STYLESHEET => $sheet];
    }

    public function extension(): string
    {
        return 'md';
    }

    /**
     * @return array<string, string>
     */
    public function format(Registry $registry, Config $config, Selection $selection): array
    {
        $templates = Templates::for('markdown', $config->templatesDir());
        $view      = Presentation::from($config);
        $pages     = $this->pages($registry, $view, $selection);
        $output    = [];

        foreach (array_keys($pages) as $page) {
            if ($selection->filter !== '' && stripos($page, $selection->filter) === false) {
                unset($pages[$page]);
            }
        }

        $lines = '';
        foreach (array_keys($pages) as $page) {
            $lines .= $this->indexLine($page, $view, $templates);
        }

        $output['index'] = $templates->render('index', ['lines' => $lines]);

        $classPage = new ClassPage(
            $templates,
            $view,
            $registry,
            $this->naming,
            $this->signature,
            $this->html
        );

        foreach ($pages as $page => $fqcns) {
            $classes = '';
            foreach ($fqcns as $fqcn) {
                $class = $registry->get($fqcn);
                if ($class !== null) {
                    $classes .= $classPage->render($class);
                }
            }

            $output[$page] = $templates->render('page', [
                'classes'   => $classes,
                'namespace' => $view->rootNamespace,
            ]);
        }

        return $output;
    }

    private function indexLine(string $page, Presentation $view, Templates $templates): string
    {
        return $templates->render('index-line', [
            'label'     => ucfirst(str_replace($view->pagePrefix, '', $page)),
            'namespace' => $view->rootNamespace,
            'page'      => $page,
        ]);
    }

    /**
     * Page key => the FQCNs on it, keys sorted and FQCNs sorted within each.
     *
     * Which file a class lands in is a Markdown decision, so the grouping
     * lives here rather than on the registry.
     *
     * @return array<string, list<string>>
     */
    private function pages(Registry $registry, Presentation $view, Selection $selection): array
    {
        $pages = [];
        foreach ($registry->definitions() as $fqcn => $class) {
            if (!$selection->matchesNamespace($fqcn)) {
                continue;
            }

            $pages[$this->naming->pageKey($class, $view)][] = $fqcn;
        }

        ksort($pages);

        foreach ($pages as $page => $fqcns) {
            sort($fqcns);
            $pages[$page] = $fqcns;
        }

        return $pages;
    }
}
