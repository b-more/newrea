<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'inquiry_number',
        'phone_number',
        'session_id',
        'communication_channel_id',
        'general_inquiry_category_id',
        'comments',
        'description'
    ];

    protected $searchableFields = ['*'];

    protected $table = 'general_inquiries';

    public function communication_channel()
    {
        return $this->belongsTo(CommunicationChannel::class);
    }

    public function generalInquiryCategory()
    {
        return $this->belongsTo(GeneralInquiryCategory::class);
    }
}
