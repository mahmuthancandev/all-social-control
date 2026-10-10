<?php

namespace Tests\Feature\Modules;

use App\Enums\ContentType;
use App\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    public function test_admin_uploads_image_with_random_name_and_metadata(): void
    {
        $admin = $this->admin();
        $file = UploadedFile::fake()->image('Kapak Görseli.png', 1280, 720);

        $this->actingAs($admin)->post('/media', ['files' => [$file], 'folder' => 'Kapaklar'])->assertSessionHasNoErrors();

        $media = MediaFile::firstOrFail();
        $this->assertSame('image', $media->type);
        $this->assertSame(1280, $media->width);
        $this->assertSame('Kapaklar', $media->folder);
        $this->assertStringNotContainsString('Kapak', $media->path); // diskte rastgele ad
        Storage::disk('media')->assertExists($media->path);
        $this->assertSame(64, strlen($media->checksum));
    }

    public function test_same_file_is_deduplicated(): void
    {
        $admin = $this->admin();
        $file = UploadedFile::fake()->image('a.png', 100, 100);

        $this->actingAs($admin)->post('/media', ['files' => [$file]]);
        $this->actingAs($admin)->post('/media', ['files' => [$file]]);

        $this->assertSame(1, MediaFile::count());
    }

    public function test_disguised_php_file_is_rejected(): void
    {
        $admin = $this->admin();
        $evil = UploadedFile::fake()->createWithContent('video.mp4', '<?php system($_GET["c"]); ?>');

        $this->actingAs($admin)->post('/media', ['files' => [$evil]])->assertSessionHasErrors('files.0');
        $this->assertSame(0, MediaFile::count());
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        $admin = $this->admin();
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs($admin)->post('/media', ['files' => [$svg]])->assertSessionHasErrors('files.0');
    }

    public function test_media_files_require_login_and_are_served_safely(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/media', ['files' => [UploadedFile::fake()->image('a.png', 10, 10)]]);
        $media = MediaFile::firstOrFail();

        auth()->logout();
        $this->get("/media/{$media->id}/file")->assertRedirect('/login');

        $this->actingAs($this->viewer())->get("/media/{$media->id}/file")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_viewer_cannot_upload_or_delete(): void
    {
        $viewer = $this->viewer();
        $media = $this->media('image');

        $this->actingAs($viewer)->post('/media', ['files' => [UploadedFile::fake()->image('a.png')]])->assertForbidden();
        $this->actingAs($viewer)->delete("/media/{$media->id}")->assertForbidden();
    }

    public function test_media_in_use_requires_force_delete(): void
    {
        $admin = $this->admin();
        $media = $this->media();
        $this->target($this->account(), ContentType::YouTubeVideo, $media);

        $this->actingAs($admin)->delete("/media/{$media->id}")->assertSessionHas('error');
        $this->assertModelExists($media);

        $this->actingAs($admin)->delete("/media/{$media->id}?force=1")->assertSessionHas('success');
        $this->assertModelMissing($media);
    }
}
