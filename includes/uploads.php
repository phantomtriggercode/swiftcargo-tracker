<?php
/**
 * Shared image-upload handling for admin-editable images (logo, branding,
 * site illustrations). Requires includes/settings.php to already be loaded.
 */

const SITE_UPLOAD_DIR = __DIR__ . '/../assets/images/uploads/';
const SITE_UPLOAD_URL_PREFIX = '/assets/images/uploads/';

/**
 * Validates and stores an uploaded image from $_FILES[$fieldName], returning
 * ['ok' => true, 'path' => '/assets/images/uploads/xyz.jpg'] on success or
 * ['ok' => false, 'error' => '...'] on failure. Returns ok=true with a null
 * path if no file was submitted for this field (nothing to do).
 */
function handle_image_upload(string $fieldName, string $filenamePrefix, int $maxBytes = 5 * 1024 * 1024): array
{
    if (empty($_FILES[$fieldName]['name']) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null];
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
    }
    if ($_FILES[$fieldName]['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'File is too large (max ' . round($maxBytes / 1024 / 1024) . 'MB).'];
    }

    $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
    $allowedExt = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'jfif'];
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'error' => 'Must be a PNG, JPG, WEBP, GIF, or SVG image.'];
    }

    if ($ext === 'svg') {
        if (stripos((string) file_get_contents($_FILES[$fieldName]['tmp_name']), '<script') !== false) {
            return ['ok' => false, 'error' => 'SVG file rejected for containing a script tag.'];
        }
    } elseif (@getimagesize($_FILES[$fieldName]['tmp_name']) === false) {
        return ['ok' => false, 'error' => 'That file does not look like a valid image.'];
    }

    // .jfif is byte-identical JPEG data; save it as .jpg so the web server's
    // default mime type mapping serves it correctly.
    if ($ext === 'jfif') {
        $ext = 'jpg';
    }

    $filename = $filenamePrefix . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!is_dir(SITE_UPLOAD_DIR)) {
        mkdir(SITE_UPLOAD_DIR, 0755, true);
    }
    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], SITE_UPLOAD_DIR . $filename)) {
        return ['ok' => false, 'error' => 'Could not save the uploaded file.'];
    }

    return ['ok' => true, 'path' => SITE_UPLOAD_URL_PREFIX . $filename];
}

/**
 * Deletes a previously-uploaded site image (identified by its setting value)
 * if it lives inside the uploads folder. Safe no-op for default asset paths.
 */
function delete_uploaded_image(string $path): void
{
    if ($path !== '' && str_starts_with($path, SITE_UPLOAD_URL_PREFIX)) {
        @unlink(__DIR__ . '/../' . ltrim($path, '/'));
    }
}
