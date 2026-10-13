<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function substituteRequest(): BelongsTo
    {
        return $this->belongsTo(SubstituteRequest::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }
}
