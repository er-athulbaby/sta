<?php

namespace App\Services\Mrz;

use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

/**
 * Local OCR, same approach as the visa application: PDF → PNG (pdftoppm), white margins cropped, grayscale and contrast,
 * read at a few widths with the passport-trained "mrz" Tesseract model (resources/tessdata, BSD-3 DoubangoTelecom/tesseractMRZ).
 * The first read whose check digits all pass wins; otherwise the read with the fewest failed checks is returned.
 */
final class TesseractMrzReader implements MrzReader
{
    public function read(string $absolutePath): ?array
    {
        $tmp = sys_get_temp_dir().'/mrz_'.bin2hex(random_bytes(6));
        try {
            $image = $absolutePath;
            if (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) === 'pdf' || @mime_content_type($absolutePath) === 'application/pdf') {
                try {
                    $ok = Process::timeout(30)->run([config('services.tesseract.pdftoppm'), '-r', '300', '-png', '-f', '1', '-l', '1', '-singlefile', $absolutePath, $tmp]);
                } catch (\Throwable $e) {
                    report($e);

                    return null;
                }
                if (! $ok->successful() || ! is_file($tmp.'.png')) {
                    return null;
                }
                $image = $tmp.'.png';
            }

            $best = null;
            $bestFailed = PHP_INT_MAX;
            foreach ([...$this->cleanedCopies($image, $tmp), $image] as $candidate) {
                $text = $this->tesseract($candidate);
                if ($text === null) {
                    return $best; // Tesseract itself is broken: trying again won't help
                }
                $lines = self::pickLines($text);
                if ($lines === null) {
                    continue;
                }
                try {
                    $failed = count((new MrzParser)->parse($lines[0], $lines[1])->invalid);
                } catch (InvalidArgumentException) {
                    continue;
                }
                if ($failed === 0) {
                    return $lines;
                }
                if ($failed < $bestFailed) {
                    [$best, $bestFailed] = [$lines, $failed];
                }
            }

            return $best;
        } finally {
            foreach (glob($tmp.'*') ?: [] as $f) {
                @unlink($f);
            }
        }
    }

    /**
     * The TD3 lines out of raw OCR text: the line starting with "P" followed by the next long line;
     * else the last two long lines.
     *
     * @return array{0:string,1:string}|null
     */
    public static function pickLines(string $ocr): ?array
    {
        $lines = array_values(array_filter(array_map(fn ($l) => preg_replace('/[^A-Z0-9<]/', '', strtoupper($l)), preg_split('/\R/', $ocr)),
            fn ($l) => strlen($l) >= 40));
        $fix = fn (string $l) => substr(str_pad($l, 44, '<'), 0, 44);
        foreach ($lines as $i => $line) {
            if ($line[0] === 'P' && isset($lines[$i + 1])) {
                return [$fix($line), $fix($lines[$i + 1])];
            }
        }
        $n = count($lines);

        return $n < 2 ? null : [$fix($lines[$n - 2]), $fix($lines[$n - 1])];
    }

    private function tesseract(string $image): ?string
    {
        $lang = config('services.tesseract.lang');
        $dir = config('services.tesseract.tessdata');
        $useModel = $lang && $dir && is_file($dir.'/'.$lang.'.traineddata');
        try {
            $result = Process::timeout(30)->run([config('services.tesseract.binary'), $image, 'stdout', '--psm', '6',
                ...($useModel ? ['--tessdata-dir', $dir, '-l', $lang] : ['-c', 'tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<'])]);
        } catch (\Throwable $e) {
            report($e); // missing binary or bad path: OCR must never block the form

            return null;
        }

        return $result->successful() ? $result->output() : null;
    }

    /**
     * White page margins cropped (a scanned page's white sheet otherwise swamps Tesseract's threshold), grayscale,
     * stronger contrast, at a few widths. Returns temp PNG paths (deleted with the $tmp prefix).
     *
     * @return list<string>
     */
    private function cleanedCopies(string $path, string $tmp): array
    {
        set_error_handler(fn () => true);
        try {
            $img = is_file($path) ? imagecreatefromstring((string) file_get_contents($path)) : false;
        } finally {
            restore_error_handler();
        }
        if (! $img) {
            return [];
        }
        // A 300 dpi A4 page is ~35 MB in GD: shrink first so the copies below stay inside PHP's memory limit.
        if (imagesx($img) > 2400 && ($small = imagescale($img, 2400))) {
            $img = $small;
        }
        $img = imagecropauto($img, IMG_CROP_THRESHOLD, 0.1, 0xFFFFFF) ?: $img;
        imagefilter($img, IMG_FILTER_GRAYSCALE);
        imagefilter($img, IMG_FILTER_CONTRAST, -40);

        // ponytail: fixed widths; the MRZ model reads best with characters ~25-45px tall. Up to 4 Tesseract runs per read.
        $copies = [];
        foreach ([1600, 1200, 2000] as $i => $width) {
            if ($scaled = imagescale($img, $width)) {
                imagepng($scaled, $out = "{$tmp}_c{$i}.png");
                $copies[] = $out;
            }
        }

        return $copies;
    }
}
