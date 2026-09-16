<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'id',
        'studentId',
        'date',
        'status',
        'subject',
        'classroomId',
        'classroomName',
        'sessionId',
        'session',
        'kind',
        'timeIn',
        'timeOut',
        'recordedBy',
        'remarks',
        'isDraft',
        'isLocked',
        'requiresVerification',
        'verificationStatus',
        'verifiedBy',
        'verifiedAt',
        'finalizedAt',
        'finalizedBy',
    ];

    protected $casts = [
        'date' => 'date',
        'session' => 'integer',
        'isDraft' => 'boolean',
        'isLocked' => 'boolean',
        'requiresVerification' => 'boolean',
        'verifiedAt' => 'datetime',
        'finalizedAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    const PRESENT = 'Present';

    const ABSENT = 'Absent';

    const LATE = 'Late';

    const EXCUSED = 'Excused';

    const KIND_CHECK = 'check';

    const KIND_FINAL = 'final';

    const VERIFICATION_PENDING = 'pending';

    const VERIFICATION_CONFIRMED = 'confirmed';

    const VERIFICATION_REJECTED = 'rejected';

    public static $statuses = [
        self::PRESENT,
        self::ABSENT,
        self::LATE,
        self::EXCUSED,
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'studentId', 'user_id');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroomId', 'id');
    }

    public function recordedByUser()
    {
        return $this->belongsTo(User::class, 'recordedBy', 'user_id');
    }

    public function scopeForClassroom($query, string $classroomId)
    {
        return $query->where('classroomId', $classroomId);
    }

    public function scopeFinalized($query)
    {
        return $query->where('kind', self::KIND_FINAL)->whereNotNull('finalizedAt');
    }

    public function scopeLocked($query)
    {
        return $query->where('isLocked', true);
    }

    public function scopeDraft($query)
    {
        return $query->where('isDraft', true);
    }

    public static function getAttendanceRate($studentId, $startDate = null, $endDate = null)
    {
        $query = self::where('studentId', $studentId)
            ->whereIn('status', [self::PRESENT, self::LATE, self::ABSENT]);

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $records = $query->get();
        $total = $records->count();

        if ($total === 0) {
            return 0;
        }

        $present = $records->where('status', self::PRESENT)->count();

        return round(($present / $total) * 100, 2);
    }
}
