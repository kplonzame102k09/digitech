<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AttendanceSession extends Model
{
    use HasFactory;

    public const OPEN = 'open';

    public const LOCKED = 'locked';

    public const CANCELLED = 'cancelled';

    public static $statuses = [self::OPEN, self::LOCKED, self::CANCELLED];

    protected $table = 'attendance_sessions';

    protected $fillable = [
        'id',
        'classroomId',
        'date',
        'startTime',
        'scheduledEndTime',
        'actualEndTime',
        'status',
        'autoSubmit',
        'startedBy',
        'lockedBy',
        'lockedAt',
    ];

    protected $casts = [
        'date' => 'date',
        'autoSubmit' => 'boolean',
        'lockedAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (AttendanceSession $session): void {
            $session->id ??= self::generateId();
            $session->status ??= self::OPEN;
        });
    }

    public static function generateId(): string
    {
        do {
            $id = 'ATS-'.now()->year.'-'.strtoupper(Str::random(6));
        } while (self::where('id', $id)->exists());

        return $id;
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroomId', 'id');
    }

    public function records()
    {
        return $this->hasMany(Attendance::class, 'sessionId', 'id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isLocked(): bool
    {
        return $this->status === self::LOCKED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::CANCELLED;
    }
}
