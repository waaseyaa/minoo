<?php

declare(strict_types=1);

namespace App\Mcp;

use Waaseyaa\AI\Schema\Mcp\McpToolDefinition;
use Waaseyaa\Mcp\Bridge\ToolRegistryInterface;

/**
 * Explicit no-op MCP tool registry until Minoo registers real tools.
 */
final class MinooNoopToolRegistry implements ToolRegistryInterface
{
    public function getTools(): array
    {
        return [];
    }

    public function getTool(string $name): ?McpToolDefinition
    {
        return null;
    }
}
