<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['organization_id', 'location_id', 'created_by_user_id', 'first_name', 'last_name', 'public_alias', 'national_id', 'email', 'phone', 'birth_date', 'address', 'city', 'client_type', 'status', 'started_on', 'sessions_remaining', 'intake', 'initial_metrics', 'informed_consent_accepted', 'risk_assumption_accepted', 'consent_date'];

    protected static function booted(): void
    {
        static::creating(function (self $member): void {
            $member->check_in_token ??= (string) Str::uuid();
        });
    }

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

    public function progressEntries(): HasMany
    {
        return $this->hasMany(MemberProgressEntry::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MemberAttendance::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(MemberPayment::class);
    }

    public function sessionMovements(): HasMany
    {
        return $this->hasMany(SessionMovement::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(MemberIncident::class);
    }

    public function portalAccesses(): HasMany
    {
        return $this->hasMany(MemberPortalAccess::class);
    }

    public function trainingPlans(): HasMany
    {
        return $this->hasMany(MemberTrainingPlan::class);
    }

    public function scopeForOrganization(Builder $query, Organization $organization): Builder
    {
        return $query->where('organization_id', $organization->id);
    }

    protected function casts(): array
    {
        return ['status' => MemberStatus::class, 'birth_date' => 'date', 'started_on' => 'date', 'consent_date' => 'date', 'check_in_token_rotated_at' => 'datetime', 'intake' => 'array', 'initial_metrics' => 'array', 'informed_consent_accepted' => 'boolean', 'risk_assumption_accepted' => 'boolean'];
    }
}
