<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClassroomMeeting extends Model
{
    use HasFactory;

    public const SCHEDULED = 'scheduled';

    public const LIVE = 'live';

    public const ENDED = 'ended';

    public const CANCELLED = 'cancelled';

    public static $statuses = [self::SCHEDULED, self::LIVE, self::ENDED, self::CANCELLED];

    protected $fillable = [
        'id',
        'classroom_id',
        'title',
        'starts_at',
        'ends_at',
        'room_name',
        'status',
        'createdBy',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (ClassroomMeeting $meeting): void {
            $meeting->id ??= 'MTG-'.now()->year.'-'.strtoupper(Str::random(6));
            $meeting->status ??= self::SCHEDULED;
        });
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'id');
    }

    public function isJoinable(): bool
    {
        return in_array($this->status, [self::SCHEDULED, self::LIVE], true);
    }
}
