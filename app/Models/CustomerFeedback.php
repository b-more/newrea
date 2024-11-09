<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CustomerFeedback extends Model
{
    protected $fillable = [
        'feedback_number',
        'phone_number',
        'session_id',
        'communication_channel_id',
        'description',
        'comment',
        'status',
        'resolution',
        'resolved_at',
        'resolved_by',
        'metadata'
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'metadata' => 'array'
    ];

    protected $attributes = [
        'status' => 'pending'
    ];

    // Boot method to set default values
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($feedback) {
            if (!$feedback->feedback_number) {
                $feedback->feedback_number = 'FB' . random_int(1000000, 9999999);
            }
            if (!$feedback->status) {
                $feedback->status = 'pending';
            }
        });
    }

    // Relationships
    public function communicationChannel(): BelongsTo
    {
        return $this->belongsTo(CommunicationChannel::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    // Methods
    public function markAsResolved($resolution = null, $userId = null)
    {
        $this->update([
            'status' => 'resolved',
            'resolution' => $resolution,
            'resolved_at' => now(),
            'resolved_by' => $userId ?? auth()->id()
        ]);
    }

    public function markAsInProgress()
    {
        $this->update(['status' => 'in_progress']);
    }

    public function reopen()
    {
        $this->update([
            'status' => 'in_progress',
            'resolved_at' => null,
            'resolved_by' => null
        ]);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'warning',
            'in_progress' => 'info',
            'resolved' => 'success',
            'closed' => 'gray',
            default => 'warning'
        };
    }

    public function getResolutionTimeAttribute()
    {
        if (!$this->resolved_at) {
            return null;
        }

        $created = Carbon::parse($this->created_at);
        $resolved = Carbon::parse($this->resolved_at);
        $hours = $created->diffInHours($resolved);

        if ($hours < 1) {
            $minutes = $created->diffInMinutes($resolved);
            return "{$minutes} minutes";
        } elseif ($hours < 24) {
            return "{$hours} hours";
        } else {
            $days = ceil($hours / 24);
            return "{$days} days";
        }
    }
}
