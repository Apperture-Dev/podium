# Discovery — Context Map (Podium)

**Status**: In Progress
**Last session**: 2026-09-12
**Feature context**: HackBarna AI Summit 26 — Podium (plataforma de despliegue + agente de ensayo)

---

## Modeling Conventions

- **Mismo término, contextos distintos no es un error.** Cuando dos BCs usan la misma palabra (p. ej. "Build"), es señal de que el lenguaje entre contextos vecinos está cerca, pero identifican partes diferentes del sistema. Se documenta explícitamente cuál es cuál — nunca se fusionan solo porque comparten nombre.
- **Sin reglas de proveedor dentro del dominio.** Cuando un contexto depende de un sistema externo con variantes (GitHub vs GitLab, etc.), el dominio se modela genérico y agnóstico; toda la diferencia de proveedor vive detrás de un único puerto, con un adaptador por proveedor. Hoy: un solo adaptador (público). Mañana, un segundo proveedor es un adaptador nuevo, no un cambio de dominio.
- **Comunicación entre BCs siempre vía eventos publicados, nunca llamadas directas.** Toda acción de dominio que necesite que otro BC actúe debe publicar un evento — es la única forma de comunicación entre contextos. Consecuencia: **App Manager es el orquestador central** del ciclo build→deploy, no un reflejo pasivo. AppSource y Build nunca se hablan directamente entre sí; Application decide y publica cuándo cada fase debe actuar.
- **Todo aggregate lleva su propio `id` (UUID)**, identidad técnica interna, separada de cualquier atributo de negocio que también identifique al aggregate de cara al usuario (p. ej. `appHash` como slug de la URL pública). El `id` nunca se expone como parte del lenguaje ubicuo de negocio; `appHash` sí.
- **Un concepto sin comportamiento propio no es un BC — es un tipo de dato dentro de otro aggregate.** Antes de dar a algo su propio bounded context, la pregunta es: ¿qué *decisión* tomaría ese BC que el aggregate que ya lo contiene no pueda tomar por sí solo? Si la respuesta es "ninguna, solo guarda y valida forma," no hace falta el BC (caso: env vars, descartadas como BC propio — son solo datos dentro del yaml que `BuildJob` valida).
- **Cuando el equipo/usuario ya mantiene un dato fuera de Podium (su propio repo), Podium lo lee — nunca lo posee ni lo duplica.** Evita el problema de dos dueños de la misma verdad (caso: env vars de build y deploy, ambas leídas del yaml del repo en vez de vivir duplicadas en dos BCs distintos).
- **Una copia materializada de solo lectura no es lo mismo que duplicar una verdad.** Duplicar es malo cuando hay dos caminos de escritura que pueden desincronizarse (caso: `NODE_ENV` en build y en runtime). Copiar un dato de otro aggregate para consulta rápida es correcto cuando hay **un solo dueño** que lo escribe y todos los demás solo lo leen, fijado en un momento conocido (caso: `Application.teamId`, copiado de `Project` al registrar — `Project` sigue siendo el único que lo cambia).

---

## Confirmed Bounded Contexts

| Name | Core Responsibility (según el arquitecto) | Status |
|---|---|---|
| **Build** | Decide y ejecuta cómo se construye la imagen: plantillas, lenguajes, sus propias reglas de construcción. No sabe nada de salud ni de despliegue | ✅ Confirmed |
| **Deploy** | Llevar una imagen a estado corriendo y verificar su salud; agota reintentos y decide cuándo un intento está fallido | ✅ Confirmed |
| **App Manager** | La aplicación frontal para los usuarios — muestra el estado de cada fase sin exponer los internals (pods de build, de la app). Agregados propios: `Application`, `Build` (reflejo de estado: éxito/fallo/SHA, no las reglas de construir), `Deployment` (reflejo de estado, no las reglas de desplegar) | ✅ Confirmed |
| **Notification** | Avisa. Nada más — no decide, no diagnostica | ✅ Confirmed |
| **Remediation** | Agnóstico al estado de la aplicación — es una *reacción* con reglas propias, fuera del modelo de estado de App Manager. Se dispara por: `BuildFailed`, salud agotada en Deploy, o un error en tiempo de ejecución (p. ej. 500) | ✅ Confirmed — independiente de App Manager |
| **AppSource** | De dónde viene el código y si cambió. Dominio agnóstico al proveedor (GitHub/GitLab/etc.) — toda la diferencia vive detrás de un único puerto, un solo adaptador por ahora (repos públicos) | ✅ Confirmed |
| **Project** | Aglutina las Applications (servicios) declaradas en el `podium.yaml` de un repo. Dueño del `hash` público — compone la URL de cada servicio. Descubre servicios nuevos y reparte los cambios de fuente por servicio | ✅ Confirmed — ver `project/discovery.md` |

## Still-Proposed (sin tocar en esta sesión)

| Name | Core Responsibility (hipótesis original) | Status |
|---|---|---|
| **Provisioning** | Aislamiento del proyecto (namespace, quota, netpol, TTL). **Nuevo, sin abrir todavía**: capacidad de base de datos (CNPG) — Provisioning declara el derecho/entitlement, Deploy materializa el manifiesto (CNPG ya instalado en el clúster) | 🆕 Proposed |
| **(sin nombre — Team)** | Dueño de una o más Applications; aggregate `Team`. Descubierto al modelar App Manager, límites y nombre aún por definir | 🆕 Proposed — ver `app-manager/discovery.md` |

## Cross-Context Events

| Event | Emitido por | Consumido por | Status |
|---|---|---|---|
| `SourceChanged` | AppSource | **Project** (ya no App Manager directamente — Project reparte por servicio) | ✅ Confirmed — ver `appsource/discovery.md` y `project/discovery.md` |
| `ApplicationSourceChanged` *(nombre propuesto)* | **Project** (reparte `SourceChanged` por cada `serviceName` conocido) | App Manager | 🆕 Nuevo — reemplaza el consumo directo de `SourceChanged` por parte de Application |
| `ServiceDiscovered` *(nombre propuesto)* | **Project** (encuentra un `serviceName` nuevo en el yaml) | App Manager (registro de `Application` nueva) | 🆕 Nuevo — mecanismo exacto de creación sin resolver |
| `ApplicationRegistered` | **App Manager** (`Application` entra en `Created`) | *(sin consumidor identificado aún)* | ✅ Confirmed nombre — consumidores por descubrir |
| `ApplicationBuildRequested` *(nombre propuesto)* | **App Manager** (`Application` entra en `Building`) | Build | 🆕 Nuevo — App Manager es quien manda a Build empezar, no AppSource |
| `BuildFailed` | Build | Notification, Remediation, App Manager (`markBuildFailed`) | ✅ Confirmed (nombre dado por el arquitecto) |
| `BuildSucceeded` | Build | **App Manager** (`markBuildSucceeded`) | ✅ Confirmed |
| `ApplicationDeployRequested` *(nombre propuesto)* | **App Manager** (`Application` entra en `Deploying`) | Deploy | 🆕 Nuevo — App Manager es quien manda a Deploy empezar, no Build directamente |
| `DeployFailed` | Deploy | App Manager (`markDeployFailed`), Remediation | ✅ Confirmed |
| `DeploySucceeded` | Deploy | App Manager (`markDeploySucceeded`) | ✅ Confirmed |
| *(error en tiempo de ejecución, ej. 500)* | Logs del pod → Alloy → Loki → agente clasificador de gravedad | Remediation, App Manager | ✅ Confirmed — origen resuelto. ⚠️ Nota: si el "agente clasificador de gravedad" tiene reglas propias de qué cuenta como grave, podría ser su propio BC (Observability); si es un filtro técnico sin reglas de negocio, es solo un adaptador hacia Remediation. Sin resolver, no bloqueante |

## Captured Invariants

| Rule | Owning context | Status |
|---|---|---|
| Un intento de deploy se marca fallido tras 3 reintentos de health-check fallidos | Deploy | ✅ Confirmed |
| No puede existir un despliegue sin un build precedente exitoso | Build → Deploy (relación entre contextos) | ✅ Confirmed |
| Ante `BuildFailed`, la corrección propuesta se dirige distinto según la causa (ver los dos caminos abajo) | Remediation | ✅ Confirmed |
| **Camino equipo** (culpa en su código): Remediation nunca escribe en el repo del equipo. Genera un plan de corrección, visible y copiable en su Dashboard. Cero acceso de escritura fuera de Podium | Remediation | ✅ Confirmed |
| **Camino plataforma** (culpa en la imagen/plantilla de Podium): Remediation ejecuta la corrección en un Pod con acceso completo por git al repo de Podium — mecanismo: Claude Code en modo headless (`claude -p -r`). El resultado es un PR contra el propio repo de Podium, nunca un merge directo — el equipo técnico revisa y decide | Remediation | ✅ Confirmed |

## Shared Kernel

| Concept | Status |
|---|---|
| `appHash` | ✅ Confirmed — identificador de proyecto, usado por todos los BCs confirmados |
| `userId` | ❌ Retirado del Shared Kernel — no se modela como dominio propio. Es el claim `sub` de un JWT emitido por Keycloak; referencia externa opaca donde haga falta (p. ej. `Token` en App Manager), nunca un aggregate local |

## Open Ambiguities

| Question | Context | Resolution |
|---|---|---|
| ¿El "agente clasificador de gravedad" de los logs tiene reglas de negocio propias (qué cuenta como grave) — y por tanto merece su propio BC (Observability) — o es un adaptador técnico sin reglas, alimentando a Remediation? | Remediation | Pendiente — no bloqueante |
| ~~¿Cómo se reorganiza la cadena AppSource → Application con Project de por medio?...~~ | AppSource / Project / Application / Team | ✅ Resuelto — `AppSource` referencia `Project` (no `Application`). `Team` es dueño de `Project`. `Application.teamId` se mantiene como copia materializada de solo lectura, no como segundo dueño |

## Session Log

### 2026-09-12
- Contexto arrancado a partir de la sesión de arquitectura de Podium (Provisioning ya validado técnicamente: namespace, quota, NetworkPolicy, PSS).
- Resuelta la ambigüedad Build vs. Deploy: son BCs separados, comunicados por evento. Invariante de dependencia dura capturada.
- Confirmados 4 BCs (Build, Deploy, App Manager, Notification); propuesto un 5º (Remediation) aún sin límites claros.
- Capturada una regla de negocio no trivial: el enrutado de la corrección depende de dónde está la culpa (equipo vs. plataforma) — mecanismo de entrega todavía sin resolver.
- Resuelto: camino equipo = recomendación pasiva en Dashboard (sin acceso de escritura); camino plataforma = ejecución autónoma vía pod con Claude Code contra el propio repo de Podium, entregada como PR, nunca merge directo.
- Resuelto: Remediation es independiente de App Manager — reglas propias, agnóstico al estado. Nuevo disparador descubierto: error en tiempo de ejecución (ej. 500), origen sin BC asignado todavía.
- App Manager acotado: agregados `Application`, `Build`, `Deployment` — pendiente de confirmar si "Build" ahí es el mismo concepto que el BC Build o un reflejo de estado.
- Resuelto: `Build`/`Deployment` en App Manager son reflejos de estado (frontend de usuario), no las reglas reales — esas viven en los BC Build y Deploy. Principio general capturado: mismo término en contextos vecinos ≠ error.
- Resuelto: Adoption se fusiona en App Manager (es la misma "aplicación frontal para los usuarios").
- Cerradas las dos ambigüedades pendientes: origen del 500 (logs → Alloy → Loki → clasificador → Remediation + App Manager); Shared Kernel confirmado (`appHash`), `userId` reconocido pero deliberadamente sin modelar todavía.
- Nuevo BC confirmado: **AppSource** (nace del discovery de App Manager, al descartar `Token`/repos privados). Dominio agnóstico al proveedor — nuevo principio de modelado capturado. `userId` corregido: no es Shared Kernel, no se modela en absoluto (Keycloak + JWT).
- **Revisión importante**: la comunicación entre BCs es siempre por evento publicado, nunca llamada directa — principio general capturado. Consecuencia: App Manager no es un reflejo pasivo, es el **orquestador central** del ciclo build→deploy. Corregidas las relaciones: AppSource nunca habla con Build directamente (pasa por App Manager); Build nunca habla con Deploy directamente (pasa por App Manager). Nuevos eventos: `ApplicationBuildRequested`, `ApplicationDeployRequested`.
- Nuevo BC candidato: **Project**. Un `podium.yaml` real reveló que un repo puede declarar varios servicios desplegables (monorepo) — rompe la asunción implícita "un AppSource = una Application" que llevábamos usando. Razón para soportarlo ya: alta probabilidad de que equipos usando asistentes de código generen justo ese patrón (frontend+backend) por defecto. Pregunta bloqueante abierta: cómo se reorganiza la cadena AppSource→Application→Team con Project de por medio, antes de tocar esos tres documentos otra vez. También reveladas: capacidad de base de datos (CNPG) para Provisioning, precedencia URL-gana-por-peso, sin sintaxis de default en secrets, referencia cruzada `${app.url}` entre servicios.
- **`Project` confirmado.** Resuelto: `hash` es de Project, nunca de Application (`Application.appHash` se renombra a `serviceName`, único dentro de su Project). Deploy sigue siendo por Application/servicio, no por Project — decisión explícita para el hackathon. Corregida la cadena de eventos: `SourceChanged` (AppSource) lo consume `Project`, no App Manager directamente; Project reparte `ApplicationSourceChanged` por servicio conocido, y `ServiceDiscovered` para servicios nuevos. Inferencia pendiente de confirmar: si `Team` pasa a ser dueño de `Project` en vez de cada `Application`.
- Resuelto: `Team` es dueño de `Project`. `Application.teamId` se mantiene como copia materializada de solo lectura, fijada al registrar — nunca un segundo dueño de la verdad. Capturada la distinción general entre duplicación (mala, dos caminos de escritura) y materialización de solo lectura (correcta, un solo dueño). Los `model.md` de App Manager y Build quedan pendientes de regenerar tras este renombrado.

