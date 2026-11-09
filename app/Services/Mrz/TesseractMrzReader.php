<?php

namespace App\Services\Mrz;

use Illuminate\Support\Facades\Process;

/** Local OCR: PDF → PNG (pdftoppm), crop the bottom of the page, threshold with GD, Tesseract with an MRZ whitelist. */
final class TesseractMrzReader implements MrzReader
{
    public function read(string $absolutePath): ?array
    {
        $tmp = sys_get_temp_dir().'/mrz_'.bin2hex(random_bytes(6));
        try {
            $image = $absolutePath;
            if (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) === 'pdf' || mime_content_type($absolutePath) === 'application/pdf') {
                $ok = Process::run([config('services.tesseract.pdftoppm'), '-r', '300', '-png', '-f', '1', '-l', '1', '-singlefile', $absolutePath, $tmp]);
                if (! $ok->successful()) {
                    return null;
                }
                $image = $tmp.'.png';
            }
            $strip = $this->bottomStrip($image, $tmp.'_strip.png');
            if ($strip === null) {
                return null;
            }

            $result = Process::run([config('services.tesseract.binary'), $strip, 'stdout', '--psm', '6',
                '-c', 'tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<']);
            if (! $result->successful()) {
                return null;
            }

            return self::pickLines($result->output());
        } finally {
            foreach (glob($tmp.'*') ?: [] as $f) {
                @unlink($f);
            }
        }
    }

    /** @return array{0:string,1:string}|null */
    public static function pickLines(string $ocr): ?array
    {
        $lines = array_values(array_filter(array_map(fn ($l) => preg_replace('/[^A-Z0-9<]/', '', strtoupper($l)), preg_split('/\R/', $ocr)),
            fn ($l) => strlen($l) >= 40));
        $n = count($lines);
        if ($n < 2) {
            return null;
        }
        $fix = fn (string $l) => substr(str_pad($l, 44, '<'), 0, 44);

        return [$fix($lines[$n - 2]), $fix($lines[$n - 1])];
    }

    private function bottomStrip(string $image, string $out): ?string
    {
        $src = @imagecreatefromstring((string) file_get_contents($image));
        if (! $src) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $stripH = (int) round($h * 0.3);
        $strip = imagecrop($src, ['x' => 0, 'y' => $h - $stripH, 'width' => $w, 'height' => $stripH]);
        if (! $strip) {
            return null;
        }
        imagefilter($strip, IMG_FILTER_GRAYSCALE);
        imagefilter($strip, IMG_FILTER_CONTRAST, -40);
        imagepng($strip, $out);

        return $out;
    }
}
