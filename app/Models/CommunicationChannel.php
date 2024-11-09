<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];
    protected $searchableFields = ['*'];

    protected $table = 'communication_channels';

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

    public function generalInquiries()
    {
        return $this->hasMany(GeneralInquiry::class);
    }

    public function customerFeedbacks()
    {
        return $this->hasMany(CustomerFeedback::class);
    }
}
