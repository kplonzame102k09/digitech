<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Requirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'studentId',
        'name',
        'type',
        'status',
        'dueDate',
        'submittedAt',
        'notes',
        'fileUrl',
        'createdAt',
        'updatedAt',
    ];

    protected $casts = [
        'dueDate' => 'date',
        'submittedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    const PENDING = 'Pending';

    const SUBMITTED = 'Submitted';

    const APPROVED = 'Approved';

    const REJECTED = 'Rejected';

    public static $statuses = [
        self::PENDING,
        self::SUBMITTED,
        self::APPROVED,
        self::REJECTED,
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'studentId', 'user_id');
    }

    public function isOverdue()
    {
        return $this->status === self::PENDING && $this->dueDate && $this->dueDate->isPast();
    }
}
