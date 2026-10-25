<?php

namespace App\Models;

use App\Enums\Grade;
use App\Enums\RequestStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 1行＝生徒1人の授業（講師×日付×授業枠×席）
 *
 * @property Carbon $date
 * @property int $seat
 * @property Grade|null $grade
 * @property bool $is_manual
 * @property-read TimeSlot $timeSlot
 * @property-read User $user
 * @property-read Subject|null $subject
 */
class Shift extends Model
{
    use HasFactory;

    /**
     * 講師1人が同時に見られる生徒数
     */
    public const MAX_SEATS = 2;

    protected $fillable = [
        'user_id',
        'date',
        'time_slot_id',
        'seat',
        'grade',
        'subject_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'seat' => 'integer',
            'grade' => Grade::class,
            'is_manual' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function termLesson(): BelongsTo
    {
        return $this->belongsTo(TermLesson::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return HasMany<SubstituteRequest, $this>
     */
    public function substituteRequests(): HasMany
    {
        return $this->hasMany(SubstituteRequest::class);
    }

    /**
     * 募集中または応募ありの依頼
     *
     * @return HasMany<SubstituteRequest, $this>
     */
    public function activeRequests(): HasMany
    {
        return $this->hasMany(SubstituteRequest::class)
            ->whereIn('status', RequestStatus::activeValues());
    }

    /**
     * 埋まる席の数（生徒1人なら1、小学生集団は満席）
     */
    public function occupiedSeats(): int
    {
        return $this->timeSlot->kind->isGroup() ? self::MAX_SEATS : 1;
    }

    /**
     * 表示用の授業内容（例：中2 数学、小学生集団）
     */
    public function lessonLabel(): string
    {
        if ($this->timeSlot->kind->isGroup()) {
            return '小学生集団';
        }

        return trim(($this->grade?->label() ?? '').' '.($this->subject->name ?? ''));
    }

    /**
     * 授業の開始日時（不成立の判定に使う）
     */
    public function startsAt(): CarbonInterface
    {
        return $this->date->copy()->setTimeFromTimeString($this->timeSlot->start_time);
    }
}
