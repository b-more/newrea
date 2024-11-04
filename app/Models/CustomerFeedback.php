<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'feedback_number',
        'phone_number',
        'session_id',
        'communication_channel_id',
        'description',
        'comment',
    ];

    protected $searchableFields = ['*'];

    protected $table = 'customer_feedbacks';

    public function communication_channel()
    {
        return $this->belongsTo(CommunicationChannel::class);
    }
}
