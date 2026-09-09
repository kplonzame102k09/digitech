<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Competency extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'studentId',
        'competency',
        'qualification',
        'status',
        'assessmentDate',
        'assessor',
        'evidence',
        'remarks',
        'createdAt',
        'createdBy',
        'updatedAt',
        'updatedBy',
    ];

    protected $casts = [
        'assessmentDate' => 'date',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    const NOT_STARTED = 'Not Started';

    const IN_PROGRESS = 'In Progress';

    const COMPETENT = 'Competent';

    const NOT_YET_COMPETENT = 'Not Yet Competent';

    public static $statuses = [
        self::NOT_STARTED,
        self::IN_PROGRESS,
        self::COMPETENT,
        self::NOT_YET_COMPETENT,
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'studentId', 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy', 'user_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updatedBy', 'user_id');
    }

    public static function isDuplicate($studentId, $competency, $qualification = null)
    {
        return self::where('studentId', $studentId)
            ->whereRaw('LOWER(competency) = ?', [strtolower($competency)])
            ->when($qualification, fn ($q) => $q->whereRaw('LOWER(qualification) = ?', [strtolower($qualification)]))
            ->exists();
    }
}
