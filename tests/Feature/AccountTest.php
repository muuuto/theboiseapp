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

    public function test_phone_photos_are_rotated_using_their_exif_orientation(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        // A landscape 800x600 JPEG that the phone marks as "rotate 90° clockwise" (EXIF orientation 6)
        $file = tempnam(sys_get_temp_dir(), 'photo') . '.jpg';
        file_put_contents($file, $this->jpegWithOrientation(800, 600, 6));

        $this->actingAs($user)->put('/account/update', [
            'name' => $user->name,
            'profilePicture' => new UploadedFile($file, 'photo.jpg', 'image/jpeg', null, true),
        ])->assertRedirect('/account');

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($user->fresh()->profilePicture));
        $this->assertGreaterThan($width, $height, 'the photo should be portrait after rotation');
    }

    private function jpegWithOrientation(int $width, int $height, int $orientation): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();

        // Minimal EXIF block: big-endian TIFF header + one IFD entry, Orientation (0x0112) as SHORT
        $tiff = "MM\x00\x2A\x00\x00\x00\x08" . "\x00\x01"
            . "\x01\x12\x00\x03\x00\x00\x00\x01" . pack('n', $orientation) . "\x00\x00"
            . "\x00\x00\x00\x00";
        $app1 = "Exif\x00\x00" . $tiff;

        // Insert the APP1 (EXIF) segment right after the JPEG start marker
        return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpeg, 2);
    }
}
