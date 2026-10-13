<?php

namespace App\Models;

use App\Enums\SubjectStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property SubjectStage $stage
 * @property string $name
 * @property bool $is_active
 */
class Subject extends Model
{
    protected $fillable = [
        'stage',
        'name',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'stage' => SubjectStage::class,
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_subject');
    }

    /**
     * 使用中の科目（非表示にした科目は除く）
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
