# ai-toolkit — design notes

Part of the [phpai stack](https://github.com/DisplaceTech). Pure-PHP
utilities every doc example wants; boring on purpose. No models, no
extensions, no framework ambitions.

This file holds design notes and decision history, not a to-do list.
What shipped is in [`CHANGELOG.md`](CHANGELOG.md); remaining ideas are
[`future` issues](https://github.com/DisplaceTech/ai-toolkit/issues?q=is%3Aissue+is%3Aopen+label%3Afuture), each with its reasoning.

## v0.1.0 scope (shipped 2026-06-11)

- [x] `Vector\Packed` — pack/unpack, dot, cosine, norm, per-vector
      normalize, MRL truncation over packed float32 buffers.
- [x] `Text\Chunker` interface.
- [x] `Text\FixedSizeChunker` — sliding character window.
- [x] `Text\RecursiveCharacterChunker` — coarsest-first structural
      splitting with overlap re-seeding.
- [x] `Text\SentenceChunker` — whole-sentence packing, sentence-count
      overlap.
- [x] CI (cs + PHPStan max + PHPUnit, PHP 8.3/8.4/8.5 × Linux/macOS).
- [x] Tag v0.1.0, register on Packagist (see RELEASE.md) — shipped 2026-06-11.
- [x] Rewrite ext-infer / ext-turbovec doc examples on these helpers
      where it clarifies — done 2026-06-11 (recipes in both repos).

## Remaining work

Tracked as [`future` issues](https://github.com/DisplaceTech/ai-toolkit/issues?q=is%3Aissue+is%3Aopen+label%3Afuture):

- [#1](https://github.com/DisplaceTech/ai-toolkit/issues/1) Markdown-aware chunker
- [#2](https://github.com/DisplaceTech/ai-toolkit/issues/2) `Packed::topK()` brute-force helper, only if a doc example needs it
- [#3](https://github.com/DisplaceTech/ai-toolkit/issues/3) Token-count estimation helper, only with a credible accuracy story

## Design notes

- **Characters, not tokens.** Token budgets are tokenizer-specific;
  character budgets are model-independent and close enough for
  retrieval chunking. A tokenizer utility belongs in ext-infer (HF
  `tokenizers` crate) per the umbrella roadmap, not here.
- **Chunk size is a hard invariant.** Every chunker guarantees
  `mb_strlen($chunk) <= $size` — oversized content is recursively
  split or, as a last resort, hard-cut. Overlap re-seeding shrinks to
  make room rather than overflow.
- **`Packed` mirrors the stack-wide data contract** (see ARCHITECTURE.md
  in the umbrella repo): packed little-endian float32, batches by
  concatenation. It exists so pure-PHP code can participate without
  loading an extension — not as a SIMD replacement.
