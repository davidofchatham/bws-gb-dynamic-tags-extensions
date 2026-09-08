#!/bin/bash
# CLAUDE.md §Spec lifecycle: ".scratch/ is gitignored and nothing under it is
# committed." A plain `git add .scratch/...` is already a no-op against an
# ignored path — this only catches the -f/--force bypass.
INPUT=$(cat)
COMMAND=$(printf '%s' "$INPUT" | node -e '
let d="";process.stdin.on("data",c=>d+=c);
process.stdin.on("end",()=>{try{process.stdout.write(String(JSON.parse(d).tool_input.command||""))}catch(e){}});
')

if echo "$COMMAND" | grep -qE '\bgit\s+add\b' \
   && echo "$COMMAND" | grep -qE '(^|\s)(-f|--force)(\s|$)' \
   && echo "$COMMAND" | grep -q '\.scratch'; then
  echo "Blocked: .scratch/ is gitignored and nothing under it is ever committed (CLAUDE.md §Spec lifecycle). Force-adding defeats that on purpose." >&2
  exit 2
fi

exit 0
