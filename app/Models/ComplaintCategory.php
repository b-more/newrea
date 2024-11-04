<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_active'];

    protected $searchableFields = ['*'];

    protected $table = 'complaint_categories';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }

}
