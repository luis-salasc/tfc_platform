<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\Member;
use App\Models\MemberPreRegistration;
use App\Services\SessionLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    use InteractsWithOrganization;

    public function index(Request $request)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist, OrganizationRole::Staff);
        $query = Member::forOrganization($this->organization($request))->latest();
        if ($search = trim((string) $request->string('q'))) {
            $query->where(fn ($members) => $members->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('national_id', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }
        if ($request->boolean('low_sessions')) {
            $query->where('sessions_remaining', '<=', 3);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->boolean('new_this_month')) {
            $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        }

        return view('members.index', ['members' => $query->select(['id', 'first_name', 'last_name', 'email', 'phone', 'status', 'sessions_remaining', 'created_at'])->paginate(20)->withQueryString(), 'statuses' => MemberStatus::cases()]);
    }

    public function create(Request $request)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);

        return view('members.form', [
            'member' => new MemberPreRegistration,
            'preRegistration' => new MemberPreRegistration,
            'preRegistrationMode' => true,
            'statuses' => MemberStatus::cases(),
            'canReview' => in_array($this->membership($request)->role, [OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer], true),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);
        $member = Member::create([
            ...$this->validated($request),
            'organization_id' => $this->organization($request)->id,
            'location_id' => $this->locationId($request),
            'created_by_user_id' => $request->user()->id,
            'status' => MemberStatus::Registered,
            'started_on' => now()->toDateString(),
            'sessions_remaining' => 0,
        ]);
        $this->sendConsentCopyWhenRequested($request, $member);

        return to_route('miembros.show', $member)->with('status', 'Miembro creado correctamente.');
    }

    public function show(Request $request, Member $member, SessionLedger $ledger)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist, OrganizationRole::Staff);
        $member = $this->member($request, $member);
        $member->load(['createdBy', 'portalAccesses.user', 'trainingPlans' => fn ($query) => $query->latest('starts_on')->with(['assignedBy', 'items']), 'progressEntries' => fn ($query) => $query->latest('recorded_on'), 'payments' => fn ($query) => $query->latest('paid_at')->with('receivedBy'), 'attendances' => fn ($query) => $query->latest('checked_in_at')->with('checkedInBy'), 'incidents' => fn ($query) => $query->latest('occurred_at')->with(['createdBy', 'resolvedBy'])]);

        $role = $this->membership($request)->role;

        return view('members.show', [
            'member' => $member,
            'canManage' => in_array($role, [OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer], true),
            'canIssueCard' => in_array($role, [OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist], true),
            'initialMeasurementPending' => $member->progressEntries()->doesntExist(),
            'sessionSummary' => $ledger->summary($member),
        ]);
    }

    public function edit(Request $request, Member $member)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);

        return view('members.form', ['member' => $this->member($request, $member), 'statuses' => MemberStatus::cases()]);
    }

    public function update(Request $request, Member $member)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        $member = $this->member($request, $member);
        $member->update($this->validated($request, $member));
        $this->sendConsentCopyWhenRequested($request, $member);

        return to_route('miembros.show', $member)->with('status', 'Ficha actualizada.');
    }

    public function destroyPhoto(Request $request, Member $member, string $photo)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        $member = $this->member($request, $member);
        abort_unless(in_array($photo, ['photo_front', 'photo_side', 'photo_back'], true), 404);
        $intake = $member->intake ?? [];
        if ($path = data_get($intake, "photos.{$photo}")) {
            Storage::disk('public')->delete($path);
            unset($intake['photos'][$photo]);
            if (empty($intake['photos'])) {
                unset($intake['photos']);
            }
            $member->update(['intake' => $intake]);
        }

        return back()->with('status', 'Foto eliminada.');
    }

    private function validated(Request $request, ?Member $existing = null): array
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:120'], 'last_name' => ['required', 'string', 'max:160'], 'public_alias' => ['nullable', 'string', 'max:40', Rule::unique('members', 'public_alias')->where('organization_id', $this->organization($request)->id)->ignore($existing?->id)],
            'national_id' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:150'], 'phone' => ['nullable', 'string', 'max:40'],
            'birth_date' => ['nullable', 'date', 'before:today'], 'address' => ['nullable', 'string', 'max:255'], 'city' => ['nullable', 'string', 'max:120'],
            'client_type' => ['nullable', 'string', 'max:60'],
            'informed_consent_accepted' => ['boolean'], 'risk_assumption_accepted' => ['boolean'], 'send_consent_by_email' => ['boolean'], 'consent_date' => ['nullable', 'date'],
            'sex' => ['nullable', 'string', 'max:30'], 'discovery_channel' => ['nullable', 'string', 'max:80'], 'discovery_detail' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:160'], 'desired_start_on' => ['nullable', 'date'],
            'objectives' => ['nullable', 'array'], 'objectives.*' => ['string', 'max:100'], 'other_objective' => ['nullable', 'string', 'max:255'], 'goal_date' => ['nullable', 'date'],
            'thinking_about_start' => ['nullable', 'string', 'max:255'], 'objective_importance' => ['nullable', 'integer', 'between:1,10'],
            'current_exercise' => ['nullable', 'string', 'max:10'], 'current_exercise_type' => ['nullable', 'string', 'max:2000'], 'current_exercise_frequency' => ['nullable', 'integer', 'min:1', 'max:7'], 'current_exercise_duration' => ['nullable', 'string', 'max:100'], 'current_exercise_duration_unit' => ['nullable', 'string', 'max:20'], 'current_exercise_results' => ['nullable', 'string', 'max:10'],
            'past_exercise' => ['nullable', 'string', 'max:10'], 'past_exercise_type' => ['nullable', 'string', 'max:2000'], 'past_exercise_frequency' => ['nullable', 'integer', 'min:1', 'max:7'], 'past_exercise_since' => ['nullable', 'string', 'max:100'], 'past_exercise_since_unit' => ['nullable', 'string', 'max:20'], 'past_exercise_duration' => ['nullable', 'string', 'max:100'], 'past_exercise_duration_unit' => ['nullable', 'string', 'max:20'], 'past_exercise_results' => ['nullable', 'string', 'max:10'], 'past_exercise_reason' => ['nullable', 'string', 'max:2000'],
            'barriers' => ['nullable', 'array'], 'barriers.*' => ['string', 'max:100'], 'barriers_still_active' => ['nullable', 'boolean'], 'preferred_training_times' => ['nullable', 'array'], 'preferred_training_times.*' => ['in:m,t'], 'preferred_training_ranges' => ['nullable', 'array'], 'preferred_training_ranges.*.from' => ['nullable', 'date_format:H:i'], 'preferred_training_ranges.*.to' => ['nullable', 'date_format:H:i'], 'injury_notes' => ['nullable', 'string', 'max:5000'],
            'medical_notes' => ['nullable', 'string', 'max:5000'], 'pregnancy' => ['nullable', 'boolean'], 'caaf' => ['nullable', 'array'], 'caaf.*' => ['boolean'], 'trainer_intake_reviewed' => ['boolean'],
            'weight_kg' => ['nullable', 'numeric', 'between:1,500'], 'height_cm' => ['nullable', 'integer', 'between:50,300'], 'body_fat_percentage' => ['nullable', 'numeric', 'between:0,100'], 'muscle_mass_kg' => ['nullable', 'numeric', 'between:0,500'], 'water_percentage' => ['nullable', 'numeric', 'between:0,100'], 'visceral_fat' => ['nullable', 'integer', 'between:0,100'], 'metabolic_age' => ['nullable', 'integer', 'between:1,150'],
            'waist_cm' => ['nullable', 'numeric', 'between:0,300'], 'hip_cm' => ['nullable', 'numeric', 'between:0,300'], 'chest_cm' => ['nullable', 'numeric', 'between:0,300'], 'arm_cm' => ['nullable', 'numeric', 'between:0,300'], 'leg_cm' => ['nullable', 'numeric', 'between:0,300'],
            'photo_front' => ['nullable', 'image', 'max:10240'], 'photo_side' => ['nullable', 'image', 'max:10240'], 'photo_back' => ['nullable', 'image', 'max:10240'],
        ]);

        $intakeKeys = ['sex', 'discovery_channel', 'discovery_detail', 'occupation', 'desired_start_on', 'objectives', 'other_objective', 'goal_date', 'thinking_about_start', 'objective_importance', 'current_exercise', 'current_exercise_type', 'current_exercise_frequency', 'current_exercise_duration', 'current_exercise_duration_unit', 'current_exercise_results', 'past_exercise', 'past_exercise_type', 'past_exercise_frequency', 'past_exercise_since', 'past_exercise_since_unit', 'past_exercise_duration', 'past_exercise_duration_unit', 'past_exercise_results', 'past_exercise_reason', 'barriers', 'barriers_still_active', 'preferred_training_times', 'preferred_training_ranges', 'injury_notes', 'medical_notes', 'pregnancy', 'caaf', 'trainer_intake_reviewed'];
        $metricKeys = ['weight_kg', 'height_cm', 'body_fat_percentage', 'muscle_mass_kg', 'water_percentage', 'visceral_fat', 'metabolic_age', 'waist_cm', 'hip_cm', 'chest_cm', 'arm_cm', 'leg_cm'];
        $photos = [];
        foreach (['photo_front', 'photo_side', 'photo_back'] as $field) {
            if ($request->hasFile($field)) {
                $photos[$field] = $request->file($field)->store('members', 'public');
            }
        }

        $oldIntake = $existing?->intake ?? [];
        $oldMetrics = $existing?->initial_metrics ?? [];
        $intake = array_filter(collect($validated)->only($intakeKeys)->all(), fn ($value) => $value !== null && $value !== [] && $value !== '');
        $intake['caaf_requires_review'] = collect($validated['caaf'] ?? [])->contains(fn ($answer) => (bool) $answer) || $request->boolean('pregnancy');
        $intake['consent_version'] = '2026-09-11';
        $intake['consent_recorded_at'] = now()->toIso8601String();
        $metrics = array_filter(collect($validated)->only($metricKeys)->all(), fn ($value) => $value !== null);

        return collect($validated)->except([...$intakeKeys, ...$metricKeys, 'photo_front', 'photo_side', 'photo_back', 'send_consent_by_email'])->all() + [
            'intake' => [...$oldIntake, ...$intake, ...($photos ? ['photos' => [...data_get($oldIntake, 'photos', []), ...$photos]] : [])],
            'initial_metrics' => [...$oldMetrics, ...$metrics],
            'informed_consent_accepted' => $request->boolean('informed_consent_accepted'),
            'risk_assumption_accepted' => $request->boolean('risk_assumption_accepted'),
        ];
    }

    private function sendConsentCopyWhenRequested(Request $request, Member $member): void
    {
        if (! $request->boolean('send_consent_by_email') || ! $member->email) {
            return;
        }

        Mail::raw("Hola {$member->first_name},\n\nTe enviamos una copia de los documentos de consentimiento informado y asuncion de riesgos registrados el ".($member->consent_date?->format('d/m/Y') ?? now()->format('d/m/Y')).".\n\nPuedes solicitar una copia completa al centro en cualquier momento.\n\nThe Fitness Club", function ($message) use ($member): void {
            $message->to($member->email)->subject('Copia de consentimiento - The Fitness Club');
        });
    }
}
