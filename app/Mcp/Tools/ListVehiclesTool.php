<?php

namespace App\Mcp\Tools;

use App\Models\Garaz\Vehicle;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lists the user vehicles with type, make/model, current odometer, and engine code where available.')]
class ListVehiclesTool extends Tool
{
    public function handle(Request $request): Response
    {
        $userId = $this->resolveUserId($request);

        if ($userId === null) {
            return Response::text('Authorization required: no user context attached to MCP session.');
        }

        $vehicles = Vehicle::query()
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->orderBy('nickname')
            ->get();

        if ($vehicles->isEmpty()) {
            return Response::text('User has no active vehicles in Garáž.');
        }

        $lines = [];

        foreach ($vehicles as $v) {
            $line = "- [garaz://vehicle/{$v->id}] {$v->nickname} ({$v->type?->translation()})";

            if ($v->make || $v->model) {
                $line .= ', '.trim(($v->make ?? '').' '.($v->model ?? ''));
            }

            if ($v->year_of_manufacture) {
                $line .= ' '.$v->year_of_manufacture;
            }

            if ($v->current_odometer_km) {
                $line .= ' — '.number_format($v->current_odometer_km, 0, ',', ' ').' km';
            }

            $engineCode = $v->carSpec?->engine_code;

            if ($engineCode) {
                $line .= " (engine: {$engineCode})";
            }

            $lines[] = $line;
        }

        return Response::text(implode("\n", $lines));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    private function resolveUserId(Request $request): ?int
    {
        // TODO: wire MCP session → user_id mapping (OAuth flow)
        // For local dev, accept an explicit user_id in request OR fall back to authenticated user.
        return auth()->id();
    }
}
