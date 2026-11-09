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
 * 前日の夜に、明日の代講を代講者に思い出してもらう
 * 件名だけで日時が分かるようにする（例：【明日の代講】11/8（土）5限）
 */
class SubstituteReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, SubstituteRequest>  $requests  同じ先生が明日代講する依頼（開始時刻順）
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
        /** @var SubstituteRequest $first */
        $first = $this->requests->first();
        $others = $this->requests->count() - 1;
        $more = $others > 0 ? " ほか{$others}件" : '';

        $message = (new MailMessage)
            ->subject('【明日の代講】'.DateLabel::date($first->shift->date).$first->shift->timeSlot->name.$more)
            ->greeting('明日は代講の授業があります')
            ->line('次の授業の代講をお願いします。');

        foreach ($this->requests as $request) {
            $shift = $request->shift;
            $message->line('・'.DateLabel::date($shift->date)." {$shift->timeSlot->name}（{$shift->timeSlot->timeRange()}） {$shift->lessonLabel()}（依頼した先生：{$request->requester->displayName()}）");
        }

        return $message
            ->action('引き継ぎメモを見る', $others > 0 ? route('requests.mine') : route('requests.show', $first))
            ->line('都合が悪くなった場合は、依頼した先生と教室に早めに連絡してください。');
    }
}
