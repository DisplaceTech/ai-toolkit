<?php

declare(strict_types=1);

namespace Displace\AI\Toolkit\Text;

/**
 * Sliding-window chunker: fixed-size character windows with a fixed
 * overlap, no respect for word or sentence boundaries. The dumbest
 * possible chunker — and the right baseline when the input has no
 * useful structure (logs, minified text, OCR output).
 *
 * Chunks are verbatim slices: concatenating them (minus the overlaps)
 * reproduces the input exactly.
 */
final class FixedSizeChunker implements Chunker
{
    /**
     * @param int $size    Window size in characters; at least 1.
     * @param int $overlap Characters shared between consecutive chunks;
     *                     non-negative and strictly less than `$size`.
     */
    public function __construct(
        private readonly int $size = 1000,
        private readonly int $overlap = 200,
    ) {
        if ($size < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1.');
        }

        if ($overlap < 0 || $overlap >= $size) {
            throw new \InvalidArgumentException('Overlap must be non-negative and smaller than the chunk size.');
        }
    }

    public function chunk(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $length = mb_strlen($text);
        $chunks = [];
        $start = 0;

        while ($start < $length) {
            $chunks[] = mb_substr($text, $start, $this->size);

            if ($start + $this->size >= $length) {
                break; // this window reached the end — a further step would be pure overlap
            }

            $start += $this->size - $this->overlap;
        }

        return $chunks;
    }
}
