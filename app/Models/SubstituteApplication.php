<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ApplicationStatus $status
 * @property int $substitute_request_id
 * @property int $applicant_id
 * @property-read SubstituteRequest $substituteRequest
 * @property-read User $applicant
 */
class SubstituteApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'substitute_request_id',
        'applicant_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<SubstituteRequest, $this>
     */
    public function substituteRequest(): BelongsTo
    {
        return $this->belongsTo(SubstituteRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }
}
