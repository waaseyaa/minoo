# Codified Context MCP Server Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build two file-backed MCP servers (one per repo) that expose `minoo_*` and `waaseyaa_*` spec retrieval tools to Claude Code via stdio transport.

**Architecture:** Each server is a single Node.js ES module using `@modelcontextprotocol/sdk`. It reads `.md` files from `docs/specs/` and exposes three tools: `list_specs`, `get_spec`, `search_specs`. Registered in each repo's `.claude/settings.json`.

**Tech Stack:** Node.js 20+, `@modelcontextprotocol/sdk` ^1.0.0, `zod` ^3.0.0, ES modules, stdio transport

---

## Phase 1: GitHub Issues

### Task 1: Create minoo issue

**Step 1: Create the issue**

```bash
gh issue create \
  --repo waaseyaa/minoo \
  --title "feat: codified context MCP server" \
  --body "Add a file-backed MCP server exposing minoo_list_specs, minoo_get_spec, and minoo_search_specs tools to Claude Code via stdio transport.

**Design doc:** docs/plans/2026-03-07-codified-context-mcp-server-design.md

**Deliverables:**
- mcp/server.js
- mcp/package.json
- mcp/README.md
- .claude/settings.json" \
  --milestone "v0.3"
```

**Step 2: Note the issue number** — you'll use it in PR titles as `feat(#N):`

---

### Task 2: Create framework issue

**Step 1: Create the issue**

```bash
gh issue create \
  --repo waaseyaa/framework \
  --title "feat: codified context MCP server" \
  --body "Add a file-backed MCP server exposing waaseyaa_list_specs, waaseyaa_get_spec, and waaseyaa_search_specs tools to Claude Code via stdio transport.

**Design doc:** docs/plans/2026-03-07-codified-context-mcp-server-design.md

**Deliverables:**
- mcp/server.js
- mcp/package.json
- mcp/README.md
- .claude/settings.json" \
  --milestone "v0.7"
```

**Step 2: Note the issue number**

---

## Phase 2: Minoo MCP Server

Working directory: `/home/fsd42/dev/minoo`

### Task 3: Create mcp/package.json (minoo)

**Files:**
- Create: `mcp/package.json`

**Step 1: Create the file**

```json
{
  "name": "minoo-context-mcp",
  "version": "1.0.0",
  "description": "Codified context MCP server for waaseyaa/minoo",
  "type": "module",
  "main": "server.js",
  "scripts": {
    "start": "node server.js"
  },
  "dependencies": {
    "@modelcontextprotocol/sdk": "^1.0.0",
    "zod": "^3.0.0"
  }
}
```

**Step 2: Install dependencies**

```bash
cd mcp && npm install
```

Expected: `node_modules/` created, `package-lock.json` generated.

**Step 3: Verify SDK is present**

```bash
ls mcp/node_modules/@modelcontextprotocol/sdk/
```

Expected: directory exists with `dist/` or `src/`.

**Step 4: Commit**

```bash
git add mcp/package.json mcp/package-lock.json
git commit -m "chore(#N): scaffold mcp package"
```

Replace `N` with the minoo issue number.

---

### Task 4: Create mcp/server.js (minoo)

**Files:**
- Create: `mcp/server.js`

**Step 1: Write the server**

```javascript
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';
import { readdir, readFile } from 'fs/promises';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SPECS_DIR = join(__dirname, '..', 'docs', 'specs');

const server = new McpServer({
  name: 'minoo',
  version: '1.0.0',
});

server.tool(
  'minoo_list_specs',
  'List all available Minoo specs with names and descriptions',
  {},
  async () => {
    const files = await readdir(SPECS_DIR);
    const mdFiles = files.filter(f => f.endsWith('.md')).sort();
    const specs = await Promise.all(
      mdFiles.map(async (file) => {
        const content = await readFile(join(SPECS_DIR, file), 'utf-8');
        const firstLine = content.split('\n').find(l => l.trim()) ?? '';
        const description = firstLine.replace(/^#+\s*/, '').trim();
        const name = file.replace(/\.md$/, '');
        return `${name}: ${description}`;
      })
    );
    return { content: [{ type: 'text', text: specs.join('\n') }] };
  }
);

server.tool(
  'minoo_get_spec',
  'Get the full content of a Minoo spec by name (without .md extension)',
  { name: z.string().describe('Spec name, e.g. "entity-model" or "search"') },
  async ({ name }) => {
    try {
      const content = await readFile(join(SPECS_DIR, `${name}.md`), 'utf-8');
      return { content: [{ type: 'text', text: content }] };
    } catch {
      return {
        content: [{ type: 'text', text: `Spec not found: ${name}. Use minoo_list_specs to see available specs.` }],
        isError: true,
      };
    }
  }
);

server.tool(
  'minoo_search_specs',
  'Search across all Minoo specs for a keyword (case-insensitive)',
  { query: z.string().describe('Search term') },
  async ({ query }) => {
    const files = await readdir(SPECS_DIR);
    const mdFiles = files.filter(f => f.endsWith('.md')).sort();
    const results = [];

    for (const file of mdFiles) {
      const content = await readFile(join(SPECS_DIR, file), 'utf-8');
      const lines = content.split('\n');
      const match = lines.find(l => l.toLowerCase().includes(query.toLowerCase()));
      if (match) {
        const name = file.replace(/\.md$/, '');
        results.push(`${name}:\n  ${match.trim()}`);
      }
    }

    const text = results.length
      ? results.join('\n\n')
      : `No specs matched "${query}". Use minoo_list_specs to see all available specs.`;

    return { content: [{ type: 'text', text: text }] };
  }
);

const transport = new StdioServerTransport();
await server.connect(transport);
```

**Step 2: Smoke test — list specs**

```bash
echo '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"minoo_list_specs","arguments":{}}}' \
  | node mcp/server.js
```

Expected: JSON response with all spec names listed.

**Step 3: Smoke test — get spec**

```bash
echo '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"minoo_get_spec","arguments":{"name":"search"}}}' \
  | node mcp/server.js
```

Expected: JSON response containing full content of `docs/specs/search.md`.

**Step 4: Smoke test — search**

```bash
echo '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"minoo_search_specs","arguments":{"query":"entity"}}}' \
  | node mcp/server.js
```

Expected: JSON response listing spec files containing "entity".

**Step 5: Smoke test — unknown spec returns error**

```bash
echo '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"minoo_get_spec","arguments":{"name":"does-not-exist"}}}' \
  | node mcp/server.js
```

Expected: JSON response with `isError: true` and helpful message.

**Step 6: Commit**

```bash
git add mcp/server.js
git commit -m "feat(#N): add minoo MCP server with list/get/search tools"
```

---

### Task 5: Create .claude/settings.json (minoo)

**Files:**
- Create: `.claude/settings.json`

**Step 1: Check if .claude/ directory exists**

```bash
ls .claude/ 2>/dev/null || echo "does not exist"
```

**Step 2: Create settings.json**

```json
{
  "mcpServers": {
    "minoo": {
      "command": "node",
      "args": ["mcp/server.js"]
    }
  }
}
```

**Step 3: Commit**

```bash
git add .claude/settings.json
git commit -m "chore(#N): register minoo MCP server in Claude Code settings"
```

---

### Task 6: Create mcp/README.md (minoo)

**Files:**
- Create: `mcp/README.md`

**Step 1: Write the README**

```markdown
# Minoo Codified Context MCP Server

File-backed MCP server that exposes Minoo's spec documentation to Claude Code.

## Setup

```bash
cd mcp/
npm install
```

Run once before starting a Claude Code session.

## Tools

| Tool | Arguments | Description |
|---|---|---|
| `minoo_list_specs` | — | List all specs with names and descriptions |
| `minoo_get_spec` | `name: string` | Get full content of a spec (e.g. `"entity-model"`) |
| `minoo_search_specs` | `query: string` | Keyword search across all specs |

## Registration

Registered in `.claude/settings.json` as the `"minoo"` MCP server.
Claude Code connects automatically at session start via stdio transport.

## Extending

Add new tools to `server.js` following the same pattern.
New spec files in `docs/specs/` are picked up automatically.
```

**Step 2: Commit**

```bash
git add mcp/README.md
git commit -m "docs(#N): add MCP server README"
```

---

### Task 7: Open minoo PR

**Step 1: Push branch and open PR**

```bash
git push -u origin HEAD
gh pr create \
  --title "feat(#N): add codified context MCP server" \
  --body "$(cat <<'EOF'
## Summary

- Adds `mcp/server.js` with `minoo_list_specs`, `minoo_get_spec`, `minoo_search_specs` tools
- Registers server in `.claude/settings.json` under key `"minoo"`
- Uses `@modelcontextprotocol/sdk` with stdio transport

Closes #N

## Test plan

- [ ] `cd mcp && npm install` completes without errors
- [ ] Smoke test: `minoo_list_specs` returns all spec names
- [ ] Smoke test: `minoo_get_spec` returns full spec content
- [ ] Smoke test: `minoo_search_specs` returns matching file names
- [ ] Unknown spec name returns `isError: true`
- [ ] Claude Code session shows `minoo` server connected

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Phase 3: Framework MCP Server

Working directory: `/home/fsd42/dev/waaseyaa`

### Task 8: Create mcp/package.json (framework)

**Files:**
- Create: `mcp/package.json`

**Step 1: Create the file**

```json
{
  "name": "waaseyaa-context-mcp",
  "version": "1.0.0",
  "description": "Codified context MCP server for waaseyaa/framework",
  "type": "module",
  "main": "server.js",
  "scripts": {
    "start": "node server.js"
  },
  "dependencies": {
    "@modelcontextprotocol/sdk": "^1.0.0",
    "zod": "^3.0.0"
  }
}
```

**Step 2: Install**

```bash
cd mcp && npm install
```

**Step 3: Commit**

```bash
git add mcp/package.json mcp/package-lock.json
git commit -m "chore(#N): scaffold mcp package"
```

Replace `N` with the framework issue number.

---

### Task 9: Create mcp/server.js (framework)

**Files:**
- Create: `mcp/server.js`

**Step 1: Write the server** — identical to minoo's server.js with two substitutions:

- `name: 'minoo'` → `name: 'waaseyaa'`
- All `minoo_` prefixes in tool names → `waaseyaa_`
- All `"Minoo"` in tool descriptions → `"Waaseyaa"`

Full file:

```javascript
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';
import { readdir, readFile } from 'fs/promises';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SPECS_DIR = join(__dirname, '..', 'docs', 'specs');

const server = new McpServer({
  name: 'waaseyaa',
  version: '1.0.0',
});

server.tool(
  'waaseyaa_list_specs',
  'List all available Waaseyaa framework specs with names and descriptions',
  {},
  async () => {
    const files = await readdir(SPECS_DIR);
    const mdFiles = files.filter(f => f.endsWith('.md')).sort();
    const specs = await Promise.all(
      mdFiles.map(async (file) => {
        const content = await readFile(join(SPECS_DIR, file), 'utf-8');
        const firstLine = content.split('\n').find(l => l.trim()) ?? '';
        const description = firstLine.replace(/^#+\s*/, '').trim();
        const name = file.replace(/\.md$/, '');
        return `${name}: ${description}`;
      })
    );
    return { content: [{ type: 'text', text: specs.join('\n') }] };
  }
);

server.tool(
  'waaseyaa_get_spec',
  'Get the full content of a Waaseyaa framework spec by name (without .md extension)',
  { name: z.string().describe('Spec name, e.g. "entity-system" or "access-control"') },
  async ({ name }) => {
    try {
      const content = await readFile(join(SPECS_DIR, `${name}.md`), 'utf-8');
      return { content: [{ type: 'text', text: content }] };
    } catch {
      return {
        content: [{ type: 'text', text: `Spec not found: ${name}. Use waaseyaa_list_specs to see available specs.` }],
        isError: true,
      };
    }
  }
);

server.tool(
  'waaseyaa_search_specs',
  'Search across all Waaseyaa framework specs for a keyword (case-insensitive)',
  { query: z.string().describe('Search term') },
  async ({ query }) => {
    const files = await readdir(SPECS_DIR);
    const mdFiles = files.filter(f => f.endsWith('.md')).sort();
    const results = [];

    for (const file of mdFiles) {
      const content = await readFile(join(SPECS_DIR, file), 'utf-8');
      const lines = content.split('\n');
      const match = lines.find(l => l.toLowerCase().includes(query.toLowerCase()));
      if (match) {
        const name = file.replace(/\.md$/, '');
        results.push(`${name}:\n  ${match.trim()}`);
      }
    }

    const text = results.length
      ? results.join('\n\n')
      : `No specs matched "${query}". Use waaseyaa_list_specs to see all available specs.`;

    return { content: [{ type: 'text', text: text }] };
  }
);

const transport = new StdioServerTransport();
await server.connect(transport);
```

**Step 2: Run the same four smoke tests as minoo (Task 4 Steps 2–5)**, substituting `waaseyaa_` tool names and the framework's working directory.

**Step 3: Commit**

```bash
git add mcp/server.js
git commit -m "feat(#N): add waaseyaa MCP server with list/get/search tools"
```

---

### Task 10: Create .claude/settings.json (framework)

**Files:**
- Create: `.claude/settings.json`

**Step 1: Check for existing file**

```bash
ls /home/fsd42/dev/waaseyaa/.claude/settings.json 2>/dev/null || echo "does not exist"
```

**Step 2: Create or update settings.json**

```json
{
  "mcpServers": {
    "waaseyaa": {
      "command": "node",
      "args": ["mcp/server.js"]
    }
  }
}
```

**Step 3: Commit**

```bash
git add .claude/settings.json
git commit -m "chore(#N): register waaseyaa MCP server in Claude Code settings"
```

---

### Task 11: Create mcp/README.md (framework)

**Step 1: Write the README** — identical to minoo's README with substitutions:

- `Minoo` → `Waaseyaa`
- `minoo_` → `waaseyaa_`
- `"minoo"` MCP server key → `"waaseyaa"`
- Example name: `"entity-model"` → `"entity-system"`

**Step 2: Commit**

```bash
git add mcp/README.md
git commit -m "docs(#N): add MCP server README"
```

---

### Task 12: Open framework PR

```bash
git push -u origin HEAD
gh pr create \
  --title "feat(#N): add codified context MCP server" \
  --body "$(cat <<'EOF'
## Summary

- Adds `mcp/server.js` with `waaseyaa_list_specs`, `waaseyaa_get_spec`, `waaseyaa_search_specs` tools
- Registers server in `.claude/settings.json` under key `"waaseyaa"`
- Uses `@modelcontextprotocol/sdk` with stdio transport

Closes #N

## Test plan

- [ ] `cd mcp && npm install` completes without errors
- [ ] Smoke test: `waaseyaa_list_specs` returns all 30+ spec names
- [ ] Smoke test: `waaseyaa_get_spec` with `"entity-system"` returns full content
- [ ] Smoke test: `waaseyaa_search_specs` with `"EntityAccessHandler"` returns matches
- [ ] Unknown spec name returns `isError: true`
- [ ] Claude Code session shows `waaseyaa` server connected

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Notes

- `node_modules/` in `mcp/` should be added to `.gitignore` if not already present — check before committing
- The smoke tests pipe raw JSON-RPC directly to the server process; this works because stdio transport reads from stdin
- Both servers are stateless — no restart needed after spec file changes
- `.claude/settings.json` paths are relative to the repo root where Claude Code is opened
