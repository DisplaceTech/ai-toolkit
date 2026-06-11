<h1 align="center">ai-toolkit</h1>

<p align="center">
  <strong>Pure-PHP utilities for local-first AI pipelines.</strong><br>
  Text chunkers, packed-vector math, Matryoshka truncation — boring on purpose.
</p>

<p align="center">
  <a href="https://github.com/DisplaceTech/ai-toolkit/actions/workflows/ci.yml"><img src="https://github.com/DisplaceTech/ai-toolkit/actions/workflows/ci.yml/badge.svg" alt="CI" /></a>
  <a href="https://packagist.org/packages/displace/ai-toolkit"><img src="https://img.shields.io/packagist/v/displace/ai-toolkit" alt="Packagist" /></a>
  <img src="https://img.shields.io/badge/PHP-8.3%20%7C%208.4%20%7C%208.5-777BB4?logo=php&logoColor=white" alt="PHP 8.3 / 8.4 / 8.5" />
  <img src="https://img.shields.io/badge/dependencies-mbstring%20only-brightgreen" alt="mbstring only" />
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-green" alt="MIT License" /></a>
</p>

---

## What is ai-toolkit?

The plumbing every embed-and-search pipeline needs and no one wants to
rewrite: split documents into chunks, move vectors around in the packed
float32 format, slice Matryoshka embeddings down to size. Pure PHP,
no extension required, no framework, no model downloads.

```sh
composer require displace/ai-toolkit
```

It pairs with [ext-infer](https://github.com/DisplaceTech/ext-infer)
(embeddings), [ext-turbovec](https://github.com/DisplaceTech/ext-turbovec)
(vector search), and the
[ai-contracts](https://github.com/DisplaceTech/ai-contracts) interfaces —
but depends on none of them.

## Chunkers

Three strategies behind one `Chunker` interface, sized in characters
(multibyte-aware):

```php
use Displace\AI\Toolkit\Text\RecursiveCharacterChunker;

$chunker = new RecursiveCharacterChunker(size: 1000, overlap: 200);

foreach ($chunker->chunk($markdown) as $chunk) {
    // embed → index
}
```

| Chunker | Strategy | Reach for it when |
|---|---|---|
| `RecursiveCharacterChunker` | Split on paragraphs first, then lines, sentences, words; hard-cut last | Prose, markdown, HTML-stripped text — **the default** |
| `SentenceChunker` | Pack whole sentences; never splits mid-sentence; overlap counted in sentences | Transcripts and clean copy where sentence integrity matters |
| `FixedSizeChunker` | Sliding character window, verbatim slices | Logs, minified or OCR text with no useful structure |

## Packed-vector math

Vectors in the Displace stack travel as **packed little-endian float32
binary strings** — the output of `pack('g*', ...$floats)`, batches by
plain concatenation. `Packed` is the pure-PHP companion for glue code
and tests:

```php
use Displace\AI\Toolkit\Vector\Packed;

$a = Packed::pack([0.12, 0.48, /* ... */]);   // floats → packed buffer
$b = Packed::pack([0.33, 0.19, /* ... */]);

Packed::cosine($a, $b);                       // similarity in [-1, 1]
Packed::dot($a, $b);                          // inner product
Packed::norm($a);                             // L2 norm
Packed::normalize($batch, dim: 1024);         // per-vector unit length
Packed::unpack($a, dim: 1024);                // packed buffer → floats
```

### Matryoshka (MRL) truncation

MRL-trained embedding models (Qwen3-Embedding, ...) pack the most
important information into the leading coordinates, so a prefix of the
vector is itself a usable embedding. Trade recall for a smaller index by
slicing — no re-embedding required:

```php
// 1024-dim Qwen3 embeddings down to 256-dim, renormalized per vector:
$small = Packed::truncate($vectors, fromDim: 1024, toDim: 256);
```

Only valid for MRL-trained models; truncating an ordinary embedding
just throws information away.

## On the hot path

`Packed` is for correctness, not speed — it's pure PHP. When
ext-turbovec is loaded, prefer its native `Displace\Vector\Vectors`
pack/unpack and let the index do the scoring; when you only need
similarity between a handful of vectors, `Packed` is plenty.

## Deliberately out of scope

**Token-based chunking** — token counts are model-specific and need a
tokenizer; character budgets are model-independent and close enough for
retrieval (rule of thumb: ~4 characters per English token) ·
**embedding generation** — that's ext-infer or your API client ·
**vector storage and ANN search** — that's ext-turbovec or your
database · **document loaders/parsers** (PDF, HTML, ...) — bring your
own text · **an orchestration framework** — chains and agents belong
to the frameworks.

## License

[MIT](LICENSE) &copy; 2026 Eric Mann / Displace Technologies
