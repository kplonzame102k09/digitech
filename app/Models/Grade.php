<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'studentId',
        'subject',
        'teacher',
        'teacherId',
        'grade',
        'remarks',
        'term',
        'period',
        'published',
        'publishedAt',
        'publishedBy',
        'notes',
        'updatedAt',
        'updatedBy',
    ];

    protected $casts = [
        'grade' => 'float',
        'published' => 'boolean',
        'publishedAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    public function student()
    {
        return $this->belongsTo(User::class, 'studentId', 'id');
    }

    public function teacherUser()
    {
        return $this->belongsTo(User::class, 'teacherId', 'id');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'publishedBy', 'id');
    }

    public function getNumericGrade()
    {
        if ($this->grade === null || $this->grade === '') {
            return null;
        }

        return (float) $this->grade;
    }

    public function getRemark()
    {
        if ($this->remarks) {
            return $this->remarks;
        }

        $numericGrade = $this->getNumericGrade();

        return ($numericGrade !== null && $numericGrade >= 75) ? 'Passed' : 'Failed';
    }

    public function isPublished()
    {
        return $this->published === true || $this->published === 'true' || $this->status === 'Published';
    }
}
