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
 * 当日の正午時点で未確定の依頼を、スタッフにまとめて知らせる（US-18）
 */
class UnfilledRequestsStaffNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, SubstituteRequest>  $requests  今日の未確定の依頼（開始時刻順）
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
        $this->requests->loadMissing(['shift.timeSlot', 'shift.subject', 'requester']);

        $message = (new MailMessage)
            ->subject("【DaikoLink】本日の未確定の代講：{$this->requests->count()}件")
            ->greeting('本日の代講で、まだ代講者が決まっていないものがあります')
            ->line('授業のない先生に、至急の連絡を一斉に送りました。');

        foreach ($this->requests as $request) {
            $shift = $request->shift;
            $message->line('・'.DateLabel::date($shift->date)." {$shift->timeSlot->name}（{$shift->timeSlot->timeRange()}） {$shift->lessonLabel()}（依頼した先生：{$request->requester->displayName()}・{$request->status->label()}）");
        }

        return $message->action('代講の状況を見る', route('staff.requests.index', ['date' => today()->toDateString()]));
    }
}
