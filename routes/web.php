<?php

use App\Http\Controllers\ActivateMemberPortalController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberPreRegistrationController;
use App\Http\Controllers\MemberPreRegistrationReviewController;
use App\Http\Controllers\MemberCardController;
use App\Http\Controllers\MemberIncidentController;
use App\Http\Controllers\MemberPaymentController;
use App\Http\Controllers\MemberPortalInvitationController;
use App\Http\Controllers\MemberProgressController;
use App\Http\Controllers\MemberTrainingPlanController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('activar-acceso/{token}', [ActivateMemberPortalController::class, 'create'])->middleware(['throttle:10,1', 'member.portal.enabled'])->name('portal.activate');
Route::post('activar-acceso/{token}', [ActivateMemberPortalController::class, 'store'])->middleware(['throttle:10,1', 'member.portal.enabled'])->name('portal.activate.store');
Route::view('/aprendizaje', 'aprendizaje', ['nombreClub' => 'The Fitness Club'])->name('aprendizaje');

Route::get('kiosco/{organization:slug}', [KioskController::class, 'show'])->name('kiosk.show');
Route::post('kiosco/{organization:slug}', [KioskController::class, 'store'])->middleware('throttle:30,1')->name('kiosk.store');
Route::get('carnet/{member}/{credential}', [MemberCardController::class, 'publicCard'])->middleware(['signed', 'throttle:30,1'])->name('members.card.public');
Route::middleware(['auth', 'verified', 'platform.admin'])->get('plataforma', PlatformController::class)->name('platform.index');
Route::middleware(['auth', 'verified', 'platform.admin'])->put('plataforma/perfiles/{role}', [PlatformController::class, 'updateProfile'])->name('platform.profiles.update');

Route::middleware(['auth', 'verified', 'organization.access'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::resource('miembros/altas', MemberPreRegistrationController::class)
        ->parameters(['altas' => 'preRegistration'])
        ->names('pre-registrations')
        ->only(['store', 'show', 'edit', 'update']);
    Route::post('miembros/altas/{preRegistration}/retomar', [MemberPreRegistrationController::class, 'resume'])->name('pre-registrations.resume');
    Route::post('miembros/altas/{preRegistration}/revisiones', [MemberPreRegistrationReviewController::class, 'store'])->name('pre-registrations.reviews.store');
    Route::post('miembros/altas/{preRegistration}/finalizar', [MemberPreRegistrationController::class, 'finalize'])->name('pre-registrations.finalize');
    Route::resource('miembros', MemberController::class)
        ->parameters(['miembros' => 'member'])
        ->except(['destroy', 'store']);
    Route::delete('miembros/{member}/fotos/{photo}', [MemberController::class, 'destroyPhoto'])->name('members.photos.destroy');
    Route::get('miembros/{member}/carnet', [MemberCardController::class, 'show'])->name('members.card.show');
    Route::post('miembros/{member}/carnet/regenerar', [MemberCardController::class, 'regenerate'])->name('members.card.regenerate');
    Route::post('miembros/{member}/progresos', [MemberProgressController::class, 'store'])->name('members.progress.store');
    Route::post('miembros/{member}/planes-entrenamiento', [MemberTrainingPlanController::class, 'store'])->name('members.training-plans.store');
    Route::put('miembros/{member}/planes-entrenamiento/{trainingPlan}', [MemberTrainingPlanController::class, 'update'])->name('members.training-plans.update');
    Route::post('miembros/{member}/planes-entrenamiento/{trainingPlan}/archivar', [MemberTrainingPlanController::class, 'archive'])->name('members.training-plans.archive');
    Route::post('miembros/{member}/acceso-portal', [MemberPortalInvitationController::class, 'store'])->middleware('member.portal.enabled')->name('members.portal-invitations.store');
    Route::post('miembros/{member}/incidencias', [MemberIncidentController::class, 'store'])->name('members.incidents.store');
    Route::post('miembros/{member}/incidencias/{incident}/resolver', [MemberIncidentController::class, 'resolve'])->name('members.incidents.resolve');
    Route::post('miembros/{member}/pagos', [MemberPaymentController::class, 'store'])->name('members.payments.store');
    Route::get('pagos/{payment}/justificante', [MemberPaymentController::class, 'receipt'])->name('payments.receipt');
    Route::get('pagos/{payment}/editar', [MemberPaymentController::class, 'edit'])->name('payments.edit');
    Route::put('pagos/{payment}/editar', [MemberPaymentController::class, 'update'])->name('payments.update');
    Route::post('pagos/{payment}/reenviar-justificante', [MemberPaymentController::class, 'resendReceipt'])->name('payments.receipt.resend');
    Route::get('asistencias', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('asistencias', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('pagos', [PaymentsController::class, 'index'])->name('payments.index');
    Route::get('configuracion', [SettingsController::class, 'edit'])->name('club-settings.edit');
    Route::put('configuracion', [SettingsController::class, 'update'])->name('club-settings.update');
    Route::get('administracion/usuarios', [TeamController::class, 'index'])->name('team.index');
    Route::post('administracion/usuarios', [TeamController::class, 'store'])->name('team.store');
    Route::put('administracion/usuarios/{membership}', [TeamController::class, 'update'])->name('team.update');
});

require __DIR__.'/settings.php';
