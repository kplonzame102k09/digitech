<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'entity',
        'recordId',
        'action',
        'from',
        'to',
        'notes',
        'reason',
        'actorId',
        'createdAt',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    const ENTITY_USER = 'user';

    const ENTITY_ENROLLMENT = 'enrollment';

    const ENTITY_DOCUMENT = 'document';

    const ENTITY_GRADE = 'grade';

    const ENTITY_COMPETENCY = 'competency';

    const ENTITY_SYSTEM = 'system';

    const ENTITY_ATTENDANCE = 'attendance';

    public function actor()
    {
        return $this->belongsTo(User::class, 'actorId', 'user_id');
    }

    public static function forEntity($entity, $limit = 50)
    {
        return self::where('entity', $entity)
            ->orderBy('createdAt', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function forRecord($recordId, $limit = 50)
    {
        return self::where('recordId', $recordId)
            ->orderBy('createdAt', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function byActor($actorId, $limit = 50)
    {
        return self::where('actorId', $actorId)
            ->orderBy('createdAt', 'desc')
            ->limit($limit)
            ->get();
    }
}
