<?php

declare(strict_types=1);

namespace Displace\AI\Toolkit\Text;

/**
 * Structure-aware chunker in the LangChain `RecursiveCharacterTextSplitter`
 * tradition: try to split on the coarsest separator first (paragraphs),
 * fall back to finer ones (lines, sentences, words) only where a piece is
 * still too large, and hard-cut characters as a last resort. The result
 * keeps semantically related text together far better than a fixed
 * window — this is the default chunker to reach for with prose.
 *
 * Separators are consumed in order; each piece keeps its trailing
 * separator so chunk boundaries never eat characters. Overlap is applied
 * at merge time by re-seeding each new chunk with the trailing pieces of
 * the previous one, up to `$overlap` characters.
 */
final class RecursiveCharacterChunker implements Chunker
{
    public const DEFAULT_SEPARATORS = ["\n\n", "\n", '. ', ' ', ''];

    /** @var list<string> */
    private readonly array $separators;

    /**
     * @param int          $size       Maximum chunk size in characters; at least 1.
     * @param int          $overlap    Maximum characters of trailing context
     *                                 re-seeded into the next chunk;
     *                                 non-negative and strictly less than `$size`.
     * @param list<string> $separators Coarsest-first; `''` means
     *                                 character-level splitting and is
     *                                 appended automatically if missing.
     */
    public function __construct(
        private readonly int $size = 1000,
        private readonly int $overlap = 200,
        array $separators = self::DEFAULT_SEPARATORS,
    ) {
        if ($size < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1.');
        }

        if ($overlap < 0 || $overlap >= $size) {
            throw new \InvalidArgumentException('Overlap must be non-negative and smaller than the chunk size.');
        }

        if (!\in_array('', $separators, true)) {
            $separators[] = '';
        }

        $this->separators = $separators;
    }

    public function chunk(string $text): array
    {
        $chunks = [];

        foreach ($this->split($text, $this->separators) as $chunk) {
            if (trim($chunk) !== '') {
                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }

    /**
     * @param list<string> $separators
     *
     * @return list<string>
     */
    private function split(string $text, array $separators): array
    {
        if (mb_strlen($text) <= $this->size) {
            return [$text];
        }

        [$separator, $remaining] = $this->pickSeparator($text, $separators);

        $pieces = $separator === ''
            ? mb_str_split($text)
            : self::explodeKeepingSeparator($text, $separator);

        return $this->merge($pieces, $remaining);
    }

    /**
     * First separator actually present in the text wins; `''` always
     * matches. Returns the winner and the finer separators after it.
     *
     * @param list<string> $separators
     *
     * @return array{string, list<string>}
     */
    private function pickSeparator(string $text, array $separators): array
    {
        foreach ($separators as $i => $candidate) {
            if ($candidate === '' || str_contains($text, $candidate)) {
                return [$candidate, \array_slice($separators, $i + 1)];
            }
        }

        return ['', []];
    }

    /**
     * Greedily pack pieces into chunks of at most `$size` characters,
     * recursing into any piece that is itself oversized, and seeding each
     * new chunk with up to `$overlap` characters of trailing pieces.
     *
     * @param list<string> $pieces
     * @param list<string> $finerSeparators
     *
     * @return list<string>
     */
    private function merge(array $pieces, array $finerSeparators): array
    {
        $chunks = [];
        $current = [];
        $currentLength = 0;

        foreach ($pieces as $piece) {
            $pieceLength = mb_strlen($piece);

            if ($pieceLength > $this->size) {
                // Too big to pack whole: flush, then recurse with finer separators.
                if ($current !== []) {
                    $chunks[] = implode('', $current);
                    $current = [];
                    $currentLength = 0;
                }

                foreach ($this->split($piece, $finerSeparators) as $sub) {
                    $chunks[] = $sub;
                }

                continue;
            }

            if ($current !== [] && $currentLength + $pieceLength > $this->size) {
                $chunks[] = implode('', $current);
                [$current, $currentLength] = $this->overlapTail($current);

                // The seed must still leave room for the incoming piece.
                while ($current !== [] && $currentLength + $pieceLength > $this->size) {
                    $currentLength -= mb_strlen(array_shift($current));
                }
            }

            $current[] = $piece;
            $currentLength += $pieceLength;
        }

        if ($current !== []) {
            $chunks[] = implode('', $current);
        }

        return $chunks;
    }

    /**
     * Longest run of trailing pieces totalling at most `$overlap` characters.
     *
     * @param list<string> $pieces
     *
     * @return array{list<string>, int}
     */
    private function overlapTail(array $pieces): array
    {
        $tail = [];
        $length = 0;

        for ($i = \count($pieces) - 1; $i >= 0; $i--) {
            $pieceLength = mb_strlen($pieces[$i]);

            if ($length + $pieceLength > $this->overlap) {
                break;
            }

            array_unshift($tail, $pieces[$i]);
            $length += $pieceLength;
        }

        return [$tail, $length];
    }

    /**
     * `explode()`, but each piece except the last keeps its trailing
     * separator — re-joining the pieces reproduces the input exactly.
     *
     * @param non-empty-string $separator
     *
     * @return list<string>
     */
    private static function explodeKeepingSeparator(string $text, string $separator): array
    {
        $pieces = explode($separator, $text);
        $last = \count($pieces) - 1;

        foreach ($pieces as $i => $piece) {
            if ($i < $last) {
                $pieces[$i] = $piece . $separator;
            }
        }

        return $pieces;
    }
}
