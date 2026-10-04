<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPortalAccess extends Model
{
    protected $fillable = ['organization_id', 'member_id', 'user_id', 'relationship', 'status', 'permissions', 'invite_token_hash', 'invite_expires_at', 'accepted_at', 'revoked_at', 'granted_by_user_id'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'invite_expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
