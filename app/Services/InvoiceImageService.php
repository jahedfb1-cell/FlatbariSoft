<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Compress-and-store for purchase invoice photos, plus the black-and-white
 * conversion used when an invoice is printed for an audit.
 *
 * Every upload - however it arrived - is decoded, rotated upright, scaled so
 * its long edge is at most config('invoice_images.max_edge'), and re-encoded
 * as a progressive JPEG, lowering quality (then size) until it fits
 * config('invoice_images.target_kb'). Re-encoding also strips all EXIF
 * metadata, which matters: phone photos carry the GPS location of whoever
 * took them.
 *
 * The `exif` PHP extension is NOT assumed (it is missing on this dev machine
 * and optional on shared hosting), so the orientation tag is read by hand.
 */
class InvoiceImageService
{
    /** @return array{path:string, original_kb:float, stored_kb:float, width:int, height:int, quality:int} */
    public function store(UploadedFile $file, string $folder): array
    {
        $original = (string) file_get_contents($file->getRealPath());
        $result = $this->compress($original);

        $path = trim($folder, '/').'/'.now()->format('Y/m').'/'.Str::uuid().'.jpg';
        Storage::disk($this->disk())->put($path, $result['data']);

        return [
            'path' => $path,
            'original_kb' => round(strlen($original) / 1024, 1),
            'stored_kb' => round(strlen($result['data']) / 1024, 1),
            'width' => $result['width'],
            'height' => $result['height'],
            'quality' => $result['quality'],
        ];
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    public function exists(?string $path): bool
    {
        return $path && Storage::disk($this->disk())->exists($path);
    }

    public function sizeKb(?string $path): ?float
    {
        return $this->exists($path) ? round(Storage::disk($this->disk())->size($path) / 1024, 1) : null;
    }

    public function read(string $path): string
    {
        return (string) Storage::disk($this->disk())->get($path);
    }

    public function response(string $path)
    {
        return Storage::disk($this->disk())->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * @return array{data:string, width:int, height:int, quality:int}
     *
     * @throws ValidationException when the bytes are not a usable JPEG/PNG/WebP
     */
    public function compress(string $binary): array
    {
        $info = @getimagesizefromstring($binary);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $this->fail('শুধু JPG, PNG বা WebP ছবি দেওয়া যাবে।');
        }

        [$width, $height] = $info;
        $this->assertDecodable($width, $height);

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            $this->fail('ছবিটি পড়া যায়নি — ফাইলটি নষ্ট হতে পারে।');
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $source = $this->applyOrientation($source, $this->jpegOrientation($binary));
        }

        $base = $this->scaledCopy($source, imagesx($source), imagesy($source), (int) config('invoice_images.max_edge'));
        unset($source);

        $target = (int) config('invoice_images.target_kb') * 1024;
        $start = (int) config('invoice_images.start_quality');
        $min = (int) config('invoice_images.min_quality');

        $best = null;
        $image = $base;
        // Up to 4 rounds: try every quality step, and if even the lowest is too
        // big, shrink the picture by 15% and try again.
        for ($round = 0; $round < 4; $round++) {
            for ($quality = $start; $quality >= $min; $quality -= 6) {
                $data = $this->encodeJpeg($image, $quality);
                $best = ['data' => $data, 'width' => imagesx($image), 'height' => imagesy($image), 'quality' => $quality];
                if (strlen($data) <= $target) {
                    return $best;
                }
            }
            $image = $this->scaledCopy($base, imagesx($base), imagesy($base), (int) (max(imagesx($base), imagesy($base)) * 0.85 ** ($round + 1)));
        }

        return $best;
    }

    /**
     * Greyscale + extra contrast: what actually comes out of a mono laser
     * printer legibly. Keeps the colour original untouched on disk.
     */
    public function toGrayscaleJpeg(string $jpeg, int $quality = 82): string
    {
        $image = @imagecreatefromstring($jpeg);
        if ($image === false) {
            return $jpeg;
        }

        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_CONTRAST, -25);

        return $this->encodeJpeg($image, $quality);
    }

    // ------------------------------------------------------------------

    private function disk(): string
    {
        return (string) config('invoice_images.disk');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['invoice_image' => $message]);
    }

    private function assertDecodable(int $width, int $height): void
    {
        if ($width * $height > (int) config('invoice_images.max_pixels')) {
            $this->fail('ছবির রেজোলিউশন অনেক বেশি — ছোট করে আবার দিন।');
        }

        // Decoded bitmap is 4 bytes/pixel; rotating a phone photo briefly holds two full copies (8), plus the resized one.
        $needed = $width * $height * 9 + 32 * 1024 * 1024;
        $limit = $this->memoryLimitBytes();
        if ($limit !== -1 && $needed > $limit) {
            @ini_set('memory_limit', (string) ($needed + 64 * 1024 * 1024));
            if ($this->memoryLimitBytes() !== -1 && $needed > $this->memoryLimitBytes()) {
                $this->fail('ছবিটি সার্ভারের জন্য খুব বড় — ছোট রেজোলিউশনে আবার দিন।');
            }
        }
    }

    private function memoryLimitBytes(): int
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '-1') {
            return -1;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** Scale to fit `maxEdge` (never upscales) onto a white canvas, which also flattens PNG/WebP transparency. */
    private function scaledCopy(\GdImage $src, int $width, int $height, int $maxEdge): \GdImage
    {
        $scale = min(1, $maxEdge / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $dst;
    }

    private function encodeJpeg(\GdImage $image, int $quality): string
    {
        imageinterlace($image, true); // progressive: paints blurry-to-sharp on slow connections
        ob_start();
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }

    /** Make the pixels upright according to the EXIF orientation value (1-8). */
    private function applyOrientation(\GdImage $image, int $orientation): \GdImage
    {
        $white = 0xFFFFFF;

        return match ($orientation) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($image, 180, $white) ?: $image,
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => $this->flip(imagerotate($image, -90, $white) ?: $image, IMG_FLIP_HORIZONTAL),
            6 => imagerotate($image, -90, $white) ?: $image,
            7 => $this->flip(imagerotate($image, 90, $white) ?: $image, IMG_FLIP_HORIZONTAL),
            8 => imagerotate($image, 90, $white) ?: $image,
            default => $image,
        };
    }

    private function flip(\GdImage $image, int $mode): \GdImage
    {
        imageflip($image, $mode);

        return $image;
    }

    /** Orientation (1-8) from a JPEG's EXIF block; 1 (normal) when absent or unreadable. */
    private function jpegOrientation(string $data): int
    {
        if (substr($data, 0, 2) !== "\xFF\xD8") {
            return 1;
        }

        $pos = 2;
        $length = strlen($data);
        while ($pos + 4 <= $length && $data[$pos] === "\xFF") {
            $marker = ord($data[$pos + 1]);
            if ($marker === 0xDA || $marker === 0xD9) { // start of scan / end of image: no more headers
                break;
            }
            $size = unpack('n', substr($data, $pos + 2, 2))[1];
            if ($marker === 0xE1 && substr($data, $pos + 4, 6) === "Exif\0\0") {
                return $this->tiffOrientation(substr($data, $pos + 10, max(0, $size - 8)));
            }
            $pos += 2 + $size;
        }

        return 1;
    }

    private function tiffOrientation(string $tiff): int
    {
        if (strlen($tiff) < 14) {
            return 1;
        }
        $order = substr($tiff, 0, 2);
        if ($order !== 'II' && $order !== 'MM') {
            return 1;
        }
        $little = $order === 'II';
        $u16 = fn (int $o) => unpack($little ? 'v' : 'n', substr($tiff, $o, 2))[1];
        $u32 = fn (int $o) => unpack($little ? 'V' : 'N', substr($tiff, $o, 4))[1];

        $ifd = $u32(4);
        if ($ifd + 2 > strlen($tiff)) {
            return 1;
        }
        $entries = $u16($ifd);
        for ($i = 0; $i < $entries; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($entry + 12 > strlen($tiff)) {
                break;
            }
            if ($u16($entry) === 0x0112) {
                $value = $u16($entry + 8);

                return $value >= 1 && $value <= 8 ? $value : 1;
            }
        }

        return 1;
    }
}
