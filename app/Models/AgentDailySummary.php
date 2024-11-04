<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentDailySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'summary_date',
        'total_transactions',
        'total_amount',
        'total_commission',
        'transaction_breakdown'
    ];

    protected $casts = [
        'summary_date' => 'date',
        'total_amount' => 'decimal:2',
        'total_commission' => 'decimal:2',
        'transaction_breakdown' => 'array'
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
