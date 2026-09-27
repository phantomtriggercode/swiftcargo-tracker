<?php
/**
 * Strips embedded metadata from uploaded images.
 *
 * Image files can carry a surprising amount of hidden information alongside
 * the picture itself: EXIF (camera, lens, GPS coordinates, timestamps), XMP
 * and IPTC (author, copyright, editing history), and Content Credentials
 * (C2PA), which record where an image came from and what was done to it.
 *
 * None of that belongs on a public business website. GPS coordinates in a
 * photo taken on a phone will tell any visitor exactly where it was shot,
 * and provenance records describe the image's whole editing history. Both
 * are invisible in the picture and survive every copy of the file.
 *
 * This works at the byte level rather than re-encoding the image, so the
 * picture is bit-for-bit unchanged — no quality loss, no colour shift, and
 * animation in APNG/GIF is preserved. Colour-management data (ICC profiles)
 * is deliberately kept, since removing it would change how the image looks.
 *
 * Used by includes/uploads.php on every image an admin uploads. Pure PHP:
 * no extensions, no command-line tools, nothing to install on the host.
 */

/**
 * Rewrites $path in place with metadata removed.
 *
 * Fails safe in every direction: if the file can't be read, isn't a format
 * handled here, or anything looks malformed, the original is left exactly as
 * it was and this returns false. An upload is never lost or corrupted just
 * because its metadata couldn't be cleaned.
 */
function strip_image_metadata(string $path): bool
{
    $data = @file_get_contents($path);
    if ($data === false || $data === '') {
        return false;
    }

    $cleaned = null;
    if (str_starts_with($data, "\xFF\xD8\xFF")) {
        $cleaned = strip_jpeg_metadata($data);
    } elseif (str_starts_with($data, "\x89PNG\r\n\x1A\n")) {
        $cleaned = strip_png_metadata($data);
    } elseif (str_starts_with($data, 'RIFF') && substr($data, 8, 4) === 'WEBP') {
        $cleaned = strip_webp_metadata($data);
    } elseif (stripos(ltrim($data), '<') === 0 && stripos($data, '<svg') !== false) {
        $cleaned = strip_svg_metadata($data);
    }

    if ($cleaned === null || $cleaned === $data) {
        return false;
    }

    return @file_put_contents($path, $cleaned) !== false;
}

/**
 * JPEG is a sequence of marker segments. Metadata lives in the APPn
 * application segments and COM comment segments; the picture itself is in
 * the scan that follows the SOS marker.
 *
 * Dropped: APP1 (EXIF and XMP), APP11 (JUMBF — this is where C2PA Content
 * Credentials live), APP13 (IPTC/Photoshop), and COM comments.
 * Kept: APP0 (JFIF), APP2 (ICC colour profile) and APP14 (Adobe colour
 * transform), because dropping either of those last two can visibly change
 * the image's colours.
 */
function strip_jpeg_metadata(string $data): ?string
{
    $len = strlen($data);
    $out = "\xFF\xD8";   // SOI
    $i = 2;

    // Markers carrying metadata we remove, keyed by their second byte.
    $drop = ["\xE1" => true, "\xEB" => true, "\xED" => true, "\xFE" => true];

    while ($i < $len) {
        if ($data[$i] !== "\xFF") {
            return null; // Not where a marker should be — leave the file alone.
        }
        // Markers may be padded with any number of 0xFF fill bytes.
        while ($i < $len && $data[$i] === "\xFF") {
            $i++;
        }
        if ($i >= $len) {
            return null;
        }
        $marker = $data[$i];
        $i++;

        // Standalone markers: no length field, no payload.
        if ($marker === "\xD8" || $marker === "\x01" || ($marker >= "\xD0" && $marker <= "\xD7")) {
            $out .= "\xFF" . $marker;
            continue;
        }
        if ($marker === "\xD9") { // EOI
            $out .= "\xFF\xD9";
            return $out;
        }

        if ($i + 2 > $len) {
            return null;
        }
        $segLen = (ord($data[$i]) << 8) | ord($data[$i + 1]);
        if ($segLen < 2 || $i + $segLen > $len) {
            return null;
        }

        if ($marker === "\xDA") {
            // Start of Scan: the compressed picture data runs to the end of
            // the file, so copy everything from here unchanged.
            $out .= "\xFF\xDA" . substr($data, $i, $len - $i);
            return $out;
        }

        if (!isset($drop[$marker])) {
            $out .= "\xFF" . $marker . substr($data, $i, $segLen);
        }
        $i += $segLen;
    }

    return $out;
}

/**
 * PNG is a stream of length-prefixed chunks. Only a named list is removed,
 * so anything not recognised here (including the acTL/fcTL/fdAT chunks that
 * make an APNG animate) is passed through untouched.
 *
 * caBX is the chunk C2PA Content Credentials use in PNG files.
 */
function strip_png_metadata(string $data): ?string
{
    $len = strlen($data);
    $out = substr($data, 0, 8); // 8-byte PNG signature
    $i = 8;

    $drop = ['tEXt' => true, 'zTXt' => true, 'iTXt' => true, 'eXIf' => true, 'caBX' => true, 'dSIG' => true];

    while ($i + 8 <= $len) {
        $chunkLen = unpack('N', substr($data, $i, 4))[1];
        $type = substr($data, $i + 4, 4);
        $total = 12 + $chunkLen; // length + type + data + CRC
        if ($chunkLen < 0 || $i + $total > $len) {
            return null;
        }

        if (!isset($drop[$type])) {
            $out .= substr($data, $i, $total);
        }
        $i += $total;

        if ($type === 'IEND') {
            return $out;
        }
    }

    return null; // Ran off the end without IEND — malformed, leave it alone.
}

/**
 * WebP is a RIFF container. Metadata sits in its own EXIF/XMP/C2PA chunks,
 * and the VP8X header carries flag bits announcing that those chunks exist,
 * so those bits are cleared too or a strict decoder will look for chunks
 * that are no longer there.
 */
function strip_webp_metadata(string $data): ?string
{
    $len = strlen($data);
    $body = '';
    $i = 12; // 'RIFF' + size + 'WEBP'

    $drop = ['EXIF' => true, 'XMP ' => true, 'C2PA' => true];

    while ($i + 8 <= $len) {
        $type = substr($data, $i, 4);
        $chunkLen = unpack('V', substr($data, $i + 4, 4))[1];
        $padded = $chunkLen + ($chunkLen % 2); // chunks are padded to even length
        if ($i + 8 + $padded > $len) {
            return null;
        }

        if (!isset($drop[$type])) {
            $chunk = substr($data, $i, 8 + $padded);
            if ($type === 'VP8X' && $chunkLen >= 1) {
                // Clear the EXIF (0x08) and XMP (0x04) presence flags.
                $flags = ord($chunk[8]) & ~0x0C;
                $chunk[8] = chr($flags);
            }
            $body .= $chunk;
        }
        $i += 8 + $padded;
    }

    if ($body === '') {
        return null;
    }

    return 'RIFF' . pack('V', 4 + strlen($body)) . 'WEBP' . $body;
}

/**
 * SVG is XML, so its metadata is readable text: <metadata> blocks (often
 * holding the name and version of whatever drew the file) and comments.
 */
function strip_svg_metadata(string $svg): ?string
{
    $out = preg_replace('~<metadata\b[^>]*>.*?</metadata\s*>~is', '', $svg);
    if ($out === null) {
        return null;
    }
    $out = preg_replace('~<metadata\b[^>]*/\s*>~i', '', $out);
    if ($out === null) {
        return null;
    }
    $out = preg_replace('~<!--(?!\[if).*?-->~s', '', $out);
    if ($out === null) {
        return null;
    }
    return $out;
}
