<?php

declare(strict_types=1);

namespace App\Mcp;

use Waaseyaa\Mcp\Bridge\ToolExecutorInterface;

/**
 * Returns a structured MCP error for unknown tool names.
 */
final class MinooUnknownToolExecutor implements ToolExecutorInterface
{
    public function execute(string $toolName, array $arguments): array
    {
        return [
            'content' => [['type' => 'text', 'text' => "Unknown tool: {$toolName}"]],
            'isError' => true,
        ];
    }
}
