<?php

namespace App\Notifications;

use App\Models\PlanShare;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlanShareAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(public PlanShare $share) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $other = $notifiable->id === $this->share->owner_id
            ? $this->share->viewer
            : $this->share->owner;

        return [
            'type' => 'plan_share_accepted',
            'plan_share_id' => $this->share->id,
            'from_user_id' => $other->id,
            'from_user_name' => $other->name,
            'can_edit' => $this->share->can_edit,
            'message' => 'Partage de plan accepté avec '.$other->name.'.',
        ];
    }
}
