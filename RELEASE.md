# Cutting a release

Pure-PHP package: a release is a git tag plus Packagist metadata.
No binaries, no release workflow.

1. **Green CI on `main`** — cs, PHPStan, tests across the matrix.
2. **Update `PLAN.md`** — check off shipped items, note the version.
3. **Tag**, annotated, `v`-prefixed semver:

   ```sh
   git tag -a v0.1.0 -m "v0.1.0 — chunkers + packed-vector math"
   git push origin v0.1.0
   ```

4. **Packagist** — first release only: submit
   `https://github.com/DisplaceTech/ai-toolkit` at
   [packagist.org/packages/submit](https://packagist.org/packages/submit)
   under the `displace` vendor, then enable the GitHub hook so later
   tags publish automatically. Subsequent releases: pushing the tag is
   the whole job.
5. **Verify** — `composer require displace/ai-toolkit:^0.1` in a
   scratch directory resolves and
   `(new RecursiveCharacterChunker())->chunk('hello world')` runs.

## Versioning policy

Semver. Chunk-boundary algorithm changes are **minor** versions even
when BC-compatible in signature — downstream indexes built on old
boundaries won't match new ones, and that deserves a visible version
step. Patch versions never change chunk output or packed-buffer bytes.
