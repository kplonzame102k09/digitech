<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacherRegistration',
        'adminRegistration',
        'institutionName',
        'schoolYear',
        'passingGrade',
        'enrollmentDeadline',
        'notifyStudents',
        'notifyParents',
        'notifyTeachers',
        'notifyAdmins',
        'updatedBy',
        'updatedAt',
    ];

    protected $casts = [
        'teacherRegistration' => 'boolean',
        'adminRegistration' => 'boolean',
        'passingGrade' => 'float',
        'enrollmentDeadline' => 'date',
        'notifyStudents' => 'boolean',
        'notifyParents' => 'boolean',
        'notifyTeachers' => 'boolean',
        'notifyAdmins' => 'boolean',
        'updatedAt' => 'datetime',
    ];

    public $timestamps = false;

    public function updater()
    {
        return $this->belongsTo(User::class, 'updatedBy', 'id');
    }

    public static function getInstance()
    {
        return self::firstOrCreate(
            [],
            [
                'teacherRegistration' => true,
                'adminRegistration' => false,
                'institutionName' => 'Digitech College',
                'schoolYear' => date('Y') . '-' . (date('Y') + 1),
                'passingGrade' => 75,
                'notifyStudents' => true,
                'notifyParents' => true,
                'notifyTeachers' => true,
                'notifyAdmins' => true,
            ]
        );
    }
}
