<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'user_id',
        'role',
        'status',
        'firstName',
        'lastName',
        'middleName',
        'contact',
        'birthDate',
        'birthPlace',
        'barangay',
        'city',
        'province',
        'region',
        'email',
        'username',
        'password',
        'rolePassword',
        'photo',
        'strand',
        'address',
        'childId',
        'childIds',
        'guardianName',
        'guardianContact',
        'mustChangePassword',
        'employeeId',
        'department',
        'profile_extra',
    ];

    protected $hidden = [
        'password',
        'rolePassword',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'birthDate' => 'date',
            'childIds' => 'array',
            'profile_extra' => 'array',
            'mustChangePassword' => 'boolean',
            'password' => 'hashed',
            'rolePassword' => 'hashed',
        ];
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isParent(): bool
    {
        return $this->role === 'parent';
    }

    public function linkedChildren()
    {
        if ($this->role !== 'parent') {
            return collect();
        }

        $childIds = $this->childIds ?? [];

        if ($this->childId) {
            $childIds[] = $this->childId;
        }

        $childIds = array_values(array_unique(array_filter($childIds)));

        if ($childIds === []) {
            return collect();
        }

        return self::query()->whereIn('user_id', $childIds)->get();
    }
}
