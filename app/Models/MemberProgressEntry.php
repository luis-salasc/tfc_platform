<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberProgressEntry extends Model
{
    protected $fillable = ['member_id', 'recorded_on', 'metrics', 'notes'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    protected function casts(): array
    {
        return ['recorded_on' => 'date', 'metrics' => 'array'];
    }
}
