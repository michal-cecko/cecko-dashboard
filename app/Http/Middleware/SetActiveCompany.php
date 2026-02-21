<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetActiveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->active_company_id) {
            $ownsCompany = $user->companies()->where('id', $user->active_company_id)->exists();

            if (! $ownsCompany) {
                $user->update(['active_company_id' => null]);
            }
        }

        if (! $user->active_company_id) {
            $firstCompany = $user->companies()->first();

            if ($firstCompany) {
                $user->update(['active_company_id' => $firstCompany->id]);
            }
        }

        return $next($request);
    }
}
