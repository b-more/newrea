<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_number',
        'phone_number',
        'session_id',
        'communication_channel_id',
        'complaint_category_id',
        'complaint_status_id',
        'meter_number',
        'description',
        'comments',
        'resolved_by',
        'status'
    ];

    public function communication_channel()
    {
        return $this->belongsTo(CommunicationChannel::class);
    }

    public function complaint_category()
    {
        return $this->belongsTo(ComplaintCategory::class);
    }

    public function complaint_status()
    {
        return $this->belongsTo(ComplaintStatus::class);
    }
}
