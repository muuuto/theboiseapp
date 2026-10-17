<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_page_renders(): void
    {
        $this->actingAs(User::factory()->create())->get('/account')->assertOk();
    }

    public function test_user_can_change_name(): void
    {
        $user = User::factory()->create(['name' => 'Old name']);

        $this->actingAs($user)->put('/account/update', ['name' => 'New name'])
            ->assertRedirect('/account');

        $this->assertEquals('New name', $user->fresh()->name);
    }

    public function test_name_must_be_unique(): void
    {
        User::factory()->create(['name' => 'Taken']);
        $user = User::factory()->create(['name' => 'Mine']);

        $this->actingAs($user)->put('/account/update', ['name' => 'Taken']);

        $this->assertEquals('Mine', $user->fresh()->name);
    }

    public function test_profile_picture_is_resized_and_converted_to_webp(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->put('/account/update', [
            'name' => $user->name,
            'profilePicture' => UploadedFile::fake()->image('me.jpg', 1200, 800),
        ])->assertRedirect('/account');

        $path = $user->fresh()->profilePicture;
        $this->assertStringStartsWith('profilePic/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertLessThanOrEqual(384, max($width, $height));
    }
}
