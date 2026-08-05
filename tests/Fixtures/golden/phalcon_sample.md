---
hide:
    - navigation
---

!!! info "NOTE"

    All classes are prefixed with `Phalcon`


## Sample\Base

<span class="badge badge--abstract">Abstract</span>
[:material-github: Source on GitHub](https://github.com/phalcon/cphalcon/blob/5.0.x/phalcon/Sample/Base.zep){ .src-btn }

The abstract root.

<div class="api-tree" markdown>

- **`Phalcon\Sample\Base`**
    - [`Phalcon\Sample\Child`](#samplechild)

</div>

### Method Summary

<div class="api-list">
<a class="api-item" href="#samplebase-describe">
<code class="vis vis-public">public</code>
<code class="ret">string</code>
<code class="sig"><span class="sf">describe</span>(<span class="prm"><span class="st">string</span> <span class="sv">$text</span><span class="sm"> = &#039;none&#039;</span>,</span><span class="prm"><span class="st">int</span> <span class="sv">$depth</span><span class="sm"> = 0</span></span>)</code>
<span class="desc">Describes the subject.</span>
</a>
<a class="api-item" href="#samplebase-conceal">
<code class="vis vis-protected">protected</code>
<code class="sig"><span class="sf">conceal</span>( <span class="st">bool</span> <span class="sv">$flag</span> )</code>
</a>
</div>

### Constants

<div class="api-list">
<div class="api-item">
<code class="ret">int</code>
<code class="sig"><span class="sc">LIMIT</span><span class="sm"> = 10</span></code>
<span class="desc">How many.</span>
</div>
<div class="api-item">
<code class="ret">string</code>
<code class="sig"><span class="sc">MARKER</span></code>
</div>
</div>

### Properties

<div class="api-list">
<div class="api-item">
<code class="vis vis-protected">protected</code>
<code class="ret">string|null</code>
<code class="sig"><span class="sv">$label</span><span class="sm"> = null</span></code>
<span class="desc">The label.</span>
</div>
<div class="api-item">
<code class="vis vis-public">public</code>
<code class="ret">array</code>
<code class="sig"><span class="sv">$store</span></code>
</div>
</div>

### Methods

<div class="api-group">Public · 1</div>

#### `describe()` { #samplebase-describe }

```php
public function describe(
    string $text = 'none',
    int $depth = 0
): string;
```

Describes the subject.

<div class="api-group">Protected · 1</div>

#### `conceal()` { #samplebase-conceal }

```php
protected function conceal( bool $flag );
```


## Sample\Child

<span class="badge badge--final">Final</span>
[:material-github: Source on GitHub](https://github.com/phalcon/cphalcon/blob/5.0.x/phalcon/Sample/Child.zep){ .src-btn }

The concrete leaf.

<div class="api-tree" markdown>

- [`Phalcon\Sample\Base`](#samplebase)
    - **`Phalcon\Sample\Child`** - implements [`Phalcon\Sample\Contract`](#samplecontract)

</div>

__Uses__ `Phalcon\Sample\Base` · `Phalcon\Sample\Contract`
{ .api-uses }


## Sample\Contract

<span class="badge badge--interface">Interface</span>
[:material-github: Source on GitHub](https://github.com/phalcon/cphalcon/blob/5.0.x/phalcon/Sample/Contract.zep){ .src-btn }

What the leaf promises.

<div class="api-tree" markdown>

- **`Phalcon\Sample\Contract`**

</div>

### Method Summary

<div class="api-list">
<a class="api-item" href="#samplecontract-describe">
<code class="vis vis-public">public</code>
<code class="ret">string</code>
<code class="sig"><span class="sf">describe</span>( <span class="st">string</span> <span class="sv">$text</span> )</code>
<span class="desc">Describes the subject.</span>
</a>
</div>

### Methods

<div class="api-group">Public · 1</div>

#### `describe()` { #samplecontract-describe }

```php
public function describe( string $text ): string;
```

Describes the subject.


## Sample\Helper

<span class="badge badge--trait">Trait</span>
[:material-github: Source on GitHub](https://github.com/phalcon/cphalcon/blob/5.0.x/phalcon/Sample/Helper.zep){ .src-btn }

Shared behavior.

<div class="api-tree" markdown>

- **`Phalcon\Sample\Helper`**

</div>

__Used by__ [`Phalcon\Sample\Child`](#samplechild)
{ .api-used-by }


## Sample\Plain

<span class="badge badge--class">Class</span>
[:material-github: Source on GitHub](https://github.com/phalcon/cphalcon/blob/5.0.x/phalcon/Sample/Plain.zep){ .src-btn }

<div class="api-tree" markdown>

- **`Phalcon\Sample\Plain`**

</div>
