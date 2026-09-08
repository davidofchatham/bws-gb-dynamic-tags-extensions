#!/bin/bash
# Advisory only — never blocks. CLAUDE.md §Update triggers: touching a
# trigger's files means running its harness before the change lands. The
# trigger→harness map is large and lives in CLAUDE.md / docs/update-triggers.md
# (owned there, not duplicated here) — this just points at it with the files
# actually staged.
INPUT=$(cat)
COMMAND=$(printf '%s' "$INPUT" | node -e '
let d="";process.stdin.on("data",c=>d+=c);
process.stdin.on("end",()=>{try{process.stdout.write(String(JSON.parse(d).tool_input.command||""))}catch(e){}});
')

if ! echo "$COMMAND" | grep -qE '\bgit\s+commit\b'; then
  exit 0
fi

STAGED=$(git diff --cached --name-only 2>/dev/null)

if [ -n "$STAGED" ]; then
  echo "Reminder: check CLAUDE.md §Update triggers / docs/update-triggers.md for these staged files — run the matching test harness before this lands:" >&2
  echo "$STAGED" >&2
fi

exit 0
