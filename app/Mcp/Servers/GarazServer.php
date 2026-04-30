<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\VehicleProfileResource;
use App\Mcp\Tools\ListVehiclesTool;
use App\Mcp\Tools\SearchKnowledgeTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Garáž')]
#[Version('0.1.0')]
#[Instructions(<<<'TXT'
Vehicle maintenance assistant for Synapps Garáž.

Available tools:
  list_vehicles        — list the user's vehicles with type, make/model, current km
  search_knowledge     — search the user's KnowledgeNote store (forum posts, FB tips, manual notes)

Available resources:
  garaz://vehicle/{id} — full vehicle profile (specs, recent service history, expiring documents)

Use this server to ground answers about a specific vehicle in the user's actual data — service history, mileage, community notes — instead of generic make/model knowledge.

Authorization: all tools currently scope to user_id from the authenticated MCP session. Configure OAuth via routes/ai.php and config/mcp.php before exposing externally.
TXT)]
class GarazServer extends Server
{
    protected array $tools = [
        ListVehiclesTool::class,
        SearchKnowledgeTool::class,
    ];

    protected array $resources = [
        VehicleProfileResource::class,
    ];

    protected array $prompts = [
        //
    ];
}
