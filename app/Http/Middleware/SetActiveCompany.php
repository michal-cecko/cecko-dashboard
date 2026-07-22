<?php

namespace App\Http\Middleware;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Invoices\Company;
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
            $canUseCompany = $user->companies()->where('id', $user->active_company_id)->exists()
                || ($user->hasCapability(UserCapabilityEnum::MANAGE_ALL_INVOICES) && Company::query()->whereKey($user->active_company_id)->exists());

            if (! $canUseCompany) {
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
