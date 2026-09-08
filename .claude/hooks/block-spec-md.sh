#!/bin/bash
# CLAUDE.md §Spec lifecycle: "The root SPEC.md artifact is RETIRED. Do not create one."
# Specs now live at .scratch/<feature-slug>/spec.md (lowercase) — this only
# targets a bare, case-sensitive root-level "SPEC.md" being written to.
INPUT=$(cat)
COMMAND=$(printf '%s' "$INPUT" | node -e '
let d="";process.stdin.on("data",c=>d+=c);
process.stdin.on("end",()=>{try{process.stdout.write(String(JSON.parse(d).tool_input.command||""))}catch(e){}});
')

# Neutralize "->" prose arrows (never valid shell redirection) and split on
# command separators so a verb/redirect in one clause can't pair with an
# unrelated "SPEC.md" mention in another (e.g. a commit message).
SEGMENTS=$(printf '%s' "$COMMAND" | sed -E 's/->/@@/g; s/(&&|\|\||;|\|)/\n/g')

BLOCKED=0
while IFS= read -r SEG; do
  if echo "$SEG" | grep -qE '(^|[^./A-Za-z0-9_-])SPEC\.md\b' \
     && echo "$SEG" | grep -qE '(>{1,2}|\btouch\b|\bcp\b|\bmv\b|\btee\b|\bNew-Item\b)'; then
    BLOCKED=1
  fi
done <<< "$SEGMENTS"

if [ "$BLOCKED" = "1" ]; then
  echo "Blocked: CLAUDE.md §Spec lifecycle retired the root SPEC.md artifact — do not recreate it. Specs live at .scratch/<feature-slug>/spec.md." >&2
  exit 2
fi

exit 0
