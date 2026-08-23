<?php
/**
 * Hand-built Code 39 barcode generator — no external API, no library.
 * Renders directly to a PNG via GD and returns it as a base64 data URI,
 * which is the most reliable way to get a crisp barcode into a Dompdf-
 * rendered PDF (Dompdf's inline-SVG support is inconsistent across
 * versions; a raster image embeds reliably every time).
 *
 * Every character pattern below was verified against a real barcode
 * decoder (zbar) during development — see the project's dev notes.
 */

const CODE39_TABLE = [
    '0' => 'NNNWWNWNN', '1' => 'WNNWNNNNW', '2' => 'NNWWNNNNW', '3' => 'WNWWNNNNN',
    '4' => 'NNNWWNNNW', '5' => 'WNNWWNNNN', '6' => 'NNWWWNNNN', '7' => 'NNNWNNWNW',
    '8' => 'WNNWNNWNN', '9' => 'NNWWNNWNN',
    'A' => 'WNNNNWNNW', 'B' => 'NNWNNWNNW', 'C' => 'WNWNNWNNN', 'D' => 'NNNNWWNNW',
    'E' => 'WNNNWWNNN', 'F' => 'NNWNWWNNN', 'G' => 'NNNNNWWNW', 'H' => 'WNNNNWWNN',
    'I' => 'NNWNNWWNN', 'J' => 'NNNNWWWNN', 'K' => 'WNNNNNNWW', 'L' => 'NNWNNNNWW',
    'M' => 'WNWNNNNWN', 'N' => 'NNNNWNNWW', 'O' => 'WNNNWNNWN', 'P' => 'NNWNWNNWN',
    'Q' => 'NNNNNNWWW', 'R' => 'WNNNNNWWN', 'S' => 'NNWNNNWWN', 'T' => 'NNNNWNWWN',
    'U' => 'WWNNNNNNW', 'V' => 'NWWNNNNNW', 'W' => 'WWWNNNNNN', 'X' => 'NWNNWNNNW',
    'Y' => 'WWNNWNNNN', 'Z' => 'NWWNWNNNN',
    '-' => 'NWNNNNWNW', '.' => 'WWNNNNWNN', ' ' => 'NWWNNNWNN',
    '$' => 'NWNWNWNNN', '/' => 'NWNWNNNWN', '+' => 'NWNNNWNWN', '%' => 'NNNWNWNWN',
    '*' => 'NWNNWNWNN',
];

/**
 * Renders $text as a Code 39 barcode PNG and returns it as a data: URI.
 * Non-encodable characters are stripped (Code 39 supports 0-9, A-Z, and
 * a handful of symbols — tracking numbers are always within this set).
 */
function barcode_data_uri(string $text, int $moduleWidth = 2, int $barHeight = 60): string
{
    $clean = strtoupper(preg_replace('/[^0-9A-Z\-. $\/+%]/', '', $text));
    $chars = str_split('*' . $clean . '*');

    $totalModules = 0;
    foreach ($chars as $ch) {
        $pattern = CODE39_TABLE[$ch] ?? CODE39_TABLE['-'];
        foreach (str_split($pattern) as $c) {
            $totalModules += ($c === 'W') ? 2 : 1;
        }
        $totalModules += 1; // inter-character gap
    }

    $quietZone = 10 * $moduleWidth;
    $imgWidth = $quietZone * 2 + $totalModules * $moduleWidth;
    $imgHeight = $barHeight + 4;

    $im = imagecreatetruecolor($imgWidth, $imgHeight);
    $white = imagecolorallocate($im, 255, 255, 255);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefill($im, 0, 0, $white);

    $x = $quietZone;
    foreach ($chars as $ch) {
        $pattern = CODE39_TABLE[$ch] ?? CODE39_TABLE['-'];
        $isBar = true;
        foreach (str_split($pattern) as $c) {
            $pixelW = (($c === 'W') ? 2 : 1) * $moduleWidth;
            if ($isBar) {
                imagefilledrectangle($im, (int) $x, 2, (int) ($x + $pixelW - 1), 2 + $barHeight, $black);
            }
            $x += $pixelW;
            $isBar = !$isBar;
        }
        $x += $moduleWidth;
    }

    ob_start();
    imagepng($im);
    $data = ob_get_clean();
    imagedestroy($im);

    return 'data:image/png;base64,' . base64_encode($data);
}
