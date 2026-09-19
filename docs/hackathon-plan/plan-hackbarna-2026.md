# Plan de ataque — HackBarna @ AI Summit Barcelona 2026

**Fechas:** sábado 19 – domingo 20 de septiembre 2026, Norrsken House
**Ventana en el venue:** sáb 11:00 → 23:00 (puertas cerradas) · dom 9:00 → 11:00 (entrega de código) · demos 14:00 · jurado 16:00
**No hay hacking nocturno en la sala.** Lo que no esté hecho el sábado a las 23:00 se hace en remoto o no se hace.
**Equipo:** máx. 3 · formación de equipos el sábado por la mañana · **Regla clave:** el código del proyecto se escribe durante el evento; infraestructura y plantillas previas son herramientas (confirmar con organización el sábado a primera hora)
**Público:** no-code, vibe-code y full-code. Los equipos no-code ya tienen hosting; el mercado real son los full-code con backend fuera de JS (15–25 equipos estimados).
**Estado:** plaza confirmada ✅

---

## 1. Qué es el proyecto (una frase)

> **Podium** *(nombre provisional)*: la plataforma de despliegue del hackathon — cualquier repo, cualquier lenguaje, URL pública en 60 segundos — con un agente que ensaya tu demo y la repara antes de que el jurado llegue a tu mesa.

### Lo que hace, visto por un equipo cualquiera del hackathon

1. Pega la URL de su repo, elige lenguaje/framework, pega sus API keys de sponsors.
2. En ~60 s tiene `https://{hash}.apperture.dev` viva, con TLS, usable como webhook público (Vonage) y aislada del resto.
3. Escribe su guion de demo en lenguaje natural. El agente lo ejecuta contra la app, detecta lo que falla, lee logs y eventos, corrige lo corregible (env var, puerto, memoria), reintenta, y si no puede, le dice exactamente qué está roto.
4. Un panel muestra el estado de todas las demos desplegadas — y el equipo puede ver si la suya sigue viva cuando pasa el jurado.

### Por qué encaja con el jurado

- **Necesidad demostrada en la sala:** el domingo a las 14:00 hay N demos de otros equipos corriendo encima. Nadie más puede enseñar adopción real durante el evento.
- **Tema "agentic":** el agente tiene un objetivo (demo sana), un contrato cerrado de herramientas, y un bucle percibir → actuar → verificar → escalar. No es un chatbot con un prompt largo.
- **Sponsors:** Vonage (webhooks públicos), Nebius/Fal (las apps que se despliegan los usan), Mastra (framework del agente si se va en TS).
- **Credibilidad técnica:** aislamiento de producción (NetworkPolicy validada 11/11, quotas, Pod Security), abierto a desconocidos y aun así seguro.

---

## 1b. Retos publicados y encaje

| Reto | Premio | Encaje | Acción |
|---|---|---|---|
| **Nebius** — Build, adapt and ship con Token Factory. Uso significativo que aporte a la funcionalidad central o mejore medible calidad / grounding / **evaluación** / velocidad / coste / **fiabilidad** | $1.000 / $500 / $100 · 2 mentores en sala | **Directo.** El agente de ensayo infiere en Token Factory y su función es evaluar fiabilidad | Token Factory como único proveedor de inferencia del agente. Medir algo: p. ej. coste/latencia de triaje con modelo pequeño vs diagnóstico con modelo grande |
| **Cognition** — Build anything (open source + README + buena demo) | Créditos Devin | Trivial | Repo público desde el minuto uno, README de 3 pasos |
| **Titan OS** — agente conversacional de recomendación para TV | Philips Ambilight 43" por miembro · 4 mentores | Ninguno como proyecto. **Sí como cliente:** será el reto más concurrido y todos necesitan backend público | Plantilla "backend para agente de TV" (TMDB + Token Factory cableados). Adopción garantizada |
| **Vonage** (Gold, sin reto publicado, 3 dev advocates) | — (¿se anuncia el sábado?) | Alto: webhooks públicos | Plantilla "app con webhook de Vonage". Preguntar a los DevRel el sábado si hay premio |
| **Premio general** — juez listado: VC (Bynd VC). 1º: pitch en la competición de startups del AI Summit, MacBook, 3 gold tickets, $1.000 Nebius | | **Lente de inversor**: problema, producto, tracción, mercado | Pitch con tracción en la sala + "quién paga": bootcamps (Le Wagon es community partner) y organizadores de hackathones |

**Doble premio Nebius:** el hackathon global de Nebius (Devpost, hasta 30 oct) exige correr en Token Factory con al menos un modelo open source de NVIDIA. Usar Nemotron como modelo del agente cumple ambos a la vez. Confirmar el sábado con los mentores de Nebius si HackBarna cuenta como ciudad participante.

**Mastra** no tiene reto ni mentores listados. Elegir stack por velocidad del equipo, no por bounty.

**Criterios de jurado:** no publicados. Asumir los de un VC: claridad del problema, demo que funciona, tracción, mercado.


### Núcleo (sin esto no hay demo)

| Pieza | Qué hace | Hito |
|---|---|---|
| **Poller** | Cada 1 min: por cada proyecto trackeado, `GET` al último commit del repo (API de GitHub, con token — sin auth el rate limit de 60/h no aguanta 15-25 proyectos) → compara con el SHA ya desplegado (guardado como anotación en el propio `Application`, sin base de datos aparte) | Un commit nuevo dispara un build en <60s sin que el equipo haga nada |
| **Build** | Si hay SHA nuevo: `Job` con buildah, clona el repo, usa su Dockerfile o la plantilla del lenguaje detectado, push al registro con tag = SHA corto | Imagen construida y en el registro |
| **Deploy path** | `kubectl patch application` con el `image.tag` nuevo → auto-sync de ArgoCD aplica al instante → réplica fija arriba. Ruta alterna para equipos avanzados: dar directamente una imagen ya construida, sin repo ni poller | Commit → build → deploy → URL viva, sin intervención del equipo |
| **Aislamiento** | Namespace por proyecto con quota, LimitRange, las 5 NetworkPolicy validadas (+1 condicional: ingress desde el namespace del activator, solo si el stretch de escalado a 0 sale adelante), PSS, TTL | Validado — parametrizar `{hash}`; la regla extra solo si se activa el activator |
| **Secrets** | Env vars por proyecto guardadas como Secret, inyectadas en el Deployment, destruidas con el namespace | Una key de sponsor usada por una app desplegada |
| **Status board** | Página pública: proyectos, URL, salud (readiness), último deploy, contador de adopción | Visible desde el móvil del jurado |
| **Agente de ensayo** | Tools: `get_build_logs`, `http_probe`, `get_status`, `get_logs`, `get_events`, `patch_env`, `patch_resources`, `redeploy`, `escalate`. Bucle con máx. 3 intentos | Un self-heal completo en directo con un fallo provocado — de build o de despliegue |

### Deseable (solo si el núcleo está cerrado el sábado a las 20:00)

- Trazas OTel automáticas de llamadas LLM por proyecto (tokens, coste, latencia) — tu trilogía OTel en versión fin de semana.
- CLI de una línea (`podium deploy .`).
- Notificación al equipo (Slack/Discord) cuando su demo cae.

### Fuera de alcance (decidido, no se discute el sábado)

- Cuentas/login (token por proyecto y ya) · Dockerfile automático o buildpacks (el equipo elige lenguaje/framework, la plantilla ya existe) · webhooks de GitHub (se resuelve con polling) · dominios propios · HPA · facturación · multi-clúster.

---

## 3. Decisiones de arquitectura (fijar ANTES del sábado, no el sábado)

### Poller: build y deploy sin pipeline, al estilo Vercel/Netlify

Ni el equipo ni Podium dependen de que alguien configure CI/CD. Podium mismo vigila cada repo trackeado y decide cuándo construir — igual que Vercel/Netlify, pero por sondeo en vez de webhook: **más simple de montar en 24h** (un webhook necesita endpoint público con verificación de firma HMAC y que el equipo dé permisos de admin sobre su repo; el sondeo solo necesita lectura). El coste es latencia — hasta ~60-90s en vez de instantáneo — asumible en una demo de hackathon, y es justo lo que hace accesible la plataforma a los equipos no-code/vibe-code que no van a escribir su propio pipeline.

**Estado, sin base de datos aparte:** el propio `Application` de ArgoCD guarda anotaciones (`podium.dev/repo`, `podium.dev/branch`, `podium.dev/deployed-sha`, `podium.dev/attempted-sha`). El poller solo necesita listar `Applications` con la etiqueta `podium.dev/managed: "true"` — Kubernetes hace de base de datos.

**Bucle, cada 1 min (proceso propio del control plane, no un CronJob — menos piezas que depurar el sábado):**

1. Por cada `Application` trackeada: `GET /repos/{owner}/{repo}/commits/{branch}` a la API de GitHub, **con token** — sin autenticar el límite es 60 req/h y con 15-25 proyectos sondeados cada minuto se agota en los primeros minutos.
2. Si el SHA difiere de `deployed-sha` **y** de `attempted-sha` (para no reintentar en bucle un build ya roto — solo se reintenta cuando llega un commit nuevo): lanza un `Job` de build, sin bloquear el sondeo del resto de proyectos.
3. `Job`: clona el repo, usa su Dockerfile o la plantilla del lenguaje elegido, `buildah build` + push al registro con tag = SHA corto. Actualiza `attempted-sha`.
4. Si el build sale bien: `kubectl patch application` con el `image.tag` nuevo → auto-sync despliega → actualiza `deployed-sha`. Si falla: se queda en `attempted-sha` y ahí es donde entra el agente — **diagnóstico de build**, no solo de despliegue, y es un ángulo de demo más universal (un error de compilación lo entiende cualquiera en la sala, un `NetworkPolicy` mal puesto no).

**Ruta rápida para equipos avanzados:** si un equipo prefiere darte directamente una imagen ya construida, se salta poller y build por completo — mismo `Application`, mismo patch, sin repo de por medio. Las dos rutas conviven sin conflicto.

### Deploy: ArgoCD Application apuntando a un chart Helm en el registro OCI — sin git

Un chart de Helm genérico ("podium-app"), escrito una vez, plantilla lo mismo que iba en la base kustomize: Namespace, ResourceQuota, LimitRange, las 5 NetworkPolicy, Deployment, Service, Ingress — parametrizado por `values.yaml`.

Se empaqueta y se sube al mismo registro OCI donde ya van las imágenes de los equipos (`helm push podium-app-0.1.0.tgz oci://registry.apperture.dev/charts`), y se registra ese repo en ArgoCD una vez (Secret `type: helm`, `enableOCI: "true"`).

**Por cada equipo, el control plane crea/actualiza un `Application`** — un CRD normal en el namespace `argocd`, aplicado con `kubectl apply`, referenciando ese mismo chart con `helm.valuesObject` específico del tenant (imagen, tag, hash, host). Sin repositorio git en ningún punto del flujo — ni para el chart, ni para los valores por tenant.

```yaml
apiVersion: argoproj.io/v1alpha1
kind: Application
metadata:
  name: tenant-{hash}
  namespace: argocd
spec:
  project: default
  source:
    repoURL: registry.apperture.dev/charts   # sin oci://
    chart: podium-app
    targetRevision: 0.1.0
    helm:
      valuesObject:
        hash: "{hash}"
        image: { repository: "ghcr.io/equipo/app", tag: "abc123" }
        ingress: { host: "{hash}.apperture.dev" }
  destination:
    server: https://kubernetes.default.svc
    namespace: "{hash}"
  syncPolicy:
    automated: { prune: true, selfHeal: true }
    syncOptions: [CreateNamespace=true]
```

**El control plane se reduce a:** conseguir la imagen (build propio, o el equipo la sube ya construida) → `kubectl apply` de este `Application` (nuevo o actualizado) → ArgoCD sincroniza. **Redeploy/parcheo del agente** = `kubectl patch application tenant-{hash}` con nuevos `valuesObject` (imagen, env, límites). **Rollback** = historial de sync propio de ArgoCD por `Application` (`argocd app rollback tenant-{hash} <n>`), sin depender de git.

**Cerrar el círculo commit → imagen → deploy:** ya resuelto arriba — el poller detecta el SHA nuevo, dispara el build, y al terminar hace el mismo `kubectl patch` de `image.tag` que usa el agente para sus parcheos. Auto-sync aplica el cambio al instante. (Se consideró Argo CD Image Updater vigilando el registro directamente — método `argocd`, sin git — pero el poller propio ya cubre esa función y además dispara el build, que Image Updater no hace.)

**Escalado a 0 — tres niveles, del más seguro al más ambicioso:**

1. **Núcleo (siempre activo):** réplica fija durante el evento. Predecible para la demo, cero piezas nuevas.
2. **Fallback intermedio:** reaper simple — `kubectl scale --replicas=0` sobre apps sin tráfico reciente (barrido periódico), y un botón "despertar" explícito en el status board que escala a 1 y muestra una pantalla de espera. Nada de tráfico en vivo pasando por un componente nuevo.
3. **Stretch, solo después del congelado de las 22:30 — activator propio en Go** (sustituye al spike de KEDA HTTP, no se suman): un servicio que enruta después del ingress, mantiene la petición mientras el pod despierta, y hace proxy en cuanto está listo.
   - Tabla de rutas por watch/informer sobre Deployments/Services etiquetados (`podium.dev/managed: "true"`), sin acoplarse al control plane.
   - `golang.org/x/sync/singleflight` por hostname — una sola operación de escalado aunque lleguen varias peticiones concurrentes a la misma app fría.
   - Patch acotado a `.spec.replicas` con reintento ante conflicto de versión (puede chocar con el poller o el agente tocando el mismo Deployment).
   - Alerta si el arranque tarda más de 60s (no es un cold start normal — imagen sin cachear, pull fallido, crashloop, cuota agotada). Nueva superficie de diagnóstico para el agente.
   - La otra mitad, dormir: el propio activator ve cada request, así que es el sitio natural para llevar el timestamp de "última vez visto" por tenant y disparar el barrido de scale-to-0 — sin esto, el "despertar" nunca se ejercita.
   - NetworkPolicy: el tráfico llega al pod desde el namespace del activator, no directamente desde `ingress-nginx` — misma excepción de ingress que hacía falta para KEDA. Revalidar con `test.sh`.
   - **Interruptor de apagado obligatorio:** un flag que revierte el Ingress a apuntar directo al Service de la app, sin pasar por el activator. Es un componente nuevo en el camino de tráfico de las 15-25 demos a la vez — si falla durante horas de jurado, tiene que poder desactivarse al instante sin tocar nada más. Nunca se activa en horas de jurado a menos que lleve ya un rato funcionando sin sobresaltos.

**Nota (ADR de un párrafo):** se evaluó Knative Serving + Kourier (infra nueva sin validar, descartado) y un enfoque GitOps con repo de git + ApplicationSet (añadía una capa de commit/push innecesaria cuando ArgoCD ya soporta charts OCI directamente). Se elige Application → chart OCI por ser lo más simple que cumple el objetivo: nada de git, ni para el chart ni para el despliegue por tenant.


### Stack del control plane y del agente

Decisión por velocidad del equipo real, no por preferencia:

- **Si hay alguien fluido en TS:** control plane en Node (Fastify) + agente en **Mastra** (sponsor, bounty probable, tools tipadas y memoria de intentos out-of-the-box).
- **Si el equipo es PHP:** Symfony + FrankenPHP + `symfony/ai-bundle` — ya lo conoces del agente de Axistral, cero curva.

Regla: se decide el jueves 17 como muy tarde y se registra en un ADR de un párrafo. El sábado no se discute.

### Inferencia: Nebius Token Factory, modelo Nemotron

Único proveedor de inferencia del agente. Cumple el reto de Nebius (uso central) y el requisito del hackathon global de Nebius (modelo open source de NVIDIA). Medir y enseñar un número: latencia y coste del triaje de logs con un modelo pequeño frente al diagnóstico con uno grande.

### Contrato de herramientas del agente (hexagonal en miniatura)

El LLM decide *cuándo* y *en qué orden*, nunca *qué existe*. Cada tool es una función tipada con entrada/salida cerrada; ninguna recibe YAML libre ni comandos shell. `patch_env` y `patch_resources` solo tocan el overlay del propio namespace. `escalate` devuelve el diagnóstico al humano. Ese contrato es tu "domain contract before agent delegation" y es un punto del pitch.

### Escenarios de fallo provocados para la demo

No se confía en un fallo espontáneo en directo. Se preparan tres repos con fallos conocidos:

1. Env var que falta → la app arranca y peta al primer request → agente la detecta en logs y pide el valor / la añade.
2. Puerto mal expuesto → readiness nunca pasa → agente lo lee en eventos y corrige el Service.
3. Límite de memoria bajo → OOMKilled → agente sube el límite dentro de la quota y redespliega.

---

## 4. Infraestructura: checklist previo al evento

Todo esto es "herramienta previa", permitida, y es lo que hace que el sábado sea programar y no montar clúster.

### Bloqueantes (sin esto no hay proyecto)

- [x] **Plaza en el hackathon** — confirmada.
- [x] **Alcance público del ingress** — confirmado, `www.apperture.dev` responde desde fuera de Tailscale.
- [x] **DNS por subdominio** — resuelto vía external-dns: crea el registro automáticamente al ver el `Ingress` de cada tenant. No hace falta wildcard DNS estático.
- [ ] **Equipo:** cerrar 2 personas antes del evento si es posible (formación de equipos el sábado es un plan B, no un plan A: pierdes la mañana).
- [x] **TLS wildcard** — `*.apperture.dev` ya existe. Reflector lo replica automáticamente a cada namespace nuevo. **Verificar:** la anotación `reflection-allowed-namespaces` del Secret origen acepta cualquier `{hash}` futuro (patrón/regex), no una lista fija — si no, el control plane debe añadir el namespace a esa lista al crearlo.
- [ ] **Latencia de external-dns:** por defecto sincroniza cada ~1 min (`--interval`). Medir el tiempo real deploy→DNS resuelto; bajar a `--interval=15s` para el evento si hace falta.
- [ ] **Registro de imágenes** accesible desde el `Job` de build (buildah, con permisos rootless verificados en el clúster) y desde containerd de los nodos (probar un push + pull manual). Verificar también un token de GitHub con presupuesto de rate limit suficiente para el poller.
- [ ] **Confirmación escrita** de HackBarna sobre la regla de infraestructura previa — ya despejado por el FAQ oficial, pero mencionarlo el sábado no cuesta nada.

### Plantillas (probar cada una con un hello-world de verdad)

- [ ] **Chart Helm "podium-app"** empaquetado y subido al registro OCI; repo OCI registrado en ArgoCD; un `Application` de prueba aplicado a mano de principio a fin.
- [ ] (Opcional, si sobra tiempo esta semana) prototipo del activator en Go: watch de Deployments etiquetados + patch de réplicas + proxy — probarlo antes del sábado quita incertidumbre, pero no bloquea nada del núcleo si no se llega.
- [ ] **Ruta rápida opcional**: si algún equipo prefiere subir su propia imagen ya construida en vez de que Podium la construya, que el formulario acepte también una referencia de imagen directa.
- [ ] **Starters de adopción** (repos plantilla que un equipo clona, construye con el starter de arriba, y despliega en 60 s): "backend para agente de TV" (Node o Python, TMDB + Token Factory cableados, endpoint de chat) y "app con webhook de Vonage" (recibe SMS/llamada, responde). Son el gancho para el sábado por la tarde.
- [ ] Dockerfiles por lenguaje (PHP/Symfony, Node/TS, Python, Go, Rust, Java, .NET) — el `Job` de build los usa cuando el repo del equipo no trae uno propio.
- [ ] Base kustomize por tenant: Namespace (label `hackathon: "true"`, PSS `baseline`, `expires-at`), ResourceQuota, LimitRange, las 5 NetworkPolicy, Deployment, Service, Ingress (anotaciones `limit-connections`, TLS), Secret vacío para env.
- [ ] `02-protect-*.yaml` aplicado en todos los namespaces sensibles: `monitoring`, `cognos`, `keycloak`, `argocd`, Milvus, Postgres.
- [ ] `nodeAffinity` para que los tenants caigan en el nodo del ingress (evitar hairpin por Tailscale) y taint en `apperture-4` (GPU) para que nunca caigan ahí.
- [ ] CronJob de TTL: borra namespaces con `expires-at` vencido. Fecha por defecto: domingo 20, 17:00.

### Capacidad

- [ ] Sumar CPU/RAM libre real de `apperture-3`, `master-2`, `ubuntu`. Con quota de 1 CPU / 2 Gi por proyecto, calcular cuántos equipos caben con margen. Si son menos de 15, bajar la quota a 500m / 1 Gi.
- [ ] Ensayo end-to-end **manual** (sin control plane): clonar un repo, `kubectl create job` de build, `kubectl apply -k` con un hash, abrir la URL desde el móvil. Cronometrar. Ese tiempo es la promesa del pitch.

### Abuso

- [ ] Egress solo 443 (ya), quota (ya), TTL (ya), sin `automountServiceAccountToken`. Con eso un minero de cripto no compensa y no puede pivotar. Token por proyecto para que nadie borre el despliegue de otro.

---

## 5. Fases durante el evento

| Cuándo | Fase | Objetivo | Hito verificable |
|---|---|---|---|
| Sáb 11:30–12:30 | 0 · Arranque | Lanzamiento 11:30. Confirmar regla con organización, cerrar equipo, crear repo público, fijar contratos (anotaciones del `Application`, tools del agente) | Contratos en el README, todos programando a las 12:30 |
| Sáb 12:30–15:30 | 1 · Deploy path | Control plane: formulario (repo o imagen), `Application` por equipo, poller de 1 min, `Job` de build. Lunch 13:00 en la mesa. Uno pasa 30 min por el taller de Nebius (14:00) por keys y contacto con mentores | Push al repo de un compañero → build → viva en `{hash}.apperture.dev` sin tocar nada más |
| Sáb 15:30–18:00 | 2 · Abrir a la sala | Secrets, status board, token por proyecto, README "3 pasos", plantillas "backend TV (Titan)" y "webhook Vonage". Producto va mesa por mesa desde las 16:00, empezando por los equipos del reto de Titan OS | Primer equipo externo desplegado antes de la cena (18:00) |
| Sáb 18:00–22:30 | 3 · Agente | Tools + bucle + los 3 escenarios provocados. En paralelo: seguir captando equipos hasta las 22:00 | Un self-heal completo grabado en vídeo antes de salir del venue |
| Sáb 22:30–23:00 | Cierre | Alertas activas (uptime del status board → móvil), congelar el deploy path. Nada experimental en producción con demos ajenas encima | Plataforma estable al cerrar puertas |
| Noche (remoto) | 4 · Opcional | Solo si hay energía: OTel, pulido de UI. Uno con las alertas del status board en el móvil. Prohibido tocar el deploy path | Cero caídas de demos ajenas |
| Dom 9:00–10:45 | 5 · Congelar | No features. README, vídeo de backup, entrega | Código entregado a las 10:45 |
| Dom 10:45–14:00 | 6 · Pitch | Ensayar 3 veces con cronómetro. Recoger números de adopción. Slides | Pitch de 3 min sin mirar notas |

### Reparto (equipo de 3)

- **Infra + control plane** (tú): deploy path (chart Helm, gestión de `Application` CRs), status board backend.
- **Agente**: tools, bucle, escenarios provocados, integración LLM.
- **Producto + pitch**: UI, README, ir mesa por mesa el sábado tarde, slides, vídeo, guion de demo. Este rol pesa más de lo que parece: es quien consigue la adopción.

Si sois 2: se cae la observabilidad y el UI es un formulario feo. La adopción y el agente no se recortan.

---

## 6. Cómo vender la idea

### La lente del jurado

El único juez listado es un VC y el primer premio es pitchear ante inversores. El pitch se construye para esa lente: problema claro, producto que funciona, **tracción medida en la sala**, y un mercado fuera del hackathon. Lo técnico (aislamiento, contrato de tools) es la respuesta a "¿y por qué no lo copia cualquiera?", no el arranque.

**Quién paga (una frase):** bootcamps con demo day (Le Wagon está en la sala como partner) y organizadores de hackathones que repiten el mismo problema cada edición. Pricing por evento, no por usuario.

### Estructura del pitch (3 minutos)

1. **Problema (30 s):** "¿Cuántos de vosotros vais a enseñar la demo desde localhost hoy? ¿Cuántos habéis perdido una hora con ngrok para un webhook de Vonage?" Una foto de una demo muerta en un portátil.
2. **Opciones que había (20 s):** localhost (frágil), ngrok (un túnel, sin salud, sin secretos), Vercel (solo JS). Ninguna piensa en que la demo tiene que estar viva a las 14:00.
3. **Solución (30 s):** Podium. Repo → URL en 60 s, cualquier lenguaje, aislada. Y un agente que ensaya tu demo y la repara.
4. **Demo en directo (90 s):** desplegar un repo con el fallo provocado nº 1. Ver el agente leer logs, diagnosticar, corregir, reintentar, dar la URL. Abrir la URL desde el móvil del jurado.
5. **Prueba (20 s):** status board con **N equipos, M despliegues, uptime** desde ayer a las 16:00. "Esto no es una demo; estos son vuestros proyectos."
6. **Cierre (10 s):** contrato cerrado de tools = agente autónomo y seguro. Mercado: bootcamps y organizadores de eventos, pricing por evento. Open source (reto Cognition) + Token Factory como motor (reto Nebius).

### Mensajes por audiencia

- **Jurado técnico:** aislamiento real (netpol, quota, PSS), agente con contrato cerrado, bucle con escalado. "Abierto a desconocidos y aun así seguro."
- **Jurado de producto/sponsors:** adopción en la sala, webhooks públicos para Vonage, sin fricción de cuentas.
- **Sala:** "¿Quieres tu demo viva ahora? Ven a la mesa X."

### Lo que hace ganar

- La adopción visible. Todo el sábado por la tarde se invierte en conseguir equipos, no en features.
- El fallo provocado que se repara en directo. Es el único momento "wow" que hace falta.
- Vídeo de backup del self-heal grabado el sábado por la noche. Si el wifi del venue muere, se enseña el vídeo sin pedir perdón.
- Un QR gigante en la mesa hacia el status board.

### Errores que hunden el pitch

- Empezar por la arquitectura. Nadie compra kustomize.
- Prometer "PaaS para producción". Es una herramienta de hackathon; ese encuadre es la fuerza, no la debilidad.
- Enseñar el agente sin un fallo que reparar.
- No tener el número de equipos que lo usaron.

---

## 7. Riesgos y mitigación

| Riesgo | Probabilidad | Mitigación |
|---|---|---|
| No entrar desde la lista de espera | Media | Escribir a HackBarna esta semana; ofrecer el proyecto como infraestructura útil para el evento |
| Ingress no alcanzable desde fuera de Tailscale | Alta si no se ha probado | Verificar esta semana desde datos móviles; túnel o IP pública si falla |
| Organización cuestiona la infra previa | Baja — confirmado por FAQ oficial: *"teams should start a new project during the hackathon... brainstorm and prepare ideas before"* | El repo de Podium debe mostrar commits desde el sábado (verificable por los organizadores vía acceso al repositorio, según FAQ) |
| Un equipo tumba el nodo | Media | Quota + LimitRange (ya) + `nodeAffinity` fuera del GPU + vigilancia nocturna |
| Nadie usa la plataforma | Media | Rol de producto dedicado a ir mesa por mesa; "3 pasos" en README; ofrecer ayuda a desplegar in situ |
| El agente no repara nada en directo | Media | Escenarios provocados + vídeo de backup |
| Wifi del venue | Alta | Vídeo de backup; demo desde datos móviles si hace falta |
| Plataforma cae de noche con demos ajenas encima y nadie en la sala | Media | Congelar deploy path a las 22:30; alerta de uptime al móvil; no tocar nada en remoto salvo para reparar |
| "Esto ya lo hace Netlify/Vercel" | Alta (Netlify patrocina eventos HackBarna) | Posicionar como complementario: backend en cualquier lenguaje, webhook público para Vonage, aislamiento, agente. Nunca criticar al sponsor |
| Pocos equipos full-code | Media | Objetivo realista: 8–12 despliegues externos ya son adopción demostrable. Ofrecer ayuda in situ a los vibe-coders con backend propio |
| La sala se va en masa al reto de Titan OS | Alta | Convertirlo en cliente: starter "backend para agente de TV" listo el sábado a las 16:00. Cada equipo de Titan es un despliegue en Podium |

---

## 8. Esta semana (8–18 sept)

1. **Spike de ArgoCD + chart OCI** (medio día): empaquetar y subir "podium-app" al registro, registrar el repo OCI en ArgoCD, aplicar un `Application` de prueba y confirmar sync end-to-end con una imagen real.
2. (Opcional) probar el prototipo del activator en Go — ver checklist de infra. No bloquea nada más.
3. Poller + `Job` de build con buildah (rootless, verificar permisos en el clúster) + starters de adopción (TV, Vonage) + base kustomize/chart del namespace-tenant.
4. Ensayo manual end-to-end cronometrado: imagen de prueba → `Application` aplicado → sincronizado → URL.
5. Cerrar equipo y decidir stack del control plane/agente (ADR de un párrafo).
6. Preparar los 3 repos con fallos provocados.
7. Esqueleto de slides y guion de demo (con la lente de inversor: problema, tracción, mercado).
8. Crear el repo público de Podium **sin código** — solo README y estructura — para que el primer commit real quede fechado el sábado.
