<?php

declare(strict_types=1);

namespace Displace\AI\Toolkit\Tests\Text;

use Displace\AI\Toolkit\Text\RecursiveCharacterChunker;
use PHPUnit\Framework\TestCase;

final class RecursiveCharacterChunkerTest extends TestCase
{
    public function testShortTextIsOneChunk(): void
    {
        $chunks = (new RecursiveCharacterChunker(size: 100, overlap: 0))->chunk('short text');

        self::assertSame(['short text'], $chunks);
    }

    public function testEmptyAndWhitespaceTextsYieldNoChunks(): void
    {
        $chunker = new RecursiveCharacterChunker();

        self::assertSame([], $chunker->chunk(''));
        self::assertSame([], $chunker->chunk("  \n\n  "));
    }

    public function testPrefersParagraphBoundaries(): void
    {
        $text = "First paragraph here.\n\nSecond paragraph here.\n\nThird paragraph here.";

        $chunks = (new RecursiveCharacterChunker(size: 30, overlap: 0))->chunk($text);

        // Each paragraph fits in a chunk on its own, so no paragraph is split.
        self::assertSame(
            ["First paragraph here.\n\n", "Second paragraph here.\n\n", 'Third paragraph here.'],
            $chunks,
        );
    }

    public function testPacksSmallPiecesTogetherUpToSize(): void
    {
        $text = "one.\n\ntwo.\n\nthree.\n\nfour.";

        $chunks = (new RecursiveCharacterChunker(size: 14, overlap: 0))->chunk($text);

        self::assertSame(["one.\n\ntwo.\n\n", "three.\n\nfour."], $chunks);
    }

    public function testFallsBackToFinerSeparatorsForOversizedPieces(): void
    {
        $text = 'word ' . str_repeat('a', 40) . ' more words here';

        $chunks = (new RecursiveCharacterChunker(size: 20, overlap: 0))->chunk($text);

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(20, mb_strlen($chunk));
        }

        // The 40-char unbroken run had to be hard-cut at character level.
        self::assertContains(str_repeat('a', 20), $chunks);
    }

    public function testChunksNeverExceedTheConfiguredSize(): void
    {
        $text = implode("\n\n", array_fill(0, 10, implode(' ', array_fill(0, 30, 'word'))));

        $chunks = (new RecursiveCharacterChunker(size: 50, overlap: 10))->chunk($text);

        self::assertNotEmpty($chunks);

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(50, mb_strlen($chunk));
        }
    }

    public function testNoInputContentIsLost(): void
    {
        $text = "Alpha beta gamma delta.\n\nEpsilon zeta eta theta iota kappa. "
            . 'Lambda mu nu xi omicron pi rho sigma tau upsilon phi chi psi omega.';

        $chunks = (new RecursiveCharacterChunker(size: 40, overlap: 0))->chunk($text);

        // With zero overlap, concatenating the chunks reproduces the input.
        self::assertSame($text, implode('', $chunks));
    }

    public function testOverlapCarriesTrailingContextIntoTheNextChunk(): void
    {
        $words = implode(' ', array_map(static fn(int $i): string => "w$i", range(1, 30)));

        $chunks = (new RecursiveCharacterChunker(size: 40, overlap: 12))->chunk($words);

        self::assertGreaterThan(1, \count($chunks));

        for ($i = 1; $i < \count($chunks); $i++) {
            $seed = mb_substr($chunks[$i], 0, mb_strpos($chunks[$i], ' ') ?: null);
            // Every chunk after the first starts with text already seen at
            // the tail of the previous chunk.
            self::assertStringContainsString($seed, $chunks[$i - 1]);
        }
    }

    public function testMultibyteTextSplitsOnCharacterBoundaries(): void
    {
        $text = str_repeat('これは日本語の文章です。', 20);

        $chunks = (new RecursiveCharacterChunker(size: 30, overlap: 5))->chunk($text);

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(30, mb_strlen($chunk));
            self::assertTrue(mb_check_encoding($chunk, 'UTF-8'));
        }
    }

    public function testCustomSeparators(): void
    {
        $chunks = (new RecursiveCharacterChunker(size: 10, overlap: 0, separators: ['|', '']))
            ->chunk('aaa|bbb|ccc|ddd');

        self::assertSame(['aaa|bbb|', 'ccc|ddd'], $chunks);
    }

    public function testRejectsNonPositiveSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecursiveCharacterChunker(size: 0);
    }

    public function testRejectsOverlapNotSmallerThanSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecursiveCharacterChunker(size: 10, overlap: 12);
    }
}
