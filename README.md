# Podium — Documentación de dominio

Plataforma de despliegue de HackBarna AI Summit 26: repo público → build → deploy → URL en segundos, con un agente que ensaya la demo y se auto-repara. Generada con discovery de dominio estilo `/speckit.bc` — interrogación conversacional, un BC a la vez, el arquitecto decide.

## Por dónde empezar

1. **`context-map.md`** — el mapa completo: los seis Bounded Contexts, sus eventos cruzados, los invariantes que los relacionan, y las convenciones de modelado que se fueron fijando por el camino (mismo término en contextos distintos, comunicación siempre por evento, un id técnico separado del identificador de negocio, etc.). Empieza aquí.
2. **`c4-diagrams.md`** — la misma información en C4: Nivel 1 (Podium en su contexto — GitHub, ArgoCD, el registro OCI, el clúster) y Nivel 2 (los contenedores dentro de Podium, un BC por contenedor).
3. Una carpeta por Bounded Context — el detalle de agregados, value objects, acciones y eventos de cada uno.

## Estado de cada Bounded Context

| BC | Carpeta | `discovery.md` | `model.md` | Implementado (PHP) |
|---|---|---|---|---|
| Team | `team/` | ✅ Cerrado | ✅ | ✅ |
| AppSource | `appsource/` | ✅ Cerrado | ✅ | ✅ |
| Project | `project/` | ✅ Cerrado | ✅ | ✅ |
| App Manager | `app-manager/` | ✅ Cerrado | ✅ | ✅ |
| Build | `build/` | ✅ Cerrado | ✅ | ✅ (el lanzador de Kubernetes en Go queda diferido) |
| Deploy | `deploy/` | ✅ Cerrado | ✅ | ✅ (el lanzador de ArgoCD en Go queda diferido) |
| Remediation | — | ⏳ No arrancado | ⏳ | — |

Provisioning y Notification están confirmados en `context-map.md` pero sin discovery propio — su alcance ya se intuye por las referencias que hacen los BC cerrados (Provisioning: namespace/quota/netpol/TTL, más la capacidad de BBDD vía CNPG; Notification: solo avisa, sin lógica de diagnóstico).

El backend PHP vive en `services/php/` — ver más abajo cómo levantarlo.

## Cómo leer un BC

Cada carpeta tiene dos documentos con propósitos distintos — no son lo mismo:

- **`discovery.md`** — el proceso. Lenguaje ubicuo, candidatos a agregado, y sobre todo la sección de **Session Log**: cada decisión, en el orden en que se tomó, con el porqué. Si una duda te suena ("¿por qué el comando no es un campo configurable?", "¿por qué Registry no es un aggregate?"), la respuesta está ahí, no solo el qué.
- **`model.md`** — el artefacto formal. Diagrama de clases en Mermaid, tabla de responsabilidades, eventos publicados/consumidos. Es el que se comparte con quien no necesita el histórico completo.

## Convenciones que aplican a todo el modelo

Capturadas en detalle en `context-map.md`, resumidas aquí porque se usan constantemente:

- Comunicación entre BCs **siempre por evento publicado**, nunca llamada ni consulta directa.
- Todo aggregate lleva su propio **`id` (UUID)** técnico, separado de cualquier identificador de negocio (`serviceName`, `hash`).
- Mismo término en dos BCs vecinos no es un error — es lenguaje cercano señalando partes distintas del sistema.
- Una copia materializada de solo lectura (un dato con un único dueño, leído desde otro aggregate) no es lo mismo que duplicar una verdad con dos caminos de escritura.
- Cuando el equipo ya mantiene un dato en su propio repo (`podium.yaml`), Podium lo lee — nunca lo posee ni lo duplica.

## Cómo levantar el backend (`services/php/`)

Requisito: Docker + Docker Compose. Todo corre en contenedores — no hace falta PHP ni Composer en el host.

### 1. Arrancar los servicios

```bash
cd services/php
docker compose up -d
```

Levanta `frankenphp` (stage `dev`: bind mount de todo el código, sin worker mode, corre como usuario no-root — ver `docker/franken-php/Dockerfile`), `database` (Postgres), `redis` y `keycloak` (identity provider, realm `podium` ya importado).

### 2. Instalar dependencias (solo la primera vez)

La imagen `dev` no trae `vendor/` horneado — se instala en caliente y queda en el host gracias al bind mount:

```bash
docker compose exec frankenphp composer install
```

### 3. Migraciones (dev + test)

```bash
docker compose exec frankenphp bin/console doctrine:migrations:migrate --no-interaction

docker compose exec frankenphp bin/console doctrine:database:create --env=test --if-not-exists
docker compose exec frankenphp bin/console doctrine:migrations:migrate --env=test --no-interaction
```

### 4. Sembrar el catálogo de `Template` (Build)

Necesario antes de poder registrar cualquier `Application` — hoy solo existe la plantilla `nodejs`/`nestjs` (ver `services/build-runner/`):

```bash
docker compose exec frankenphp bin/console app:build:seed-templates
```

### 5. Conseguir un JWT real de Keycloak

La API exige un Bearer JWT en casi todos los endpoints (`GET /api/teams`, etc.) — verificado contra el realm `podium` (cliente `podium-api`, usuario de prueba `testuser`/`testuser`, ya cargado al importar el realm):

```bash
TOKEN=$(curl -s -X POST http://localhost:8091/realms/podium/protocol/openid-connect/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password&client_id=podium-api&client_secret=podium-dev-secret&username=testuser&password=testuser" \
  | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
```

Admin console de Keycloak: http://localhost:8091/admin (`admin`/`admin`).

### 6. Sembrar datos de mentira para el frontend

`app:fixtures:load` necesita el `sub` real del JWT (el userId con el que se va a autenticar el frontend) — se extrae del propio token:

```bash
SUB=$(echo "$TOKEN" | cut -d. -f2 | base64 -d 2>/dev/null | python3 -c "import sys,json; print(json.load(sys.stdin)['sub'])")

docker compose exec frankenphp bin/console app:fixtures:load "$SUB"
```

Crea 2 Team, 3 Project y 4 Application repartidas entre los estados reales (`Deployed`, `BuildFailed`, `Building`, `DeployFailed`) — pensado para que el frontend tenga algo que mostrar en cada vista sin montar el ciclo build→deploy completo a mano.

### 7. Probarlo

```bash
curl -s http://localhost:8090/api/teams -H "Authorization: Bearer $TOKEN"
```

- API: http://localhost:8090
- Swagger UI: http://localhost:8090/api/doc
- Rutas disponibles hoy: `GET/POST /api/teams`, `GET/POST /api/projects`, `GET /api/teams/{teamId}/projects/{projectId}/applications[/{serviceName}]`

### 8. Tests

```bash
docker compose exec frankenphp vendor/bin/phpunit
```

`tests/Unit` (dominio puro), `tests/Integration` (repositorios contra Postgres real), `tests/Functional` (HTTP end-to-end, incluida la autenticación).

## Fuera del dominio, pero documentado

Dos carpetas hermanas, generadas en la misma sesión, cubren la parte de infraestructura que no es modelo de dominio:

- **`hackathon-plan/`** — plan de ataque del hackathon: fases, retos de los sponsors, riesgos, guion de pitch.
- **`hackathon-netpol/`** — las `NetworkPolicy` del namespace-por-tenant, validadas 11/11 con `test.sh`.
