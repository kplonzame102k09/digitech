<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentLinkRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'parentId',
        'studentId',
        'status',
        'reviewedAt',
        'reviewedBy',
        'createdAt',
        'updatedAt',
    ];

    protected $casts = [
        'reviewedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    const PENDING = 'Pending';

    const APPROVED = 'Approved';

    const REJECTED = 'Rejected';

    public static $statuses = [
        self::PENDING,
        self::APPROVED,
        self::REJECTED,
    ];

    public function parent()
    {
        return $this->belongsTo(User::class, 'parentId', 'user_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'studentId', 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewedBy', 'user_id');
    }
}
