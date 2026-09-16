<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountRequest extends Model
{
    protected $fillable = [
        'request_id',
        'role',
        'status',
        'firstName',
        'lastName',
        'middleName',
        'email',
        'username',
        'contact',
        'strand',
        'purpose',
        'adminNotes',
    ];

    protected function casts(): array
    {
        return [];
    }
}
