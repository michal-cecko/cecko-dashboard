<?php

use App\Http\Controllers\Stride\AuthController;
use App\Http\Controllers\Stride\CoachController;
use App\Http\Controllers\Stride\GoalController;
use App\Http\Controllers\Stride\HomeController;
use App\Http\Controllers\Stride\InjuryController;
use App\Http\Controllers\Stride\LibraryController;
use App\Http\Controllers\Stride\PersonalRecordController;
use App\Http\Controllers\Stride\PlanController;
use App\Http\Controllers\Stride\ProfileController;
use App\Http\Controllers\Stride\SessionController;
use App\Http\Controllers\Stride\SpotController;
use App\Http\Controllers\Stride\WeightController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Stride API routes
|--------------------------------------------------------------------------
|
| Mounted under the "api/stride" prefix (see bootstrap/app.php). The mobile
| (Capacitor) app is the only consumer. Auth is a Bearer UserApiToken with the
| "stride" ability, enforced by the stride.auth middleware.
|
*/

Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('stride.auth')->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Profile preferences (language, …)
    Route::patch('profile', [ProfileController::class, 'update']);

    // Library
    Route::get('library', [LibraryController::class, 'index']);
    Route::get('equipment', [LibraryController::class, 'equipment']);

    // Home + plan
    Route::get('home', [HomeController::class, 'index']);
    Route::get('plan', [PlanController::class, 'index']);
    Route::get('blocks/{block}', [PlanController::class, 'show']);

    // Onboarding plan generation
    Route::post('plan/recommend', [PlanController::class, 'recommend']);
    Route::post('plan/questions', [PlanController::class, 'questions']);
    Route::post('plan/answers', [PlanController::class, 'answers']);
    Route::post('plan/generate', [PlanController::class, 'generate']);

    // Sessions (active player)
    Route::get('sessions/{session}', [SessionController::class, 'show']);
    Route::post('sessions/{session}/start', [SessionController::class, 'start']);
    Route::post('sessions/{session}/complete', [SessionController::class, 'complete']);
    Route::patch('sessions/{session}/sets/{set}', [SessionController::class, 'logSet']);

    // Goals
    Route::get('goals', [GoalController::class, 'index']);
    Route::post('goals', [GoalController::class, 'store']);
    Route::patch('goals/{goal}', [GoalController::class, 'update']);
    Route::delete('goals/{goal}', [GoalController::class, 'destroy']);

    // Injuries
    Route::get('injuries', [InjuryController::class, 'index']);
    Route::post('injuries', [InjuryController::class, 'store']);
    Route::get('injuries/{injury}', [InjuryController::class, 'show']);
    Route::patch('injuries/{injury}', [InjuryController::class, 'update']);
    Route::post('injuries/{injury}/journal', [InjuryController::class, 'addJournal']);

    // Weight
    Route::get('weight', [WeightController::class, 'index']);
    Route::post('weight', [WeightController::class, 'store']);

    // Personal records (type-aware bests per exercise)
    Route::get('personal-records', [PersonalRecordController::class, 'index']);
    Route::post('personal-records', [PersonalRecordController::class, 'store']);
    Route::patch('personal-records/{personalRecord}', [PersonalRecordController::class, 'update']);
    Route::delete('personal-records/{personalRecord}', [PersonalRecordController::class, 'destroy']);

    // Spots (training locations)
    Route::get('spots', [SpotController::class, 'index']);
    Route::post('spots', [SpotController::class, 'store']);

    // AI coach
    Route::get('coach/conversations', [CoachController::class, 'index']);
    Route::post('coach/conversations', [CoachController::class, 'store']);
    Route::get('coach/conversations/{conversation}', [CoachController::class, 'show']);
    Route::post('coach/conversations/{conversation}/messages', [CoachController::class, 'message']);
    Route::patch('coach/conversations/{conversation}/persona', [CoachController::class, 'setPersona']);
    Route::get('coach/adjustments', [CoachController::class, 'adjustments']);

    // Block-scoped coach chat (edits the whole block).
    Route::get('coach/blocks/{block}/conversation', [CoachController::class, 'blockConversation']);

    // Propose → confirm: coach changes are staged, then applied/dismissed by the user.
    Route::get('coach/proposals', [CoachController::class, 'proposals']);
    Route::post('coach/proposals/{adjustment}/apply', [CoachController::class, 'applyProposal']);
    Route::post('coach/proposals/{adjustment}/dismiss', [CoachController::class, 'dismissProposal']);
});
