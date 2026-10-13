<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_TEACHER = 'teacher';

    public const ROLE_STAFF = 'staff';

    /**
     * role と is_active は一括代入させず、スタッフの操作でのみ明示的に設定する
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeTeachers(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_TEACHER);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function substituteRequests(): HasMany
    {
        return $this->hasMany(SubstituteRequest::class, 'requester_id');
    }

    public function substituteApplications(): HasMany
    {
        return $this->hasMany(SubstituteApplication::class, 'applicant_id');
    }

    /**
     * 担当できる科目
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'user_subject')->orderBy('sort_order');
    }
}
