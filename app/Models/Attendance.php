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
        'recordedBy',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    const PRESENT = 'Present';

    const ABSENT = 'Absent';

    const LATE = 'Late';

    public static $statuses = [
        self::PRESENT,
        self::ABSENT,
        self::LATE,
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'studentId', 'user_id');
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
