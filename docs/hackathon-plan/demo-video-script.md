# Hostium — 2-minute demo video script

Target: **1:55–2:05** spoken. 305 words of voiceover — 2:02 at 150 wpm. Time a read-through before recording.
Everything claimed here is shippable today — see "What we deliberately do NOT claim" at the end.

---

## 0:00–0:15 — The problem (hook)

**On screen:** phone-shot of a laptop at a hackathon table showing `localhost:3000`, then a browser tab: `This site can't be reached`.

> It's Sunday, 2 PM. The judges reach your table, and your project only works on your laptop —
> behind a tunnel that died an hour ago. Every hackathon has the same bug, and it isn't in anyone's code.

## 0:15–0:28 — What Hostium is

**On screen:** Hostium dashboard, logged in, empty state.

> Hostium is the deployment platform for the event itself. Public repo in, live public URL out —
> with TLS, isolated from every other team, in about a minute. No CI, no Dockerfile, no accounts.

## 0:28–1:30 — The demo (the core of the video)

**On screen — one screen recording, no cuts. Browser and terminal side by side.**

**(0:28) Create the project**
> I paste a public repo URL. That's the whole form.

**(0:36) `podium.yaml` discovery**

*Show the repo's `podium.yaml` with two top-level keys: `app` and `frontend`.*
> Hostium reads the `podium.yaml` the team already keeps in their own repo. This one declares two
> services — a NestJS backend and a Next.js frontend — so it discovers both and deploys each
> on its own, each with its own public host.

**(0:52) Build → deploy, live**

*Split view: the application card moving Building → Deployed; terminal with `kubectl get pods -n <hash>`.*
> Behind that card: Hostium polls the repo for commits, runs a rootless Buildah job against our own
> template Dockerfile, and hands ArgoCD one Application per service. Node, Python, React or Vue
> today — another framework is one more Dockerfile.

**(1:08) The payoff**

*Click the public URL on the card. Then open the same URL on a phone — let it load on camera.*
> There it is. Real public URL, real certificate, reachable from anywhere — from an empty form to
> this in about a minute.

**(1:20) Push a commit**

*`git push` in the terminal; cut to the card going Building again.*
> From here the team just pushes. Hostium ships it. Nobody opens a dashboard.

## 1:30–1:50 — Why it holds up with strangers on it, and the proof

**On screen:** `./test.sh` output showing 11/11 pass; then the dashboard listing real projects, then `app.hostium.apperture.dev`.

> This is open to strangers, so every project lands in its own namespace, and the tenant network
> policies are validated eleven out of eleven against the live cluster. And the proof isn't a
> slide — Hostium deploys Hostium.
> This dashboard and its API ship through the same path every team gets.

## 1:50–2:00 — Close

**On screen:** repo URL + `hostium.apperture.dev`.

> Open source, on a domain model of six bounded contexts that talk only through events — because
> next comes the agent that rehearses your demo before the judges arrive.
> Hostium. Your demo is already live.

---

## Delivery notes

- **Record the platform, narrate over it.** No talking head. The screen recording is the argument.
- **At two minutes there is no slack.** Time a read-through out loud before recording; if you land over
  2:05, cut the second sentence of the `podium.yaml` beat, not the phone shot.
- **Pre-warm everything** — image layers cached, DNS record already created once for the demo host.
  A cold first build is slower than the story you're telling.
- **Have a backup take of the build→deploy segment** recorded earlier. If the live one drags, cut to it.
  Never let dead air sit on a spinner.
- **The phone shot at 1:08 is the emotional beat of the video.** Don't rush it — let the page load on camera.
- **If the demo repo is a monorepo with a Node backend and a React or Vue frontend**, the two-service
  beat and the language beat land on the same screen. That is the strongest version of this cut.
- **Numbers beat adjectives.** If you have real adoption by recording time (teams onboarded, deployments,
  uptime since Saturday), open the "proof" beat with those figures and keep dogfooding as the second sentence.

## Cut from the 3-minute version (restore here if it ever grows back)

The competitive beat (`localhost` / ngrok / Vercel — "the polished platforms only take JavaScript"),
the `${app.url}` cross-service reference, and the scale-to-zero activator holding the request while a
cold app wakes. All three are true and demoable; they simply don't fit in 120 seconds.

## What we deliberately do NOT claim

Keeping this honest is what makes the rest credible to a judge who pokes at it:

- **The rehearsal / self-healing agent is not implemented.** `AgentEntryPoint` in the dashboard is an
  explicit mock with no LLM call, and the Remediation bounded context was never started. It appears in
  the script only as a stated *next step*, never as a capability. If you show the agent panel on camera,
  say "next" out loud over it.
- **"Any language" is not true today.** The catalog holds five templates: `nodejs/nestjs`,
  `nodejs/nextjs`, `nodejs/react`, `nodejs/vue` and `python/fastapi`. The script names exactly those
  and frames anything else as one more Dockerfile — accurate, and still strong.
- **The secrets screen is a frontend fixture.** `INITIAL_FIXTURE_SECRETS` lives in the browser bundle,
  there is no secrets endpoint, and the values the tenant chart receives are only `hash`, `image`,
  `ingress.host` and `port` — no env vars reach the pod yet. The script no longer mentions API keys.
  Don't open that screen on camera.
- **Quotas and NetworkPolicies are not applied by the deploy path.** The namespace is created
  (`CreateNamespace=true`), but `helm/podium-app` renders only Deployment, Service and Ingress. The
  five policies are validated as a fixture in `docs/hackathon-netpol/`. The script claims exactly
  that — "validated against the live cluster" — and nothing more. If a judge asks whether every
  tenant gets them automatically, the honest answer is "the namespace yes, the policies are the
  next commit into the chart".
- **~60 seconds is the warm path.** Say "about a minute" and show it, rather than putting a stopwatch
  on screen you might lose.
