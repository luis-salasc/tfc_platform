<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionSettlement extends Model
{
    protected $fillable = [
        'attendance_movement_id',
        'payment_movement_id',
        'quantity',
        'settled_at',
        'settled_by_user_id',
    ];

    public function attendanceMovement(): BelongsTo
    {
        return $this->belongsTo(SessionMovement::class, 'attendance_movement_id');
    }

    public function paymentMovement(): BelongsTo
    {
        return $this->belongsTo(SessionMovement::class, 'payment_movement_id');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by_user_id');
    }

    protected function casts(): array
    {
        return ['settled_at' => 'datetime'];
    }
}
