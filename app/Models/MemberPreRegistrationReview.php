<?php

namespace App\Models;

use App\Enums\MemberPreRegistrationReviewDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPreRegistrationReview extends Model
{
    protected $fillable = [
        'member_pre_registration_id',
        'decision',
        'observations',
        'triggering_circumstances',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    public function preRegistration(): BelongsTo
    {
        return $this->belongsTo(MemberPreRegistration::class, 'member_pre_registration_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'decision' => MemberPreRegistrationReviewDecision::class,
            'triggering_circumstances' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
