<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClassroomMessageRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'classroom_id',
        'userId',
        'lastReadAt',
    ];

    protected $casts = [
        'lastReadAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (self $read): void {
            $read->id ??= 'MRD-'.now()->year.'-'.strtoupper(Str::random(6));
        });
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'user_id');
    }
}
