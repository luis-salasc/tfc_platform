<?php

namespace App\Models;

use App\Enums\MemberPreRegistrationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberPreRegistration extends Model
{
    protected $fillable = [
        'organization_id',
        'location_id',
        'created_by_user_id',
        'status',
        'first_name',
        'last_name',
        'birth_date',
        'document_type',
        'document_number',
        'email',
        'phone',
        'client_type',
        'intake',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(MemberPreRegistrationReview::class)->latest('reviewed_at');
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    public function triggeringCircumstances(): array
    {
        $caaf = collect(data_get($this->intake, 'caaf', []))
            ->filter(fn ($answer) => (bool) $answer)
            ->map(fn ($answer, $question) => true)
            ->all();

        return array_filter([
            'caaf' => $caaf,
            'pregnancy' => (bool) data_get($this->intake, 'pregnancy'),
        ], fn ($value) => $value !== [] && $value !== false);
    }

    public function requiresReview(): bool
    {
        return $this->triggeringCircumstances() !== [];
    }

    protected function casts(): array
    {
        return [
            'status' => MemberPreRegistrationStatus::class,
            'birth_date' => 'date',
            'intake' => 'array',
        ];
    }
}
