<?php
namespace SepoWB\SignIn;

defined('ABSPATH') || exit;

/**
 * A QR code generator, drawn as inline SVG.
 *
 * It exists so the authenticator setup screen can show a code to scan from a
 * laptop without sending the secret to an outside QR service: whoever runs
 * that service would hold a working second factor for the account.
 *
 * Byte mode, error correction level M (L when the text is too long for M),
 * versions 1 to 40, all eight masks tried and the best kept, following ISO/IEC
 * 18004. An otpauth address is about 120 to 200 bytes, which lands at
 * version 6 to 9.
 */
final class Qr
{
    private const ECC_PER_BLOCK = [
        'L' => [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'M' => [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
    ];
    private const BLOCKS = [
        'L' => [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        'M' => [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
    ];
    private const FORMAT_ECL = ['L' => 1, 'M' => 0];

    private int $version;
    private string $ecl;
    private int $size;
    /** @var list<list<bool>> */
    private array $modules = [];
    /** @var list<list<bool>> */
    private array $isFunction = [];

    /**
     * The matrix for a text, as rows of booleans (true is dark), or null when
     * the text is too long for any version.
     *
     * @return list<list<bool>>|null
     */
    public static function matrix(string $text, ?int $forceMask = null): ?array
    {
        foreach (['M', 'L'] as $ecl) {
            for ($v = 1; $v <= 40; $v++) {
                $cap = self::dataCodewords($v, $ecl) * 8;
                $need = 4 + ($v <= 9 ? 8 : 16) + strlen($text) * 8;
                if ($need <= $cap) {
                    $qr = new self($v, $ecl);
                    $qr->build($text, $forceMask);
                    return $qr->modules;
                }
            }
        }
        return null;
    }

    /** The code as an SVG element, scaled to its container. '' when the text does not fit. */
    public static function svg(string $text, string $label = ''): string
    {
        $m = self::matrix($text);
        if ($m === null) {
            return '';
        }
        $n = count($m);
        $quiet = 4;
        $path = '';
        foreach ($m as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M' . ($x + $quiet) . ' ' . ($y + $quiet) . 'h1v1h-1z';
                }
            }
        }
        $total = $n + 2 * $quiet;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges" role="img" aria-label="' . esc_attr($label !== '' ? $label : __('QR code', 'sepo-bridge')) . '">'
            . '<rect width="' . $total . '" height="' . $total . '" fill="#ffffff"/><path fill="#000000" d="' . $path . '"/></svg>';
    }

    private function __construct(int $version, string $ecl)
    {
        $this->version = $version;
        $this->ecl = $ecl;
        $this->size = $version * 4 + 17;
        $row = array_fill(0, $this->size, false);
        $this->modules = array_fill(0, $this->size, $row);
        $this->isFunction = array_fill(0, $this->size, $row);
    }

    // ---------------------------------------------------------------- capacity

    private static function rawDataModules(int $v): int
    {
        $r = (16 * $v + 128) * $v + 64;
        if ($v >= 2) {
            $align = intdiv($v, 7) + 2;
            $r -= (25 * $align - 10) * $align - 55;
            if ($v >= 7) {
                $r -= 36;
            }
        }
        return $r;
    }

    private static function dataCodewords(int $v, string $ecl): int
    {
        return intdiv(self::rawDataModules($v), 8) - self::ECC_PER_BLOCK[$ecl][$v] * self::BLOCKS[$ecl][$v];
    }

    // ---------------------------------------------------------------- build

    private function build(string $text, ?int $forceMask): void
    {
        $bits = [];
        $put = static function (int $value, int $len) use (&$bits): void {
            for ($i = $len - 1; $i >= 0; $i--) {
                $bits[] = ($value >> $i) & 1;
            }
        };
        $put(0b0100, 4);
        $put(strlen($text), $this->version <= 9 ? 8 : 16);
        for ($i = 0, $n = strlen($text); $i < $n; $i++) {
            $put(ord($text[$i]), 8);
        }
        $cap = self::dataCodewords($this->version, $this->ecl) * 8;
        $put(0, min(4, $cap - count($bits)));
        $put(0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $cap; $pad ^= 0xEC ^ 0x11) {
            $put($pad, 8);
        }
        $data = [];
        foreach (array_chunk($bits, 8) as $byte) {
            $data[] = (int) bindec(implode('', $byte));
        }

        $this->drawFunctionPatterns();
        $this->drawCodewords($this->interleave($data));

        if ($forceMask !== null) {
            $mask = $forceMask;
        } else {
            $mask = 0;
            $best = PHP_INT_MAX;
            for ($m = 0; $m < 8; $m++) {
                $this->applyMask($m);
                $this->drawFormatBits($m);
                $p = $this->penalty();
                if ($p < $best) {
                    $best = $p;
                    $mask = $m;
                }
                $this->applyMask($m);
            }
        }
        $this->applyMask($mask);
        $this->drawFormatBits($mask);
    }

    /** @param list<int> $data @return list<int> */
    private function interleave(array $data): array
    {
        $v = $this->version;
        $numBlocks = self::BLOCKS[$this->ecl][$v];
        $eccLen = self::ECC_PER_BLOCK[$this->ecl][$v];
        $raw = intdiv(self::rawDataModules($v), 8);
        $numShort = $numBlocks - $raw % $numBlocks;
        $shortLen = intdiv($raw, $numBlocks);
        $divisor = self::rsDivisor($eccLen);
        $blocks = [];
        $k = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $len = $shortLen - $eccLen + ($i < $numShort ? 0 : 1);
            $dat = array_slice($data, $k, $len);
            $k += $len;
            $ecc = self::rsRemainder($dat, $divisor);
            if ($i < $numShort) {
                $dat[] = 0;
            }
            $blocks[] = array_merge($dat, $ecc);
        }
        $out = [];
        $blockLen = count($blocks[0]);
        for ($i = 0; $i < $blockLen; $i++) {
            foreach ($blocks as $j => $b) {
                if ($i !== $shortLen - $eccLen || $j >= $numShort) {
                    $out[] = $b[$i];
                }
            }
        }
        return $out;
    }

    private static function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }

    /** @return list<int> */
    private static function rsDivisor(int $degree): array
    {
        $r = array_fill(0, $degree, 0);
        $r[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $r[$j] = self::gfMul($r[$j], $root);
                if ($j + 1 < $degree) {
                    $r[$j] ^= $r[$j + 1];
                }
            }
            $root = self::gfMul($root, 0x02);
        }
        return $r;
    }

    /** @param list<int> $data @param list<int> $divisor @return list<int> */
    private static function rsRemainder(array $data, array $divisor): array
    {
        $r = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($r);
            $r[] = 0;
            foreach ($divisor as $i => $d) {
                $r[$i] ^= self::gfMul($d, $factor);
            }
        }
        return $r;
    }

    // ---------------------------------------------------------------- drawing

    private function set(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        $s = $this->size;
        for ($i = 0; $i < $s; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$s - 4, 3], [3, $s - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $s && $y >= 0 && $y < $s) {
                        $d = max(abs($dx), abs($dy));
                        $this->set($x, $y, $d !== 2 && $d !== 4);
                    }
                }
            }
        }
        $pos = $this->alignmentPositions();
        $n = count($pos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->set($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        // Reserve the format areas now; the real bits are drawn after masking.
        $this->drawFormatBits(0);
        if ($this->version >= 7) {
            $rem = $this->version;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }
            $bits = ($this->version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($bits >> $i) & 1) === 1;
                $a = $s - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->set($a, $b, $bit);
                $this->set($b, $a, $bit);
            }
        }
    }

    /** @return list<int> */
    private function alignmentPositions(): array
    {
        $v = $this->version;
        if ($v === 1) {
            return [];
        }
        $num = intdiv($v, 7) + 2;
        $step = $v === 32 ? 26 : (int) ceil(($v * 4 + 4) / ($num * 2 - 2)) * 2;
        $out = [6];
        for ($p = $this->size - 7; count($out) < $num; $p -= $step) {
            array_splice($out, 1, 0, [$p]);
        }
        return $out;
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (self::FORMAT_ECL[$this->ecl] << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = static fn(int $i): bool => (($bits >> $i) & 1) === 1;
        $s = $this->size;
        for ($i = 0; $i <= 5; $i++) {
            $this->set(8, $i, $bit($i));
        }
        $this->set(8, 7, $bit(6));
        $this->set(8, 8, $bit(7));
        $this->set(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->set(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->set($s - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->set(8, $s - 15 + $i, $bit($i));
        }
        $this->set(8, $s - 8, true);
    }

    /** @param list<int> $data */
    private function drawCodewords(array $data): void
    {
        $s = $this->size;
        $total = count($data) * 8;
        $i = 0;
        for ($right = $s - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $s; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $up = (($right + 1) & 2) === 0;
                    $y = $up ? $s - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        $s = $this->size;
        for ($y = 0; $y < $s; $y++) {
            for ($x = 0; $x < $s; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $flip = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => ((($x + $y) % 2) + ($x * $y) % 3) % 2 === 0,
                };
                if ($flip) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /** The standard's penalty score: lower scans more reliably. */
    private function penalty(): int
    {
        $s = $this->size;
        $m = $this->modules;
        $score = 0;
        $lines = [];
        for ($i = 0; $i < $s; $i++) {
            $row = '';
            $col = '';
            for ($j = 0; $j < $s; $j++) {
                $row .= $m[$i][$j] ? '1' : '0';
                $col .= $m[$j][$i] ? '1' : '0';
            }
            $lines[] = $row;
            $lines[] = $col;
        }
        foreach ($lines as $line) {
            // Runs of five or more of one colour.
            if (preg_match_all('/0{5,}|1{5,}/', $line, $runs)) {
                foreach ($runs[0] as $r) {
                    $score += 3 + strlen($r) - 5;
                }
            }
            // Patterns that look like a finder.
            $score += 40 * (substr_count('0000' . $line, '00001011101') + substr_count($line . '0000', '10111010000'));
        }
        for ($y = 0; $y < $s - 1; $y++) {
            for ($x = 0; $x < $s - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        $dark = 0;
        foreach ($m as $row) {
            $dark += count(array_filter($row));
        }
        $total = $s * $s;
        $k = (int) ceil(abs($dark * 20 - $total * 10) / $total) - 1;
        return $score + max(0, $k) * 10;
    }
}
