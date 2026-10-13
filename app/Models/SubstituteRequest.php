<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubstituteRequest extends Model
{
    use HasFactory;

    /**
     * status・substitute_id・confirmed_at は一括代入させず、処理の中で明示的に設定する
     */
    protected $fillable = [
        'shift_id',
        'requester_id',
        'student_count',
        'subject',
        'grade',
        'reason',
        'handover_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'student_count' => 'integer',
            'confirmed_at' => 'datetime',
            'staff_notified_at' => 'datetime',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(SubstituteApplication::class);
    }
}
