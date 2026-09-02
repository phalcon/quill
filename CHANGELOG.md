# Changelog

All notable changes are documented here. The format is based on [Keep a Changelog][keep_a_changelog] and this project adheres to [Semantic Versioning][semantic_versioning].

## [0.5.0](https://github.com/phalcon/quill/releases/tag/v0.5.0) (2026-09-01)

### Added

- A `baseUri` configuration key, optional: the path the generated pages are published under, `/5.20/api` for example. The nimbus format writes every link from it, so a link is absolute instead of relative to the page that carries it.
- `--base-uri=<path>`, which overrides that key for one run. A site that publishes one version per run gives it here rather than editing the configuration between runs.
- `Formatter\Dialect::indexLink()`, the link form of the index. The index is served one segment above the pages it lists, so it reaches a page without climbing, where a page reaches a sibling with `../`.

### Changed

- `Dialect::nimbus()` and `FormatterFactory::create()` take an optional base URI. Both default to none, which keeps the relative links.
- The `index-line` template of both formats receives `link`, the built link, in place of writing one around `page`. `page` is still passed, so a template that overrides the shipped one and uses it is unaffected.

### Fixed

- The nimbus format wrote its links in a form that a nimbus site cannot verify. `../page/` was reported as a relative link and skipped by the link rule, and the bare `page/` of the index was resolved against the site root, where no such page is. Both reached the right page in a browser, but neither was checked, so a link to a page that does not exist would not have been reported. A run with `baseUri` writes one absolute form, which the site resolves against its route set.
- The link of the index was built in the template while the link of a page was built in the dialect, each with its own assumption about the depth of the file that refers. Both now come from the dialect.

With no base URI the output is unchanged, byte for byte.

## [0.4.0](https://github.com/phalcon/quill/releases/tag/v0.4.0) (2026-08-31)

### Added

- The `nimbus` output format, `--format=nimbus`: MDX for a nimbus-docs site. The markup is components carrying properties rather than classed HTML, so the format emits no stylesheet.
- Twenty templates under `resources/templates/nimbus`, named as the markdown set is, so the `templates` configuration key overrides either format one file at a time.
- `Formatter\Dialect`, holding what separates one Markdown output from another: the template directory, the file extension, the stylesheet, how a link to another page is written, and what prose must have escaped.
- `Formatter\Markdown\Mdx`, which escapes what MDX reads as syntax - braces such as `{@see method()}`, `<Word>` that is not HTML, and an inline tag left unclosed in its paragraph. Fenced blocks and inline code keep their own rules and are not touched.
- `Formatter\Markdown\Rows`, with `MarkdownRows` and `NimbusRows`: the four member shapes whose slot values, not only their markup, differ between the two outputs.
- A `title` slot on the `page` template. The markdown template ignores it.

### Changed

- `MarkdownFormatter::__construct()` takes an optional `Dialect` and defaults to markdown, so an existing call is unaffected.
- `ClassPage::__construct()` takes a `Rows` and a `Dialect`, and no longer takes a `Signature`.
- A link to another page comes from the dialect: `phalcon_events.md` for mkdocs, `../phalcon_events/` for nimbus, which serves every page as a directory.
- `Contracts\Formatter::assets()` may return nothing. A formatter whose markup carries its own styling ships no stylesheet.

The markdown output is unchanged, byte for byte.

## [0.3.0](https://github.com/phalcon/quill/releases/tag/v0.3.0) (2026-08-03)

### Added

- The Markdown markup now lives in twenty template files under `resources/templates/markdown`, overridable one file at a time. Output is unchanged.
- The optional `templates` configuration key, naming a directory whose templates are consulted before the shipped ones. A `.tpl` that no lookup can reach - a name outside the shipped set, or a file above the format directory - is reported with the nearest real name and ignored.

### Changed

- Rendering one class moved out of `MarkdownFormatter` into `Formatter\Markdown\ClassPage`.

## [0.2.3](https://github.com/phalcon/quill/releases/tag/v0.2.3) (2026-08-03)

### Fixed

- Fixed object detection that was previously reported as `mixed` from Zephir sources.

## [0.2.2](https://github.com/phalcon/quill/releases/tag/v0.2.2) (2026-08-02)

### Fixed

- `TypeRenderer` dropped the leading backslash from a fully qualified parameter or return type, rendering `\Throwable` as `Throwable`
- `ZephirReader` reported a typed property defaulting to null as `T` rather than `T|null`; parameters already read it that way

## [0.2.1](https://github.com/phalcon/quill/releases/tag/v0.2.1) (2026-08-02)

### Fixed

- `PhpReader` dropped `final` and `abstract` from method modifiers, reporting every such method as a parity difference

## [0.2.0](https://github.com/phalcon/quill/releases/tag/v0.2.0) (2026-08-02)

### Changed

- `Contracts\Formatter::format()` takes a `Selection` in place of the filter string
- `GenerateCommand::execute()` takes a `Selection`
- Pruning is skipped for any narrowed run, not only a filtered one

### Added

- `--namespace=` on `generate`: one namespace and everything beneath it, root implied
- `Selection`, carrying what a run narrows to
- `NamespaceNotFound`, thrown when a requested namespace matches nothing

## [0.1.0](https://github.com/phalcon/quill/releases/tag/v0.1.0) (2026-08-01)

### Changed

- Readers resolve `extends`, `implements` and `traits` to absolute names; the model no longer carries names as written
- `Naming::title()`, `anchor()` and `methodAnchor()` take a `Config`
- `Registry::__construct()` takes the root namespace used for short-name resolution

### Added

- `Contracts\Formatter::assets()`: static files written beside the documents
- `MarkdownFormatter` ships `api.css`, the stylesheet its markup depends on
- `assets` config key, optional, defaulting to `output`
- `namespace` config key, required; page names derive from it lowercased
- `MissingAsset`, for a packaged asset missing from the installation
- Banner on `--help` and no-command runs

### Fixed

- The two readers spelled the same parent differently (`\Throwable` against `Throwable`), reporting 7 false differences in parity output

### Removed

- Hardcoded `Phalcon` and `phalcon_` from `Registry::resolve()`, `Naming` and `MarkdownFormatter`


[keep_a_changelog]: https://keepachangelog.com/en/1.1.0/
[semantic_versioning]: https://semver.org/spec/v2.0.0.html
