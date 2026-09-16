<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AttendancePackage extends Model
{
    use HasFactory;

    public const SUBMITTED = 'submitted';

    public const FINALIZED = 'finalized';

    public const RETURNED = 'returned';

    public static $statuses = [self::SUBMITTED, self::FINALIZED, self::RETURNED];

    protected $table = 'attendance_packages';

    protected $fillable = [
        'id',
        'adviserId',
        'date',
        'status',
        'submittedAt',
        'submittedBy',
        'finalizedAt',
        'finalizedBy',
        'returnedAt',
        'returnedBy',
        'returnReason',
    ];

    protected $casts = [
        'date' => 'date',
        'submittedAt' => 'datetime',
        'finalizedAt' => 'datetime',
        'returnedAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (AttendancePackage $package): void {
            $package->id ??= self::generateId();
            $package->status ??= self::SUBMITTED;
        });
    }

    public static function generateId(): string
    {
        do {
            $id = 'PKG-'.now()->year.'-'.strtoupper(Str::random(6));
        } while (self::where('id', $id)->exists());

        return $id;
    }

    public function adviser()
    {
        return $this->belongsTo(User::class, 'adviserId', 'user_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::SUBMITTED;
    }

    public function isFinalized(): bool
    {
        return $this->status === self::FINALIZED;
    }

    public function isReturned(): bool
    {
        return $this->status === self::RETURNED;
    }
}
