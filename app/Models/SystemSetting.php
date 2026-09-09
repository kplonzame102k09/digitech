<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'theme',
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
    ];

    public $timestamps = false;

    public function updater()
    {
        return $this->belongsTo(User::class, 'updatedBy', 'user_id');
    }

    public static function getInstance()
    {
        return self::firstOrCreate(
            [],
            [
                'theme' => 'light',
                'teacherRegistration' => true,
                'adminRegistration' => false,
                'institutionName' => 'Digitech College',
                'schoolYear' => date('Y').'-'.(date('Y') + 1),
                'passingGrade' => 75,
                'notifyStudents' => true,
                'notifyParents' => true,
                'notifyTeachers' => true,
                'notifyAdmins' => true,
            ]
        );
    }
}
