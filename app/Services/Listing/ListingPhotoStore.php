<?php
declare(strict_types=1);

namespace App\Services\Listing;

/**
 * LS-14 Module 9.4: photos of a seller listing. At most MAX_PHOTOS per listing, MAX_BYTES each, JPEG/PNG/WebP by their real content (not the
 * file name). Every photo is decoded and re-encoded as JPEG by GD, which drops EXIF and with it any GPS position, and is scaled down to MAX_SIDE.
 * Files live outside the web root under storage/uploads/listings/{listing uuid}/ and are served only through a controller.
 */
final class ListingPhotoStore
{
    public const MAX_PHOTOS = 10;
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_SIDE = 1600;
    public const MAX_SOURCE_PIXELS = 40000000;   // refuse decompression bombs before GD allocates them
    private const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private string $dir) {}

    /**
     * @param array{tmp_name:string,error:int,size:int} $file one entry of $_FILES
     * @return array{ok:bool,error?:string,file_name?:string,width?:int,height?:int,bytes?:int}
     */
    public function store(string $listingUuid, array $file, int $existing, bool $requireUploaded = true): array
    {
        if ($existing >= self::MAX_PHOTOS) { return ['ok' => false, 'error' => 'TOO_MANY']; }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { return ['ok' => false, 'error' => 'UPLOAD_FAILED']; }
        $tmp = (string)$file['tmp_name'];
        if ($requireUploaded && !is_uploaded_file($tmp)) { return ['ok' => false, 'error' => 'UPLOAD_FAILED']; }
        $size = (int)@filesize($tmp);
        if ($size <= 0 || $size > self::MAX_BYTES) { return ['ok' => false, 'error' => 'TOO_LARGE']; }
        $mime = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, self::TYPES, true)) { return ['ok' => false, 'error' => 'NOT_AN_IMAGE']; }
        $info = @getimagesize($tmp);
        if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_SOURCE_PIXELS) { return ['ok' => false, 'error' => 'NOT_AN_IMAGE']; }
        if ($mime === 'image/webp' && !function_exists('imagecreatefromwebp')) { return ['ok' => false, 'error' => 'NOT_AN_IMAGE']; }   // this PHP's GD has no WebP: refuse politely instead of a fatal error
        $src = match ($mime) { 'image/jpeg' => @imagecreatefromjpeg($tmp), 'image/png' => @imagecreatefrompng($tmp), default => @imagecreatefromwebp($tmp) };
        if (!$src) { return ['ok' => false, 'error' => 'NOT_AN_IMAGE']; }
        $w = imagesx($src); $h = imagesy($src);
        $scale = min(1.0, self::MAX_SIDE / max($w, $h));
        $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // transparent PNG areas become white, not black
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        if (!preg_match('/^[a-f0-9]{32}$/', $listingUuid)) { imagedestroy($dst); return ['ok' => false, 'error' => 'BAD_LISTING']; }
        $folder = $this->folder($listingUuid);
        if (!is_dir($folder) && !@mkdir($folder, 0750, true)) { imagedestroy($dst); return ['ok' => false, 'error' => 'STORAGE']; }
        $name = bin2hex(random_bytes(12)) . '.jpg';
        $ok = imagejpeg($dst, $folder . '/' . $name, 82);
        imagedestroy($dst);
        if (!$ok) { return ['ok' => false, 'error' => 'STORAGE']; }
        return ['ok' => true, 'file_name' => $name, 'width' => $nw, 'height' => $nh, 'bytes' => (int)filesize($folder . '/' . $name)];
    }

    /** Absolute path of a stored photo, or null for anything that is not one of our file names. */
    public function path(string $listingUuid, string $fileName): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $listingUuid) || !preg_match('/^[a-f0-9]{24}\.jpg$/', $fileName)) { return null; }
        $p = $this->folder($listingUuid) . '/' . $fileName;
        return is_file($p) ? $p : null;
    }

    public function delete(string $listingUuid, ?string $fileName = null): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $listingUuid)) { return; }
        $folder = $this->folder($listingUuid);
        if ($fileName !== null) { $p = $this->path($listingUuid, $fileName); if ($p) { @unlink($p); } return; }
        foreach (glob($folder . '/*.jpg') ?: [] as $f) { @unlink($f); }
        @rmdir($folder);
    }

    private function folder(string $uuid): string { return rtrim($this->dir, '/\\') . '/' . $uuid; }
}
