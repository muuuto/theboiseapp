<?php

namespace Tests\Feature;

use App\Support\AlbumCover;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlbumCoverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_transparent_padding_is_removed_however_thin(): void
    {
        // A 400x600 photo padded to 800x600 with transparent bars, like BulkResize did
        $image = $this->canvas(800, 600, transparent: true);
        imagefilledrectangle($image, 200, 0, 599, 599, imagecolorallocate($image, 200, 120, 60));
        $path = $this->store($image);

        $trimmed = AlbumCover::trimBars($path);

        $this->assertNotNull($trimmed);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($trimmed));
        $this->assertEquals([400, 600], [$width, $height]);
        // The original stays as a backup
        Storage::disk('public')->assertExists($path);
    }

    public function test_black_bars_on_both_sides_are_removed(): void
    {
        $image = $this->canvas(800, 600);
        imagefilledrectangle($image, 0, 75, 799, 524, imagecolorallocate($image, 90, 160, 220));

        $this->assertEquals([0, 0, 75, 75], AlbumCover::detectBars($image));
    }

    public function test_a_dark_edge_on_one_side_only_is_part_of_the_photo(): void
    {
        // Night sky on the left: dark, but nothing on the right
        $image = $this->canvas(800, 600);
        imagefilledrectangle($image, 100, 0, 799, 599, imagecolorallocate($image, 230, 200, 120));

        $this->assertEquals([0, 0, 0, 0], AlbumCover::detectBars($image));
        $this->assertNull(AlbumCover::trimBars($this->store($image)));
    }

    public function test_uploads_lose_their_padding_too(): void
    {
        // An old padded preview uploaded again: 400x600 photo in an 800x600 transparent frame
        $image = $this->canvas(800, 600, transparent: true);
        imagefilledrectangle($image, 200, 0, 599, 599, imagecolorallocate($image, 200, 120, 60));
        $file = tempnam(sys_get_temp_dir(), 'cover') . '.png';
        imagepng($image, $file);

        $path = AlbumCover::store(new UploadedFile($file, 'old-preview.png', 'image/png', null, true));

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertEquals([400, 600], [$width, $height]);
    }

    public function test_normal_uploads_keep_their_full_size_and_shape(): void
    {
        // Laravel's fake images are completely black: a very dark photo must not be "trimmed" away
        $path = AlbumCover::store(UploadedFile::fake()->image('night.jpg', 1200, 900));

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertEquals([1200, 900], [$width, $height]);
    }

    private function canvas(int $width, int $height, bool $transparent = false): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        $background = $transparent ? imagecolorallocatealpha($image, 0, 0, 0, 127) : imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $background);

        return $image;
    }

    private function store(GdImage $image): string
    {
        ob_start();
        imagewebp($image, null, 100);
        Storage::disk('public')->put($path = 'logos/test.webp', ob_get_clean());

        return $path;
    }
}
