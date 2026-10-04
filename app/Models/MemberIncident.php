<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberIncident extends Model
{
    protected $fillable = ['organization_id', 'location_id', 'member_id', 'attendance_id', 'created_by_user_id', 'resolved_by_user_id', 'type', 'severity', 'status', 'source', 'title', 'details', 'context', 'occurred_at', 'resolved_at', 'resolution'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(MemberAttendance::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    protected function casts(): array
    {
        return ['context' => 'array', 'occurred_at' => 'datetime', 'resolved_at' => 'datetime'];
    }
}
