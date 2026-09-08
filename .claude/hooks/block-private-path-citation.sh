#!/bin/bash
# CLAUDE.md §Cross-link rules: "SHIPPED CODE CITES IDS, NOT PRIVATE PATHS."
# includes/, assets/, docs/adr/, README.md and CHANGELOG.md must never gain a
# reference to a .scratch/ or .claude/ path — those are unreadable to anyone
# but the author. Blocks `git commit` if the staged diff adds one there.
INPUT=$(cat)
COMMAND=$(printf '%s' "$INPUT" | node -e '
let d="";process.stdin.on("data",c=>d+=c);
process.stdin.on("end",()=>{try{process.stdout.write(String(JSON.parse(d).tool_input.command||""))}catch(e){}});
')

if ! echo "$COMMAND" | grep -qE '\bgit\s+commit\b'; then
  exit 0
fi

HITS=$(git diff --cached -U0 -- includes assets docs/adr README.md CHANGELOG.md 2>/dev/null \
  | grep -E '^\+[^+]' \
  | grep -E '\.scratch/|\.claude/')

if [ -n "$HITS" ]; then
  echo "Blocked: staged diff cites a .scratch/ or .claude/ path inside shipped code/docs (CLAUDE.md §Cross-link rules — shipped code cites IDs, not private paths)." >&2
  echo "$HITS" >&2
  exit 2
fi

exit 0
