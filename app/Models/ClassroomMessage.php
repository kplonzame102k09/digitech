<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClassroomMessage extends Model
{
    use HasFactory;

    /**
     * Longest body we accept. Kept well under MySQL's TEXT limit and small
     * enough that a full page of the channel stays cheap to return.
     */
    public const MAX_BODY = 2000;

    protected $fillable = [
        'id',
        'classroom_id',
        'userId',
        'body',
        'fileUrl',
        'fileName',
    ];

    /**
     * `seq` is the auto-increment primary key and the only field the channel
     * orders or pages by. `id` is the public, URL-ciphered identifier and stays
     * unique but carries no ordering, because its random suffix would interleave
     * messages written in the same second.
     */
    protected $primaryKey = 'seq';

    protected $keyType = 'int';

    public $incrementing = true;

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            $message->id ??= 'MSG-'.now()->year.'-'.strtoupper(Str::random(6));
        });
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'classroom_id', 'id');
    }

    /**
     * Senders are referenced by users.user_id (the logical string id), which is
     * the convention for every person column outside the classroom_student
     * pivot, which points at the numeric users.id.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'userId', 'user_id');
    }

    public function hasAttachment(): bool
    {
        return filled($this->fileUrl);
    }
}
