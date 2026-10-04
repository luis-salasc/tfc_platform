<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberAttendance extends Model
{
    protected $fillable = ['organization_id', 'location_id', 'member_id', 'checked_in_by_user_id', 'checked_in_at', 'requires_settlement'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_user_id');
    }

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'requires_settlement' => 'boolean'];
    }
}
