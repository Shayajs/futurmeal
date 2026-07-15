<?php

namespace App\Notifications;

use App\Models\Friendship;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FriendRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public Friendship $friendship) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'friend_request',
            'friendship_id' => $this->friendship->id,
            'from_user_id' => $this->friendship->user_id,
            'from_user_name' => $this->friendship->user->name,
            'message' => $this->friendship->user->name.' souhaite devenir ton ami.',
        ];
    }
}
