<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type_id',
        'start_date',
        'end_date'
    ];

    public function report_type()
    {
        $this->belongsTo(ReportType::class);
    }
}
