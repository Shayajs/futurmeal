<?php

namespace App\Notifications;

use App\Models\Friendship;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FriendRequestAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(public Friendship $friendship) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $friend = $this->friendship->user_id === $notifiable->id
            ? $this->friendship->friend
            : $this->friendship->user;

        return [
            'type' => 'friend_accepted',
            'friendship_id' => $this->friendship->id,
            'from_user_id' => $friend->id,
            'from_user_name' => $friend->name,
            'message' => $friend->name.' a accepté ta demande d\'ami.',
        ];
    }
}
