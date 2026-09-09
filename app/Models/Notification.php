<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'userId',
        'title',
        'message',
        'read',
        'source',
        'recordId',
        'createdAt',
        'updatedAt',
    ];

    protected $casts = [
        'read' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    const SOURCE_DOCUMENT = 'document';

    const SOURCE_GRADE = 'grade';

    const SOURCE_ENROLLMENT = 'enrollment';

    const SOURCE_COMPETENCY = 'competency';

    const SOURCE_ANNOUNCEMENT = 'announcement';

    const SOURCE_ATTENDANCE = 'attendance';

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'user_id');
    }

    public function markAsRead()
    {
        $this->update(['read' => true]);

        return $this;
    }

    public function markAsUnread()
    {
        $this->update(['read' => false]);

        return $this;
    }

    public static function getUnreadCount($userId)
    {
        return self::where('userId', $userId)
            ->where('read', false)
            ->count();
    }
}
