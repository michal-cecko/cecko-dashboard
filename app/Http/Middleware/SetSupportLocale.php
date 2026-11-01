<?php

namespace App\Http\Middleware;

use App\Models\Support\SupportProject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSupportLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $parameter = $request->route('project');
        $project = $parameter instanceof SupportProject ? $parameter : SupportProject::query()->where('slug', $parameter)->firstOrFail();
        $previous = app()->getLocale();
        app()->setLocale($project->locale);
        try {
            return $next($request);
        } finally {
            app()->setLocale($previous);
        }
    }
}
