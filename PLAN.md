# ai-toolkit — Plan

Part of the [phpai stack](https://github.com/DisplaceTech). Pure-PHP
utilities every doc example wants; boring on purpose. No models, no
extensions, no framework ambitions.

## v0.1.0 scope (current)

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
- [ ] Rewrite ext-infer / ext-turbovec doc examples on these helpers
      where it clarifies.

## Later (unscheduled)

- Markdown-aware chunker (split on headings before paragraphs) if doc
  examples keep wanting it.
- `Packed::topK()` brute-force search helper — only if a doc example
  needs search without ext-turbovec; otherwise it competes with the
  extension for no reason.
- Token-count estimation helper — only with a credible accuracy story;
  ~4 chars/token guidance lives in the README until then.

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
