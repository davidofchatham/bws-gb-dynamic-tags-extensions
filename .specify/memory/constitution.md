<!--
  SYNC IMPACT REPORT
  ==================
  Version change: 1.0.0 → 2.0.0

  MAJOR bump rationale: All five principles redefined in focus (tech-stack-centric
  → UX/code-quality/testing-centric). "Technical Constraints" section removed.
  Backward-incompatible governance change: agents following v1.0.0 would apply
  different compliance gates than those following v2.0.0.

  Modified principles:
    I.   Framework Integration Integrity → Predictable Tag Behavior
    II.  Clean Tag Naming               → Intuitive, Conflict-Free Tag Names
    III. Option Defaults Minimalism     → Clean, Stable Tag Configuration
    IV.  Graceful Degradation           → Code Quality & Simplicity
    V.   Simplicity & YAGNI             → Validation Before Shipping

  Added sections: N/A

  Removed sections:
    - Technical Constraints (tech-stack specifics belong in CLAUDE.md, not
      constitutional governance)

  Templates requiring updates:
    ✅ .specify/templates/plan-template.md
       — Constitution Check section is generic; no project-specific gates to add.
    ✅ .specify/templates/spec-template.md
       — No mandatory sections added or removed by this amendment.
    ✅ .specify/templates/tasks-template.md
       — No new principle-driven task categories introduced. Test tasks remain
         optional (Principle V addresses manual validation, not task generation).
    ✅ .specify/templates/agent-file-template.md
       — No outdated references; template is generically structured.

  No `.specify/templates/commands/` directory exists — no command files to check.

  Follow-up TODOs: None.
-->

# BWS GB Dynamic Tags Extensions Constitution

## Core Principles

### I. Predictable Tag Behavior

Every tag MUST return a usable value or an empty string. Tags MUST never produce
warnings, errors, or raw exception output visible to site visitors or stored in
rendered content. When a data source is unavailable or a field is empty, the tag
MUST follow its defined fallback chain and degrade silently to the next available
value.

Output MUST be deterministic: given the same post context and options, a tag MUST
always return the same result.

**Rationale**: Pages built with these tags may be served to thousands of readers.
Unpredictable output — even a PHP notice — breaks content and erodes trust in the
plugin.

### II. Intuitive, Conflict-Free Tag Names

Tag names MUST be self-describing and source-prefixed so site builders can identify
what data a tag returns without consulting documentation. Names MUST describe the
data shape rather than the field plugin providing it, ensuring names remain valid
regardless of which field framework is active.

Tag names MUST NOT conflict with host framework built-ins. When a conflict risk
exists due to shared-prefix resolution rules, the naming convention MUST resolve it
proactively, before registration.

When a tag name is retired, a compatibility wrapper MUST preserve backward
compatibility for at least one major plugin version so that existing content is not
silently broken.

**Rationale**: A site builder choosing a tag in the editor should immediately
understand what it returns. Naming ambiguity creates misused tags and silent content
failures that are hard to diagnose.

### III. Clean, Stable Tag Configuration

Tag options MUST produce the smallest possible serialized footprint. Optional
options MUST use an empty-string default so that unchanged settings are not written
into stored content. Tag strings stored in post content MUST remain readable and
predictable across plugin updates.

Renaming an existing option key is a breaking change and requires a MAJOR version
bump with a documented migration path.

**Rationale**: Tag configurations are embedded in post content. Cluttered or
unstable tag strings silently corrupt content after updates and make troubleshooting
in the editor unnecessarily difficult.

### IV. Code Quality & Simplicity

Code MUST solve the problem at hand without over-engineering. New abstractions are
justified only when the same pattern recurs across three or more distinct concrete
use sites; single-use logic MUST remain inline. No new tooling or runtime
dependencies may be introduced without explicit justification.

All new code MUST follow the established patterns already present in the codebase.
Deliberate divergence from existing patterns MUST be explained in a code comment.

**Rationale**: This plugin operates without an automated test suite. Simplicity is
the primary structural defense against regressions, and consistency lowers the
cognitive overhead of reviewing and maintaining changes.

### V. Validation Before Shipping

Every code change MUST be manually validated in a live WordPress environment with
all runtime dependencies active before it is committed. Validation MUST confirm:

1. The affected tag returns the expected value in a rendered block.
2. The tag degrades gracefully when its data source is absent or its field is empty.
3. No warnings or errors appear in the site's debug log during tag resolution.
4. The tag string stored in post content matches the expected format (no spurious
   option keys introduced by the change).

For changes that affect tag option keys or tag names, validation MUST also include
confirming that existing content using the old key/name still resolves correctly.

**Rationale**: Manual validation is the only quality gate between a code change and
live content. Skipping any step risks silent content failures that are difficult to
attribute and costly to remediate.

## Development Workflow

Changes to this plugin are made by directly editing PHP files. There is no build
step or automated test suite.

**Deprecated tags**: When a tag name or option key is retired, a compatibility
wrapper MUST be registered for at least one MAJOR version before removal, or until
the developer explicitly specifies removal. See Principle II.

**New tag checklist** (MUST verify before committing any new or modified tag):
1. Tag name is self-describing, source-prefixed, and conflict-free (Principle II).
2. Tag name is framework-agnostic — describes data shape, not the field plugin
   (Principle II).
3. All optional options use empty-string defaults (Principle III).
4. Callback implements the full fallback chain to empty string (Principle I).
5. Optional dependency calls are guarded against missing dependencies (Principle I).
6. Tag type matches the data it surfaces — image tags use the media type
   (Principle I).
7. Validation steps from Principle V are completed before committing.

**Commits**: Commits SHOULD reference the tag name(s) affected and the version bump
(e.g., `feat: add term_custom_image tag (v4.3.0)`).

## Governance

This constitution supersedes all other development guidance for this project. Where
the constitution conflicts with CLAUDE.md, the constitution governs technical
decisions; CLAUDE.md governs interaction and workflow preferences.

**Amendments**: Any change to a principle requires updating this file, incrementing
the version per the policy below, and updating the Sync Impact Report at the top.

**Version policy**:
- MAJOR: principle removals, renames, or backward-incompatible redefinitions.
- MINOR: new principles or materially expanded guidance added.
- PATCH: clarifications, wording refinements, typo fixes.

**Compliance review**: Every new feature plan (`speckit.plan`) MUST include a
Constitution Check section that verifies compliance with Principles I–V before
Phase 0 research proceeds.

**Runtime guidance**: For session-specific patterns and project memory, see
`.claude/projects/.../memory/MEMORY.md` and `CLAUDE.md`.

---

**Version**: 2.0.0 | **Ratified**: 2026-02-23 | **Last Amended**: 2026-02-23
