<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use Waaseyaa\AI\Tools\AgentTool;
use Waaseyaa\Mcp\Bridge\ToolRegistryInterface;

/**
 * Explicit no-op MCP tool registry until Minoo registers real tools.
 *
 * Updated for alpha.181 (#1496): the McpToolDefinition VO was replaced by
 * Waaseyaa\AI\Tools\AgentTool. The registry surface now returns AgentTool.
 */
final class MinooNoopToolRegistry implements ToolRegistryInterface
{
    public function getTools(): array
    {
        return [];
    }

    public function getTool(string $name): ?AgentTool
    {
        return null;
    }
}
