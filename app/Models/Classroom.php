<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Classroom extends Model
{
    use HasFactory;

    public const ACTIVE = 'active';

    public const ARCHIVED = 'archived';

    public static $statuses = [self::ACTIVE, self::ARCHIVED];

    protected $fillable = [
        'id',
        'teacherId',
        'name',
        'subject',
        'sessionsPerDay',
        'description',
        'inviteCode',
        'inviteToken',
        'status',
    ];

    protected $casts = [
        'sessionsPerDay' => 'integer',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (Classroom $classroom): void {
            $classroom->id ??= self::generateId();
            $classroom->inviteCode ??= self::generateInviteCode();
            $classroom->inviteToken ??= Str::random(32);
            $classroom->status ??= self::ACTIVE;
        });
    }

    public static function generateId(): string
    {
        do {
            $id = 'CLS-'.now()->year.'-'.strtoupper(Str::random(6));
        } while (self::where('id', $id)->exists());

        return $id;
    }

    /**
     * 9-digit numeric invite code (100000000-999999999).
     */
    public static function generateInviteCode(): string
    {
        do {
            $code = (string) random_int(100000000, 999999999);
        } while (self::where('inviteCode', $code)->exists());

        return $code;
    }

    public static function normalizeCode(?string $code): string
    {
        return preg_replace('/\D/', '', (string) $code) ?? '';
    }

    public static function formatCode(string $code): string
    {
        $digits = self::normalizeCode($code);
        if (strlen($digits) !== 9) {
            return $code;
        }

        return substr($digits, 0, 3).'-'.substr($digits, 3, 3).'-'.substr($digits, 6, 3);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacherId', 'user_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'classroom_student', 'classroom_id', 'student_id')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function joinLink(): string
    {
        return url('/classroom/join/'.$this->inviteToken);
    }
}
