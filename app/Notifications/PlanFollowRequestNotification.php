<?php

namespace App\Notifications;

use App\Models\PlanShare;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlanFollowRequestNotification extends Notification
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
            'type' => 'plan_follow_request',
            'plan_share_id' => $this->share->id,
            'from_user_id' => $this->share->viewer_id,
            'from_user_name' => $this->share->viewer->name,
            'can_edit' => false,
            'message' => $this->share->viewer->name.' demande à suivre ton plan de repas.',
        ];
    }
}
