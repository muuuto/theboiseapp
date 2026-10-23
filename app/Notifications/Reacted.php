<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// "Zecca reacted ❤️ to your album": shown in the notification bell only, never emailed
class Reacted extends Notification
{
    use Queueable;

    public function __construct(
        public User $reactor,
        public Listing|Post $target,
        public string $emoji,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $isAlbum = $this->target instanceof Listing;

        return [
            'reactor_id' => $this->reactor->id,
            'reactor_name' => $this->reactor->name,
            'emoji' => $this->emoji,
            'target_type' => $isAlbum ? 'album' : 'post',
            'target_id' => $this->target->id,
            'title' => $this->target->title,
            'url' => $isAlbum ? "/listings/{$this->target->id}" : "/forum/{$this->target->category_id}/{$this->target->id}",
        ];
    }
}
