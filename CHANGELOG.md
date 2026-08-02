# Changelog

All notable changes are documented here. The format is based on [Keep a Changelog][keep_a_changelog] and this project adheres to [Semantic Versioning][semantic_versioning].

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
