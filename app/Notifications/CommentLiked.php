<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

// "Zecca liked your comment": shown in the notification bell only, never emailed
class CommentLiked extends Notification
{
    use Queueable;

    public function __construct(
        public User $liker,
        public Comment|PostComment $comment,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $isAlbum = $this->comment instanceof Comment;
        $parent = $isAlbum ? $this->comment->listing : $this->comment->post;

        return [
            'liker_id' => $this->liker->id,
            'liker_name' => $this->liker->name,
            'comment_type' => $isAlbum ? 'album_comment' : 'post_comment',
            'comment_id' => $this->comment->id,
            'excerpt' => Str::limit(trim(html_entity_decode(strip_tags($this->comment->comment))), 60),
            'title' => $parent?->title,
            'url' => ($isAlbum
                ? "/listings/{$parent?->id}"
                : "/forum/{$parent?->category_id}/{$parent?->id}") . "#comment-{$this->comment->id}",
        ];
    }
}
