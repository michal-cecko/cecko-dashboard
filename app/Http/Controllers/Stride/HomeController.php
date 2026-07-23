<?php

namespace App\Http\Controllers\Stride;

use App\Http\Controllers\Controller;
use App\Http\Presenters\Stride\SessionPresenter;
use App\Models\Stride\Block;
use App\Models\Stride\Goal;
use App\Models\Stride\Session;
use App\Models\Stride\StrideProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Home tab aggregate: today's session, the current week, recent activity,
 * goals-on-track and the streak. One round-trip for the landing screen.
 */
class HomeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = StrideProfile::firstOrCreate(['user_id' => $user->id]);

        // Belt & braces: only the ACTIVE block's session counts as "today" — a
        // stale 'today' row from a replaced plan must never surface on Home.
        $today = Session::ownedBy($user)
            ->where('status', 'today')
            ->whereHas('block', fn ($q) => $q->where('status', 'active'))
            ->with('exercises.sets')
            ->orderBy('scheduled_date')
            ->first()
            // Already trained today? Home still needs that session (done card +
            // recap) — otherwise a completed day reads as a false "rest day".
            ?? Session::ownedBy($user)
                ->where('status', 'done')
                ->whereDate('scheduled_date', today())
                ->with('exercises.sets')
                ->orderByDesc('completed_at')
                ->first();

        $activeBlock = Block::ownedBy($user)->active()->first();

        // The soonest upcoming session, so Home can show "rest day — next training
        // in X days" instead of a false "nothing planned" when a plan starts later.
        $nextSession = $today ? null : Session::ownedBy($user)
            ->where('status', 'planned')
            ->whereHas('block', fn ($q) => $q->where('status', 'active'))
            ->whereDate('scheduled_date', '>=', today())
            ->orderBy('scheduled_date')
            ->first();

        $week = $activeBlock
            ? $activeBlock->sessions()->ownedBy($user)->get()
            : Session::ownedBy($user)
                ->whereBetween('scheduled_date', [now()->startOfWeek(), now()->endOfWeek()])
                ->orderBy('scheduled_date')
                ->get();

        $recent = Session::ownedBy($user)
            ->where('status', 'done')
            ->orderByDesc('scheduled_date')
            ->limit(5)
            ->get();

        $goals = Goal::ownedBy($user)->where('is_achieved', false)->get();
        $sessionsDoneThisWeek = $week->where('status', 'done')->count();

        return response()->json([
            'today' => $today ? SessionPresenter::full($today) : null,
            'has_plan' => $activeBlock !== null,
            'next_session' => $nextSession ? [
                'title' => $nextSession->title,
                'kind' => $nextSession->kind,
                'scheduled_date' => $nextSession->scheduled_date->toDateString(),
                'in_days' => (int) today()->diffInDays($nextSession->scheduled_date->copy()->startOfDay(), false),
            ] : null,
            'week' => $week->map(SessionPresenter::summary(...))->values(),
            'recent' => $recent->map(SessionPresenter::summary(...))->values(),
            'goals_on_track' => [
                'total' => $goals->count(),
                'on_track' => $goals->where('progress', '>=', 0.5)->count(),
            ],
            'streak_days' => $profile->streak_days,
            'this_week' => [
                'done' => $sessionsDoneThisWeek,
                'target' => $week->whereNotIn('kind', ['Rest'])->count(),
            ],
        ]);
    }
}
