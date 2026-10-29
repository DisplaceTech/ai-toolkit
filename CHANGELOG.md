# Changelog

All notable changes to ai-toolkit are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
this project adheres to [Semantic Versioning](https://semver.org/).
Chunk-boundary algorithm changes are always minor releases, even when
signature-compatible. Dates are tag dates.

## [0.1.1] - Unreleased

No code changes; chunk boundaries are identical to 0.1.0.

### Changed

- CI runs weekly as well as on push, and tests PHP 8.5 on macOS arm64
  again; setup-php is unpinned from 2.36.0, which carried known
  advisories. Dependabot keeps the dev toolchain and actions current.
- README: Status section, stack table, links to the write-up; PLAN.md
  reframed as design notes; remaining ideas moved to `future` issues.

## [0.1.0] - 2026-06-11

First release.

### Added

- Text chunkers behind one `Chunker` interface, sized in characters
  (multibyte-aware) with a hard `mb_strlen($chunk) <= $size` invariant:
  `RecursiveCharacterChunker`, `SentenceChunker`, `FixedSizeChunker`.
- `Vector\Packed`: `pack()` / `unpack()`, `dot()`, `cosine()`, `norm()`,
  per-vector `normalize()`, and Matryoshka `truncate()` over packed
  float32 buffers.

[0.1.1]: https://github.com/DisplaceTech/ai-toolkit/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/DisplaceTech/ai-toolkit/releases/tag/v0.1.0
