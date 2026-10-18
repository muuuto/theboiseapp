<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Support\AlbumCover;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

// One-time fix: album previews made with BulkResize ("exact 800x600") have black bars when the
// photo was vertical. The trimmed preview is a new file: the original stays as a backup.
class TrimAlbumCoverBars extends Command
{
    protected $signature = 'albums:trim-cover-bars {--dry-run : Only list the previews that would be trimmed}';
    protected $description = 'Remove the black bars baked into old album previews';

    public function handle()
    {
        $trimmed = 0;

        foreach (Listing::whereNotNull('logo')->get() as $listing) {
            if (! Storage::disk('public')->exists($listing->logo)) {
                continue;
            }

            try {
                if ($this->option('dry-run')) {
                    $image = imagecreatefromstring(Storage::disk('public')->get($listing->logo));
                    $bars = $image ? AlbumCover::detectBars($image) : [0, 0, 0, 0];
                    if (array_sum($bars)) {
                        $this->line("#{$listing->id} {$listing->title}: bars " . implode('/', $bars) . ' (left/right/top/bottom)');
                        $trimmed++;
                    }
                    continue;
                }

                if ($newPath = AlbumCover::trimBars($listing->logo)) {
                    $listing->update(['logo' => $newPath]);
                    $this->line("#{$listing->id} {$listing->title}: trimmed");
                    $trimmed++;
                }
            } catch (Throwable $e) {
                $this->warn("#{$listing->id} {$listing->title}: skipped ({$e->getMessage()})");
            }
        }

        $this->info(($this->option('dry-run') ? 'Would trim ' : 'Trimmed ') . "{$trimmed} album previews.");

        return self::SUCCESS;
    }
}
