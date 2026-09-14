<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

    /**
     * Targeted alert for flows that bypass the portal-sync relay (classroom
     * grading, activities, joins). Collapses like client-pushed rows: an
     * unread row for the same recipient/source/record is bumped to the latest
     * event (title/message refreshed, back on top, unread) instead of stacking.
     */
    public static function alert(string $userId, string $title, string $message, string $source, ?string $recordId = null): void
    {
        if ($userId === '') {
            return;
        }

        $query = self::query()
            ->where('userId', $userId)
            ->where('source', $source)
            ->where('read', false);

        if ($recordId !== null && $recordId !== '') {
            $query->where('recordId', $recordId);
        } else {
            $query->where('title', $title)->where('message', $message);
        }

        $existing = $query->first();

        if ($existing) {
            $existing->update([
                'title' => $title,
                'message' => $message,
                'read' => false,
                'created_at' => now(),
            ]);

            return;
        }

        do {
            $id = 'NOT-'.date('Y').'-'.strtoupper(Str::random(6));
        } while (self::query()->whereKey($id)->exists());

        self::query()->create([
            'id' => $id,
            'userId' => $userId,
            'title' => $title,
            'message' => $message,
            'read' => false,
            'source' => $source,
            'recordId' => $recordId,
        ]);
    }
}
