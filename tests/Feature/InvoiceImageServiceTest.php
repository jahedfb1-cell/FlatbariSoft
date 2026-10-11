<?php

namespace Tests\Feature;

use App\Services\InvoiceImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\MakesInvoiceImages;
use Tests\TestCase;

/**
 * The "5 MB phone photo -> a few hundred KB" pipeline: scale, re-encode,
 * rotate upright from EXIF, strip metadata, flatten transparency.
 */
class InvoiceImageServiceTest extends TestCase
{
    use MakesInvoiceImages;

    private InvoiceImageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->service = app(InvoiceImageService::class);
    }

    public function test_a_large_photo_is_squeezed_to_a_fraction_of_its_size(): void
    {
        $original = $this->makeJpeg(3600, 2400);

        $result = $this->service->compress($original);

        $this->assertGreaterThan(1_000_000, strlen($original), 'fixture should be a heavy, camera-like file');
        $this->assertLessThanOrEqual(config('invoice_images.target_kb') * 1024, strlen($result['data']));
        $this->assertLessThan(strlen($original) * 0.25, strlen($result['data']));
        [$w, $h] = $this->dimensions($result['data']);
        $this->assertLessThanOrEqual(config('invoice_images.max_edge'), max($w, $h));
        $this->assertSame([$w, $h], [$result['width'], $result['height']]);
    }

    public function test_output_is_a_progressive_jpeg_whatever_went_in(): void
    {
        $result = $this->service->compress($this->makePng(300, 200));

        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($result['data'])[2]);
        // Progressive JPEGs use the SOF2 marker (FFC2) instead of baseline SOF0 (FFC0).
        $this->assertStringContainsString("\xFF\xC2", $result['data']);
    }

    public function test_small_images_are_never_upscaled(): void
    {
        $result = $this->service->compress($this->makeJpeg(200, 100));

        $this->assertSame([200, 100], $this->dimensions($result['data']));
    }

    public function test_exif_rotation_is_applied_so_a_sideways_phone_photo_comes_out_upright(): void
    {
        // Phone held in portrait: sensor data is landscape 800x400, EXIF says "rotate 90 CW" (6).
        $sideways = $this->withExifOrientation($this->makeJpeg(800, 400), 6);

        $this->assertSame([400, 800], $this->dimensions($this->service->compress($sideways)['data']));
    }

    public function test_every_rotation_orientation_gives_the_right_shape(): void
    {
        $base = $this->makeJpeg(600, 300);

        foreach ([1 => [600, 300], 2 => [600, 300], 3 => [600, 300], 4 => [600, 300],
            5 => [300, 600], 6 => [300, 600], 7 => [300, 600], 8 => [300, 600]] as $orientation => $expected) {
            $out = $this->service->compress($this->withExifOrientation($base, $orientation))['data'];
            $this->assertSame($expected, $this->dimensions($out), "orientation {$orientation}");
        }
    }

    public function test_rotation_goes_the_right_way_not_just_the_right_shape(): void
    {
        // White 400x200 sensor image with a red block in its TOP-LEFT corner.
        $img = imagecreatetruecolor(400, 200);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 0, 0, 59, 59, imagecolorallocate($img, 255, 0, 0));
        ob_start();
        imagejpeg($img, null, 95);
        $jpeg = (string) ob_get_clean();

        $isRed = fn ($image, int $x, int $y) => ((imagecolorat($image, $x, $y) >> 16) & 255) > 200
            && ((imagecolorat($image, $x, $y) >> 8) & 255) < 90;

        // EXIF 6 = "rotate 90 CW to display": top-left corner travels to TOP-RIGHT of the 200x400 result.
        $cw = imagecreatefromstring($this->service->compress($this->withExifOrientation($jpeg, 6))['data']);
        $this->assertTrue($isRed($cw, 190, 10), 'orientation 6: red block should land top-right');
        $this->assertFalse($isRed($cw, 10, 10));

        // EXIF 8 = "rotate 90 CCW": top-left travels to BOTTOM-LEFT.
        $ccw = imagecreatefromstring($this->service->compress($this->withExifOrientation($jpeg, 8))['data']);
        $this->assertTrue($isRed($ccw, 10, 390), 'orientation 8: red block should land bottom-left');

        // EXIF 3 = upside down: top-left travels to BOTTOM-RIGHT.
        $flip = imagecreatefromstring($this->service->compress($this->withExifOrientation($jpeg, 3))['data']);
        $this->assertTrue($isRed($flip, 390, 190), 'orientation 3: red block should land bottom-right');

        // EXIF 2 = mirrored: top-left travels to TOP-RIGHT, shape unchanged.
        $mirror = imagecreatefromstring($this->service->compress($this->withExifOrientation($jpeg, 2))['data']);
        $this->assertTrue($isRed($mirror, 390, 10), 'orientation 2: red block should land top-right');
    }

    public function test_exif_metadata_including_gps_is_stripped(): void
    {
        $withExif = $this->withExifOrientation($this->makeJpeg(400, 300), 1);
        $this->assertStringContainsString("Exif\x00\x00", $withExif);

        $this->assertStringNotContainsString("Exif\x00\x00", $this->service->compress($withExif)['data']);
    }

    public function test_transparent_png_is_flattened_onto_white_not_black(): void
    {
        $out = $this->service->compress($this->makePng(120, 120, transparent: true))['data'];
        $rgb = imagecolorat(imagecreatefromstring($out), 60, 60);

        $this->assertGreaterThan(245, ($rgb >> 16) & 255);
        $this->assertGreaterThan(245, ($rgb >> 8) & 255);
        $this->assertGreaterThan(245, $rgb & 255);
    }

    public function test_non_images_and_unsupported_formats_are_rejected(): void
    {
        foreach (['just some text, not a picture', $this->makeGif()] as $bad) {
            try {
                $this->service->compress($bad);
                $this->fail('expected a ValidationException');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('invoice_image', $e->errors());
            }
        }
    }

    public function test_absurd_resolutions_are_refused_before_decoding(): void
    {
        config(['invoice_images.max_pixels' => 1000]);

        $this->expectException(ValidationException::class);
        $this->service->compress($this->makeJpeg(100, 100));
    }

    public function test_grayscale_conversion_leaves_no_colour(): void
    {
        $colour = $this->service->compress($this->makeJpeg(500, 400))['data'];

        $img = imagecreatefromstring($this->service->toGrayscaleJpeg($colour));
        foreach ([[50, 50], [250, 200], [450, 350], [400, 300]] as [$x, $y]) {
            $c = imagecolorat($img, $x, $y);
            [$r, $g, $b] = [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
            $this->assertLessThanOrEqual(4, max($r, $g, $b) - min($r, $g, $b), "pixel {$x},{$y} is still coloured");
        }
    }

    public function test_store_writes_a_jpg_on_the_private_disk_and_reports_sizes(): void
    {
        $upload = UploadedFile::fake()->createWithContent('phone-photo.png', $this->makeJpeg(2400, 1800));

        $stored = $this->service->store($upload, 'invoices/gas');

        $this->assertStringStartsWith('invoices/gas/'.now()->format('Y/m').'/', $stored['path']);
        $this->assertStringEndsWith('.jpg', $stored['path']);
        Storage::disk('local')->assertExists($stored['path']);
        $this->assertLessThan($stored['original_kb'], $stored['stored_kb']);
        $this->assertSame($stored['stored_kb'], $this->service->sizeKb($stored['path']));
    }

    public function test_delete_removes_the_file_and_tolerates_null(): void
    {
        $stored = $this->service->store(UploadedFile::fake()->createWithContent('a.jpg', $this->makeJpeg(400, 300)), 'invoices/gas');

        $this->service->delete($stored['path']);
        $this->service->delete(null);

        Storage::disk('local')->assertMissing($stored['path']);
    }
}
