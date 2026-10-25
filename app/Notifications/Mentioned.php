<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

// "Muto mentioned you": shown in the notification bell (and on the phone), never emailed
class Mentioned extends Notification
{
    use Queueable;

    public function __construct(
        public User $author,
        public string $excerpt,
        public string $title,
        public string $url,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'author_id' => $this->author->id,
            'author_name' => $this->author->name,
            'excerpt' => $this->excerpt,
            'title' => $this->title,
            'url' => $this->url,
        ];
    }
}
