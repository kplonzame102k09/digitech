<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'title',
        'message',
        'category',
        'audience',
        'authorId',
        'createdBy',
        'image',
        'updatedAt',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    const AUDIENCE_ALL = 'all';

    const AUDIENCE_ADMIN = 'admin';

    const AUDIENCE_TEACHER = 'teacher';

    const AUDIENCE_STUDENT = 'student';

    const AUDIENCE_PARENT = 'parent';

    public static $audiences = [
        self::AUDIENCE_ALL,
        self::AUDIENCE_ADMIN,
        self::AUDIENCE_TEACHER,
        self::AUDIENCE_STUDENT,
        self::AUDIENCE_PARENT,
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'authorId', 'user_id');
    }

    public function getAudienceLabel()
    {
        $labels = [
            self::AUDIENCE_ALL => 'Everyone',
            self::AUDIENCE_ADMIN => 'Administrators',
            self::AUDIENCE_TEACHER => 'Teachers',
            self::AUDIENCE_STUDENT => 'Students',
            self::AUDIENCE_PARENT => 'Parents',
        ];

        return $labels[$this->audience] ?? $this->audience;
    }
}
