<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPayment extends Model
{
    protected $fillable = ['organization_id', 'location_id', 'member_id', 'received_by_user_id', 'edited_by_user_id', 'idempotency_key', 'sessions_purchased', 'sessions_regularized', 'amount', 'payment_method', 'payment_method_detail', 'notes', 'delivery_methods', 'paid_at', 'edited_at'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'delivery_methods' => 'array', 'paid_at' => 'datetime', 'edited_at' => 'datetime'];
    }
}
