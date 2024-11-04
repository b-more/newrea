<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'activity_type',
        'session_id',
        'phone_number',
        'details',
        'status',
        'ip_address'
    ];

    protected $casts = [
        'details' => 'array'
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
