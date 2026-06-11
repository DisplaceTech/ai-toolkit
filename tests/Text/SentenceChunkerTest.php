<?php

declare(strict_types=1);

namespace Displace\AI\Toolkit\Tests\Text;

use Displace\AI\Toolkit\Text\SentenceChunker;
use PHPUnit\Framework\TestCase;

final class SentenceChunkerTest extends TestCase
{
    public function testShortTextIsOneChunk(): void
    {
        $chunks = (new SentenceChunker(size: 100))->chunk('One sentence. Another sentence.');

        self::assertSame(['One sentence. Another sentence.'], $chunks);
    }

    public function testEmptyAndWhitespaceTextsYieldNoChunks(): void
    {
        $chunker = new SentenceChunker();

        self::assertSame([], $chunker->chunk(''));
        self::assertSame([], $chunker->chunk("   \n  "));
    }

    public function testSentencesAreNeverSplitAcrossChunks(): void
    {
        $text = 'Alpha beta gamma. Delta epsilon zeta. Eta theta iota. Kappa lambda mu.';

        $chunks = (new SentenceChunker(size: 40, overlapSentences: 0))->chunk($text);

        self::assertGreaterThan(1, \count($chunks));

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(40, mb_strlen($chunk));
            // Every chunk ends at a sentence boundary.
            self::assertMatchesRegularExpression('/[.!?…]$/u', $chunk);
        }
    }

    public function testEverySentenceAppearsInSomeChunk(): void
    {
        $sentences = array_map(static fn(int $i): string => "Sentence number $i ends here.", range(1, 12));
        $text = implode(' ', $sentences);

        $chunks = (new SentenceChunker(size: 70, overlapSentences: 1))->chunk($text);
        $joined = implode(' ', $chunks);

        foreach ($sentences as $sentence) {
            self::assertStringContainsString($sentence, $joined);
        }
    }

    public function testOverlapRepeatsTrailingSentences(): void
    {
        $text = 'First point made. Second point made. Third point made. Fourth point made.';

        $chunks = (new SentenceChunker(size: 45, overlapSentences: 1))->chunk($text);

        self::assertGreaterThan(1, \count($chunks));

        for ($i = 1; $i < \count($chunks); $i++) {
            // Each chunk opens with the closing sentence of its predecessor.
            $firstSentence = explode('.', $chunks[$i])[0] . '.';
            self::assertStringEndsWith($firstSentence, $chunks[$i - 1]);
        }
    }

    public function testZeroOverlapNeverRepeatsContent(): void
    {
        $text = 'Aaa bbb. Ccc ddd. Eee fff. Ggg hhh.';

        $chunks = (new SentenceChunker(size: 20, overlapSentences: 0))->chunk($text);

        self::assertSame($text, implode(' ', $chunks));
    }

    public function testSentenceLongerThanChunkIsHardCut(): void
    {
        $monster = str_repeat('verylongword ', 10);   // ~130 chars, no terminal punctuation

        $chunks = (new SentenceChunker(size: 50, overlapSentences: 0))->chunk($monster);

        self::assertGreaterThan(1, \count($chunks));

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(50, mb_strlen($chunk));
        }
    }

    public function testHandlesQuestionAndExclamationBoundaries(): void
    {
        $text = 'Is this a question? Yes! And this is a statement.';

        $chunks = (new SentenceChunker(size: 25, overlapSentences: 0))->chunk($text);

        self::assertSame(['Is this a question? Yes!', 'And this is a statement.'], $chunks);
    }

    public function testMultibyteSentences(): void
    {
        $text = 'これは最初の文です。 これは二番目の文です。 これは三番目の文です。';

        $chunks = (new SentenceChunker(size: 25, overlapSentences: 0))->chunk($text);

        self::assertNotEmpty($chunks);

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(25, mb_strlen($chunk));
            self::assertTrue(mb_check_encoding($chunk, 'UTF-8'));
        }
    }

    public function testRejectsNonPositiveSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SentenceChunker(size: 0);
    }

    public function testRejectsNegativeOverlap(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SentenceChunker(size: 10, overlapSentences: -1);
    }
}
