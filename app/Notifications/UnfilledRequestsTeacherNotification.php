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
 * 当日の正午になっても代講者が決まっていない依頼を、その日に授業のない先生へ一斉に知らせる
 */
class UnfilledRequestsTeacherNotification extends Notification implements ShouldQueue
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
        $this->requests->loadMissing(['shift.timeSlot', 'shift.subject']);

        $message = (new MailMessage)
            ->subject('【至急】本日の代講者を探しています')
            ->greeting('本日の代講者を探しています')
            ->line('次の授業の代講者が、まだ見つかっていません。来られる方は、サイトから応募してください。');

        foreach ($this->requests as $request) {
            $shift = $request->shift;
            $message->line('・'.DateLabel::date($shift->date)." {$shift->timeSlot->name}（{$shift->timeSlot->timeRange()}） {$shift->lessonLabel()}");
        }

        return $message
            ->action('募集中の代講を見る', route('requests.index'))
            ->line('このお知らせは、今日授業が入っていない先生に送っています。受け取らない設定は、プロフィールから変更できます。');
    }
}
