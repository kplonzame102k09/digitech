<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'studentId',
        'documentType',
        'purpose',
        'copies',
        'notes',
        'status',
        'requestDate',
        'reviewNotes',
        'rejectionReason',
        'releaseMethod',
        'releaseDate',
        'reviewedAt',
        'reviewedBy',
        'createdBy',
    ];

    protected $casts = [
        'copies' => 'integer',
        'requestDate' => 'datetime',
        'releaseDate' => 'datetime',
        'reviewedAt' => 'datetime',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    const PENDING = 'Pending';

    const PROCESSING = 'Processing';

    const READY_FOR_RELEASE = 'Ready for Release';

    const RELEASED = 'Released';

    const REJECTED = 'Rejected';

    public static $statuses = [
        self::PENDING,
        self::PROCESSING,
        self::READY_FOR_RELEASE,
        self::RELEASED,
        self::REJECTED,
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'studentId', 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewedBy', 'user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'createdBy', 'user_id');
    }
}
