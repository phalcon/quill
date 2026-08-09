# <img src="resources/quill-mark.svg" height="22" alt=""> Phalcon Quill

[![Latest Version][packagist-version-badge]][packagist-version-link]
[![PHP Version][php-version-badge]][packagist-version-link]
[![Total Downloads][packagist-downloads-badge]][packagist-downloads-link]
[![License][license-badge]][license-link]

[![Quill CI][quill-ci-badge]][quill-ci-link]
[![Quality Gate Status][sonar-quality-badge]][sonar-link]
[![Coverage][sonar-coverage-badge]][sonar-link]
[![PDS Skeleton][pds-skeleton-badge]][pds-skeleton-link]

[![Discord][discord-badge]][discord-link]
[![Contributors][contributors-badge]][contributors-link]
[![OpenCollective Backers][oc-backers-badge]][oc-backers-link]
[![OpenCollective Sponsors][oc-sponsors-badge]][oc-sponsors-link]

API documentation generator for Zephir and PHP sources.

Quill reads a source tree into a typed model, then renders that model. Readers know one language and nothing about output; formatters know one output format and nothing about the language it came from.

```
ZephirReader  (phalcon/zephir)   ─┐                      ┌─> MarkdownFormatter (mkdocs pages)
                                  ├>  Model -> toArray() ┤
PhpReader     (nikic/php-parser) ─┘   (object graph)     └─> JsonFormatter     (model document)
```

## Requirements

- PHP `^8.1`
- `phalcon/zephir` to read `.zep` sources; selecting `language: zephir` without it fails with an explanation rather than a "class not found"

`nikic/php-parser` is a hard dependency and comes with quill - the PHP reader is not optional the way the Zephir one is.

## Install

    composer require --dev phalcon/quill

## Usage

    vendor/bin/quill generate                             every page, using ./quill.php
    vendor/bin/quill generate encryption                  only pages matching the filter
    vendor/bin/quill generate --format=json               one model document instead
    vendor/bin/quill generate --namespace=Phalcon\Config  one namespace and below
    vendor/bin/quill parity left.json right.json          structural differences
    vendor/bin/quill docblocks left.json right.json out.csv

## Options

| Option | Purpose |
|---|---|
| `--config=<path>` | explicit path to `quill.php`, default `./quill.php` |
| `--output=<dir>` | destination override for one run; assets follow the documents |
| `--format=<name>` | `markdown` (default) or `json` |
| `--namespace=<ns>` | limit to one namespace and everything beneath it; the configured root is implied, so `Config` and `Phalcon\Config` are the same. A namespace matching nothing is an error |
| `--help`, `-h` | usage |
| `<filter>` | positional; narrows what is written, matched case-insensitively |

The registry is always built from every source file regardless of the filter, so cross-page links stay correct.

## Configuration

Everything project-specific lives in a `quill.php` at the project root. Nothing about any particular repository is compiled into quill.

```php
<?php

return [
    'language'   => 'zephir',
    'source'     => 'phalcon',
    'output'     => 'output/docs/api',
    'assets'     => 'output/docs/assets/css',
    'repository' => 'phalcon/cphalcon',
    'branch'     => '5.0.x',
    'prefix'     => 'phalcon',
    'extension'  => 'zep',
    'namespace'  => 'Phalcon',
    'templates'  => 'output/docs/templates',
];
```

| Key | Purpose |
|---|---|
| `language` | selects the reader: `zephir` or `php` |
| `source` | source tree to read |
| `output` | where documents are written |
| `assets` | where a formatter's assets are written; optional, defaults to `output` |
| `repository`, `branch`, `prefix` | build the "Source on GitHub" link: `https://github.com/<repository>/blob/<branch>/<prefix>/<path>` |
| `extension` | file extension the reader collects |
| `namespace` | root namespace; headings drop it and page names carry it lowercased |
| `templates` | directory holding your own templates; each is looked up there first and falls back to the shipped one. Optional |

`source`, `output`, `assets` and `templates` are relative to `quill.php` unless they start with a slash. Every key except `assets` and `templates` is required and must be a non-empty string; anything missing raises `MissingConfigurationKey` naming the key.

Splitting `output` from `assets` lets the destination mirror the layout of whatever consumes it. With the values above, `cp -r nikos/docs/* <site>/docs/` lands the pages and the stylesheet where each belongs.

## Templates

The Markdown formatter emits no markup of its own. Every fragment comes from a file in `resources/templates/markdown`, and `templates` points at a directory of your own that is consulted first, per name. Overriding one template is not vendoring the other nineteen.

Files go under a directory named for the format, so `templates` set to `docs/templates` means `docs/templates/markdown/class.tpl`. A `.tpl` whose name is not in the shipped set, or one sitting above the format directory, is ignored with a warning naming it and the nearest real name - both would otherwise produce a successful run that applied no override.

Slots are `{{name}}`, substituted in a single pass: a value that happens to contain `{{title}}` is text, not an instruction. A placeholder a template does not use is ignored, so a template may take fewer slots than it is handed; one it invents is fatal, and `UnknownPlaceholder` names every unsupplied token at once. Loops, ordering and conditionals stay in PHP - a section that renders nothing is handed an empty string rather than asked to decide.

A template's trailing newline is stripped, exactly one. A fragment whose output must end in a newline is therefore written with a blank final line, which is also what an editor leaves behind.

| Template | Renders | Placeholders |
|---|---|---|
| `index` | the index page | `lines` |
| `index-line` | one entry on it | `namespace`, `label`, `page` |
| `page` | one page's frontmatter and notice | `namespace`, `classes` |
| `class` | one class's whole section | `title`, `structure`, `badge`, `sourceUrl`, `description`, `tree`, `uses`, `usedBy`, `summary`, `constants`, `properties`, `methods` |
| `class-description` | its prose, when it has any | `description` |
| `tree` | the inheritance block | `lines` |
| `uses` | the import list | `entries` |
| `used-by` | the classes pulling a trait in | `entries` |
| `summary` | the method summary section | `rows` |
| `summary-row` | one summary row | `anchor`, `visibility`, `returnType`, `signature`, `description` |
| `summary-return-type` | its type chip, when the method declares one | `type` |
| `constants` | the constants section | `rows` |
| `constant-row` | one constant | `type`, `name`, `default`, `description` |
| `properties` | the properties section | `rows` |
| `property-row` | one property | `visibility`, `type`, `name`, `default`, `description` |
| `row-description` | the description cell shared by all three row shapes | `description` |
| `methods` | the method detail section | `groups` |
| `method-group` | one visibility group's header and body | `label`, `count`, `methods` |
| `method` | one method's heading and signature block | `name`, `anchor`, `signature`, `description` |
| `method-description` | its prose, when it has any | `description` |

The class names the templates emit are declared in `Formatter\Markdown\Classes` and styled by `resources/api.css`; a test binds all three, so a name cannot drift out of one of them unnoticed.

## What `generate` writes

- one page per top-level namespace segment, named `<namespace>_<segment>.<ext>`
- an `index` page linking to the rest
- the formatter's static assets, if it has any

A complete run also prunes: documents in the output directory that this run did not produce are deleted, so a source namespace that disappears takes its page with it. A filtered run is deliberately partial and never prunes. Pruning is scoped to the formatter's own extension - anything else in the directory belongs to someone else.

## Formatters

| | `markdown` | `json` |
|---|---|---|
| Output | one page per namespace, plus an index | one `model.json` |
| Assets | `api.css`, the stylesheet its markup depends on | none |
| Private members | filtered out | present, with visibility |
| Enums | rendered as classes | `structure.keyword: enum` |
| Traits | `Trait` badge, plus a `Used by` list | `structure.keyword: trait` |

The model is deliberately complete and the Markdown formatter is deliberately opinionated: anything a reader can observe cheaply goes into the model even when today's formatters ignore it, so adding a formatter never means revisiting a reader.

`api.css` carries selectors only. Colors come from `--api-*` custom properties it reads but does not define, which leaves the palette - and light and dark - to the site rendering the pages.

## The model as an integration point

`ClassDefinition::toArray()` serializes a whole definition and carries a `version`. It is a published format the moment anything reads it, so treat a shape change as a version bump. A document written by one installation is read back by another, and `parity` refuses a document whose version it does not recognize rather than reporting the moved keys as differences.

A `ClassDefinition` is six things: a `Location` (fqcn, namespace, relPath), a `Structure` (keyword plus modifiers, `null` rather than `false` where they do not apply), a `description`, `Imports`, `Relations` and `Members`. The serialization mirrors that graph exactly.

`uses` and `traits` are different relations that share a keyword: `uses` are the file's namespace imports, `traits` are what the class body pulls in. `Registry` inverts the latter into `usedBy()`.

Names in `Relations` are absolute and backslash-prefixed. Both languages spell a parent three ways - `\Foo`, `Foo` behind a `use`, or `Foo` meaning the sibling in the same namespace - and the readers resolve all three as they read, so two trees that agree cannot look like they disagree.

## Comparing two implementations

`parity` and `docblocks` both take two model documents, normally one per implementation:

    vendor/bin/quill generate --format=json --config=cphalcon/quill.php
    vendor/bin/quill generate --format=json --config=phalcon/quill.php

Add `--namespace=` to both sides to compare one subsystem at a time, which keeps the diff readable:

    vendor/bin/quill generate --format=json --namespace=Phalcon\Config --config=cphalcon/quill.php

`parity` reports definitions present on one side only and, for shared ones, which members differ. It exits non-zero when anything differs, so it can gate a build.

`docblocks` writes the documentation disagreements to a spreadsheet, one row per difference with a `winner` column to fill in. Rows where one side is blank are pre-filled; the rest are a human decision. Nothing here edits source.

## Development

    docker compose up -d --build
    docker exec -w /srv quill-8.1 composer install
    docker exec -w /srv quill-8.1 composer test
    docker exec -w /srv quill-8.1 composer analyze
    docker exec -w /srv quill-8.1 composer cs

`quill-8.1` is the floor and where the byte-for-byte comparison runs; `quill-8.5` covers deprecations. The suite must pass on both.

### The full-corpus gate

The suite proves the rendering over fixtures. The gate proves it over the whole cphalcon tree - roughly 2,600 declarations, which is where a signature shape that appears once and in no fixture turns up. Two directories, both gitignored because committing them buries every source change under a few thousand generated lines:

| Directory | Role |
|---|---|
| `tests/_baseline` | the expectation - a snapshot of known-good output |
| `tests/_output/gate` | the candidate - where a fresh run writes |

Run it. `tests/Fixtures/config/cphalcon.php` already points `output` at `gate`, so no `--output` is needed; `phalcon.php` beside it does the same for the PHP implementation:

    docker exec -w /srv quill-8.1 rm -rf tests/_output/gate
    docker exec -w /srv quill-8.1 php bin/quill generate --config=tests/Fixtures/config/cphalcon.php
    diff -r tests/_baseline tests/_output/gate

**Silence is the pass.** Any output is a real change to what quill emits and belongs in the CHANGELOG. Delete `gate` first or a page that should have disappeared survives from the previous run and the diff stays quiet about it.

Regenerating the baseline is the same binary with the destination redirected:

    docker exec -w /srv quill-8.1 php bin/quill generate \
        --config=tests/Fixtures/config/cphalcon.php --output=/srv/tests/_baseline

Read the diff before you do, and move the old snapshot aside rather than deleting it - it is gitignored, so there is no `git checkout` to undo an `rm`. Regenerating is how an accepted change is recorded; doing it to make the diff go away is how the next one goes unnoticed.

Two things that catch people out. `bin/quill`, not `vendor/bin/quill` - a package in its own tree has no `vendor/bin` shim. And a redirected run keeps `templates` while dropping `assets`, so the stylesheet follows the pages into the baseline and `api.css` is compared too, while an override still applies - a run that quietly fell back to the shipped templates would produce a clean diff having compared the wrong thing.

Nothing enforces this. The baseline is refreshed by hand, so it goes stale silently; when the gate reports a difference, check its age against the commits since it was written before assuming the working tree is at fault.

## License

BSD-3-Clause. See [LICENSE](LICENSE).

<!-- Badges -->
[packagist-version-badge]:   https://img.shields.io/packagist/v/phalcon/quill?include_prereleases&style=flat-square&logo=packagist&logoColor=white
[packagist-version-link]:    https://packagist.org/packages/phalcon/quill
[packagist-downloads-badge]: https://img.shields.io/packagist/dt/phalcon/quill?style=flat-square&logo=packagist&logoColor=white
[packagist-downloads-link]:  https://packagist.org/packages/phalcon/quill/stats
[php-version-badge]:         https://img.shields.io/packagist/php-v/phalcon/quill?style=flat-square&logo=php&logoColor=white
[license-badge]:             https://img.shields.io/github/license/phalcon/quill?style=flat-square&logo=opensourceinitiative&logoColor=white
[license-link]:              https://github.com/phalcon/quill/blob/master/LICENSE
[quill-ci-badge]:            https://github.com/phalcon/quill/actions/workflows/main.yml/badge.svg?branch=master
[quill-ci-link]:             https://github.com/phalcon/quill/actions/workflows/main.yml
[sonar-quality-badge]:       https://sonarcloud.io/api/project_badges/measure?project=phalcon_quill2&metric=alert_status
[sonar-coverage-badge]:      https://sonarcloud.io/api/project_badges/measure?project=phalcon_quill2&metric=coverage
[sonar-link]:                https://sonarcloud.io/summary/new_code?id=phalcon_quill2
[pds-skeleton-badge]:        https://img.shields.io/badge/pds-skeleton-blue.svg?style=flat-square
[pds-skeleton-link]:         https://github.com/php-pds/skeleton
[discord-badge]:             https://img.shields.io/discord/310910488152375297?label=Discord&logo=discord&style=flat-square
[discord-link]:              https://phalcon.io/discord
[contributors-badge]:        https://img.shields.io/github/contributors/phalcon/quill?style=flat-square&logo=github&logoColor=white
[contributors-link]:         https://github.com/phalcon/quill/graphs/contributors
[oc-backers-badge]:          https://img.shields.io/opencollective/backers/phalcon?style=flat-square&logo=opencollective&logoColor=white
[oc-backers-link]:           https://opencollective.com/phalcon
[oc-sponsors-badge]:         https://img.shields.io/opencollective/sponsors/phalcon?style=flat-square&logo=opencollective&logoColor=white
[oc-sponsors-link]:          https://opencollective.com/phalcon
