<?php

use App\Enums\MembershipStatus;
use App\Enums\MemberStatus;
use App\Enums\OrganizationRole;
use App\Models\Location;
use App\Models\Member;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->organization = Organization::factory()->create(['is_active' => true]);
    Location::factory()->for($this->organization)->create();
    $this->user = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($this->user)->create(['role' => OrganizationRole::Owner, 'status' => MembershipStatus::Active]);
    $this->member = Member::create(['organization_id' => $this->organization->id, 'first_name' => 'Ana', 'last_name' => 'García', 'phone' => '600000000', 'status' => MemberStatus::Active, 'started_on' => now()->toDateString()]);
});

test('authorized staff can open and print a member card', function () {
    $this->actingAs($this->user)->get(route('members.card.show', $this->member))
        ->assertOk()
        ->assertSee('Carnet de acceso')
        ->assertSee('Imprimir')
        ->assertSee('Enviar por WhatsApp')
        ->assertSee('<svg', false);
});

test('the signed public card rejects invalid and expired credentials', function () {
    $valid = URL::temporarySignedRoute('members.card.public', now()->addMinute(), ['member' => $this->member, 'credential' => $this->member->check_in_token]);
    $invalidCredential = URL::temporarySignedRoute('members.card.public', now()->addMinute(), ['member' => $this->member, 'credential' => 'otro-codigo']);

    $this->get($valid)->assertOk()->assertSee('Descargar imagen')->assertDontSee('Imprimir');
    $this->get($invalidCredential)->assertNotFound();
    $this->get($valid.'&signature=manipulada')->assertForbidden();
});

test('regenerating the qr invalidates the previous public card', function () {
    $oldToken = $this->member->check_in_token;
    $oldUrl = URL::temporarySignedRoute('members.card.public', now()->addMinute(), ['member' => $this->member, 'credential' => $oldToken]);

    $this->actingAs($this->user)->post(route('members.card.regenerate', $this->member))->assertRedirect();

    expect($this->member->refresh()->check_in_token)->not->toBe($oldToken)
        ->and($this->member->check_in_token_rotated_by_user_id)->toBe($this->user->id)
        ->and($this->member->check_in_token_rotated_at)->not->toBeNull();
    $this->get($oldUrl)->assertNotFound();
});
