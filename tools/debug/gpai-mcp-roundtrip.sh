#!/usr/bin/env bash
# GeneratePress AI exposure, MCP wire half. NOT shipped with the plugin.
#
# Pipes JSON-RPC to the MCP Adapter's STDIO server for GeneratePress AI on the testbed, calls
# `generatepress-ai-list-dynamic-tags`, and reports what arrives over the wire for our tags:
# empty descriptions, descriptions with angle brackets or backticks, and the `aiSyntax` count
# and bytes. Run from WSL (the wp-litespeed env lives there); set WPLS to move the env.
#
#   bash tools/debug/gpai-mcp-roundtrip.sh
#
# Prints a report; asserts nothing. Facts about GPAI itself: the shared reference's
# GeneratePress AI file.
set -euo pipefail
cd "${WPLS:-$HOME/wp-litespeed}"
docker compose run -T --rm --no-deps wpcli \
  --path=/var/www/vhosts/testbed/html mcp-adapter serve --server=generatepress-ai --user=1 2>/dev/null <<'JSONRPC' | python3 -c '
import sys, json
for l in sys.stdin:
    l = l.strip()
    if not l.startswith("{"): continue
    d = json.loads(l)
    if d.get("id") != 3: continue
    r = d["result"]
    if r.get("isError"): print("ERROR", json.dumps(d)[:400]); continue
    body = r.get("structuredContent") or json.loads(r["content"][0]["text"])
    tags = body.get("tags", body)
    base = set("text content title permalink image datetime_single datetime_range join email phone table call".split())
    ours = [t for t in tags if t["tag"] in base or t["tag"].startswith("try_")]
    n = b = 0
    for t in ours:
        for o in (t.get("options") or {}).values():
            if "aiSyntax" in o: n += 1; b += len(o["aiSyntax"].encode())
    print("wire: total tags", len(tags), "ours", len(ours), "bytes", len(l))
    print("empty descriptions:", [t["tag"] for t in ours if not t.get("description")])
    print("descriptions with < > or backtick:", [t["tag"] for t in ours if any(c in t.get("description", "") for c in "<>`")])
    print("aiSyntax over wire: %d options, %d bytes" % (n, b))
'
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"probe","version":"0"}}}
{"jsonrpc":"2.0","method":"notifications/initialized"}
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"generatepress-ai-list-dynamic-tags","arguments":{}}}
JSONRPC
