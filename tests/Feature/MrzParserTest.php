<?php

use App\Services\Mrz\MrzParser;
use App\Services\Mrz\TesseractMrzReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\Process;

const BHR_L1 = 'P<BHRALSAYED<<NOOR<KHALID<AHMED<<<<<<<<<<<<<';
const BHR_L2 = 'BH12345676BHR0605276F3101159<<<<<<<<<<<<<<00';
const ICAO_L1 = 'P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<';
const ICAO_L2 = 'L898902C36UTO7408122F1204159ZE184226B<<<<<10';

it('parses a Bahrain-style passport with four names', function () {
    $r = (new MrzParser)->parse(BHR_L1, BHR_L2, Carbon::parse('2026-11-09'));

    expect($r->surname)->toBe('Alsayed')
        ->and($r->givenNames)->toBe(['Noor', 'Khalid', 'Ahmed'])
        ->and($r->fullName)->toBe('Noor Khalid Ahmed Alsayed')
        ->and($r->passportNo)->toBe('BH1234567')
        ->and($r->issuingCode)->toBe('BHR')->and($r->nationalityCode)->toBe('BHR')
        ->and($r->dob)->toBe('2006-05-27')->and($r->gender)->toBe('female')
        ->and($r->expiry)->toBe('2031-01-15')->and($r->invalid)->toBe([]);
});

it('parses the ICAO specimen with a 19xx birth year', function () {
    $r = (new MrzParser)->parse(ICAO_L1, ICAO_L2, Carbon::parse('2026-11-09'));
    expect($r->dob)->toBe('1974-08-12')->and($r->passportNo)->toBe('L898902C3')->and($r->givenNames)->toBe(['Anna', 'Maria'])
        ->and($r->invalid)->toBe([]);
});

it('flags fields whose check digit fails and does not return them', function () {
    $bad = substr_replace(BHR_L2, '5', 19, 1); // dob check digit 6 -> 5
    $r = (new MrzParser)->parse(BHR_L1, $bad, Carbon::parse('2026-11-09'));
    expect($r->invalid)->toContain('dob')->and($r->dob)->toBeNull()->and($r->passportNo)->toBe('BH1234567');
});

it('tolerates OCR spaces and lower case, and rejects non-TD3 input', function () {
    $r = (new MrzParser)->parse(strtolower(' '.BHR_L1.' '), str_replace('<', ' <', BHR_L2));
    expect($r->passportNo)->toBe('BH1234567');
    expect(fn () => (new MrzParser)->parse('P<SHORT', 'X'))->toThrow(InvalidArgumentException::class);
});

it('computes ICAO check digits', function () {
    expect(MrzParser::checkDigit('L898902C3'))->toBe(6)->and(MrzParser::checkDigit('<<<<<<<<<<<<<<'))->toBe(0);
});

it('picks the last two MRZ-like lines from OCR text', function () {
    $ocr = "REPUBLIC OF BAHRAIN\nPASSPORT\nP<BHRALSAYED<<NOOR<KHALID<AHMED<<<<<<<<<<<<<\nBH12345676BHR0605276F3101159<<<<<<<<<<<<<<00\n";
    expect(TesseractMrzReader::pickLines($ocr))->toBe([
        'P<BHRALSAYED<<NOOR<KHALID<AHMED<<<<<<<<<<<<<', 'BH12345676BHR0605276F3101159<<<<<<<<<<<<<<00']);
});

it('picks the line starting with P and the next one, even with noise below the MRZ', function () {
    $ocr = "PASSPORT\n".BHR_L1."\n".BHR_L2."\nXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX\n";
    expect(TesseractMrzReader::pickLines($ocr))->toBe([BHR_L1, BHR_L2]);
});

it('fixes OCR look-alikes: letters in digit fields and digits in country codes', function () {
    // dob 060527 read as O6O527, expiry check 9 read as ... kept, nationality BHR read as 8HR
    $l2 = 'BH12345676'.'8HR'.'O6O5276F3101159<<<<<<<<<<<<<<OO';
    $r = (new MrzParser)->parse(BHR_L1, $l2, Carbon::parse('2026-11-09'));
    expect($r->dob)->toBe('2006-05-27')->and($r->nationalityCode)->toBe('BHR')->and($r->invalid)->toBe([]);
});

it('reads with the passport-trained mrz model, tries cleaned copies and stops at the first fully valid read', function () {
    Process::fake(fn () => Process::result("NOISE\n".BHR_L1."\n".BHR_L2."\n"));
    $img = imagecreatetruecolor(800, 500);
    imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
    imagepng($img, $file = tempnam(sys_get_temp_dir(), 'pp').'.png');

    expect((new TesseractMrzReader)->read($file))->toBe([BHR_L1, BHR_L2]);
    Process::assertRanTimes(fn ($p) => in_array('mrz', $p->command, true)
        && in_array(resource_path('tessdata'), $p->command, true), 1);
    @unlink($file);
});

it('returns the read with the fewest failed checks when none is fully valid', function () {
    $bad = substr_replace(BHR_L2, '5', 19, 1);
    Process::fake(fn () => Process::result(BHR_L1."\n".$bad."\n"));
    $img = imagecreatetruecolor(800, 500);
    imagepng($img, $file = tempnam(sys_get_temp_dir(), 'pp').'.png');

    expect((new TesseractMrzReader)->read($file))->toBe([BHR_L1, $bad]);
    Process::assertRanTimes(fn ($p) => true, 4); // 3 cleaned widths + the original
    @unlink($file);
});
