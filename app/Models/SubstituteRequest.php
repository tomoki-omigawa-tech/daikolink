<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property RequestStatus $status
 * @property int $shift_id
 * @property int $requester_id
 * @property int|null $substitute_id
 * @property Carbon|null $confirmed_at
 * @property-read Shift $shift
 * @property-read User $requester
 * @property-read User|null $substitute
 * @property-read Collection<int, SubstituteApplication> $applications
 */
class SubstituteRequest extends Model
{
    use HasFactory;

    /**
     * status・substitute_id・confirmed_at は一括代入させず、処理の中で明示的に設定する
     */
    protected $fillable = [
        'shift_id',
        'requester_id',
        'reason',
        'handover_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'confirmed_at' => 'datetime',
            'staff_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function substitute(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_id');
    }

    /**
     * @return HasMany<SubstituteApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(SubstituteApplication::class);
    }
}
