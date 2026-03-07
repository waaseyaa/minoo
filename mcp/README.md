# Minoo Codified Context MCP Server

Exposes Minoo's spec documentation to Claude Code via stdio transport.

## Setup

```bash
cd mcp && npm install
```

Run once per clone before starting a Claude Code session.

## Tools

| Tool | Args | Description |
|---|---|---|
| `minoo_list_specs` | — | List all specs with names and descriptions |
| `minoo_get_spec` | `name: string` | Get full content of a spec (e.g. `"entity-model"`) |
| `minoo_search_specs` | `query: string` | Keyword search across all specs with context |

Registered as `"minoo"` in `.claude/settings.json`. Claude Code connects automatically at session start.
