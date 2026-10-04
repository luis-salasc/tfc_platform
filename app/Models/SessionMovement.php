<?php

namespace App\Models;

use App\Enums\SessionMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionMovement extends Model
{
    protected $fillable = [
        'member_id',
        'quantity',
        'type',
        'source_type',
        'source_id',
        'occurred_at',
        'created_by_user_id',
        'idempotency_key',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function settlementsAsAttendance(): HasMany
    {
        return $this->hasMany(SessionSettlement::class, 'attendance_movement_id');
    }

    public function settlementsAsPayment(): HasMany
    {
        return $this->hasMany(SessionSettlement::class, 'payment_movement_id');
    }

    protected function casts(): array
    {
        return [
            'type' => SessionMovementType::class,
            'occurred_at' => 'datetime',
        ];
    }
}
