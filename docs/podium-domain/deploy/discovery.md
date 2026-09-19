# Discovery — Deploy

**Status**: Ready for Modeling
**Last session**: 2026-09-19
**Bounded Context**: Deploy — lleva una imagen a estado corriendo y verifica su salud; agota reintentos y decide cuándo un intento falló. Agnóstico a cómo se construyó la imagen
**Contexto heredado** (ya confirmado en `context-map.md`, sesión de arquitectura previa a este discovery): consume `ApplicationDeployRequested` (App Manager, al transicionar `Built → Deploying`); publica `DeploySucceeded`/`DeployFailed`. No puede existir un despliegue sin un build precedente exitoso. Invariante: un intento se marca fallido tras 3 reintentos de health-check fallidos. Deploy materializa el manifiesto CNPG cuando `podium.yaml` declara una base de datos (Provisioning solo declara el derecho/entitlement — ver `build/discovery.md`)

---

## Ubiquitous Language

| Term | Definition | Status |
|---|---|---|
| serviceName / projectId | Mismos conceptos materializados que en Build — llegan en el payload de `ApplicationDeployRequested`, sin referencia viva a Application | ✅ Confirmed |
| Health check | Verificación de que el servicio desplegado está corriendo y sano. La política de reintentos (3) vive en infraestructura, no en el dominio de Deploy (ver Open Ambiguities → resuelta) | ✅ Confirmed nombre — mecanismo de infra sin detallar hoy |

## Mecanismo de ejecución (infraestructura, no dominio — mismo split que Build, ver `build/discovery.md` revisión 2026-09-19)

**Revisión (simplifica el mecanismo original)**: no hay un segundo artefacto OCI de manifiestos por build — el plan de hackathon ya preveía un único Helm chart genérico (`podium-app`), publicado una sola vez, fuera del ciclo de cada build. `DeployAttempt` nunca llama a la API de Kubernetes: publica un evento pidiendo el despliegue con todo lo que ese chart genérico necesita como `values` (imagen, env vars, secrets por convención, declaración de base de datos si aplica). Un lanzador Go (mismo componente que el de Build, ampliado, o uno nuevo — decisión de infraestructura, diferida) solo hace `apply` de un manifiesto Kubernetes: el CR `Application` de ArgoCD (chart genérico + esos values). ArgoCD reconcilia y gestiona el `Deployment` real — la vigilancia de salud y sus reintentos (3, configuración de infraestructura) quedan en ArgoCD/el lanzador Go, no en Deploy. Al agotar reintentos o confirmar salud, algo (diferido) señaliza el resultado — traducido por `DeployAttempt` en sus propias acciones, igual que `JobSucceeded`/`JobFailed` en Build.

## Aggregate Candidates

| Name | Responsibilities | Invariants | Status |
|---|---|---|---|
| DeployAttempt | Un intento concreto de despliegue para un servicio, paralelo a `BuildJob` en Build. Decide y publica la petición de despliegue (values para el chart genérico de ArgoCD); traduce la señal de infraestructura final (salud confirmada / agotada) en `Succeeded`/`Failed` | Sin vida propia entre intentos, por simetría con `BuildJob` | ✅ Confirmed |

## Value Object Candidates

| Name | Construction Rule | Scope | Status |
|---|---|---|---|
| DeployValues | `{image, envVars, databaseDeclaration}` — el conjunto de valores que se le pasan al chart genérico de ArgoCD (`values`/`valuesObject`) para renderizar el `Deployment` real. Paralelo a `BuildYamlSnapshot` en Build | Local | 🆕 Proposed |

## Domain Events

| Name | Trigger | Payload | Status |
|---|---|---|---|
| `DeployAttemptRequested` | `DeployAttempt` se crea (`Pending`) | `deployAttemptId`, `DeployValues` (image, envVars, databaseDeclaration) | 🆕 Proposed — paralelo a `BuildJobRequested` |
| `DeploySucceeded` | Señal terminal de salud confirmada | `serviceName`, `projectId`, `version` | ✅ Confirmed nombre (ya en `context-map.md`) |
| `DeployFailed` | Señal terminal de reintentos agotados | `serviceName`, `projectId`, `version`, `errorMessage`, `retryCount` (informativo, si es fácil de reportar) | ✅ Confirmed nombre |

## Open Ambiguities

| Question | Context | Resolution |
|---|---|---|
| ¿Dónde vive el conteo de los 3 reintentos de health-check: lo cuenta el dominio (`DeployAttempt` recibe una señal por cada chequeo) o la infraestructura (una única señal terminal, como `JobSucceeded`/`JobFailed` en Build)? | DeployAttempt | ✅ Resuelto — **infraestructura cuenta** (interpretación B): el lanzador Go/la política de reintentos de Kubernetes decide cuándo agotó los 3 intentos y manda una única señal terminal. `DeployAttempt` puede guardar el número de intentos reportado como dato informativo (no como lógica que él mismo decide), si es fácil de incluir en el payload de la señal terminal — no es una invariante que el dominio proteja activamente contando mensaje a mensaje |

## Session Log

### 2026-09-19
- BC arrancado tras cerrar Build. Contexto heredado de `context-map.md`: responsabilidad, invariantes (3 reintentos, sin deploy sin build previo), y la materialización de CNPG ya confirmados en sesiones anteriores, sin discovery propio hasta ahora.
- Aggregate root confirmado: `DeployAttempt` (paralelo a `BuildJob`).
- Resuelta la ambigüedad del conteo de reintentos: infraestructura decide (interpretación B), `DeployAttempt` puede guardar el conteo reportado como dato informativo si es fácil, sin que sea el propio dominio quien lo cuente mensaje a mensaje.
- **Simplificación importante del mecanismo**: no hay una imagen OCI de manifiestos por build — el chart de Kubernetes es genérico y se publica una sola vez (ya estaba en el plan de hackathon, se había pasado por alto). `DeployAttempt` no produce ni gestiona manifiestos propios: solo junta los `values` (imagen, env vars, secrets, base de datos) que ese chart genérico necesita. El lanzador Go (diferido) reduce su trabajo a un solo `kubectl apply` del CR `Application` de ArgoCD — ArgoCD gestiona el `Deployment` real y su salud. VO `DeployValues` propuesto para ese conjunto de valores, paralelo a `BuildYamlSnapshot`.
- Eventos: `DeployAttemptRequested` (salida, paralelo a `BuildJobRequested`), `DeploySucceeded`/`DeployFailed` (salida, ya confirmados en `context-map.md`). Entrada: señal de infraestructura única al terminar (nombre pendiente de decidir al modelar, mismo patrón `JobSucceeded`/`JobFailed`).
- Checklist de cierre cumplido: aggregate confirmado, VO propuesto con regla de construcción, invariantes heredadas + resuelta la del conteo, eventos nombrados. Propuesto pasar a `/speckit.model`.
