<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\Member;
use App\Models\MemberTrainingPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberTrainingPlanController extends Controller
{
    use InteractsWithOrganization;

    public function store(Request $request, Member $member)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        $member = $this->member($request, $member);
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $member, $data): void {
            $member->trainingPlans()->where('status', 'active')->update(['status' => 'archived']);
            $plan = $member->trainingPlans()->create([
                ...collect($data)->except('items')->all(),
                'organization_id' => $member->organization_id,
                'assigned_by_user_id' => $request->user()->id,
                'status' => 'active',
            ]);
            $this->replaceItems($plan, $data['items']);
        });

        return back()->with('status', 'Plan de entrenamiento asignado.');
    }

    public function update(Request $request, Member $member, MemberTrainingPlan $trainingPlan)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        $member = $this->member($request, $member);
        $this->assertPlanBelongsToMember($member, $trainingPlan);
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $trainingPlan, $data): void {
            $trainingPlan->update([
                ...collect($data)->except('items')->all(),
                'assigned_by_user_id' => $request->user()->id,
            ]);
            $this->replaceItems($trainingPlan, $data['items']);
        });

        return back()->with('status', 'Plan de entrenamiento actualizado.');
    }

    public function archive(Request $request, Member $member, MemberTrainingPlan $trainingPlan)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        $member = $this->member($request, $member);
        $this->assertPlanBelongsToMember($member, $trainingPlan);
        $trainingPlan->update(['status' => 'archived']);

        return back()->with('status', 'Plan archivado. El historial se conserva.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'objective' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'sessions_per_week' => ['nullable', 'integer', 'between:1,14'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.session_name' => ['nullable', 'string', 'max:100'],
            'items.*.exercise' => ['required', 'string', 'max:180'],
            'items.*.sets' => ['nullable', 'string', 'max:30'],
            'items.*.repetitions' => ['nullable', 'string', 'max:60'],
            'items.*.rest_seconds' => ['nullable', 'integer', 'between:0,3600'],
            'items.*.intensity' => ['nullable', 'string', 'max:80'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function replaceItems(MemberTrainingPlan $plan, array $items): void
    {
        $plan->items()->delete();
        foreach (array_values($items) as $position => $item) {
            $plan->items()->create([...$item, 'position' => $position]);
        }
    }

    private function assertPlanBelongsToMember(Member $member, MemberTrainingPlan $trainingPlan): void
    {
        abort_unless($trainingPlan->member_id === $member->id && $trainingPlan->organization_id === $member->organization_id, 404);
    }
}
