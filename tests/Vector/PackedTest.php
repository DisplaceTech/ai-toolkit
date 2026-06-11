<?php

declare(strict_types=1);

namespace Displace\AI\Toolkit\Tests\Vector;

use Displace\AI\Toolkit\Vector\Packed;
use PHPUnit\Framework\TestCase;

final class PackedTest extends TestCase
{
    public function testPackMatchesNativePack(): void
    {
        $floats = [1.0, -0.5, 0.25, 3.75];

        self::assertSame(pack('g*', ...$floats), Packed::pack($floats));
    }

    public function testPackUnpackRoundTripsF32RepresentableValues(): void
    {
        $floats = [0.0, 1.0, -1.0, 0.5, -127.25, 65536.0];

        self::assertSame($floats, Packed::unpack(Packed::pack($floats), 6));
    }

    public function testUnpackValidatesWholeVectors(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Packed::unpack(Packed::pack([1.0, 2.0, 3.0]), 2);
    }

    public function testUnpackRejectsNonPositiveDim(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Packed::unpack('', 0);
    }

    public function testDotOfKnownVectors(): void
    {
        $a = Packed::pack([1.0, 2.0, 3.0]);
        $b = Packed::pack([4.0, -5.0, 6.0]);

        self::assertEqualsWithDelta(4.0 - 10.0 + 18.0, Packed::dot($a, $b), 1e-6);
    }

    public function testDotRejectsLengthMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Packed::dot(Packed::pack([1.0, 2.0]), Packed::pack([1.0, 2.0, 3.0]));
    }

    public function testDotRejectsEmptyBuffers(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Packed::dot('', '');
    }

    public function testNormOfPythagoreanVector(): void
    {
        self::assertEqualsWithDelta(5.0, Packed::norm(Packed::pack([3.0, 4.0])), 1e-6);
    }

    public function testCosineOfParallelVectorsIsOne(): void
    {
        $a = Packed::pack([1.0, 2.0, 3.0]);
        $b = Packed::pack([2.0, 4.0, 6.0]);

        self::assertEqualsWithDelta(1.0, Packed::cosine($a, $b), 1e-6);
    }

    public function testCosineOfOrthogonalVectorsIsZero(): void
    {
        $a = Packed::pack([1.0, 0.0]);
        $b = Packed::pack([0.0, 1.0]);

        self::assertEqualsWithDelta(0.0, Packed::cosine($a, $b), 1e-6);
    }

    public function testCosineWithZeroVectorIsZero(): void
    {
        $a = Packed::pack([0.0, 0.0]);
        $b = Packed::pack([1.0, 1.0]);

        self::assertSame(0.0, Packed::cosine($a, $b));
    }

    public function testNormalizeProducesUnitVectors(): void
    {
        $normalized = Packed::normalize(Packed::pack([3.0, 4.0]), 2);

        self::assertEqualsWithDelta(1.0, Packed::norm($normalized), 1e-6);
        self::assertEqualsWithDelta([0.6, 0.8], Packed::unpack($normalized, 2), 1e-6);
    }

    public function testNormalizeHandlesBatchesPerVector(): void
    {
        $batch = Packed::pack([3.0, 4.0]) . Packed::pack([0.0, 5.0]);

        $floats = Packed::unpack(Packed::normalize($batch, 2), 2);

        self::assertEqualsWithDelta([0.6, 0.8, 0.0, 1.0], $floats, 1e-6);
    }

    public function testNormalizePassesZeroVectorsThroughUnchanged(): void
    {
        $zero = Packed::pack([0.0, 0.0, 0.0, 0.0]);

        self::assertSame($zero, Packed::normalize($zero, 4));
    }

    public function testTruncateKeepsThePrefixCoordinates(): void
    {
        $vector = Packed::pack([1.0, 2.0, 3.0, 4.0]);

        $truncated = Packed::truncate($vector, fromDim: 4, toDim: 2, renormalize: false);

        self::assertSame(Packed::pack([1.0, 2.0]), $truncated);
    }

    public function testTruncateRenormalizesByDefault(): void
    {
        $vector = Packed::pack([3.0, 4.0, 100.0, 100.0]);

        $truncated = Packed::truncate($vector, fromDim: 4, toDim: 2);

        self::assertEqualsWithDelta([0.6, 0.8], Packed::unpack($truncated, 2), 1e-6);
    }

    public function testTruncateHandlesBatches(): void
    {
        $batch = Packed::pack([1.0, 0.0, 9.0, 9.0]) . Packed::pack([0.0, 2.0, 9.0, 9.0]);

        $truncated = Packed::truncate($batch, fromDim: 4, toDim: 2);

        self::assertEqualsWithDelta([1.0, 0.0, 0.0, 1.0], Packed::unpack($truncated, 2), 1e-6);
    }

    public function testTruncateRejectsWideningTheVector(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Packed::truncate(Packed::pack([1.0, 2.0]), fromDim: 2, toDim: 4);
    }

    public function testTruncateRejectsRaggedPayloads(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Packed::truncate(Packed::pack([1.0, 2.0, 3.0]), fromDim: 4, toDim: 2);
    }
}
