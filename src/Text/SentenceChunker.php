<?php

declare(strict_types=1);

namespace Displace\AI\Toolkit\Text;

/**
 * Sentence-aware chunker: text is split into sentences, then whole
 * sentences are packed into chunks of at most `$size` characters, with
 * the last `$overlapSentences` sentences of each chunk repeated at the
 * start of the next for context continuity. No sentence is ever split
 * across chunks — except a single sentence longer than an entire chunk,
 * which is hard-cut as a last resort.
 *
 * Sentence boundaries are detected with a pragmatic regex (terminal
 * `.`, `!`, `?`, or `…` followed by whitespace). Abbreviations like
 * "Dr." will occasionally over-split; for retrieval chunking that costs
 * little. Embedding-friendly output: chunks rejoin sentences with a
 * single space.
 */
final class SentenceChunker implements Chunker
{
    /** @var int<1, max> */
    private readonly int $size;

    /** @var int<0, max> */
    private readonly int $overlapSentences;

    /**
     * @param int $size             Maximum chunk size in characters; at least 1.
     * @param int $overlapSentences How many trailing sentences to repeat
     *                              at the start of the next chunk;
     *                              non-negative.
     */
    public function __construct(int $size = 1000, int $overlapSentences = 1)
    {
        if ($size < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1.');
        }

        if ($overlapSentences < 0) {
            throw new \InvalidArgumentException('Sentence overlap must be non-negative.');
        }

        $this->size = $size;
        $this->overlapSentences = $overlapSentences;
    }

    public function chunk(string $text): array
    {
        $sentences = $this->sentences($text);

        $chunks = [];
        /** @var list<string> $current */
        $current = [];

        foreach ($sentences as $sentence) {
            if ($current !== [] && self::joinedLength([...$current, $sentence]) > $this->size) {
                $chunks[] = implode(' ', $current);

                $current = $this->overlapSentences > 0
                    ? \array_slice($current, -$this->overlapSentences)
                    : [];

                // The seed must still leave room for the incoming sentence.
                while ($current !== [] && self::joinedLength([...$current, $sentence]) > $this->size) {
                    array_shift($current);
                }
            }

            $current[] = $sentence;
        }

        if ($current !== []) {
            $chunks[] = implode(' ', $current);
        }

        return $chunks;
    }

    /**
     * Split into trimmed sentences, hard-cutting any single sentence
     * longer than a whole chunk.
     *
     * @return list<string>
     */
    private function sentences(string $text): array
    {
        $normalized = trim($text);

        if ($normalized === '') {
            return [];
        }

        $split = preg_split('/(?<=[.!?…])\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = [];

        foreach ($split === false ? [$normalized] : $split as $sentence) {
            $sentence = trim($sentence);

            if ($sentence === '') {
                continue;
            }

            if (mb_strlen($sentence) > $this->size) {
                foreach (mb_str_split($sentence, $this->size) as $piece) {
                    $sentences[] = $piece;
                }

                continue;
            }

            $sentences[] = $sentence;
        }

        return $sentences;
    }

    /**
     * @param list<string> $sentences
     */
    private static function joinedLength(array $sentences): int
    {
        return mb_strlen(implode(' ', $sentences));
    }
}
