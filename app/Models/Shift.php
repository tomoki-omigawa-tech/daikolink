<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date
 * @property int|null $student_count
 * @property-read TimeSlot $timeSlot
 * @property-read User $user
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
        'student_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'student_count' => 'integer',
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

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function substituteRequests(): HasMany
    {
        return $this->hasMany(SubstituteRequest::class);
    }

    /**
     * 募集中または応募ありの依頼（1人ずつ募集なら最大2件）
     */
    public function activeRequests(): HasMany
    {
        return $this->hasMany(SubstituteRequest::class)
            ->whereIn('status', RequestStatus::activeValues());
    }

    /**
     * 埋まっている席の数（小学生集団は満席扱い）
     */
    public function occupiedSeats(): int
    {
        if ($this->timeSlot->kind->isGroup()) {
            return self::MAX_SEATS;
        }

        return $this->student_count ?? self::MAX_SEATS;
    }

    /**
     * 表示用の生徒数（例：2人、集団）
     */
    public function studentLabel(): string
    {
        return $this->timeSlot->kind->isGroup() ? '集団' : $this->occupiedSeats().'人';
    }

    /**
     * 授業の開始日時（不成立の判定に使う）
     */
    public function startsAt(): CarbonInterface
    {
        return $this->date->copy()->setTimeFromTimeString($this->timeSlot->start_time);
    }
}
