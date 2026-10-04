<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberTrainingPlanItem extends Model
{
    protected $fillable = ['training_plan_id', 'position', 'session_name', 'exercise', 'sets', 'repetitions', 'rest_seconds', 'intensity', 'notes'];

    public function trainingPlan(): BelongsTo
    {
        return $this->belongsTo(MemberTrainingPlan::class, 'training_plan_id');
    }
}
