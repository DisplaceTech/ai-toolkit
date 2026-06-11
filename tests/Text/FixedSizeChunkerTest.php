<?php

declare(strict_types=1);

namespace Displace\AI\Toolkit\Tests\Text;

use Displace\AI\Toolkit\Text\FixedSizeChunker;
use PHPUnit\Framework\TestCase;

final class FixedSizeChunkerTest extends TestCase
{
    public function testShortTextIsOneChunk(): void
    {
        $chunks = (new FixedSizeChunker(size: 100, overlap: 10))->chunk('short text');

        self::assertSame(['short text'], $chunks);
    }

    public function testEmptyAndWhitespaceTextsYieldNoChunks(): void
    {
        $chunker = new FixedSizeChunker();

        self::assertSame([], $chunker->chunk(''));
        self::assertSame([], $chunker->chunk("  \n\t  "));
    }

    public function testChunksNeverExceedTheConfiguredSize(): void
    {
        $chunks = (new FixedSizeChunker(size: 10, overlap: 3))->chunk(str_repeat('abcde ', 20));

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(10, mb_strlen($chunk));
        }
    }

    public function testConsecutiveChunksShareTheConfiguredOverlap(): void
    {
        $text = implode('', range('a', 'z'));   // 26 distinct chars

        $chunks = (new FixedSizeChunker(size: 10, overlap: 4))->chunk($text);

        for ($i = 1; $i < \count($chunks); $i++) {
            self::assertSame(
                mb_substr($chunks[$i - 1], -4),
                mb_substr($chunks[$i], 0, 4),
            );
        }
    }

    public function testEveryInputCharacterAppearsInSomeChunk(): void
    {
        $text = str_repeat('0123456789', 10);

        $chunks = (new FixedSizeChunker(size: 10, overlap: 3))->chunk($text);

        $reassembled = $chunks[0];

        for ($i = 1; $i < \count($chunks); $i++) {
            $reassembled .= mb_substr($chunks[$i], 3);   // drop the overlap
        }

        self::assertSame($text, $reassembled);
    }

    public function testMultibyteTextIsSlicedOnCharacterBoundaries(): void
    {
        $text = str_repeat('日本語のテキスト', 10);   // 80 chars, 3 bytes each

        $chunks = (new FixedSizeChunker(size: 12, overlap: 2))->chunk($text);

        foreach ($chunks as $chunk) {
            self::assertLessThanOrEqual(12, mb_strlen($chunk));
            self::assertTrue(mb_check_encoding($chunk, 'UTF-8'));
        }
    }

    public function testNoTrailingChunkThatIsPureOverlap(): void
    {
        // 13 chars, size 10, overlap 5 → window 2 (chars 5..13) reaches the
        // end; a third window would replay chars 10..13 already emitted.
        $chunks = (new FixedSizeChunker(size: 10, overlap: 5))->chunk('abcdefghijklm');

        self::assertSame(['abcdefghij', 'fghijklm'], $chunks);
    }

    public function testRejectsNonPositiveSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new FixedSizeChunker(size: 0);
    }

    public function testRejectsOverlapNotSmallerThanSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new FixedSizeChunker(size: 10, overlap: 10);
    }
}
