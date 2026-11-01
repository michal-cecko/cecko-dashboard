<?php

namespace App\Http\Middleware;

use App\Models\Support\SupportProject;
use App\Services\Support\SupportAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupportAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $parameter = $request->route('project');
        $slug = $parameter instanceof SupportProject ? $parameter->slug : $parameter;
        if (! Auth::guard('support')->check()) {
            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect()->route('support.login', ['project' => $slug]);
        }
        $project = app(SupportAccess::class)->project($slug);
        $previous = app()->getLocale();
        app()->setLocale($project->locale);
        try {
            return $next($request);
        } finally {
            app()->setLocale($previous);
        }
    }
}
