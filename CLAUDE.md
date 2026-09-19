# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repo status

**This repo currently has no application code.** It holds domain discovery documentation and hackathon planning for Podium, generated with a `/speckit.bc`-style discovery process (conversational interrogation, one Bounded Context at a time). Implementation is deliberately deferred until the HackBarna AI Summit 26 hackathon (2026-09-19/20) — see `docs/hackathon-plan/plan-hackbarna-2026.md` for why ("el código del proyecto se escribe durante el evento").

Because there's no code yet, there are no build/lint/test commands. The one runnable artifact is the NetworkPolicy validation script:

```bash
cd docs/hackathon-netpol
./test.sh    # validates tenant NetworkPolicy isolation against a live k8s cluster (kubectl context required)
```

When application code lands, update this file with real build/test/lint commands rather than leaving this section stale.

## What Podium is

The deployment platform for the hackathon itself: any public repo, any language, live public URL in ~60s, plus an agent that rehearses a team's demo against the running app and self-heals fixable failures (missing env var, wrong port, OOM) before the judges arrive. Full product framing, jury angle, sponsor fit, timeline, and infra checklist are in `docs/hackathon-plan/plan-hackbarna-2026.md`.

## Where the domain model lives

Start at `docs/podium-domain/context-map.md` — the six confirmed Bounded Contexts, cross-context events, captured invariants, and the modeling conventions that recur everywhere. Each BC then has its own folder with two documents that serve different purposes:

- **`discovery.md`** — the process. Ubiquitous language, aggregate candidates, and a chronological **Session Log** with the *why* behind each decision (why a field isn't configurable, why something isn't its own aggregate, etc.). If a modeling choice looks surprising, the reasoning is here, not in `model.md`.
- **`model.md`** — the formal artifact. Mermaid class diagram, responsibility table, published/consumed events. What you'd hand to someone who doesn't need the full history.

Bounded contexts and their state:

| BC | Folder | Owns |
|---|---|---|
| Project | `project/` | The `hash` that composes each service's public URL; discovers services declared in a repo's `podium.yaml`; fans out source changes per service |
| AppSource | `appsource/` | Where a Project's code comes from and whether it changed (provider-agnostic — GitHub today, one adapter behind a generic port) |
| App Manager | `app-manager/` | `Application` (one deployable service inside a Project) — the **central orchestrator** of the build→deploy cycle; also holds read-only reflections of Build/Deploy state, never their rules |
| Build | `build/` | `Template` catalog (language/framework, param schema, job image) and `BuildJob` (one build attempt) |
| Deploy | not yet started | Bringing an image to a running, healthy state; exhausts retries and decides when an attempt has failed |
| Remediation | not yet started | Reacts to `BuildFailed` / exhausted health checks / runtime errors; team-owned code gets a passive dashboard fix, platform-owned code gets an autonomous PR against Podium's own repo |

`Provisioning` and `Notification` are confirmed in the context map but have no discovery session yet — their scope is inferred from references in the closed BCs.

## Modeling conventions that apply everywhere

These are captured in full in `context-map.md`; the ones that matter most when extending any BC:

- **Cross-BC communication is always via published domain events, never direct calls or queries.** App Manager is the orchestrator of build→deploy specifically *because* AppSource, Build, and Deploy are never allowed to call each other directly.
- **Every aggregate has its own technical `id` (UUID)**, separate from whatever business identifier also identifies it to users (`serviceName`, `hash`). The `id` never appears in the ubiquitous language; the business identifier does.
- **Same term, different BC is not a collision.** E.g. "Build" as a BC (real construction rules) vs. `Build` as a read-only reflection inside App Manager — document which is which, never merge them just because they share a name.
- **A concept with no decision-making behavior of its own is not a BC** — it's just a data shape inside the aggregate that already owns it (e.g. env vars were rejected as their own BC; they're just fields Build's `BuildJob` validates).
- **If the team already keeps a piece of data in their own repo (`podium.yaml`), Podium reads it — it never owns or duplicates it** (e.g. build/deploy env vars, database declarations).
- **A read-only materialized copy is not the same as duplicating a truth.** Duplication is bad when there are two write paths that can drift (avoided). Copying a value from another aggregate for fast reads is fine when there's exactly one owner writing it, fixed at a known moment (e.g. `Application.teamId` is copied from `Project` at registration; `Project` remains the only writer).
- **No provider rules inside the domain.** When a BC depends on an external system with variants (GitHub vs. GitLab), the domain models it generically behind a single port; provider differences live entirely in adapters.

## Infrastructure notes (outside the domain model, but load-bearing)

- `docs/hackathon-netpol/` — the per-tenant `NetworkPolicy` set (namespace isolation for hackathon participants' deployed apps), validated 11/11 via `test.sh`. `01-tenant-test.yaml` is the tenant policy fixture; `02-protect-monitoring.yaml` locks down the monitoring namespace from tenant egress; `03-activator-netpol.yaml` is the (optional, stretch-goal) activator's own policy — `test.sh` picks it up automatically if `activator-system` exists, otherwise skips those assertions without failing.
- Planned deploy path (per the hackathon plan, not yet built): no git-based GitOps — a generic Helm chart (`podium-app`) pushed once to an OCI registry, then one ArgoCD `Application` CR per team (`kubectl apply`, `helm.valuesObject` per tenant) for create/update/rollback. A custom poller (not GitHub webhooks) watches tracked repos every ~1 min for new commits, using `Application` annotations as state instead of a separate database.
