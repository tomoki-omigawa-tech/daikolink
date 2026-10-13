<?php

namespace App\Models;

use App\Enums\Grade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 学期の時間割の授業（曜日×授業枠×講師×席）。1行＝生徒1人
 *
 * @property int $weekday
 * @property int $seat
 * @property Grade|null $grade
 * @property-read TimeSlot $timeSlot
 * @property-read User $user
 * @property-read Subject|null $subject
 */
class TermLesson extends Model
{
    protected $fillable = [
        'term_id',
        'weekday',
        'time_slot_id',
        'seat',
        'user_id',
        'grade',
        'subject_id',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'seat' => 'integer',
            'grade' => Grade::class,
        ];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * 埋まる席の数（生徒1人なら1、小学生集団は満席）
     */
    public function occupiedSeats(): int
    {
        return $this->timeSlot->kind->isGroup() ? Shift::MAX_SEATS : 1;
    }
}
