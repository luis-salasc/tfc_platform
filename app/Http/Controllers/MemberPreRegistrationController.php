<?php

namespace App\Http\Controllers;

use App\Enums\MemberPreRegistrationReviewDecision;
use App\Enums\MemberPreRegistrationStatus;
use App\Enums\MemberStatus;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\MemberPreRegistration;
use App\Services\MemberPreRegistrationFinalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberPreRegistrationController extends Controller
{
    use InteractsWithOrganization;

    private const CONSENT_VERSION = '2026-09-11';

    private const OBJECTIVE_VALUES = [
        'mejorar_salud', 'subir_peso', 'bajar_peso', 'disminuir_volumen', 'tonificar', 'mejorar_postura',
        'aumentar_resistencia', 'reducir_grasa', 'sentirse_mejor', 'aumentar_masa_muscular', 'rehabilitacion',
        'aliviar_dolores', 'fortalecer', 'rendimiento_laboral', 'dormir_mejor', 'eliminar_celulitis',
        'reducir_estres', 'rendimiento_deportivo',
    ];

    public function create(Request $request)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);

        return $this->formView(new MemberPreRegistration, $request);
    }

    public function store(Request $request)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);
        $draft = $request->boolean('save_as_draft');
        $data = $this->validated($request, $draft);

        $signaturePath = $this->storeSignature($data['member_signature'] ?? null);
        unset($data['member_signature']);

        try {
            $preRegistration = DB::transaction(function () use ($data, $draft, $request, $signaturePath): MemberPreRegistration {
            $review = $this->reviewInput($data, $draft, $request);
            unset($data['review_decision'], $data['review_observations']);
            $preRegistration = MemberPreRegistration::create([
                ...$data,
                'organization_id' => $this->organization($request)->id,
                'location_id' => $this->locationId($request),
                'created_by_user_id' => $request->user()->id,
                'status' => $draft ? MemberPreRegistrationStatus::Draft : $this->statusFor($data['intake']),
                'consent_version' => $draft ? null : self::CONSENT_VERSION,
                'consent_accepted_at' => $draft ? null : now(),
                'member_signature_path' => $signaturePath,
            ]);
            $this->recordInitialReview($preRegistration, $review, $request);

            return $preRegistration;
        });
        } catch (\Throwable $exception) {
            if ($signaturePath) {
                Storage::disk('local')->delete($signaturePath);
            }

            throw $exception;
        }

        return $this->afterPersistence($request, $preRegistration, $draft);
    }

    public function show(Request $request, MemberPreRegistration $preRegistration)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);
        $preRegistration = $this->preRegistration($request, $preRegistration);
        $preRegistration->load(['createdBy', 'reviews.reviewedBy']);
        $canReview = in_array($this->membership($request)->role, [OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer], true);

        return view('pre-registrations.show', compact('preRegistration', 'canReview'));
    }

    public function edit(Request $request, MemberPreRegistration $preRegistration)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);
        $preRegistration = $this->preRegistration($request, $preRegistration);
        abort_unless($preRegistration->status === MemberPreRegistrationStatus::Draft, 409);

        return $this->formView($preRegistration, $request);
    }

    public function update(Request $request, MemberPreRegistration $preRegistration)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);
        $preRegistration = $this->preRegistration($request, $preRegistration);
        abort_unless($preRegistration->status === MemberPreRegistrationStatus::Draft, 409);
        $draft = $request->boolean('save_as_draft');
        $data = $this->validated($request, $draft);

        $signaturePath = $this->storeSignature($data['member_signature'] ?? null);
        unset($data['member_signature']);

        try {
            DB::transaction(function () use ($preRegistration, $data, $draft, $request, $signaturePath): void {
            $review = $this->reviewInput($data, $draft, $request);
            unset($data['review_decision'], $data['review_observations']);
            $attributes = [
                ...$data,
                'status' => $draft ? MemberPreRegistrationStatus::Draft : $this->statusFor($data['intake']),
            ];
            if (! $draft) {
                $attributes['consent_version'] = self::CONSENT_VERSION;
                $attributes['consent_accepted_at'] = now();
            }
            if ($signaturePath) {
                $attributes['member_signature_path'] = $signaturePath;
            }
            $preRegistration->update($attributes);
            $this->recordInitialReview($preRegistration, $review, $request);
        });
        } catch (\Throwable $exception) {
            if ($signaturePath) {
                Storage::disk('local')->delete($signaturePath);
            }

            throw $exception;
        }

        return $this->afterPersistence($request, $preRegistration->refresh(), $draft);
    }

    public function resume(Request $request, MemberPreRegistration $preRegistration)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        $preRegistration = $this->preRegistration($request, $preRegistration);
        abort_unless($preRegistration->status === MemberPreRegistrationStatus::OnHold, 409);

        $preRegistration->update(['status' => MemberPreRegistrationStatus::RequiresReview]);

        return to_route('pre-registrations.show', $preRegistration)->with('status', 'Alta retomada para una nueva revisión.');
    }

    public function finalize(Request $request, MemberPreRegistration $preRegistration)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);
        $this->preRegistration($request, $preRegistration);
        $member = app(MemberPreRegistrationFinalizer::class)->finalize($preRegistration, $this->organization($request), $request->user());

        return to_route('miembros.show', $member)->with('status', 'Miembro dado de alta correctamente.');
    }

    private function validated(Request $request, bool $draft): array
    {
        $this->normalizeFirstSheetInput($request);

        $personalFieldRules = $draft
            ? ['nullable']
            : ['required'];
        $caafRules = $draft
            ? ['nullable', 'array']
            : ['required', 'array'];
        $answerRules = $draft
            ? ['nullable', 'boolean']
            : ['required', 'boolean'];
        $signatureRules = $draft
            ? ['nullable']
            : ['required', 'string', 'max:700000', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! preg_match('/^data:image\\/png;base64,([A-Za-z0-9+\\/=\\s]+)$/', $value, $matches)) {
                    $fail('La firma del miembro no es válida.');

                    return;
                }
                $signature = base64_decode(preg_replace('/\\s+/', '', $matches[1]), true);
                if ($signature === false || strlen($signature) > 512000 || ! str_starts_with($signature, "\x89PNG\r\n\x1a\n")) {
                    $fail('La firma del miembro no es válida.');
                }
            }];

        $validated = $request->validate([
            'first_name' => [...$personalFieldRules, 'string', 'max:120', 'regex:/^[\p{L}\p{M}]+(?:[ \'’\-][\p{L}\p{M}]+)*$/u'],
            'last_name' => [...$personalFieldRules, 'string', 'max:160', 'regex:/^[\p{L}\p{M}]+(?:[ \'’\-][\p{L}\p{M}]+)*$/u'],
            'birth_date' => [...$personalFieldRules, 'date', 'before_or_equal:today'],
            'document_type' => [...$personalFieldRules, 'in:dni,nie,passport'],
            'document_number' => [...$personalFieldRules, 'string', 'min:3', 'max:30'],
            'email' => [...$personalFieldRules, 'email', 'max:150'],
            'phone' => [...$personalFieldRules, 'string', 'max:40', 'regex:/^\+?[0-9][0-9 ()-]*$/', function (string $attribute, mixed $value, \Closure $fail): void {
                if (strlen(preg_replace('/\D/', '', (string) $value)) < 7) {
                    $fail('Introduce un teléfono válido.');
                }
            }],
            'client_type' => [...$personalFieldRules, 'in:presencial,online'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => [...$personalFieldRules, 'string', 'max:120', 'regex:/^[\p{L}\p{M}]+(?:[ .\'’\-][\p{L}\p{M}]+)*$/u'],
            'public_alias' => ['nullable', 'string', 'max:40'],
            'sex' => [...$personalFieldRules, 'in:hombre,mujer,no_aplica'],
            'discovery_channel' => ['nullable', 'in:recomendacion,instagram,facebook,busqueda_internet,publicidad,paso_por_el_centro,otro'],
            'discovery_detail' => [Rule::requiredIf(fn () => ! $draft && in_array($request->input('discovery_channel'), ['recomendacion', 'otro'], true)), 'nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:160'],
            'objectives' => $draft ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'objectives.*' => ['string', Rule::in(self::OBJECTIVE_VALUES)],
            'other_objective' => ['nullable', 'string', 'max:255'],
            'goal_date' => ['nullable', 'date'],
            'objective_importance' => ['nullable', 'integer', 'between:1,10'],
            'current_exercise' => ['nullable', 'string', 'max:10'],
            'current_exercise_type' => ['nullable', 'string', 'max:2000'],
            'current_exercise_frequency' => ['nullable', 'integer', 'between:1,7'],
            'current_exercise_duration' => ['nullable', 'string', 'max:100'],
            'current_exercise_results' => ['nullable', 'string', 'max:10'],
            'past_exercise' => ['nullable', 'string', 'max:10'],
            'past_exercise_type' => ['nullable', 'string', 'max:2000'],
            'past_exercise_frequency' => ['nullable', 'integer', 'between:1,7'],
            'past_exercise_since' => ['nullable', 'string', 'max:100'],
            'past_exercise_duration' => ['nullable', 'string', 'max:100'],
            'past_exercise_results' => ['nullable', 'string', 'max:10'],
            'past_exercise_reason' => ['nullable', 'string', 'max:2000'],
            'barriers' => ['nullable', 'array'],
            'barriers.*' => ['string', 'max:100'],
            'barriers_still_active' => ['nullable', 'boolean'],
            'preferred_training_times' => ['nullable', 'array'],
            'preferred_training_times.*' => ['in:m,t'],
            'preferred_training_ranges' => ['nullable', 'array'],
            'preferred_training_ranges.*.from' => ['nullable', 'date_format:H:i'],
            'preferred_training_ranges.*.to' => ['nullable', 'date_format:H:i'],
            'injury_notes' => ['nullable', 'string', 'max:5000'],
            'medical_notes' => ['nullable', 'string', 'max:5000'],
            'pregnancy' => ['nullable', 'boolean'],
            'review_decision' => ['nullable', Rule::enum(MemberPreRegistrationReviewDecision::class)],
            'review_observations' => ['nullable', 'string', 'max:5000'],
            'informed_consent_accepted' => $draft ? ['nullable'] : ['required', 'accepted'],
            'risk_assumption_accepted' => $draft ? ['nullable'] : ['required', 'accepted'],
            'member_signature' => $signatureRules,
            'caaf' => $caafRules,
            'caaf.heart_condition' => $answerRules,
            'caaf.chest_activity' => $answerRules,
            'caaf.chest_rest' => $answerRules,
            'caaf.dizziness' => $answerRules,
            'caaf.bones_joints' => $answerRules,
            'caaf.medication' => $answerRules,
            'caaf.other_reason' => $answerRules,
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'first_name.regex' => 'El nombre no puede contener números ni símbolos no permitidos.',
            'last_name.regex' => 'Los apellidos no pueden contener números ni símbolos no permitidos.',
            'birth_date.date' => 'Introduce una fecha de nacimiento válida.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'document_type.in' => 'Selecciona un tipo de documento válido.',
            'document_number.min' => 'El número de documento es demasiado corto.',
            'email.email' => 'Introduce un correo electrónico válido.',
            'phone.regex' => 'Introduce un teléfono válido.',
            'sex.in' => 'Selecciona una opción de sexo válida.',
            'city.regex' => 'Introduce una localidad válida.',
            'objectives.required' => 'Selecciona al menos un objetivo.',
            'objectives.min' => 'Selecciona al menos un objetivo.',
            'objectives.*.in' => 'Selecciona únicamente objetivos disponibles.',
            'discovery_channel.in' => 'Selecciona una procedencia válida.',
            'discovery_detail.required' => 'Completa el detalle de cómo nos has conocido.',
        ], [
            'first_name' => 'nombre',
            'last_name' => 'apellidos',
            'birth_date' => 'fecha de nacimiento',
            'document_type' => 'tipo de documento',
            'document_number' => 'número de documento',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'client_type' => 'tipo de alta',
            'city' => 'localidad',
            'sex' => 'sexo',
        ]);

        $intakeKeys = [
            'sex',
            'address',
            'city',
            'public_alias',
            'discovery_channel',
            'discovery_detail',
            'occupation',
            'objectives',
            'other_objective',
            'goal_date',
            'objective_importance',
            'current_exercise',
            'current_exercise_type',
            'current_exercise_frequency',
            'current_exercise_duration',
            'current_exercise_results',
            'past_exercise',
            'past_exercise_type',
            'past_exercise_frequency',
            'past_exercise_since',
            'past_exercise_duration',
            'past_exercise_results',
            'past_exercise_reason',
            'barriers',
            'barriers_still_active',
            'preferred_training_times',
            'preferred_training_ranges',
            'injury_notes',
            'medical_notes',
            'pregnancy',
            'caaf',
        ];
        $intake = collect($validated)
            ->only($intakeKeys)
            ->filter(fn ($value) => $value !== null && $value !== [] && $value !== '')
            ->all();

        return collect($validated)->except($intakeKeys)->all() + ['intake' => $intake];
    }

    private function normalizeFirstSheetInput(Request $request): void
    {
        $normalized = [];

        foreach (['first_name', 'last_name', 'birth_date', 'document_type', 'document_number', 'email', 'phone', 'client_type', 'address', 'city', 'sex', 'discovery_channel', 'discovery_detail', 'occupation'] as $field) {
            if (is_string($request->input($field))) {
                $normalized[$field] = trim($request->input($field));
            }
        }

        $request->merge($normalized);
    }

    private function statusFor(array $intake): MemberPreRegistrationStatus
    {
        $caafRequiresReview = collect($intake['caaf'] ?? [])->contains(fn ($answer) => (bool) $answer);

        return $caafRequiresReview || (bool) ($intake['pregnancy'] ?? false)
            ? MemberPreRegistrationStatus::RequiresReview
            : MemberPreRegistrationStatus::ReadyForFinalization;
    }

    private function reviewInput(array $data, bool $draft, Request $request): ?array
    {
        $canReview = in_array($this->membership($request)->role, [OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer], true);

        if (! $canReview && (filled($data['review_decision'] ?? null) || filled($data['review_observations'] ?? null))) {
            $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        }
        if ($draft || $this->statusFor($data['intake']) !== MemberPreRegistrationStatus::RequiresReview) {
            return null;
        }

        if (! $canReview) {
            return null;
        }
        if (blank($data['review_decision'] ?? null) || blank($data['review_observations'] ?? null)) {
            throw ValidationException::withMessages([
                'review_decision' => 'Selecciona una decisión de revisión.',
                'review_observations' => 'Incluye las aclaraciones u observaciones de la revisión.',
            ]);
        }

        return [
            'decision' => MemberPreRegistrationReviewDecision::from($data['review_decision']),
            'observations' => $data['review_observations'],
        ];
    }

    private function recordInitialReview(MemberPreRegistration $preRegistration, ?array $review, Request $request): void
    {
        if (! $review) {
            return;
        }
        $preRegistration->reviews()->create([
            ...$review,
            'triggering_circumstances' => $preRegistration->triggeringCircumstances(),
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        $preRegistration->update(['status' => $review['decision'] === MemberPreRegistrationReviewDecision::CanContinue ? MemberPreRegistrationStatus::ReadyForFinalization : MemberPreRegistrationStatus::OnHold]);
    }

    private function preRegistration(Request $request, MemberPreRegistration $preRegistration): MemberPreRegistration
    {
        abort_unless($preRegistration->organization_id === $this->organization($request)->id, 404);

        return $preRegistration;
    }

    private function afterPersistence(Request $request, MemberPreRegistration $preRegistration, bool $draft)
    {
        if (! $draft && $preRegistration->status === MemberPreRegistrationStatus::ReadyForFinalization) {
            $member = app(MemberPreRegistrationFinalizer::class)->finalize($preRegistration, $this->organization($request), $request->user());

            return to_route('miembros.show', $member)->with('status', 'Miembro dado de alta correctamente.');
        }

        return to_route('pre-registrations.show', $preRegistration)->with('status', $draft ? 'Borrador guardado.' : 'Alta pendiente de revisión.');
    }

    private function storeSignature(?string $signature): ?string
    {
        if (! $signature) {
            return null;
        }

        [, $encoded] = explode(',', $signature, 2);
        $path = 'member-signatures/'.Str::uuid().'.png';

        if (! Storage::disk('local')->put($path, base64_decode(preg_replace('/\s+/', '', $encoded), true))) {
            throw ValidationException::withMessages(['member_signature' => 'No se pudo guardar la firma del miembro.']);
        }

        return $path;
    }

    private function formView(MemberPreRegistration $preRegistration, Request $request)
    {
        return view('members.form', [
            'member' => $preRegistration,
            'preRegistration' => $preRegistration,
            'preRegistrationMode' => true,
            'statuses' => MemberStatus::cases(),
            'canReview' => in_array($this->membership($request)->role, [OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer], true),
        ]);
    }
}
