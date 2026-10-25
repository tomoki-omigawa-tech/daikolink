<?php

namespace App\Notifications;

use App\Models\SubstituteRequest;
use App\Support\DateLabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 新しい代講依頼が出たことを、ほかの講師に知らせる（US-05）
 */
class SubstituteRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, SubstituteRequest>  $requests  同じ先生が一度に出した依頼
     */
    public function __construct(public Collection $requests) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->requests->load(['shift.timeSlot', 'shift.subject', 'requester']);
        /** @var SubstituteRequest $first */
        $first = $this->requests->first();
        $more = $this->requests->count() > 1 ? ' ほか' : '';

        $message = (new MailMessage)
            ->subject('【DaikoLink】代講の募集：'.DateLabel::date($first->shift->date).' '.$first->shift->timeSlot->name.$more)
            ->greeting('代講の募集があります')
            ->line("{$first->requester->displayName()}さんから代講の依頼が出ました。");

        foreach ($this->requests as $request) {
            $shift = $request->shift;
            $message->line('・'.DateLabel::date($shift->date)." {$shift->timeSlot->name}（{$shift->timeSlot->timeRange()}） {$shift->lessonLabel()}");
        }

        return $message
            ->action('募集中の代講を見る', route('requests.index'))
            ->line('引き受けられる方は、サイトから応募してください。');
    }
}
