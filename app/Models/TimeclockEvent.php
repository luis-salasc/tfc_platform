<?php

namespace App\Models;

use App\Enums\TimeclockEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeclockEvent extends Model
{
    protected $fillable = ['organization_id', 'location_id', 'user_id', 'event_type', 'occurred_at', 'source'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['event_type' => TimeclockEventType::class, 'occurred_at' => 'immutable_datetime'];
    }
}
