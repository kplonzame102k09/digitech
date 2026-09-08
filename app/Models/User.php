<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'firstName',
        'lastName',
        'email',
        'username',
        'password',
        'role',
        'status',
        'contact',
        'strand',
        'address',
        'photo',
        'childId',
        'childIds',
        'birthDate',
        'guardianName',
        'guardianContact',
        'mustChangePassword',
        'createdAt',
        'updatedAt',
    ];

    protected $casts = [
        'childIds' => 'array',
        'mustChangePassword' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Enrollments for this student
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'studentId', 'id');
    }

    /**
     * Document requests for this student
     */
    public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class, 'studentId', 'id');
    }

    /**
     * Grades for this student
     */
    public function grades()
    {
        return $this->hasMany(Grade::class, 'studentId', 'id');
    }

    /**
     * Competencies for this student
     */
    public function competencies()
    {
        return $this->hasMany(Competency::class, 'studentId', 'id');
    }

    /**
     * Attendance records for this student
     */
    public function attendance()
    {
        return $this->hasMany(Attendance::class, 'studentId', 'id');
    }

    /**
     * Notifications for this user
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'userId', 'id');
    }

    /**
     * Get linked children if parent
     */
    public function linkedChildren()
    {
        if ($this->role !== 'parent') {
            return collect();
        }

        $childIds = $this->childIds ?? [];
        if ($this->childId) {
            $childIds[] = $this->childId;
        }

        return User::whereIn('id', $childIds)->get();
    }

    /**
     * Count related records
     */
    public function getRelatedRecordCount()
    {
        return $this->enrollments()->count() +
               $this->documentRequests()->count() +
               $this->grades()->count() +
               $this->competencies()->count() +
               $this->attendance()->count() +
               $this->notifications()->count();
    }
}
