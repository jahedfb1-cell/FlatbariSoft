<?php

namespace Tests\Support;

/** Builds realistic-enough invoice photos for the image-pipeline tests. */
trait MakesInvoiceImages
{
    /** A busy JPEG (hundreds of random strokes) that a real camera shot would resemble in weight. */
    protected function makeJpeg(int $width = 3000, int $height = 2000, int $quality = 95): string
    {
        mt_srand(42);
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, 240, 235, 220));
        for ($i = 0; $i < 2500; $i++) {
            imagesetthickness($img, mt_rand(1, 4));
            imageline(
                $img,
                mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height),
                imagecolorallocate($img, mt_rand(0, 200), mt_rand(0, 200), mt_rand(0, 200)),
            );
        }
        imagefilledellipse($img, (int) ($width * 0.8), (int) ($height * 0.75), 400, 400, imagecolorallocate($img, 170, 40, 40));

        ob_start();
        imagejpeg($img, null, $quality);

        return (string) ob_get_clean();
    }

    protected function makePng(int $width, int $height, bool $transparent = false): string
    {
        $img = imagecreatetruecolor($width, $height);
        if ($transparent) {
            imagealphablending($img, false);
            imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
            imagesavealpha($img, true);
        } else {
            imagefill($img, 0, 0, imagecolorallocate($img, 10, 120, 200));
        }
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    protected function makeGif(): string
    {
        $img = imagecreate(20, 20);
        imagecolorallocate($img, 255, 255, 255);
        ob_start();
        imagegif($img);

        return (string) ob_get_clean();
    }

    /** Inject an EXIF block carrying the given Orientation (1-8), exactly as a phone camera would. */
    protected function withExifOrientation(string $jpeg, int $orientation): string
    {
        $tiff = "II*\x00".pack('V', 8)         // little-endian header, IFD0 at offset 8
            .pack('v', 1)                      // one entry
            .pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientation)."\x00\x00" // Orientation, SHORT, count 1
            .pack('V', 0);                     // no next IFD
        $exif = "Exif\x00\x00".$tiff;

        return "\xFF\xD8\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    }

    /** @return array{0:int, 1:int} */
    protected function dimensions(string $imageBytes): array
    {
        $info = getimagesizefromstring($imageBytes);

        return [$info[0], $info[1]];
    }
}
