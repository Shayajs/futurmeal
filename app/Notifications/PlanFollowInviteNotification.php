<?php

namespace App\Notifications;

use App\Models\PlanShare;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlanFollowInviteNotification extends Notification
{
    use Queueable;

    public function __construct(public PlanShare $share) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'plan_follow_invite',
            'plan_share_id' => $this->share->id,
            'from_user_id' => $this->share->owner_id,
            'from_user_name' => $this->share->owner->name,
            'can_edit' => $this->share->can_edit,
            'message' => $this->share->owner->name.' t\'invite à suivre son plan de repas.',
        ];
    }
}
