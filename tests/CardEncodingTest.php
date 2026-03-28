<?php

declare(strict_types=1);

use App\Enum\CardRankEnum;
use App\Enum\CardSymbolEnum;

use App\Game\Card\Card;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the TwoPlusTwo card encoding produced by Card::getValue().
 *
 * 32-bit card structure:
 *   +--------+--------+--------+--------+
 *   |xxxbbbbb|bbbbbbbb|cdhsrrrr|xxpppppp|
 *   +--------+--------+--------+--------+
 *
 *   p    = rank prime        (bits  0-5)
 *   r    = rank index 0-12   (bits  8-11)
 *   cdhs = suit (one bit)    (bits 12-15)  clubs=0x8000 diamonds=0x4000 hearts=0x2000 spades=0x1000
 *   b    = unique rank bit   (bits 16-28)  1 << (16 + rankIndex)
 */
class CardEncodingTest extends TestCase
{
    #[DataProvider("rankPrimeProvider")]
    public function testPrimeBits(string $rank, string $suit, int $expectedPrime): void
    {
        $card = new Card(CardRankEnum::tryFrom($rank), CardSymbolEnum::tryFrom($suit));
        $prime = $card->getValue() & 0x3F;

        $this->assertSame($expectedPrime, $prime, "Prime bits mismatch for rank $rank");
    }

    public static function rankPrimeProvider(): array
    {
        return [
            'Two' => ['2', 'S', 2],
            'Three' => ['3', 'S', 3],
            'Four' => ['4', 'S', 5],
            'Five' => ['5', 'S', 7],
            'Six' => ['6', 'S', 11],
            'Seven' => ['7', 'S', 13],
            'Eight' => ['8', 'S', 17],
            'Nine' => ['9', 'S', 19],
            'Ten' => ['10', 'S', 23],
            'Jack' => ['J', 'S', 29],
            'Queen' => ['Q', 'S', 31],
            'King' => ['K', 'S', 37],
            'Ace' => ['A', 'S', 41],
        ];
    }

    #[DataProvider("rankNibbleProvider")]
    public function testRankNibble(string $rank, int $expectedIndex): void
    {
        $card = new Card(CardRankEnum::tryFrom($rank), CardSymbolEnum::SPADE);
        $rankNibble = ($card->getValue() >> 8) & 0xF;

        $this->assertSame($expectedIndex, $rankNibble, "Rank nibble mismatch for rank $rank");
    }

    public static function rankNibbleProvider(): array
    {
        return [
            'Two' => ['2', 0],
            'Three' => ['3', 1],
            'Four' => ['4', 2],
            'Five' => ['5', 3],
            'Six' => ['6', 4],
            'Seven' => ['7', 5],
            'Eight' => ['8', 6],
            'Nine' => ['9', 7],
            'Ten' => ['10', 8],
            'Jack' => ['J', 9],
            'Queen' => ['Q', 10],
            'King' => ['K', 11],
            'Ace' => ['A', 12],
        ];
    }

    #[DataProvider("suitBitProvider")]
    public function testSuitBits(string $suit, int $expectedSuitBits): void
    {
        $card = new Card(CardRankEnum::DEUCE, CardSymbolEnum::tryFrom($suit));
        $suitBits = $card->getValue() & 0xF000;

        $this->assertSame($expectedSuitBits, $suitBits, "Suit bits mismatch for suit $suit");
    }

    public static function suitBitProvider(): array
    {
        return [
            'clubs' => ['C', 0x8000],
            'diamonds' => ['D', 0x4000],
            'hearts' => ['H', 0x2000],
            'spades' => ['S', 0x1000],
        ];
    }

    public function testExactlyOneSuitBitIsSet(): void
    {
        foreach (['C', 'D', 'H', 'S'] as $suit) {
            $card = new Card(CardRankEnum::DEUCE, CardSymbolEnum::tryFrom($suit));
            $suitBits = ($card->getValue() >> 12) & 0xF;

            $this->assertSame(
                1,
                substr_count(decbin($suitBits), '1'),
                "Exactly one suit bit must be set for suit $suit"
            );
        }
    }

    #[DataProvider("uniqueBitProvider")]
    public function testUniqueBit(string $rank, int $rankIndex): void
    {
        $card = new Card(CardRankEnum::tryFrom($rank), CardSymbolEnum::SPADE);
        $uniqueBits = $card->getValue() & 0x1FFF0000;
        $expectedBit = 1 << (16 + $rankIndex);

        $this->assertSame($expectedBit, $uniqueBits, "Unique rank bit mismatch for rank $rank");
    }

    public static function uniqueBitProvider(): array
    {
        return [
            'Two' => ['2', 0],
            'Three' => ['3', 1],
            'Four' => ['4', 2],
            'Five' => ['5', 3],
            'Six' => ['6', 4],
            'Seven' => ['7', 5],
            'Eight' => ['8', 6],
            'Nine' => ['9', 7],
            'Ten' => ['10', 8],
            'Jack' => ['J', 9],
            'Queen' => ['Q', 10],
            'King' => ['K', 11],
            'Ace' => ['A', 12],
        ];
    }

    public function testUniqueBitsAreAllDistinct(): void
    {
        $ranks = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
        $seen = [];

        foreach ($ranks as $rank) {
            $bit = (new Card(CardRankEnum::tryFrom($rank), CardSymbolEnum::SPADE))->getValue() & 0x1FFF0000;
            $this->assertNotContains($bit, $seen, "Unique bit for rank $rank is duplicated");
            $seen[] = $bit;
        }
    }

    #[DataProvider("knownCardValueProvider")]
    public function testKnownCardValues(string $rank, string $suit, int $expectedValue): void
    {
        $card = new Card(CardRankEnum::tryFrom($rank), CardSymbolEnum::tryFrom($suit));

        $this->assertSame(
            $expectedValue,
            $card->getValue(),
            sprintf('Encoded value mismatch for %s%s — expected 0x%08X', $rank, $suit, $expectedValue)
        );
    }

    public static function knownCardValueProvider(): array
    {
        return [
            // Twos
            '2c' => ['2', 'C', 0x00018002],
            '2d' => ['2', 'D', 0x00014002],
            '2h' => ['2', 'H', 0x00012002],
            '2s' => ['2', 'S', 0x00011002],

            // Aces
            'Ac' => ['A', 'C', 0x10008C29],
            'Ad' => ['A', 'D', 0x10004C29],
            'Ah' => ['A', 'H', 0x10002C29],
            'As' => ['A', 'S', 0x10001C29],

            // Mixed ranks
            'Ks' => ['K', 'S', 0x08001B25],
            'Qh' => ['Q', 'H', 0x04002A1F],
            'Jd' => ['J', 'D', 0x0200491D],
            'Th' => ['10', 'H', 0x01002817],
            '9s' => ['9', 'S', 0x00801713],
            '8c' => ['8', 'C', 0x00408611],
            '7d' => ['7', 'D', 0x0020450D],
            '6h' => ['6', 'H', 0x0010240B],
            '5s' => ['5', 'S', 0x00081307],
            '4c' => ['4', 'C', 0x00048205],
            '3d' => ['3', 'D', 0x00024103],
        ];
    }

    public function testBitsDoNotOverlap(): void
    {
        $ranks = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
        $suits = ['C', 'D', 'H', 'S'];

        foreach ($suits as $suit) {
            foreach ($ranks as $index => $rank) {
                $prime = Card::RANK_PRIMES[$index];
                $rankNibble = $index << 8;
                $suitBit = Card::SUIT_BITMASKS[$suit];
                $uniqueBit = 1 << (16 + $index);

                $this->assertSame(0, $prime & $rankNibble, "prime ∩ rank != 0 for rank $rank");
                $this->assertSame(0, $prime & $suitBit, "prime ∩ suit != 0 for $rank/$suit");
                $this->assertSame(0, $prime & $uniqueBit, "prime ∩ uniqueBit != 0 for rank $rank");
                $this->assertSame(0, $rankNibble & $suitBit, "rank ∩ suit != 0 for $rank/$suit");
                $this->assertSame(0, $rankNibble & $uniqueBit, "rank ∩ uniqueBit != 0 for rank $rank");
                $this->assertSame(0, $suitBit & $uniqueBit, "suit ∩ uniqueBit != 0 for $suit rank $rank");
            }
        }
    }

    public function testPrimesAreAllPrime(): void
    {
        $isPrime = static function (int $n): bool {
            if ($n < 2) {
                return false;
            }
            for ($i = 2; $i * $i <= $n; $i++) {
                if ($n % $i === 0) {
                    return false;
                }
            }
            return true;
        };

        foreach (Card::RANK_PRIMES as $index => $prime) {
            $this->assertTrue($isPrime($prime), "Value $prime at index $index is not prime");
        }
    }

    public function testPrimesAreAllDistinct(): void
    {
        $this->assertSame(
            Card::RANK_PRIMES,
            array_unique(Card::RANK_PRIMES),
            'Each rank must have a distinct prime'
        );
    }
}