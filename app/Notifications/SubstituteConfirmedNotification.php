<?php

namespace App\Notifications;

use App\Models\SubstituteRequest;
use App\Support\DateLabel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 代講が確定したことを、依頼した先生・代講する先生・スタッフに知らせる（US-17）
 * 引き継ぎメモの本文はメールに載せず、サイトへのリンクだけにする
 */
class SubstituteConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SubstituteRequest $substituteRequest) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->substituteRequest->loadMissing(['shift.timeSlot', 'shift.subject', 'requester', 'substitute']);
        $shift = $request->shift;
        $when = DateLabel::date($shift->date)." {$shift->timeSlot->name}";

        return (new MailMessage)
            ->subject("【DaikoLink】代講が確定しました：{$when}")
            ->greeting('代講が確定しました')
            ->line("{$when}（{$shift->timeSlot->timeRange()}） {$shift->lessonLabel()}")
            ->line("依頼した先生：{$request->requester->displayName()}")
            ->line('代講する先生：'.($request->substitute?->displayName() ?? '—'))
            ->line('シフトは代講する先生の担当に書き換わっています。')
            ->action('引き継ぎメモを見る', route('requests.show', $request));
    }
}
