<?php

declare(strict_types=1);

namespace Displace\AI\Toolkit\Vector;

/**
 * Pure-PHP math over packed little-endian float32 vectors — the
 * `pack('g*', ...$floats)` format every vector in the Displace stack
 * travels in. Batches are plain concatenation, so a "vectors" argument
 * here is one or more vectors back to back.
 *
 * These helpers exist for glue code and tests; on a hot path with
 * ext-turbovec loaded, prefer its native `Vectors` pack/unpack and let
 * the index do the scoring.
 */
final class Packed
{
    private function __construct() {}

    /**
     * Pack a flat list of floats — byte-identical to
     * `pack('g*', ...$floats)`. PHP floats are doubles and narrow to
     * float32 here, exactly as `pack('g*')` would.
     *
     * @param list<int|float> $floats
     */
    public static function pack(array $floats): string
    {
        return pack('g*', ...$floats);
    }

    /**
     * Unpack a packed buffer back into a flat list of floats. `$dim`
     * validates that the payload holds whole vectors; use
     * `array_chunk($floats, $dim)` for per-vector rows.
     *
     * @return list<float>
     *
     * @throws \InvalidArgumentException If `$dim < 1` or the payload is
     *                                   not a whole number of vectors.
     */
    public static function unpack(string $packed, int $dim): array
    {
        if ($dim < 1) {
            throw new \InvalidArgumentException('Dimensionality must be at least 1.');
        }

        if (\strlen($packed) % (4 * $dim) !== 0) {
            throw new \InvalidArgumentException(\sprintf(
                'Payload of %d bytes is not a whole number of %d-dimensional float32 vectors.',
                \strlen($packed),
                $dim,
            ));
        }

        return self::floats($packed);
    }

    /**
     * Dot product of two packed vectors of equal dimensionality.
     *
     * @throws \InvalidArgumentException If either buffer is empty, ragged,
     *                                   or the lengths differ.
     */
    public static function dot(string $a, string $b): float
    {
        if ($a === '' || \strlen($a) % 4 !== 0 || \strlen($a) !== \strlen($b)) {
            throw new \InvalidArgumentException(
                'dot() needs two non-empty packed vectors of the same dimensionality.',
            );
        }

        $fa = self::floats($a);
        $fb = self::floats($b);
        $sum = 0.0;

        foreach ($fa as $i => $value) {
            $sum += $value * $fb[$i];
        }

        return $sum;
    }

    /**
     * Cosine similarity of two packed vectors of equal dimensionality.
     * Returns `0.0` when either vector has zero norm.
     *
     * @throws \InvalidArgumentException If either buffer is empty, ragged,
     *                                   or the lengths differ.
     */
    public static function cosine(string $a, string $b): float
    {
        $dot = self::dot($a, $b);
        $norms = self::norm($a) * self::norm($b);

        return $norms === 0.0 ? 0.0 : $dot / $norms;
    }

    /**
     * L2 norm of one packed vector.
     *
     * @throws \InvalidArgumentException If the buffer is empty or ragged.
     */
    public static function norm(string $vector): float
    {
        if ($vector === '' || \strlen($vector) % 4 !== 0) {
            throw new \InvalidArgumentException('norm() needs one non-empty packed vector.');
        }

        $sum = 0.0;

        foreach (self::floats($vector) as $value) {
            $sum += $value * $value;
        }

        return sqrt($sum);
    }

    /**
     * Scale each vector in a packed batch to unit length. Zero vectors
     * pass through unchanged (there is no direction to preserve).
     *
     * @throws \InvalidArgumentException If `$dim < 1` or the payload is
     *                                   not a whole number of vectors.
     */
    public static function normalize(string $vectors, int $dim): string
    {
        $out = '';

        foreach (self::eachVector($vectors, $dim) as $vector) {
            $norm = self::norm($vector);
            $out .= $norm === 0.0
                ? $vector
                : pack('g*', ...array_map(static fn(float $v): float => $v / $norm, self::floats($vector)));
        }

        return $out;
    }

    /**
     * Matryoshka (MRL) truncation: keep the first `$toDim` coordinates of
     * each `$fromDim`-dimensional vector in a packed batch. Only valid
     * for MRL-trained embeddings (Qwen3-Embedding, ...), where prefixes
     * of the vector are themselves usable embeddings.
     *
     * Truncation destroys unit length, so the result is renormalized per
     * vector by default — pass `renormalize: false` if you plan to batch
     * the normalization elsewhere.
     *
     * @throws \InvalidArgumentException If the dims are out of order or
     *                                   the payload is ragged.
     */
    public static function truncate(string $vectors, int $fromDim, int $toDim, bool $renormalize = true): string
    {
        if ($toDim < 1 || $toDim > $fromDim) {
            throw new \InvalidArgumentException(\sprintf(
                'Target dimensionality must be between 1 and %d, got %d.',
                $fromDim,
                $toDim,
            ));
        }

        $out = '';

        foreach (self::eachVector($vectors, $fromDim) as $vector) {
            $out .= substr($vector, 0, 4 * $toDim);
        }

        return $renormalize ? self::normalize($out, $toDim) : $out;
    }

    /**
     * Iterate a packed batch one vector (= one `4 * $dim` byte slice) at
     * a time, validating the payload shape up front.
     *
     * @return iterable<string>
     */
    private static function eachVector(string $vectors, int $dim): iterable
    {
        if ($dim < 1) {
            throw new \InvalidArgumentException('Dimensionality must be at least 1.');
        }

        $bytesPerVector = 4 * $dim;

        if (\strlen($vectors) % $bytesPerVector !== 0) {
            throw new \InvalidArgumentException(\sprintf(
                'Payload of %d bytes is not a whole number of %d-dimensional float32 vectors.',
                \strlen($vectors),
                $dim,
            ));
        }

        for ($offset = 0; $offset < \strlen($vectors); $offset += $bytesPerVector) {
            yield substr($vectors, $offset, $bytesPerVector);
        }
    }

    /**
     * @return list<float>
     */
    private static function floats(string $packed): array
    {
        $raw = unpack('g*', $packed);

        if ($raw === false) {
            throw new \InvalidArgumentException('Buffer is not a valid packed float32 payload.');
        }

        $floats = [];

        foreach ($raw as $value) {
            if (!\is_float($value)) {
                throw new \InvalidArgumentException('Buffer is not a valid packed float32 payload.');
            }

            $floats[] = $value;
        }

        return $floats;
    }
}
