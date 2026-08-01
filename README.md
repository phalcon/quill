# Scribe

API documentation generator for Zephir and PHP sources.

Scribe reads a source tree into a typed model, then renders that model. Readers
know one language and nothing about output; formatters know one output format
and nothing about the language it came from.

```
ZephirReader  (Zephir\Parser)   ─┐                        ┌─→ MarkdownFormatter
                                 ├─→  Model  ─→ toArray() ─┤
PhpReader     (Phase 2)         ─┘   (object graph)        └─→ (further formatters)
```

## Configuration

Everything project-specific lives in a `scribe.php` at the project root, which
returns an array. Nothing about any particular repository is baked into scribe.

```php
<?php

return [
    // Selects the reader. 'zephir' today; 'php' arrives in Phase 2.
    'language'   => 'zephir',

    // Source tree to read, and where the documents are written. Both are
    // relative to this file's directory unless they start with a slash.
    'source'     => 'phalcon',
    'output'     => 'nikos/api',

    // Used to build the "Source on GitHub" link on every class:
    // https://github.com/<repository>/blob/<branch>/<prefix>/<path>
    'repository' => 'phalcon/cphalcon',
    'branch'     => '5.0.x',
    'prefix'     => 'phalcon',

    // File extension the reader collects.
    'extension'  => 'zep',
];
```

Every key is required and must be a non-empty string; anything missing raises
`InvalidConfiguration` naming the key.

## Usage

```bash
scribe generate                                  # everything, using ./scribe.php
scribe generate encryption                       # only pages matching the filter
scribe generate --config=build/scribe.php
scribe generate --output=/somewhere/else
```

The filter is matched case-insensitively against the page key and narrows what
gets written, index included. The registry is always built from every source
file regardless, so cross-page links stay correct.

`--output` overrides just the destination, for ad-hoc runs. Normal use does not
need it.

## The model as an integration point

`ClassDefinition::toArray()` serializes the whole definition, and carries a
`version` key. It is a published format the moment anything reads it — treat a
shape change as a version bump.

`ClassDefinition` is six things: a `Location` (fqcn, namespace, relPath), a
`Structure` (keyword plus modifiers, which are `null` rather than `false` where
they do not apply), a `description`, `Imports`, `Relations` and `Members`. The
serialization mirrors that graph exactly.

The model is deliberately complete and the formatter is deliberately opinionated:

| | Model | `MarkdownFormatter` |
|---|---|---|
| Private members | captured, with visibility | filtered out |
| Enums | `structure.keyword: enum` | rendered as a class |
| Traits | `structure.keyword: trait` | `Trait` badge, plus a `Used by` list |

`uses` and `traits` are different relations that share a keyword: `uses` are the
file's namespace imports, `traits` are what the class body pulls in. `Registry`
inverts the latter into `usedBy()`.

Anything a reader can observe cheaply goes into the model even when today's only
formatter ignores it, so adding a formatter never means revisiting a reader.

## Status

Phase 1, complete. The port was verified byte-for-byte against cphalcon's
`bin/generate-api-docs.php` before anything was changed, then two deliberate
improvements were applied on top:

- traits carry a `Trait` badge instead of falling through to `Class`
- traits list the classes that pull them in

Against the frozen legacy baseline that is a diff of exactly 38 badge changes
and 38 `Used by` blocks - one per trait - and nothing else.

Both add markup the documentation theme needs styles for: the `badge--trait`
modifier and the `.api-used-by` block.

## Development

```bash
docker compose up -d --build
docker exec -w /srv scribe-8.1 composer install
docker exec -w /srv scribe-8.1 composer test
docker exec -w /srv scribe-8.1 composer analyze
docker exec -w /srv scribe-8.1 composer cs
```

`scribe-8.1` is the floor and where the byte-for-byte comparison runs;
`scribe-8.5` covers deprecations. The suite must pass on both.

`phalcon/zephir` is a dev dependency and a `suggest` rather than a hard
requirement, so a project using only the PHP reader does not pull in the Zephir
compiler. Selecting `language: zephir` without it installed fails with an
explanation.
