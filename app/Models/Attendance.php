<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'studentId',
        'date',
        'status',
        'remarks',
        'createdAt',
        'updatedAt',
    ];

    protected $casts = [
        'date' => 'date',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
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
        return $this->belongsTo(User::class, 'studentId', 'id');
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
