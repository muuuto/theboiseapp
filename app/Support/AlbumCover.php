<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

// Album preview images: resized by the site, keeping the photo's own shape (no padding, no forced crop).
// The old previews were padded to 800x600 with transparent (sometimes black) bars by BulkResize.
// Cards show them cropped to 4:3 with CSS, the album page shows the whole photo.
class AlbumCover
{
    private const MAX_SIZE = 1600;
    private const QUALITY = 82;

    // Stores an uploaded photo in the public disk and returns its path (album previews in logos/,
    // also used for forum covers, attachments and category logos). Any format, phone rotation fixed,
    // padding removed (in case someone uploads an old padded preview), saved as WebP
    public static function store(UploadedFile $file, string $folder = 'logos', int $maxSize = self::MAX_SIZE): string
    {
        $image = self::load($file->getRealPath());
        $image = self::crop($image) ?? $image;
        $image = self::fitWithin($image, $maxSize);

        return self::save($image, $folder);
    }

    // Removes black bars baked into an existing preview. Returns the new path, or null if it had none
    public static function trimBars(string $path): ?string
    {
        $trimmed = self::crop(self::load(Storage::disk('public')->path($path)));

        return $trimmed ? self::save($trimmed) : null;
    }

    // The image without its padding, or null if it has none
    private static function crop(GdImage $image): ?GdImage
    {
        [$left, $right, $top, $bottom] = self::detectBars($image);

        if (! $left && ! $right && ! $top && ! $bottom) {
            return null;
        }

        $width = imagesx($image) - $left - $right;
        $height = imagesy($image) - $top - $bottom;

        // Almost everything would go (an all-black or empty image): that's not padding, keep it as it is
        if ($width < imagesx($image) * 0.2 || $height < imagesy($image) * 0.2) {
            return null;
        }

        return imagecrop($image, ['x' => $left, 'y' => $top, 'width' => $width, 'height' => $height]);
    }

    // Padding in pixels on each side [left, right, top, bottom]:
    // - transparent lines are always padding (photos are opaque), however thin
    // - after them, flat dark bars count only in pairs on opposite sides wider than 3%, so a photo
    //   with one dark edge (a night sky...) is left alone
    public static function detectBars(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $sides = [
            'left' => [$width, fn ($i, $j) => [$i, $j], $height],
            'right' => [$width, fn ($i, $j) => [$width - 1 - $i, $j], $height],
            'top' => [$height, fn ($i, $j) => [$j, $i], $width],
            'bottom' => [$height, fn ($i, $j) => [$j, $height - 1 - $i], $width],
        ];

        $transparent = [];
        $dark = [];
        foreach ($sides as $side => [$lines, $point, $length]) {
            $transparent[$side] = self::countLines($image, $lines, $point, $length, 0, self::isTransparentLine(...));
            $dark[$side] = self::countLines($image, $lines, $point, $length, $transparent[$side], self::isDarkLine(...));
        }

        $minimum = 0.03;
        $horizontal = $dark['left'] > $width * $minimum && $dark['right'] > $width * $minimum;
        $vertical = $dark['top'] > $height * $minimum && $dark['bottom'] > $height * $minimum;

        return [
            $transparent['left'] + ($horizontal ? $dark['left'] : 0),
            $transparent['right'] + ($horizontal ? $dark['right'] : 0),
            $transparent['top'] + ($vertical ? $dark['top'] : 0),
            $transparent['bottom'] + ($vertical ? $dark['bottom'] : 0),
        ];
    }

    // How many lines from one side (starting at $from) match $test.
    // $point maps (line, position on the line) to x, y
    private static function countLines(GdImage $image, int $lines, callable $point, int $length, int $from, callable $test): int
    {
        $count = 0;
        for ($line = $from; $line < $lines / 2; $line++) {
            $pixels = [];
            for ($position = 0; $position < $length; $position += 5) {
                $pixels[] = imagecolorat($image, ...$point($line, $position));
            }
            if (! $test($pixels)) {
                break;
            }
            $count++;
        }

        return $count;
    }

    // Mostly see-through, including the half-transparent border blended into the photo
    private static function isTransparentLine(array $pixels): bool
    {
        $clear = count(array_filter($pixels, fn ($color) => (($color >> 24) & 127) > 10));

        return $clear >= count($pixels) * 0.9;
    }

    private static function isDarkLine(array $pixels): bool
    {
        $values = array_map(fn ($color) => ((($color >> 16) & 255) + (($color >> 8) & 255) + ($color & 255)) / 3, $pixels);

        return array_sum($values) / count($values) <= 24 && max($values) - min($values) <= 24;
    }

    private static function load(string $file): GdImage
    {
        $type = @exif_imagetype($file);
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            IMAGETYPE_WEBP => @imagecreatefromwebp($file),
            IMAGETYPE_GIF => @imagecreatefromgif($file),
            default => false,
        };

        if (! $image) {
            throw new RuntimeException('Unsupported or broken image');
        }

        // Phone photos store their rotation in EXIF
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $angle = match ((int) (@exif_read_data($file)['Orientation'] ?? 1)) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };
            if ($angle) {
                $image = imagerotate($image, $angle, 0);
            }
        }

        imagepalettetotruecolor($image);
        imagesavealpha($image, true);

        return $image;
    }

    private static function fitWithin(GdImage $image, int $max): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min(1, $max / max($width, $height));

        if ($ratio === 1) {
            return $image;
        }

        return imagescale($image, (int) round($width * $ratio), (int) round($height * $ratio), IMG_BICUBIC);
    }

    private static function save(GdImage $image, string $folder = 'logos'): string
    {
        ob_start();
        imagewebp($image, null, self::QUALITY);
        $data = ob_get_clean();

        $path = $folder . '/' . Str::random(40) . '.webp';
        Storage::disk('public')->put($path, $data);

        return $path;
    }
}
