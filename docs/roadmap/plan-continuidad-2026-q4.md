# Plan de continuidad — Podium/Hostium, los cuatro meses después del hackathon

**Ventana:** 21 septiembre 2026 → 18 enero 2027 · 17 semanas · ~10 h/semana ≈ **170 h**
**Punto de partida:** `bea710e` — plataforma ya desplegada y sirviendo en
`app` / `api` / `auth.hostium.apperture.dev`
**Objetivo de la ventana, en una frase:**

> Que un desconocido pueda registrarse, desplegar su repo — público o privado — y arreglar solo
> lo que le falle, sin que nadie le explique nada y sin poder tocar lo que no es suyo.

**Documentos hermanos:** `docs/hackathon-plan/plan-hackbarna-2026.md` (el plan *previo* al evento,
con la arquitectura que se decidió y el alcance que se recortó) y
`docs/podium-domain/c4-diagrams.md` (la arquitectura **construida**, no la planificada — incluida
su tabla de lo que no existe). Si hay conflicto sobre qué existe hoy, manda `c4-diagrams.md` y
este archivo se corrige.

---

## 1. Lo que hay construido hoy — y lo que solo lo parece

En 48 horas no salió un prototipo: salió una plataforma en producción. Este es el inventario real.

| Pieza | Estado | Dónde |
|---|---|---|
| API Symfony 8.1 / PHP 8.4 sobre FrankenPHP, 6 bounded contexts, Doctrine con mapeo XML | ✅ En producción | `services/php/src/{Project,AppSource,AppManager,Build,Deploy,Team}` |
| Dashboard Next.js 16 / React 19 / Tailwind 4 / shadcn sobre Base UI, actuando de BFF | ✅ En producción | `services/web` |
| Login real: Keycloak, JWT como cookie `httpOnly`, el token **nunca llega al navegador** | ✅ En producción | `services/web/src/lib/auth/`, `src/proxy.ts` |
| Bus de eventos de dominio sobre Redis Streams (Symfony Messenger), 14 handlers | ✅ En producción | `services/php/config/packages/messenger.yaml` |
| `build-launcher` (Go): consume Redis → crea un `Job` de Kubernetes → publica el resultado | ✅ En producción | `services/build-launcher` |
| `deploy-launcher` (Go): consume Redis → crea/parchea el CR `Application` de ArgoCD → sondea salud | ✅ En producción | `services/deploy-launcher` |
| `build-runner`: imagen con buildah y 10 Dockerfile de plantilla (5 lenguajes) | ✅ En producción | `services/build-runner` |
| Chart genérico `podium-app` publicado en registro OCI, un CR de ArgoCD por servicio de tenant | ✅ En producción | `helm/podium-app`, `deploy/argocd/` |
| CI en GitLab: buildah → push → commit del digest en el overlay → ArgoCD reconcilia | ✅ En producción | `.gitlab-ci.yml`, `deploy/overlays/prod` |
| Base de datos opcional por servicio vía CNPG, declarada en el `podium.yaml` del equipo | ✅ En producción | `helm/podium-app/templates/database.yaml` |
| `activator` (escalado a cero con despertar por petición) | ⚠️ Desplegado pero **inerte por diseño** | `services/activator`, `deploy/activator/` |
| Las 5 `NetworkPolicy` de aislamiento por tenant | ⚠️ Validadas 11/11 **como fixture — nada las aplica** | `docs/hackathon-netpol/` |
| BC Notification | ⏳ No arrancado — 4 transportes se publican **sin consumidor** | — |
| BC Remediation y el agente de ensayo | ⏳ No arrancado | — |

La cadena completa, tal como funciona hoy: `CronJob` de poll cada 60 s → `SourceChanged` →
`Project` reparte por servicio → `Application` (el orquestador) → `BuildJobRequested` →
`build-launcher` → `Job` privilegiado con buildah → push al registro → `BuildSucceeded` →
`DeployAttemptRequested` → `deploy-launcher` → CR de ArgoCD → sondeo de salud → `DeploySucceeded`.
URL pública por servicio: `{serviceName}-{hash}.apperture.dev`.

### Lo que parece real y no lo es

Esta tabla es la más importante del documento, porque **un usuario externo no puede distinguir
nada de esto de una plataforma rota**.

| Falso | Dónde | Veredicto |
|---|---|---|
| Descripciones de proyecto inventadas, cicladas de un array de 6 frases | `services/web/src/lib/mock-project-description.ts` | ❌ Borrar (Fase 1). El cuerpo de la tarjeta pasa a datos reales: nº de servicios, estado agregado, último deploy |
| Pantalla de Secrets sobre fixtures en memoria, que se pierden al recargar | `src/lib/fixtures/secrets.ts`, `src/lib/secrets-context.tsx` | ⚠️ Etiquetar **en la propia pantalla** hasta la Fase 6 — no en un README que nadie lee |
| URLs `*.apperture.dev` construidas como string en el cliente, con **dos fórmulas divergentes**, y que ni siquiera son enlaces | `project-card.tsx` (`{hash}…`), `application-card.tsx` (`{serviceName}-{hash}…`) | 🆕 Consolidar en `src/lib/public-url.ts` y enlazar ya (Fase 1); sustituir por el campo real de la API (Fase 2). Dos fórmulas en dos ficheros es un bug latente, no un detalle cosmético |
| Avatares pedidos a `api.dicebear.com` en tiempo de ejecución | `src/lib/dicebear-avatar.ts` | ❌ Borrar la petición (Fase 1): es un tercero por tarjeta, filtra nombres de servicio, y morirá en cuanto se aplique la `NetworkPolicy` de egress |
| Panel ✦ del agente con respuesta enlatada | `src/components/shell/agent-entry-point.tsx` | ⚠️ Etiquetar como vista previa. Su primer trabajo honesto y **sin LLM** será "¿por qué falló mi build?" |
| `Despliegues`, `Equipo` y `Ajustes` en la barra lateral | `src/components/shell/app-sidebar.tsx` | 🆕 `Despliegues` se activa en la Fase 2, `Ajustes` en la 5, `Equipo` en la 6. Hasta entonces el tooltip lleva el número de fase: "próximamente" solo vale cuando hay fecha |

---

## 2. Lo primero: este repo miente sobre sí mismo

Con agentes de código leyendo el repositorio, documentación falsa es el defecto más caro que
existe — cuesta una hora arreglarlo y contamina cada sesión futura.

| Mentira | Dónde | Realidad |
|---|---|---|
| "This repo currently has no application code" | `CLAUDE.md:7` | Hay 6 servicios, 10 migraciones, 41 tests y una plataforma en producción |
| Deploy y Remediation "not yet started"; el deploy path "planned… not yet built" | `CLAUDE.md` | Deploy está implementado y desplegando; solo Remediation sigue sin arrancar |
| Describe un "dev auth bridge" con `testuser` fijo | `openspec/changes/add-frontend-dashboard/design.md` | Se borró en `73942fa`; hoy hay login real con Keycloak y registro propio |
| `openspec/specs/` solo tiene un `.gitkeep` | `openspec/` | El change se implementó entero y **nunca se archivó ni se sincronizó** |
| Asume un namespace `activator-system` con `podSelector: {}` | `docs/hackathon-netpol/03-activator-netpol.yaml` | El activator se consolidó en `hostium`; aplicar ese fichero tal cual rompería php, web y keycloak. Nunca se aplicó, así que no hay nada que revertir — solo una fixture que reescribir con `podSelector: {app: activator}` |
| No es un documento | `docs/defense.md` | Notas pegadas de un escáner de seguridad |

Todo esto se paga en la **Fase 0**, junto.

---

## 3. Decisiones fijadas antes de empezar

| Decisión | Razón | Alternativa descartada |
|---|---|---|
| **Ventana de 4 meses, no 3** | Con 13 semanas la suma daba ~150 h contra 130 h de presupuesto. Se amplió la ventana en vez de recortar peticiones | Recortar la GitHub App a enero (era lo recomendado), o el aislamiento, o editar/borrar |
| **El login ya existe: solo se endurece** | Keycloak + cookie `httpOnly` funcionando. Lo que falta es rotación de refresh token y quitar el atajo del admin maestro | Rehacer autenticación — sería trabajo tirado |
| **Repos privados con GitHub App** | Instalación por organización, tokens de instalación de vida corta, permisos mínimos y webhooks incluidos | PAT pegado a mano (higiene y rotación manual) y deploy keys SSH (sin webhooks) |
| **El tiempo real lo sirve Mercure, no PHP** | El hub viene **incorporado en FrankenPHP** y mantiene las conexiones **en Go, dentro de Caddy**: PHP nunca sostiene un stream, así que los cuatro asesinos de la fila siguiente dejan de aplicar. Y Mercure *es* SSE: cumple la decisión original | Un `StreamedResponse` de Symfony (ver fila siguiente), un `Deployment` `php-sse` con config propia, un `event-gateway` en Go, o que el BFF de Next leyera Redis |
| ⚠️ **Los cuatro asesinos silenciosos** que hacen inviable aquí un `StreamedResponse` | `frankenphp { worker … }` — cada conexión secuestraría un worker de por vida, con `cpu: 1000m` en 2 réplicas; `max_execution_time=15` + `exit_on_timeout=1` en `services/php/docker/franken-php/conf.d/05-frankenphp.ini` — corta a los 15 s **y mata el worker**; `timeouts { write 30s }` en el `Caddyfile`; y el bloque `encode … match header Content-Type text/*`, que **comprime y bufferiza `text/event-stream`** porque encaja en `text/*`. Ninguno da error: fallan en silencio | — |
| El hub va en su **propio `Deployment` de 1 réplica** | El hub incorporado no clusteriza. Con `php-deployment` a 2 réplicas cada pod tendría su propio hub y la mitad de los clientes se perdería la mitad de los eventos: **silenciosamente mal, que es peor que no tener tiempo real**. Un hub aparte recibe los publish de ambos pods | Fijar `php` a 1 réplica; afinidad de sesión |
| Transporte **`mercure.local`, sin historial** | ✅ Comprobado sobre la imagen real: `frankenphp list-modules` lista `http.handlers.mercure`, `mercure.local` y `mercure.bolt` — no hay que compilar nada. Se elige `local` para no añadir un componente con estado, y el hueco lo cubre algo más simple y más fiable: **invalidar toda la caché al reconectar**. Una línea, y es la historia completa de recuperación de huecos | `mercure.bolt` + PVC — queda anotado como la mejora exacta si algún día hace falta replay real por `Last-Event-ID` |
| Los publish van **async por Messenger** | Publicar en el hub no debe poder ralentizar ni romper una escritura de dominio | Publicar en línea dentro de la petición HTTP |
| El navegador **no habla con el hub**: lo proxea el BFF | El hub vive en el dominio de la API y el navegador en el de la app. Proxear mantiene el mismo origen, deja el token de suscriptor en cookie `httpOnly` y evita abrir CORS. Node hace de puente sin coste | Exponer el hub con CORS y cookie en el dominio de la API |
| La autorización sigue **en el dominio** | Symfony mintea un JWT de suscriptor con selectores de tópico (`/teams/{teamId}/{+}`) solo tras pasar `Team::hasMember()`, y publica con `private: true`. El aislamiento entre equipos lo impone el hub, no el cliente | Un canal global filtrado en el cliente — que no es aislamiento, es maquillaje |
| El feed lo posee el BC **Notification**, que por fin existe | Está `✅ Confirmed` en `docs/podium-domain/context-map.md` desde el primer día y sin implementar ("Avisa. Nada más"). Al nacer consume además los 4 transportes huérfanos que hoy se publican sin consumidor | Colgar el feed de App Manager — le daría una responsabilidad que no es suya |
| El payload del evento es **notificación, no dato** | `{teamId, projectId, serviceName, state, version, at}` y el frontend refetchea lo afectado. Así el contrato no crece cada vez que la UI quiere un campo más, y la UI nunca puede mostrar un valor obsoleto pero plausible | Enviar el `ApplicationDTO` completo en cada evento |
| **Abrirlo a usuarios reales externos** es el objetivo | Convierte el endurecimiento multi-tenant en alcance, no en extra: es la Fase 6 y es una puerta, no una mejora | Uso propio, o escaparate técnico |
| Diagnóstico: primero lo ya persistido | `build_jobs.error_message`, `deploy_attempts.error_message` y `application_history_logs` ya se escriben y **no tienen ruta de lectura**. Un controlador convierte una tarjeta roja en algo accionable | Ir directo a logs de pod |
| Los agentes quedan **fuera de la ventana** | Ver §10. Sus dos cimientos (activity y logs) sí entran | — |

---

## 4. El presupuesto: 170 h repartidas

| Fase | Semanas | Horas | Qué desbloquea |
|---|---|---|---|
| **0 — Cerrar la puerta y dejar de mentir** | 1–2 | ~18 h | Que nadie pueda crear equipos a nombre de otro; que el CI verifique algo; y la victoria barata del diagnóstico |
| **1 — La app aparece sola y el estado se mueve** | 3–5 | ~30 h | La petición de cabecera. Refresco, tiempo real, y la muerte de los falsos |
| **2 — Una tarjeta en rojo deja de ser un callejón sin salida** | 6–7 | ~18 h | Saber por qué falló y reintentar. Página de detalle de aplicación |
| **3 — El botón de logs** | 8–9 | ~20 h | La petición literal. Logs de build y de runtime |
| **4 — Editar y borrar sin romper el clúster** | 10–11 | ~20 h | Los verbos de escritura que hoy no existen |
| **5 — Repos privados de verdad** | 12–14 | ~32 h | GitHub App de punta a punta, incluido el clone del builder |
| **6 — Abrir la puerta** | 15–17 | ~30 h | Secrets reales, aislamiento del tenant, invitar miembros |
| | | **~168 h** | de 170 disponibles |

El colchón es de 2 h, o sea que no hay colchón. **Si se desborda, el orden de recorte es:**
aislamiento de tenant (se pospone abrir a externos, no se abre sin él) → persistencia de logs de
build → catálogo de plantillas como pantalla.

**Nunca se recortan dos cosas**, porque dejan el sistema peor que no haber empezado:

1. El arreglo del `git clone` sin credenciales (Fase 5) — sin él, "repos privados" significa que
   la UI dice que sí y el build muere al clonar.
2. El finalizer de ArgoCD (Fase 4) — sin él, "borrar" deja huérfanos el Deployment, el Service,
   el Ingress y el `Cluster` de CNPG, que siguen corriendo y gastando.

---

## 5. Las fases

Formato de cada fase: intención, tabla de piezas, **Hito** verificable, y dependencias. Antes de
la primera, tres reglas que se repiten en todas porque ya costaron tiempo una vez.

| Regla | Por qué se repite |
|---|---|
| **Esto es Base UI, no Radix** — polimorfismo con `render={<Link/>}`, nunca `asChild`; `DropdownMenuItem` dispara `onClick`, no `onSelect` | Es el bug registrado en `openspec/changes/add-frontend-dashboard/design.md` ("silently did nothing on click"), y cada menú o diálogo nuevo es otra oportunidad de repetirlo |
| **La UI nunca afirma más de lo que la plataforma sabe** | `podium.yaml` **declara** lenguaje y framework; Podium no los detecta (`docs/podium-config/podium-yaml-guide.md` lo dice explícitamente). La etiqueta es "Declarado en podium.yaml", nunca "Detectado". Esa misma disciplina es la que mata las descripciones inventadas y las URLs falsas |
| **Copy en español, sin capa de i18n** | Decisión ya tomada (`lang="es"`, mensajes de `ApiError` en español). Una app medio internacionalizada es peor que una monolingüe |

### Fase 0 — Cerrar la puerta y dejar de mentir

*Semanas 1–2 · ~18 h · sin dependencias de nadie*

Dos cosas a la vez: tapar los agujeros que hacen imposible invitar a nadie, y cobrar la victoria
más barata del plan. Nada de esto es glamuroso y todo lo demás depende de ello.

| Pieza | Qué hace | Dónde |
|---|---|---|
| Jobs de CI para PHP y web | `test-php` (`composer install`, `phpunit`, `lint:container`, `doctrine:schema:validate`) y `test-web` (`npm ci`, `lint`, `typecheck`, `next build`), cada uno bloqueando su `build-*` igual que `test-deploy-launcher` bloquea a `build-deploy-launcher`. Hoy los **41 tests de PHPUnit y la config de eslint no han corrido nunca en CI**: solo los 3 servicios Go están protegidos | `.gitlab-ci.yml`; añadir `"typecheck": "tsc --noEmit"` a `services/web/package.json` |
| Cerrar los dos POST públicos | `POST /api/teams` es **público y toma `creatorUserId` del body**: cualquiera crea un equipo a nombre de cualquiera. `POST /api/projects` es público y engancha un proyecto a cualquier `teamId` conocido. Es un bypass completo de autorización. Se toma el dueño del `#[CurrentUser]`, se comprueba membresía al crear proyecto, y se quitan las dos líneas `PUBLIC_ACCESS` | `services/php/config/packages/security.yaml:26-27`, `RegisterTeamController`, `RegisterProjectController`, `services/web/src/app/api/projects/route.ts` (que hoy proxea el POST **sin token**) |
| Rotación de refresh token | **No es higiene: es prerrequisito de la Fase 1.** El access token de Keycloak dura 5 minutos y no se renueva, así que un stream de tiempo real moriría cada 5 minutos — y hoy además desloguea en silencio a mitad de sesión | `services/web/src/lib/auth/session.ts` (segunda cookie `podium_rt`), `src/lib/auth/proxy.ts`, `src/proxy.ts` |
| **La victoria barata del diagnóstico** | `GET …/applications/{serviceName}/activity`: timeline de `application_history_logs` fusionado con la última fila de `build_jobs` y de `deploy_attempts`, exponiendo `errorMessage`, `retryCount`, `image` y `version`. Todo eso **ya se persiste y nada lo lee** — de hecho `config/services.yaml:33-35` mantiene la repo del historial con `public: true` precisamente porque no tiene consumidor. Un controlador y dos métodos de repositorio, y de paso se elimina ese `public: true`. Más `GET …/builds` para la lista de intentos | `src/AppManager/Infrastructure/Http/`, `src/Build/`, `src/Deploy/` |
| Materializar `lang` y añadir el estado `Unsupported` | `Application` guarda `framework` pero **no `lang`** (viaja en `ServiceDiscovered` y se tira). Y cuando el par lenguaje/framework no está en las 10 filas de `templates`, `DoctrineTemplateResolver` lanza y **la `Application` no se crea nunca**: el usuario no ve nada, para siempre, sin explicación. "La app aparece sola" tiene que incluir la que no se puede construir | `src/AppManager/Domain/Application.php`, `DoctrineTemplateResolver`, migración |
| Primera revisión resuelta en línea | Hoy la primera tarjeta de un proyecto nuevo espera hasta 60 s a que pase el poller. El adaptador ya existe: resolver la revisión al consumir `ProjectRegistered`. Cinco líneas, y la primera tarjeta aparece en ~1 s | `ProjectRegisteredHandler` |
| `/api/health` propio en web y sondas separadas | Hoy **liveness y readiness apuntan las dos a `/login`**, así que una regresión en la pantalla de login se lee como pod caído y provoca un bucle de reinicios en vez de un informe de bug. El nuevo endpoint no lee cookie ni llama a Symfony **a propósito**: acoplar readiness al backend convertiría una caída parcial en total | `services/web/src/app/api/health/route.ts`, `deploy/base/web-deployment.yaml` |
| Un test de Playwright, lo primero de todo | Que cambiar de equipo cambie de verdad la lista visible. Es exactamente el bug de Base UI ya documentado, se cazó con un script ad-hoc que ya no existe, y es la red que protege la migración de capa de datos de la Fase 1. Hoy: Playwright instalado y **cero ficheros de test** | `services/web/tests/` |
| Dejar de mentir | Reescribir `CLAUDE.md` contra la realidad; archivar y sincronizar el change de OpenSpec; borrar `docs/defense.md`; reescribir `03-activator-netpol.yaml` con `podSelector: {app: activator}` | `CLAUDE.md`, `openspec/`, `docs/hackathon-netpol/` |

**Hito:** un build fallido muestra su motivo real en la UI, nadie puede crear un equipo a nombre
de otro, y el CI rechaza un test de PHP roto.

**Desbloquea:** la Fase 1 (refresh token), la Fase 2 (activity) y cualquier trabajo posterior con
red de seguridad.

---

### Fase 1 — La app aparece sola y el estado se mueve

*Semanas 3–5 · ~30 h · depende de la Fase 0 (refresh token)*

La fase central y la más arriesgada: cambia la capa de datos y añade streaming a la vez. Por eso
se entrega en dos mitades, y **la primera ya arregla la queja de cabecera sin tocar el backend**.

#### Primera mitad — la capa de datos

Hoy no hay ningún mecanismo de refresco: cero `setInterval`, cero `EventSource`, cero
`react-query`, cero `router.refresh()`. Una transición `Building → Deployed` es invisible hasta
que el usuario cambia de equipo y vuelve, o recarga el navegador.

| Pieza | Qué hace | Dónde |
|---|---|---|
| Adoptar **TanStack Query v5** | Los tres contextos hechos a mano **son caché escrita a mano, y peor**. `projects-context.tsx` desaparece entero — incluida la lectura de la caché de lista dentro de `loadProjectDetail()`, que es justo la causa de que el detalle muestre un estado obsoleto. `team-context.tsx` se parte en `useTeams()` + un `ActiveTeamProvider` que solo guarda `activeTeamId` (hoy resuelve el equipo activo dentro del `.then()` del fetch, o sea que un `localStorage` compite con una llamada de red) | `src/lib/projects-context.tsx` (borrar), `src/lib/team-context.tsx` (partir), `src/components/providers.tsx` |
| Claves jerárquicas | `["teams", teamId, "projects", projectId, "applications", serviceName]`, con una factoría en `src/lib/queries/keys.ts` para que nada sea un string suelto. Jerárquicas a propósito: un evento de semántica desconocida puede invalidar `["teams", teamId]` y ser correcto, solo grueso — es la escotilla que permite que backend y frontend avancen desacoplados | `src/lib/queries/` |
| Extender el transporte, no sustituirlo | `src/lib/api/client.ts` es buen código y se queda: mismo origen, `ApiError` con `status`. Se le añade `patchJson`/`del`, tolerancia a 204, el `cache: "no-store"` que a `postJson` le falta, y una señal única de `session-expired` que un único listener convierte en redirección — en vez de que cada pantalla pinte "La solicitud falló (401)" | `src/lib/api/client.ts` |
| Botón "Actualizar" | Antes que el tiempo real. Un dashboard necesita un botón de refrescar exista o no el streaming, y `refetchOnWindowFocus` da además, el primer día, **el primer mecanismo de refresco que la app ha tenido nunca**: cambias de pestaña, vuelves, y está al día | `src/components/shell/page-header.tsx` |
| Piezas que faltan y todo lo demás necesita | Host de *toasts* (hoy no hay ninguno en `src/components/ui/`, y cada mutación de las fases 2–6 lo necesita); primitivas de vacío / error / carga aplicadas en todas las rutas — hoy son un `<p>Cargando…</p>` y un `<p class="text-destructive">`; y el toggle de modo oscuro: el bloque `.dark` está **entero** en `globals.css:86`, `@custom-variant dark` declarado, y **nada pone nunca la clase** | `src/components/ui/`, `next-themes` |
| Matar los falsos de §1 | Borrar las descripciones inventadas y la petición a DiceBear; consolidar las dos fórmulas de URL en `src/lib/public-url.ts` y renderizarlas como `<a target="_blank" rel="noopener noreferrer">`; etiquetar Secrets y el panel ✦ como vista previa **en la propia pantalla** | `src/lib/`, `src/components/projects/` |

#### Segunda mitad — el streaming

| Pieza | Qué hace | Dónde |
|---|---|---|
| El evento que hoy no existe | `Application` muta el estado y escribe su fila de historial **sin avisar a nadie**: `markBuildFailed`, `markDeploySucceeded` y `markDeployFailed` ni siquiera despachan lo que devuelven. Se añade `ApplicationStateChanged`, registrado en cada `mark*`, colgado del único punto por el que ya pasan todas las mutaciones (`logMutation()`) | `src/AppManager/Domain/Application.php:234`, `Application/ApplicationService.php` |
| BC **Notification** | Nace con un puerto `FeedPublisher` y handlers para `ApplicationStateChanged`, `ApplicationRegistered`, `BuildFailed` y `DeployFailed` — con lo que de paso **deja de haber 4 transportes publicando al vacío**. Publica con `private: true` y tópico `/teams/{teamId}/applications/{serviceName}` | `services/php/src/Notification/` (nuevo) |
| Hub de Mercure | `Deployment` propio de 1 réplica, imagen FrankenPHP con su `Caddyfile` por ConfigMap: directiva `mercure` con transporte `local`, `timeouts { write 0 }`, y **`encode` excluido para `/.well-known/mercure`** — si no, Caddy comprime y bufferiza el stream | `deploy/base/mercure-{deployment,service,config}.yaml` |
| Publish **async** | El handler no llama al hub: despacha un mensaje que el worker consume y publica. Publicar en el hub no puede ralentizar ni romper una escritura de dominio | `messenger.yaml`, `deploy/base/worker.yaml` |
| Token de suscriptor | Endpoint que, tras pasar `Team::hasMember()`, mintea un JWT de suscriptor con selectores de tópico acotados al equipo. El aislamiento lo impone el hub | `src/Notification/Infrastructure/Http/` |
| `proxyStream()` en el BFF | Hermano de `proxyGet()`, que **siempre hace `response.json()` y por tanto bufferiza — no se puede reutilizar**. Pasa el cuerpo tal cual con `Content-Type: text/event-stream`, `Cache-Control: no-cache, no-transform`, `X-Accel-Buffering: no`, `runtime = "nodejs"`, `dynamic = "force-dynamic"`, y propaga el `AbortSignal` para que cerrar la pestaña libere el recurso. Devuelve 401 **en JSON, no stream**, para que el cliente distinga "sin sesión" de "backend caído" | `services/web/src/lib/auth/proxy.ts`, `src/app/api/events/route.ts` |
| Anotaciones de ingress | En **`web-ingress`, no en `php-ingress`**: la conexión del navegador va `browser → app.hostium… → pod web → hub`, y en el segundo salto no hay ingress. `proxy-buffering: "off"`, `proxy-read-timeout: "3600"`, `proxy-http-version: "1.1"` | `deploy/base/web-ingress.yaml` |
| Un reductor **puro** | `applyEvent(queryClient, event)`: sin DOM, sin timers, sin red. Parchea la fila afectada con `setQueryData` (refetchear una lista para enterarse de un enum es desperdicio y hace parpadear el badge) y **la marca obsoleta con `refetchType: "none"`** para que el siguiente refetch natural reconcilie lo que el parche no acertó. Ante un `event:` desconocido invalida en grueso en vez de perderlo. Puro a propósito: es la única pieza del plan que merece un test unitario, y por eso la suscripción (impura) vive en otro fichero | `src/lib/events/apply-event.ts`, `src/lib/events/event-stream-provider.tsx` |
| Una sola conexión por pestaña | Montada en `providers.tsx` y reiniciada al cambiar de equipo. No una por tarjeta: HTTP/1.1 limita a ~6 conexiones por origen y un proyecto con 6 servicios bloquearía sus propias peticiones | `src/components/providers.tsx` |
| Degradación visible | Punto de estado en el topbar (`en vivo` / `reconectando…` / `sin conexión`). **Al volver a `live`, invalidar todo** — no podemos saber qué nos perdimos, y es la historia completa de recuperación en una línea. Y polling de 15 s acotado *solo* mientras el stream está caído *y* hay algo en vuelo: la única excepción consciente a "no polling", porque la alternativa es una UI muerta afirmando estar viva | `src/components/shell/app-topbar.tsx` |

#### Y tres defectos que el streaming va a hacer visibles

Se pagan aquí, no más tarde, porque en cuanto la UI esté en vivo se le echarán encima a ella.

| Defecto | Por qué ahora |
|---|---|
| Los dos lanzadores Go hacen `return` **sin `XACK`** y el bucle lee con `>` (solo mensajes nuevos), así que un mensaje que falla se queda en la lista de pendientes **y no se reintenta nunca, ni al reiniciar el pod**. La `Application` se queda en `Deploying` para siempre, y un commit nuevo no la desatasca porque `markSourceChanged` solo marca el cambio como pendiente mientras está en `Deploying`. Arreglo: `XAUTOCLAIM` al arrancar | Documentado en `deploy/deploy-launcher/README.md:49-67` y sin resolver. Con una UI en vivo, una app colgada para siempre pasa de bug invisible a la queja número uno |
| Sin salida por tiempo para `DeployAttempt` | Un cron que expira intentos zombis: `Application::timeOut(reason)` |
| `failed_events` está en la lista de consumo del worker **sin `retry_strategy` configurada en ninguna parte**, así que un mensaje envenenado va al transporte de fallos, se consume, falla, y vuelve al transporte de fallos, indefinidamente — quemando CPU y enterrando todos los demás fallos | `messenger.yaml`, `deploy/base/worker.yaml` |

**Hito:** registrar un proyecto con dos servicios, apartarse del teclado, y ver aparecer las dos
tarjetas y recorrer `Created → Building → Deployed` sin tocar el navegador. Dejar la pestaña
abierta una hora y seguir en vivo.

---

### Fase 2 — Una tarjeta en rojo deja de ser un callejón sin salida

*Semanas 6–7 · ~18 h · depende de la Fase 0 (activity) y la Fase 1 (stream, toasts)*

Hoy una tarjeta `BuildFailed` es una lápida: badge negro y nada más. Ni motivo, ni enlace al log,
ni botón de reintentar. Con el `activity` de la Fase 0 ya expuesto, aquí se consume.

| Pieza | Qué hace | Dónde |
|---|---|---|
| Nota de fallo en la tarjeta | Panel en tono destructivo con el `errorMessage` real (3 líneas, expandible) y dos acciones: "Ver log" y "Reintentar". Exponer **un campo** compra casi todo el valor de un subsistema de logs entero | `src/components/projects/application-failure-note.tsx` |
| **Página de detalle de aplicación** | `/projects/[id]/apps/[serviceName]`. Las aplicaciones **hoy no tienen página**, y logs, timeline, configuración y acciones necesitan un sitio donde vivir. No es glamurosa: desbloquea cuatro funciones. Cuatro secciones: *Estado*, *Configuración* (de solo lectura, ver Fase 4), *Secrets requeridos* (Fase 6), *Acciones* | `src/app/projects/[id]/apps/[serviceName]/` |
| Timeline de despliegues | Vertical, una fila por transición `before → after`, con los campos que cambiaron resaltados en el cliente. Es **el mayor valor por línea de backend de todo el plan**: el dato ya se escribe en cada mutación. Es además la respuesta a "revisar el estado" cuando el stream estuvo caído, y la lista de versiones que un futuro rollback necesita | `src/components/projects/deployment-timeline.tsx` |
| Reintentar y redesplegar | `POST …/rebuild` y `POST …/redeploy` → `Application::forceRebuild(?revision)`, que **ignora a propósito la guarda de `Deploying`**: es también la salida manual del defecto de los lanzadores | `src/AppManager/` |
| La URL desde la API | `url` en el modelo de lectura de `Application` y `Project`, y se borra `public-url.ts`. Hoy se construye en el cliente con dos fórmulas distintas porque la API nunca devuelve una URL | `src/AppManager/Application/ApplicationDTO.php` |
| `Despliegues` deja de ser "próximamente" | Feed de actividad entre proyectos, el más reciente primero, en vivo. Casi gratis con el timeline ya expuesto — y es **la pantalla que hace que el tiempo real se note** | `src/app/deployments/` |
| El visor de logs se empieza aquí | Contra un stub que reproduce un fichero de fixture, detrás de `NEXT_PUBLIC_FEATURE_LOGS`. Es la pieza de UI más grande del plan: conviene desriesgarla antes de que exista su endpoint | `src/components/logs/`, `src/app/api/…/logs/route.ts` (stub) |

**Hito:** provocar un build fallido, leer el motivo y la cronología completa en la UI, y
reintentar — sin abrir una terminal.

---

### Fase 3 — El botón de logs

*Semanas 8–9 · ~20 h · depende de la Fase 2 (página de detalle, stub del visor)*

La petición literal. Hoy no hay **nada**: ni endpoint, ni componente, ni una regla `pods/log` en
ningún `rbac.yaml` del repo. Tres decisiones que fija esta fase.

| Decisión | Razón | Descartado |
|---|---|---|
| **Un servicio nuevo en Go, `services/tenant-gateway`** | Sigue el precedente exacto de los tres servicios Go — `client-go`, `rest.InClusterConfig`, su `rbac.yaml`, su job de `go vet && go test -race`: todo eso existe ya tres veces — y una goroutine por stream es gratis | Symfony con cliente de Kubernetes: no hay ninguno en `composer.json`, y su modelo de petición es tan hostil a un `follow=true` como lo era al SSE. **Loki** es el destino final (`context-map.md` ya planea Alloy→Loki para el clasificador de 500 que alimentará Remediation), pero es un componente con estado — almacenamiento, retención, multi-tenancy, un DaemonSet de Alloy — que no aporta nada que la API de Kubernetes no dé hoy. 🕓 Se aplaza |
| **Autorización por token de capacidad firmado, 60 s de vida** | El gateway no tiene base de datos y **no debe aprender qué es un equipo**. Symfony corre `requireMember()` y mintea un HS256 (`firebase/php-jwt` ya es dependencia) con `{ns, selector, container, tail, follow, exp}`. El gateway verifica el HMAC y **además** aplica una lista negra dura de namespaces (`hostium`, `argocd`, `kube-*`, `cnpg-system`), para que un bug de minteo en Symfony no pueda destapar logs de plataforma. RBAC: solo `pods: get,list` y **`pods/log: get`** | Que el gateway consulte membresía (le daría base de datos y conocimiento de dominio); o confiar solo en la firma sin lista negra |
| **Protocolo `text/plain` troceado, no SSE** | El framing por línea en JSON triplica los bytes; y el delimitador de mensaje de SSE es una línea en blanco, así que **una línea de log vacía corrompería el stream** salvo que se escape todo. `fetch()` + `response.body.getReader()` + `TextDecoderStream` es más simple y correcto | SSE reutilizando el hub de Mercure |

Dos fuentes distintas, dos endpoints, un selector de pestaña en la UI — **sin intentar unificarlas
tras una abstracción**, porque no son lo mismo.

| Fuente | Naturaleza | Cómo se resuelve |
|---|---|---|
| **Logs de build** | Finitos, tienen final — y **desaparecen**: viven en pods de `Job` que `TTLSecondsAfterFinished` borra | Hay que **persistirlos al capturarlos**: añadir `pods/log` al Role de `build-launcher` (hoy tiene `pods` get/list/watch pero **no el subrecurso**, o sea que no puede leer logs aunque quisiera), leer el log al terminar el Job, recortar a 256 KB, publicar `BuildLogCaptured` y guardarlo en `build_job_logs`. Direccionables por `buildJobId`, para poder abrir un build histórico desde el timeline |
| **Logs de runtime** | Infinitos, sin principio significativo | Del gateway, en vivo, con `?tail=200` por defecto y `&follow=1`. Y **`?previous=1` para el contenedor que ya murió**: la pregunta real más frecuente es "¿por qué está en `CrashLoopBackOff`?" y el contenedor actual no tiene logs |

Comportamientos del visor, en orden de construcción: (1) tail; (2) **follow que se desactiva solo
al hacer scroll hacia arriba**, con píldora "volver al final" — el comportamiento más importante
de un visor de logs y el que más se hace mal; (3) filtro con recuento de coincidencias; (4) copiar
y descargar `.log` (cero backend); (5) wrap y timestamps. Tope de buffer en ~10 000 líneas.
**Sin virtualizador especulativo**: rompe la selección de texto y Ctrl-F, que es exactamente lo
que la gente hace con un log — primero se mide un log real.

Estado direccionable por URL (`?log=<serviceName>&phase=build`): sobrevive a un refresco y, sobre
todo, **se puede pegar a un compañero**, que es el flujo de verdad.

⚠️ Y un estado que nadie ha diseñado y que con el activator escalando a cero es **el estado normal
de la mayoría de las apps la mayor parte del tiempo**: "no hay logs — la aplicación está dormida".
Si no se diseña, se lee como un bug para siempre.

Accesibilidad concreta: `aria-live="polite"` sobre un log en streaming es hostil para un lector de
pantalla. `aria-live="off"` en el cuerpo, `role="log"`, y un aviso discreto aparte
("124 líneas nuevas").

**Hito:** tailear en vivo una app corriendo, y abrir un build que falló hace tres días y leer su
salida completa.

---

### Fase 4 — Editar y borrar sin romper el clúster

*Semanas 10–11 · ~20 h · depende de la Fase 1 (stream) y la Fase 2 (página de detalle)*

Hoy **no existe un solo verbo de escritura más allá de POST**: ni un `PUT`, ni un `PATCH`, ni un
`DELETE` en toda la API.

Truco de paralelización que conviene dejar escrito: **el cliente se escribe contra
`PATCH`/`DELETE` y el route handler del BFF absorbe la discrepancia** si el backend prefiere
endpoints de comando (`POST …/rename`). El cliente nunca se entera y el frontend no se bloquea
esperando una decisión de forma REST. Y el BFF necesita un `proxyWrite()` que **compruebe
`Origin`/`Sec-Fetch-Site`**: sus propias rutas se autentican por cookie de mismo origen, así que a
diferencia de Symfony **sí tienen exposición CSRF** en cuanto existan métodos no-GET —
`sameSite: lax` bloquea el POST clásico de formulario pero no es un sustituto.

| Concern | Decisión |
|---|---|
| `Project::rename()` | Cosmético, sin efecto aguas abajo. Publica `ProjectRenamed` |
| `Project::repoint(url, branch)` | Publica `ProjectRepositoryChanged`; AppSource lo consume, hace `repointTo()` y pone `revision = null`, así que el siguiente poll o webhook dispara `SourceChanged` sin condiciones y toda la cadena se re-ejecuta |
| El `hash` | **Inmutable, y hay que decirlo en voz alta**: es el nombre del namespace y compone todas las URLs públicas. Ese es justo el premio — al repuntar el repo, el CR de ArgoCD, el namespace, el release y el host no se tocan: el siguiente deploy solo parchea `valuesObject.image` |
| ⚠️ **Finalizer de ArgoCD** | Prerrequisito de todo el borrado: `argospec.Build` **no pone** `resources-finalizer.argocd.argoproj.io`, así que borrar el CR hoy **deja huérfanos** el `Deployment`, el `Service`, el `Ingress` y el `Cluster` de CNPG, que siguen corriendo. Un botón de borrar que no borra — y que sigue gastando CPU. Hay que añadirlo **al crear**, antes de que exista ningún borrado |
| Borrar aplicación | **Soft delete**: la fila se queda con estado `Archived` y `archived_at`, fuera de los listados salvo `?includeArchived=1`. Motivo: el `serviceName` puede reaparecer en el siguiente commit, y el historial es la única auditoría que hay. El **namespace no se borra**: los servicios hermanos del mismo `podium.yaml` lo comparten |
| Borrar proyecto | `ProjectDeletionRequested` en abanico, un grupo de consumidor por BC (el patrón que `build_failed_*` ya establece). **Sin saga**: cada paso idempotente más un reconciliador `app:project:reap-deleted` que verifica ausencia de namespace y de CRs y cierra el estado. Es lo honesto en un sistema **sin una sola FK** y sin transacciones distribuidas. Y el orden importa: **primero el CR, después el namespace** — con `syncPolicy.automated.selfHeal: true`, borrar el contenido mientras el CR vive hace que ArgoCD lo recree |
| La base de datos CNPG | La única pérdida irreversible del sistema → exige confirmación escrita **y** un `deleteData: true` explícito en el body |
| Imágenes construidas | No se borran por app: se resuelve con política de retención en el registro más un cron sobre `build_jobs`. No se construye recolección de basura por borrado |
| DNS | **Nada que hacer** — el host está bajo el comodín `*.apperture.dev` y no hay registro por app. Se dice explícitamente para que nadie planifique trabajo ahí |
| `ServiceRetired` | `Project.knownServiceNames` **nunca se encoge**: borrar un servicio del `podium.yaml` deja hoy una `Application` zombi, con su `Deployment`, su `Ingress` y su base de datos vivos **para siempre**, y sin nadie construyéndola. Es un bug preexistente, y repuntar un repo lo convierte en el caso normal → `Project::reconcileDeclaredServices()` → `ServiceRetired` → `Application::retire()` → desmontaje |

#### "Editar la aplicación": casi nada es editable, y hay que explicar por qué

Es la petición con más riesgo de construirse mal, y las convenciones del propio repo la deciden:
*"cuando el equipo ya mantiene un dato en su propio repo, Podium lo lee — nunca lo posee ni lo
duplica"*.

| Campo | Veredicto |
|---|---|
| `lang`, `framework`, `src`, `environment`, bloque `database` | ❌ **De solo lectura, con candado y la etiqueta "Definido en podium.yaml"**, más enlace profundo al fichero en el commit construido. Un formulario sobre ellos crea un segundo camino de escritura que el siguiente build sobrescribe en silencio: exactamente la deriva que el modelo prohíbe. Y en la UI, no solo en este documento: *"Estos valores viven en tu repositorio. Edítalos ahí y Podium los recogerá en el siguiente build."* |
| `port` | ❌ De solo lectura, pero por otro motivo: sale de `Template.defaultPort`, no de la app. Se muestra como "Puerto 3000 · plantilla `nodejs/nextjs`" |
| `serviceName` | ❌ Identidad. No se edita |
| `autoDeploy` on/off | ✅ **Editable** — `Application::pauseAutoDeploy()`/`resume()`, con `markSourceChanged` saliendo temprano y marcando el cambio como pendiente. Esto es lo que convierte "editar la aplicación" en una pantalla con contenido real |
| Acciones (`rebuild`, `redeploy`, y más adelante `rollback`) | ✅ No son campos, son verbos — y es lo que la gente quiere de verdad |
| Valores de secrets | ✅ Editables, en la Fase 6 |

Más adelante, la versión honesta de "editar la configuración" es **abrir un PR contra tu
`podium.yaml`**, no un formulario que finge poseer el fichero.

#### UX destructiva

`Dialog`, **no un item de menú** — las acciones destructivas merecen una parada completa. Y que
**nombre cada consecuencia concretamente**: las N aplicaciones por su nombre, el namespace, la
base de datos. No "esto no se puede deshacer", sino *qué* no se puede deshacer. Escribir el nombre
del proyecto para habilitar el botón: sin soft delete de proyecto, es la única red que hay. Y
progreso guiado por el stream (`project.deleting → project.deleted`), porque desmontar un
namespace no es instantáneo y un spinner que se resuelve con un 202 miente.

**Hito:** renombrar un proyecto, repuntarlo a otro repo, borrar una aplicación y ver desaparecer
de verdad sus recursos, y borrar el proyecto entero dejando el clúster limpio —
`kubectl get ns,application,cluster` sin restos.

---

### Fase 5 — Repos privados de verdad

*Semanas 12–14 · ~32 h · la fase más larga, y la única que toca los cinco servicios*

Hoy hay **un solo PAT compartido** en tres sitios, el provider está fijo a `'github'` y la rama
está **fija a `main`**. Y el agujero que mata la historia entera: `build-runner` clona sin
credenciales, así que en un repo privado la lectura del manifiesto funciona (API + token) y el
build muere al clonar — después de que la UI ya haya dicho que todo va bien.

| Pieza | Decisión |
|---|---|
| Permisos de la App | **Contents: read-only** y **Metadata: read-only**. Nada más. Opcional después: *Commit statuses: write*, para publicar el estado del deploy sobre el commit — gran UX por casi nada. **No** se pide `Pull requests: write`: el "camino plataforma" de Remediation abre PRs contra el repo de Podium, y esa es otra credencial distinta |
| Eventos de webhook | `push`, `installation`, `installation_repositories`, `repository`. Secretos (`APP_ID`, `PRIVATE_KEY`, `WEBHOOK_SECRET`) en un Secret `github-app` |
| Dónde vive la instalación | **BC nuevo `Integration`**, aggregate `GitHubInstallation{teamId, installationId, accountLogin, repositorySelection, suspendedAt}`. **No en `AppSource`**: una instalación cubre N repositorios, así que guardar `installationId` por AppSource crearía N escritores de la misma verdad — exactamente lo que el context map prohíbe. **Tampoco en `Team`**, que está cerrado como nombre + miembros y metérle credenciales de proveedor rompe su discovery |
| Minteo de tokens | `firebase/php-jwt` **ya es dependencia**, no hace falta paquete nuevo: JWT de App RS256 a 9 minutos → `POST /app/installations/{id}/access_tokens` **reducido por llamada** (`repositories: [ese repo]`, `permissions: {contents: read}`) → cacheado en Redis con `symfony/cache` a 50 minutos |
| Migración sin corte | Puerto compartido `SourceCredentialProvider` con adaptador **en cadena**: token de instalación si hay una que cubra al owner → si no, el PAT heredado → si no, anónimo. Los proyectos públicos actuales siguen funcionando sin tocarlos, y el PAT se borra al cerrar la fase |
| La rama fija | `GitHubLatestCommitChecker::DEFAULT_BRANCH = 'main'` y no hay columna de rama en ninguna tabla: **un repo en `master` o `develop` está hoy silenciosamente roto**. Se añade `app_sources.branch`, se resuelve la real con `GET /repos/{o}/{r}` → `default_branch` al registrar, `Project` lleva la rama visible para el usuario, y el webhook filtra por `refs/heads/{branch}` |
| Webhooks | `POST /api/webhooks/github`, público en `security.yaml` pero autenticado por HMAC `X-Hub-Signature-256` con `hash_equals`; idempotente por `SETNX` del `X-GitHub-Delivery` (GitHub reintenta); **despacha y devuelve 202 en milisegundos**. Nunca la cadena en línea: GitHub corta a los 10 s y desactiva el webhook |
| El poller | ✅ **Se queda, degradado a reconciliador.** Las entregas de webhook se pierden y es lo único que lo caza. Pasa de `* * * * *` a `*/10`, por lotes con `findStale(limit, olderThan)`, con jitter, y saltándose las fuentes cuyo proyecto tenga instalación sana y entrega reciente. Hoy recorre **todos** los AppSource cada minuto con un PAT compartido, y 5 000 peticiones/hora es un techo duro que llega solo con crecer |
| ⚠️ **El `git clone` sin credenciales** | **Descartado**: meter el token en `BuildJobRequested.envVars` — acabaría en el `env:` del Job, legible por cualquiera con `pod get` en `podium-build`, y persistido en Redis hasta el `XDEL`. **Elegido**: ruta interna `POST /internal/build-credentials {buildJobId}` donde **la capacidad se deriva del propio `buildJobId`** — Symfony carga el `BuildJob`, coge *su* `repositoryUrl` y mintea para ese único repo, así que el lanzador **no puede pedir un repo arbitrario**. El token llega como Secret con `ownerReferences` al Job (se recoge con él, sin código de limpieza), montado como fichero, y `entrypoint.sh` usa `git config credential.helper "store --file=…"` — así el token **no aparece en la URL que el script hace `echo`** y que la UI está a punto de mostrar en el visor de logs. Con `git init` + `git fetch --depth 1 origin <SHA>` + `checkout FETCH_HEAD`, además, clonar repos grandes deja de doler |
| Cerrar `/internal` | `php-ingress` es hoy `path: /` con `Prefix`: **todo lo que Symfony expone es alcanzable desde internet**. Se aprieta a `/api`, más un firewall `^/internal` con secreto compartido y su `NetworkPolicy` restringida a `build-launcher` |

Frontend de la fase:

| Pieza | Detalle |
|---|---|
| `/settings/integrations` | "Conectar GitHub" → ruta del BFF que **firma un `state` en servidor** y redirige a `installations/new`. Ese `state` es la única defensa CSRF del flujo, y es justo lo que se salta porque el camino feliz funciona sin él. El callback valida el `state`, envía la instalación a Symfony y vuelve. `setup_action=request` (aprobación pendiente del admin de la organización) se trata como estado propio, "pendiente de aprobación": es frecuente y si no se modela parece un fallo |
| Estado conectado | Lista de instalaciones (cuenta, avatar, "todos los repos" vs "N seleccionados"), "Gestionar en GitHub" — **la selección de repos vive en GitHub, no se reconstruye** — y "Desconectar" avisando de qué proyectos se quedan sin origen |
| El selector de repo | El campo de texto libre de `project-form.tsx` se sustituye por: instalación → **combobox de repositorios** (con búsqueda filtrada en servidor; `cmdk` ya está instalado y el team switcher es el patrón a copiar) → rama, por defecto la del repo. Con escotilla "pegar una URL pública" tras un disclosure, para quien no quiera instalar nada |
| ⭐ **Comprobación previa del `podium.yaml`** | Al elegir repo y rama: *"`podium.yaml` encontrado — 2 servicios: `app`, `frontend`"* o *"no se encontró `podium.yaml` en esta rama"* con una plantilla copiable. **Un repo sin manifiesto produce cero aplicaciones, y hoy ese resultado es indistinguible de una plataforma rota.** Es el arreglo del "desplegué y no pasó nada", que va a ser el informe número uno |
| Catálogo de plantillas | Pantalla con los 10 pares soportados, que dobla como documentación del `podium.yaml` y convierte el caso `Unsupported` de la Fase 0 en algo que el usuario arregla solo. Incluye la nota no obvia de la guía: en go y rust el "framework" nombra la cadena de build, no la librería (gin → `stdlib`, axum → `cargo`) |
| Puerta de seguridad | **El selector no ofrece repos privados hasta que el token de instalación llegue de verdad al builder.** Listar privados y luego fallar el build es peor experiencia que el campo de texto |

**Hito:** conectar un repositorio privado desde la UI, hacer push, y verlo construir y desplegar en
vivo.

---

### Fase 6 — Abrir la puerta

*Semanas 15–17 · ~30 h · la fase que convierte "funciona" en "se puede invitar a un desconocido"*

| Pieza | Decisión |
|---|---|
| Dónde viven los secrets | **Solo** como claves de un Secret nativo `podium-secrets` en el namespace del tenant. Postgres guarda **nombres y metadatos** (`created_at`, `created_by`, `last_rotated_at`), nunca valores: así un dump de Postgres **no es un dump de credenciales**. Es exactamente lo que ya documenta `docs/podium-config/podium-yaml-guide.md` |
| Modelado | Entidad `ProjectSecret` **dentro del aggregate `Project`**, no un BC nuevo. Pasa el propio test del context map — *"¿qué decisión tomaría ese BC que el aggregate que ya lo contiene no pueda tomar?"*: tiene reglas (forma del nombre `[A-Z_][A-Z0-9_]*`, escritura ciega, no se borra si el `podium.yaml` vigente lo referencia) pero **no identidad propia**, y el namespace en que vive *es* el hash del proyecto. Eventos `ProjectSecretDeclared`/`Revoked` — **metadatos, nunca el valor en un bus** |
| Quién escribe el Secret | El mismo `tenant-gateway` de la Fase 3, con el mismo esquema de token firmado. Es una llamada sincrónica de infraestructura, no comunicación entre BCs — misma categoría que `build-launcher` llamando a la API de Kubernetes, así que la regla de "solo por eventos" queda intacta. RBAC **afilado**: `create/update/patch/delete`, `get` restringido a `resourceNames: ["podium-secrets"]` y **sin `list` en absoluto** — un gateway comprometido no puede leer la contraseña de CNPG ni enumerar los secrets de nadie. Orden de escritura: **k8s primero, metadatos después**, para que un fallo no deje un nombre fantasma; más un cron que compara nombres declarados con claves reales |
| Cómo se enteran los deploys | **No se enteran, y ahí está la elegancia.** El `podium.yaml` es la fuente de los `${NAME}` y Deploy **ya lo lee**. Un VO `EnvDeclaration` separa literales (`NODE_ENV: production`) de referencias (`API_KEY: ${STRIPE_KEY}`) y **valida la forma, nunca la existencia ni el valor** — tal cual manda la guía. `DeployValues` gana `env` y `envFrom` con `secretKeyRef`. Deploy nunca consulta al dueño de los secrets ni ve un valor: k8s resuelve al arrancar el pod, y una clave que falta da un `CreateContainerConfigError` claro, que es el comportamiento deseado documentado ("sin valores por defecto") |
| El chart que **fusiona** | `helm/podium-app/templates/deployment.yaml` construye `env:` dentro de dos ramas `if / else if` mutuamente excluyentes, así que una env var de equipo añadida a la ligera **sustituiría** las de la base de datos — el propio `helm/podium-app/README.md` avisa: "hay que **fusionar** ambas listas, no sustituir una por otra". Solución: un named template `podium-app.env` en `_helpers.tpl` que concatena las tres fuentes con orden determinista y **falla el render ante una colisión** en vez de sombrear `DATABASE_URL` en silencio |
| `CHART_VERSION` | Se sube **a mano** en `deploy/deploy-launcher/deployment.yaml` después de publicar el chart, y si no se publica **no falla nada visiblemente**: todos los tenants siguen renderizando la plantilla vieja. Es el peor tipo de fallo, y ya pasó una vez (`e665ec2`). Se automatiza en el job de publicación, en esta misma fase |
| Aislamiento del tenant | Las 5 `NetworkPolicy` validadas 11/11 **aplicadas de verdad**, desde el chart, tras un valor `tenantIsolation`; `resources`, `securityContext` y `automountServiceAccountToken: false` en el `Deployment` del tenant, que **hoy no tiene ninguno de los tres**; y etiquetas de Pod Security en el namespace — imposible con `CreateNamespace=true`, así que el chart tiene que plantillar el `Namespace` con sync-wave. Más `ResourceQuota` y `LimitRange` por tenant: hoy un `while(true)` de un usuario degrada a todos los demás |
| Invitar miembros | `Team::addMember()`/`removeMember()` con tokens de invitación. Va aquí y no antes como deseo suelto: **"editar y borrar tu proyecto" no significa nada si un equipo solo puede tener a su creador para siempre**, que es literalmente el caso hoy. Arrastra el endurecimiento de Keycloak — un service account `podium-registrar` con `manage-users` **solo en el realm `podium`**, sustituyendo al `master`/`admin`/`admin` que el registro usa hoy — porque invitar necesita buscar usuarios por email. Y hasta que exista, **no se entrega un formulario de invitación que no invite** |

⭐ La validación que corona la fase: cruzar los `${NAME}` que el `podium.yaml` del servicio
referencia con los secrets realmente subidos, y avisar *"tu `podium.yaml` referencia `API_KEY`
pero no lo has subido — el despliegue va a fallar"*, con un CTA en línea para crearlo. Convierte
un fallo garantizado y silencioso en un clic. El snapshot del yaml ya existe en el dominio
(`BuildYamlSnapshot`), solo hay que exponerlo.

**Hito:** un usuario externo se registra, invita a alguien, añade su `API_KEY`, la referencia en su
`podium.yaml`, hace push — y el contenedor arranca con su secret **y** con la env de su base de
datos, y sus pods **no** pueden alcanzar `hostium`. Comprobado con el `test.sh` que ya existe.

---

## 6. Mis sugerencias que no pediste

Ordenadas por valor. Cada una con su justificación en una línea y la fase donde se paga.

| # | Sugerencia | Por qué | Fase |
|---|---|---|---|
| 1 | **Aplicar de verdad las `NetworkPolicy` que ya están validadas** | Hoy el pod de un desconocido puede hablar con el Postgres y el Keycloak de `hostium`. Es el riesgo más grave del sistema **y el arreglo ya está escrito y probado 11/11** — solo hay que moverlo al chart | 6 |
| 2 | **Apretar `php-ingress` de `path: /` a `/api`** | Todo lo que Symfony expone es alcanzable desde internet, incluido lo interno que se añada este trimestre. Es prerrequisito de la Fase 5, no una mejora | 5 |
| 3 | **Añadir el finalizer de ArgoCD al crear el CR** | Sin él el botón de borrar no borra: deja el `Deployment`, el `Service`, el `Ingress` y la base de datos corriendo, y facturando | 4 |
| 4 | **El build corre `privileged` sobre código ajeno** | Escape de contenedor = root en el nodo, y vamos a construir repos privados de desconocidos. Camino: buildah rootless con user namespaces, o nodo dedicado con seccomp | 🕓 Después |
| 5 | **`ServiceRetired`** | Quitar un servicio del `podium.yaml` deja hoy un zombi desplegado para siempre. Repuntar un repo lo convierte en el caso normal | 4 |
| 6 | **Romper el bucle de `failed_events`** | Consumido sin `retry_strategy`: un mensaje envenenado quema CPU indefinidamente y entierra todos los demás fallos | 1 |
| 7 | **Ningún pod de tenant tiene `resources`** | Un bucle infinito de un usuario degrada a todos. Un `LimitRange` con defaults lo tapa aunque el chart no lo pida | 6 |
| 8 | **Readiness real para `php` y el worker** | Ambas sondas de `php` responden un `OK` estático de Caddy en `:9090`, así que un pod con Postgres o Redis caído se queda en el balanceador para siempre; y la del worker es un `pgrep` | 0 |
| 9 | **Los 41 tests de PHP no corren en CI y el frontend no tiene ninguno** | Y el repo ya lleva **dos** bugs documentados que un test de navegador habría cazado (el `onSelect` del team switcher, el `null` de `proxy.ts`) | 0 |
| 10 | **Retención de registro y de `build_jobs`** | Cada push escribe un tag inmutable para siempre y la tabla crece sin techo. Mejor una política que un disco lleno decidiendo por ti | 🕓 Después |
| 11 | **Validar `param_schema` de verdad** | La columna y el VO `ParamField` existen, se siembran vacíos y **nunca se comprueban**, así que un `podium.yaml` malformado falla dentro de buildah en vez de al registrar. Convierte toda una clase de fallos confusos en mensajes legibles | 5 |
| 12 | **Rate limiting en el webhook y en toda escritura, más log de auditoría** | Borrar un proyecto destruye una base de datos y hoy no deja rastro de quién lo hizo | 4 |
| 13 | **Paleta de comandos (⌘K)** | `cmdk` ya está instalado: saltar a proyecto o app, cambiar de equipo, abrir logs, cambiar tema. La respuesta más barata a "hazlo interactivo", y un camino de teclado de verdad | 2 |
| 14 | **Estado "dormida / despertando"** | Con el activator, una app `Deployed` con cero réplicas es hoy indistinguible de una rota — y ese es el estado normal de casi todas | 3 |
| 15 | **Sin mutaciones optimistas: que el stream sea la confirmación** | El `addProject()` optimista actual es justo el patrón que esconde los fallos, y con el stream la optimismo es redundante | 1 |
| 16 | **Contraste de los 7 tonos de estado, y móvil acotado con honestidad** | `application-state-tones.ts` elige los pares oklch a mano y alguno está justo. Y el objetivo móvil declarado: tablet bien, teléfono como **triaje de solo lectura** — un alcance dicho vale más que un responsive a medias | 2 |
| 17 | **Sustituir el paquete `cn` por el helper local de siempre** | `src/lib/utils.ts` reexporta `cn@0.3.0`, un micropaquete sin auditar en el camino de **cada `className` de la app**. Seis líneas locales lo eliminan | 1 |
| 18 | **`X-Request-Id` devuelto por el BFF y mostrado en los estados de error** | Es la diferencia entre una conversación de soporte y una adivinanza | 2 |

---

## 7. Deuda conocida y defectos abiertos

Los que ya estaban documentados en prosa dentro del repo, más los encontrados al planificar.

| Defecto | Dónde vive documentado | Se paga en |
|---|---|---|
| Un mensaje que falla deja el deploy colgado y nadie lo relee (sin `XACK`, lectura con `>`, sin `XAUTOCLAIM`) | `deploy/deploy-launcher/README.md:49-67` | Fase 1 |
| Los dos lanzadores procesan `Count: 1` sincrónicamente — un build a la vez | `services/build-launcher/`, `deploy-launcher/` | 🕓 Después |
| `failed_events` se consume sin `retry_strategy`: bucle de mensaje envenenado | `messenger.yaml`, `deploy/base/worker.yaml` | Fase 1 |
| `POST /api/teams` y `POST /api/projects` públicos y sin autenticar | `security.yaml:26-27` | Fase 0 |
| Registro de usuarios con el `admin`/`admin` del realm master | `services/web/src/lib/auth/config.ts` | Fase 6 |
| Sin rotación de refresh token: el access token de 5 min caduca en silencio | `services/web/src/lib/auth/session.ts` | Fase 0 |
| Liveness y readiness de web apuntan las dos a `/login` | `deploy/base/web-deployment.yaml` | Fase 0 |
| Las 5 `NetworkPolicy` son una fixture que nada aplica; `03-activator-netpol.yaml` además está mal | `docs/hackathon-netpol/`, `deploy/activator/README.md:40` | Fase 0 (fixture) y 6 (aplicarlas) |
| El `Deployment` del tenant no tiene `resources`, `securityContext` ni `automountServiceAccountToken` | `helm/podium-app/templates/deployment.yaml` | Fase 6 |
| Las env vars del equipo no llegan al contenedor, y añadirlas a la ligera pisaría las de la base de datos | `helm/podium-app/README.md` | Fase 6 |
| `CHART_VERSION` se sube a mano y su olvido no falla visiblemente | `deploy/deploy-launcher/deployment.yaml`, `e665ec2` | Fase 6 |
| El poller recorre **todos** los AppSource cada minuto con un PAT compartido | `PollAppSourcesCommand.php` | Fase 5 |
| `GitHubLatestCommitChecker` tiene la rama fija a `main` | `GitHubLatestCommitChecker.php:22` | Fase 5 |
| `build-runner` clona sin credenciales | `services/build-runner/entrypoint.sh:14` | Fase 5 |
| Sin finalizer en el CR de ArgoCD: borrar deja huérfanos | `services/deploy-launcher/internal/argospec/argospec.go` | Fase 4 |
| `knownServiceNames` nunca se encoge | `services/php/src/Project/Domain/Project.php` | Fase 4 |
| `Team` no tiene `addMember()`: la membresía es del creador para siempre | `services/php/src/Team/Domain/Team.php` | Fase 6 |
| `application_history_logs`, `build_jobs.error_message` y `deploy_attempts.error_message` sin ruta de lectura | `config/services.yaml:33-35` | Fase 0 |
| `param_schema` existe, se siembra vacío y nunca se valida | `SeedTemplatesCommand.php:75` | Fase 5 |
| El change de OpenSpec nunca se archivó; `CLAUDE.md` y `design.md` están obsoletos | `openspec/`, `CLAUDE.md` | Fase 0 |

---

## 8. Riesgos y mitigación

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| **La Fase 1 se atasca**: cambia la capa de datos y añade streaming a la vez | Alta | Se entrega en dos mitades y la primera **ya arregla la queja de cabecera sin tocar el backend**. Si el streaming se complica, el botón "Actualizar" y `refetchOnWindowFocus` ya están entregados y la fase aporta valor igual |
| **10 h/semana se evaporan** con una semana mala, un viaje o un imprevisto | Alta | El orden de recorte está fijado en §4, y las dos cosas que nunca se recortan están nombradas. Cada fase termina en un hito demostrable, así que parar entre fases no deja nada a medias |
| Una regresión silenciosa en PHP o en el frontend, sin CI que la cace | Alta → baja tras la Fase 0 | `test-php` y `test-web` son la primera pieza del plan, y el test de Playwright del team switcher se escribe **antes** de la migración que podría romperlo |
| El tiempo real se ve "vivo" pero pierde eventos | Media | Es justo lo que evita el hub de 1 réplica (§3) en vez de un stream por pod. Y la degradación es visible: punto de estado en el topbar, e invalidar todo al reconectar |
| La GitHub App se alarga y se come la Fase 6 | Media | Es la fase más larga y está puesta **antes** de la de abrir la puerta a propósito: si se desborda, lo que se retrasa es la inauguración, no una pieza a medias en producción |
| Un usuario externo tumba el clúster antes de que exista el aislamiento | Media, y grave | **No se invita a nadie hasta cerrar la Fase 6.** Es la puerta, literalmente: el hito de la fase es el `test.sh` en verde |
| El agente de octubre tira del plan hacia delante | Media | Sus dos cimientos (activity en la Fase 0, logs en la Fase 3) están en la primera mitad de la ventana justamente por eso. Adelantarlo cuesta retrasar de la 4 en adelante, y eso se decide con el plan delante |
| Mercure se queda corto de escala | Baja | El contrato no cambia: el mismo tópico y el mismo payload los puede servir un `event-gateway` en Go o `mercure.bolt` con PVC. La puerta está diseñada; no hace falta cruzarla ahora |

---

## 9. Fuera de alcance (decidido, no se rediscute)

Dominios propios · facturación · HPA · multi-clúster · buildpacks o Dockerfile automático (el
equipo declara lenguaje y framework, la plantilla ya existe) · segundo proveedor de repos
(GitLab — el dominio ya es agnóstico, sería un adaptador nuevo, no un cambio de modelo) · Loki y
observabilidad OTel · el activator saliendo de su estado inerte · y **los tres agentes dentro de
esta ventana**.

---

## 10. Lo que viene después

### Los tres agentes

Los tres están fuera de la ventana por decisión explícita, y los tres dependen de cosas que sí
entran: el `activity` de la Fase 0 y los logs de la Fase 3 **son su cimiento**, y no es
casualidad que estén en la primera mitad del plan.

| Agente | Qué hace | Qué necesita que ya existirá |
|---|---|---|
| **Agente de ensayo** (el del pitch original, BC Remediation) | Ejecuta el guion de demo del equipo contra la app desplegada, detecta lo que falla, y repara lo reparable — env var, puerto, memoria — antes de que llegue el jurado | Logs (Fase 3), timeline de estado (Fase 2), y las acciones `rebuild`/`redeploy` (Fase 2). **Puede adelantarse si hay evento en octubre** — es el único de los tres con esa nota |
| **Agente que propone mejoras arquitectónicas** | Lee el proyecto desplegado y propone cambios de arquitectura | El snapshot del `podium.yaml` y el catálogo de plantillas (Fase 5) |
| **Agente que, al saltar un bug, usa los logs para proponer la solución** | Ante un error en tiempo de ejecución, lee los logs y propone la corrección | Logs de runtime (Fase 3), y aquí sí empieza a hacer falta Loki: historial más allá de la vida del pod |

Y ya está decidido, desde `context-map.md`, cómo se entrega cada corrección: **camino equipo**
(la culpa está en su código) → plan de corrección visible y copiable en su panel, cero acceso de
escritura fuera de Podium; **camino plataforma** (la culpa está en la imagen o la plantilla de
Podium) → ejecución autónoma que acaba en un **PR contra el repo de Podium, nunca un merge
directo**.

Nota honesta sobre el panel ✦: su primer trabajo real y **sin LLM** debería ser
*"¿por qué falló mi build?"*, respondido con el `errorMessage` persistido y el log — un explicador
determinista dentro de una carcasa de chat. Un chat que finge ser un chat es el peor de los falsos
de §1.

### El resto

BC Notification más allá del feed (avisar de verdad a un Slack o un correo) · Loki cuando haga
falta historial más allá de la vida del pod · build no privilegiado · retención de registro ·
rollback a una versión anterior (barato **después** del timeline, caro antes) · segundo proveedor
de repos.

---

## 11. Cómo se verifica que una fase está cerrada

Cada hito, con el gesto o el comando concreto que lo comprueba. Sin captura de pantalla no hay
fase cerrada.

| Fase | Verificación |
|---|---|
| **0** | `docker compose exec frankenphp vendor/bin/phpunit` en verde **y el pipeline de GitLab fallando** si se rompe un test a propósito. `curl -X POST /api/teams` sin token → 401. Un build fallido muestra su `errorMessage` en la UI. `npx playwright test` en verde |
| **1** | Registrar un proyecto de dos servicios y cronometrar: la primera tarjeta en ~1 s, y las transiciones llegando solas. Dejar la pestaña una hora y comprobar que sigue `en vivo`. Matar el pod del hub y comprobar que el topbar pasa a `reconectando…` y que al volver la lista está al día. `grep -r dicebear services/web/src` y `grep -r mock-project-description services/web/src`, ambos sin resultados |
| **2** | Provocar un build fallido (por ejemplo, un `podium.yaml` con un framework inexistente) y leer motivo y cronología en la página de detalle. Pulsar "Reintentar" y ver el nuevo intento en el timeline. `Despliegues` mostrando actividad de varios proyectos |
| **3** | Tailear en vivo una app corriendo y ver aparecer líneas nuevas. Abrir un build de hace días y leer su salida. Reventar un contenedor y leerlo con `?previous=1`. Comprobar que un token de log de otro equipo devuelve 403, y que un namespace de la lista negra devuelve 403 aunque el token esté bien firmado |
| **4** | Renombrar, repuntar a otro repo y ver re-desplegar. Borrar una aplicación y después `kubectl get deployment,svc,ingress,cluster -n <hash>` sin restos. Borrar el proyecto y `kubectl get ns <hash>` → `NotFound`. Quitar un servicio del `podium.yaml` y ver su `Application` retirada |
| **5** | Conectar un repo **privado** desde la UI, hacer push, y verlo construir y desplegar. Comprobar que el log del build **no contiene el token**. Elegir un repo sin `podium.yaml` y ver el aviso previo. Borrar el PAT heredado del clúster y comprobar que todo sigue funcionando |
| **6** | `./docs/hackathon-netpol/test.sh` en verde contra un namespace de tenant **real**, no contra la fixture. Añadir un secret, referenciarlo, hacer push, y `kubectl exec` en el pod para ver la variable **y** la de la base de datos. Invitar a una segunda cuenta y comprobar que ve el equipo. Intentar `curl` al Postgres de `hostium` desde un pod de tenant → sin respuesta |

---

**Última revisión:** 21 de septiembre de 2026 · escrito el día siguiente al hackathon, con la
plataforma ya en producción y `CLAUDE.md` todavía afirmando que este repo no tiene código.
