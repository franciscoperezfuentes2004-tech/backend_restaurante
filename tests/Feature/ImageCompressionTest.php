<?php

namespace Tests\Feature;

use App\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageCompressionTest extends TestCase
{
    public function test_compression_service_converts_to_webp(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('foto_grande.jpg', 1600, 1200);

        $result = ImageCompressionService::compressAndStore($file, 'dishes', 800, 75);

        $this->assertNotEmpty($result['path']);
        $this->assertStringEndsWith('.webp', $result['path']);
        $this->assertStringContainsString('dishes/', $result['path']);
        $this->assertNotEmpty($result['url']);

        Storage::disk('public')->assertExists($result['path']);

        $savedContent = Storage::disk('public')->get($result['path']);
        $this->assertNotEmpty($savedContent);
    }
}
