<?php

use App\Mcp\Servers\GarazServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Server Routes
|--------------------------------------------------------------------------
|
| Garáž server exposes the user vehicles + knowledge notes to MCP-capable
| AI clients (Claude Desktop, Claude Code, Cursor) so they can ground
| answers in the user's actual data.
|
| Wire authentication via OAuth in config/mcp.php redirect_domains before
| pointing a real client at this endpoint.
|
*/
Mcp::web('/mcp/garaz', GarazServer::class);
