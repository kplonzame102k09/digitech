<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClassroomSubmission extends Model
{
    use HasFactory;

    public const SUBMITTED = 'Submitted';

    public const GRADED = 'Graded';

    public const RETURNED = 'Returned';

    public static $statuses = [self::SUBMITTED, self::GRADED, self::RETURNED];

    protected $fillable = [
        'id',
        'activity_id',
        'studentId',
        'fileUrl',
        'notes',
        'status',
        'score',
        'submittedAt',
        'gradedAt',
        'gradedBy',
    ];

    protected $casts = [
        'score' => 'float',
        'submittedAt' => 'datetime',
        'gradedAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (ClassroomSubmission $submission): void {
            $submission->id ??= self::generateId();
            $submission->status ??= self::SUBMITTED;
            $submission->submittedAt ??= now();
        });
    }

    public static function generateId(): string
    {
        do {
            $id = 'SUB-'.now()->year.'-'.strtoupper(Str::random(6));
        } while (self::where('id', $id)->exists());

        return $id;
    }

    public function activity()
    {
        return $this->belongsTo(ClassroomActivity::class, 'activity_id', 'id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'studentId', 'user_id');
    }
}
