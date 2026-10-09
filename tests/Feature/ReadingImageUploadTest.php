<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReadingImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_separate_cover_and_detail_images(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $article = [
            'title' => 'A Short Story',
            'rightsType' => 'original',
            'paragraphs' => [['sentences' => 'The sun rose.', 'translation' => '太阳升起来了。']],
        ];

        $this->post('/admin/articles', $article + [
            'coverImage' => UploadedFile::fake()->image('cover.png', 400, 400),
            'detailImage' => UploadedFile::fake()->image('detail.png', 960, 540),
        ])->assertRedirect();

        $saved = DB::table('reading_items')->where('title', 'A Short Story')->first();
        $this->assertNotNull($saved);
        $this->assertStringStartsWith('article-covers/', $saved->cover_asset);
        $this->assertStringStartsWith('article-detail-images/', $saved->detail_image_asset);
        Storage::disk('public')->assertExists([$saved->cover_asset, $saved->detail_image_asset]);

        DB::table('reading_items')->where('id', $saved->id)->update(['status' => 'published']);
        $this->getJson('/api/v1/reading-items/'.$saved->id)->assertOk()
            ->assertJsonPath('coverUrl', Storage::disk('public')->url($saved->cover_asset))
            ->assertJsonPath('detailImageUrl', Storage::disk('public')->url($saved->detail_image_asset));

        $this->put('/admin/articles/'.$saved->id, $article + [
            'coverImage' => UploadedFile::fake()->image('new-cover.png', 400, 400),
        ])->assertRedirect();
        $updated = DB::table('reading_items')->where('id', $saved->id)->first();
        $this->assertNotSame($saved->cover_asset, $updated->cover_asset);
        $this->assertSame($saved->detail_image_asset, $updated->detail_image_asset);
    }
}
