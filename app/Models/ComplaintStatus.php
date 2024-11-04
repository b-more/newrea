<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintStatus extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected $searchableFields = ['*'];

    protected $table = 'complaint_statuses';

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

}
