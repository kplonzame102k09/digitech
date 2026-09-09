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

        return $this->belongsToMany(User::class, 'parent_student', 'parent_id', 'student_id');
    }

    public function parentLinks()
    {
        return $this->belongsToMany(User::class, 'parent_student', 'student_id', 'parent_id');
    }

    public function studentLinks()
    {
        return $this->belongsToMany(User::class, 'parent_student', 'parent_id', 'student_id');
    }
}
