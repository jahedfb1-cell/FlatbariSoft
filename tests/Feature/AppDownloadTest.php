<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The public page + file route a phone uses to download the Android app. */
class AppDownloadTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        // Never touch the real public/downloads/ - point the config at a scratch folder.
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gv-apk-test-'.uniqid();
        mkdir($this->dir);
        config(['mobile_app.apk_path' => $this->dir.DIRECTORY_SEPARATOR.'green-valley-bms.apk']);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function fakeBuild(): void
    {
        file_put_contents(config('mobile_app.apk_path'), str_repeat('PK', 2048));
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.'app-version.json', json_encode([
            'versionName' => '1.2.3', 'builtAt' => '2026-10-26', 'sha256' => 'abc123deadbeef',
        ]));
    }

    public function test_page_is_public_and_says_when_no_app_has_been_built_yet(): void
    {
        $this->get('/download/app')->assertOk()->assertSee('এখনো তৈরি')->assertDontSee('ডাউনলোড করুন');
    }

    public function test_page_shows_version_size_checksum_and_the_download_link(): void
    {
        $this->fakeBuild();

        $this->get('/download/app')->assertOk()
            ->assertSee('1.2.3')
            ->assertSee('abc123deadbeef')
            ->assertSee('/download/apk')
            ->assertSee('অ্যাপ ডাউনলোড করুন');
    }

    public function test_apk_downloads_with_the_android_package_mime_type_and_no_login(): void
    {
        $this->fakeBuild();

        $response = $this->get('/download/apk');

        $response->assertOk();
        $this->assertSame('application/vnd.android.package-archive', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('green-valley-bms.apk', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    }

    public function test_apk_is_a_404_until_a_build_exists(): void
    {
        $this->get('/download/apk')->assertNotFound();
    }
}
