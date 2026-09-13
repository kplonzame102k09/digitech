<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClassroomActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'classroom_id',
        'title',
        'description',
        'term',
        'dueDate',
        'createdBy',
    ];

    protected $casts = [
        'dueDate' => 'date',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (ClassroomActivity $activity): void {
            $activity->id ??= self::generateId();
        });
    }

    public static function generateId(): string
    {
        do {
            $id = 'ACT-'.now()->year.'-'.strtoupper(Str::random(6));
        } while (self::where('id', $id)->exists());

        return $id;
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'id');
    }

    public function submissions()
    {
        return $this->hasMany(ClassroomSubmission::class, 'activity_id', 'id');
    }
}
