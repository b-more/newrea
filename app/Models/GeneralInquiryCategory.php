<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralInquiryCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected $searchableFields = ['*'];

    protected $table = 'general_inquiry_categories';

    public function generalInquiries()
    {
        return $this->hasMany(GeneralInquiry::class);
    }
}
