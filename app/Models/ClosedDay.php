<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 塾が休みの日（シフトを作らない）
 *
 * @property Carbon $date
 */
class ClosedDay extends Model
{
    protected $fillable = [
        'date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
