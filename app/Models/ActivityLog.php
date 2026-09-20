<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'method',
        'path',
        'ip_address',
        'user_agent',
        'status',
        'kind',
        'context',
        'created_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Persist a request record and opportunistically prune anything older
     * than the retention window so the table stays bounded.
     *
     * @param  array<string, mixed>  $data
     */
    public static function record(array $data): ?self
    {
        if (! isset($data['created_at'])) {
            $data['created_at'] = now();
        }

        $row = self::create($data);

        if (random_int(1, 40) === 1) {
            self::where('created_at', '<', now()->subDay())->limit(2000)->delete();
        }

        return $row;
    }
}