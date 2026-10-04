<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberTrainingPlan extends Model
{
    protected $fillable = ['organization_id', 'member_id', 'assigned_by_user_id', 'title', 'objective', 'starts_on', 'ends_on', 'sessions_per_week', 'status', 'notes'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MemberTrainingPlanItem::class, 'training_plan_id')->orderBy('position');
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }
}
