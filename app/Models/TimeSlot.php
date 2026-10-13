<?php

namespace App\Models;

use App\Enums\SlotKind;
use App\Enums\TimetablePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property TimetablePattern $pattern
 * @property int $period
 * @property SlotKind $kind
 * @property string $start_time
 * @property string $end_time
 */
class TimeSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'pattern',
        'period',
        'kind',
        'name',
        'start_time',
        'end_time',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'pattern' => TimetablePattern::class,
            'kind' => SlotKind::class,
            'period' => 'integer',
        ];
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * パターンの授業枠を表示順で取得する
     */
    public function scopeOfPattern(Builder $query, TimetablePattern $pattern): Builder
    {
        return $query->where('pattern', $pattern)->orderBy('sort_order');
    }

    /**
     * 時間が重なっているか（同じ枠どうしも重なる扱い）
     */
    public function overlaps(TimeSlot $other): bool
    {
        return $this->start_time < $other->end_time && $other->start_time < $this->end_time;
    }

    /**
     * ある時刻（HH:MM:SS）に授業中か
     */
    public function isInSessionAt(string $time): bool
    {
        return $this->start_time <= $time && $time < $this->end_time;
    }

    /**
     * 表示用の時間帯（例：17:05〜17:50）
     */
    public function timeRange(): string
    {
        return substr($this->start_time, 0, 5).'〜'.substr($this->end_time, 0, 5);
    }
}
