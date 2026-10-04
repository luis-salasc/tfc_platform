<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\Member;
use Illuminate\Http\Request;

class MemberProgressController extends Controller
{
    use InteractsWithOrganization;

    public function store(Request $request, Member $member)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        $member = $this->member($request, $member);
        $data = $request->validate(['recorded_on' => ['required', 'date'], 'weight_kg' => ['nullable', 'numeric', 'between:1,500'], 'body_fat_percentage' => ['nullable', 'numeric', 'between:0,100'], 'muscle_mass_kg' => ['nullable', 'numeric', 'between:0,500'], 'waist_cm' => ['nullable', 'numeric', 'between:0,300'], 'hip_cm' => ['nullable', 'numeric', 'between:0,300'], 'chest_cm' => ['nullable', 'numeric', 'between:0,300'], 'arm_cm' => ['nullable', 'numeric', 'between:0,300'], 'leg_cm' => ['nullable', 'numeric', 'between:0,300'], 'water_percentage' => ['nullable', 'numeric', 'between:0,100'], 'visceral_fat' => ['nullable', 'integer', 'between:0,100'], 'metabolic_age' => ['nullable', 'integer', 'between:1,150'], 'notes' => ['nullable', 'string', 'max:5000']]);
        $member->progressEntries()->create(['recorded_on' => $data['recorded_on'], 'notes' => $data['notes'] ?? null, 'metrics' => collect($data)->only(['weight_kg', 'body_fat_percentage', 'muscle_mass_kg', 'waist_cm', 'hip_cm', 'chest_cm', 'arm_cm', 'leg_cm', 'water_percentage', 'visceral_fat', 'metabolic_age'])->filter(fn ($value) => $value !== null)->all()]);

        return back()->with('status', 'Progreso registrado.');
    }
}
