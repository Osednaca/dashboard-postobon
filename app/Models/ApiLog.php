<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'service',
        'endpoint',
        'method',
        'request_body',
        'response_body',
        'status_code',
        'attempt',
        'success',
        'error_message',
        'duration_ms',
        'ip_address',
    ];

    protected $casts = [
        'success' => 'boolean',
        'attempt' => 'integer',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
    ];
}
