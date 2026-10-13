<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $fiscal_year
 * @property int $term_no
 * @property Carbon $start_date
 * @property Carbon $end_date
 */
class Term extends Model
{
    protected $fillable = [
        'fiscal_year',
        'term_no',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'term_no' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @return HasMany<TermLesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(TermLesson::class);
    }

    /**
     * 例：2026年度 2学期
     */
    public function label(): string
    {
        return "{$this->fiscal_year}年度 {$this->term_no}学期";
    }

    public function contains(CarbonInterface $date): bool
    {
        return $date->betweenIncluded($this->start_date, $this->end_date);
    }

    /**
     * その日を含む学期
     */
    public static function containing(CarbonInterface $date): ?self
    {
        return self::where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first();
    }
}
