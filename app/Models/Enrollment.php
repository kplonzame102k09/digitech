<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'studentId',
        'status',
        'programType',
        'gradeLevel',
        'strand',
        'track',
        'schoolYear',
        'trainingLevel',
        'assignedTeacherId',
        'assignedSection',
        'reviewNotes',
        'rejectionReason',
        'reviewedAt',
        'reviewedBy',
        'createdAt',
        'updatedAt',
    ];

    protected $casts = [
        'reviewedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    const DRAFT = 'Draft';

    const SUBMITTED = 'Submitted';

    const UNDER_REVIEW = 'Under Review';

    const APPROVED = 'Approved';

    const REJECTED = 'Rejected';

    const NEEDS_CORRECTION = 'Needs Correction';

    const ENROLLED = 'Enrolled';

    public static $statuses = [
        self::DRAFT,
        self::SUBMITTED,
        self::UNDER_REVIEW,
        self::APPROVED,
        self::REJECTED,
        self::NEEDS_CORRECTION,
        self::ENROLLED,
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'studentId', 'user_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'assignedTeacherId', 'user_id');
    }

    public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class, 'studentId', 'studentId');
    }

    public function requirements()
    {
        return $this->hasMany(Requirement::class, 'studentId', 'studentId');
    }

    public function isComplete()
    {
        return in_array($this->status, ['Approved', 'Complete', 'Completed', 'Verified', 'Submitted', 'Enrolled']);
    }

    public static function hasDuplicateActive($studentId, $schoolYear, $excludeId = null)
    {
        $query = self::where('studentId', $studentId)
            ->where('schoolYear', $schoolYear)
            ->whereIn('status', ['Approved', 'Enrolled']);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
