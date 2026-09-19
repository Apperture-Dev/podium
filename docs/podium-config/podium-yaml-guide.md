# Gestión de `podium.yaml` y de los secrets

**Derivado de**: `context-map.md`, `project/discovery.md`, `build/discovery.md`
**Fecha**: 2026-09-19

---

## Qué es y dónde vive

`podium.yaml` es el único fichero que un equipo tiene que añadir a su repo para que Podium sepa cómo construir y desplegar su proyecto. Vive en el repo del equipo — Podium nunca lo posee ni lo copia; lo lee directamente del commit correspondiente cada vez que hace falta.

Cada clave de primer nivel es un **servicio** (`serviceName`) — único dentro de ese repo (ese `Project`), no globalmente. Un repo puede declarar uno o varios: un backend y un frontend en el mismo monorepo son dos servicios en el mismo `podium.yaml`, y cada uno se despliega como su propia `Application`, con su propia URL y su propio ciclo de vida.

*(Nota honesta: la localización exacta del fichero dentro del repo — si se asume siempre en la raíz o se busca en otro sitio — sigue siendo un detalle menor sin cerrar del discovery de Build.)*

## Quién lo lee, y cuándo

El fichero se lee **dos veces, con propósitos distintos**, y ningún BC confía en lo que otro ya interpretó — cada uno lo lee por su cuenta:

1. **`AppSource`** detecta que la revisión del repo cambió — pero no abre el yaml. Su trabajo es agnóstico de contenido: "¿cambió el commit?", nada más.
2. **`Project`** lee el yaml en esa revisión para descubrir la lista de servicios declarados (las claves de primer nivel). Si encuentra un `serviceName` que no conocía, dispara el registro de una `Application` nueva; si ya lo conocía, avisa a esa `Application` de que hay una revisión nueva que construir.
3. **`Build`** (`BuildJob`) vuelve a leer el mismo yaml, esta vez solo el bloque del servicio que le toca construir — para sacar sus variables de entorno, su declaración de base de datos, y validarlas contra el esquema (`paramSchema`) que define su `Template` (la plantilla de su lenguaje/framework).

## Qué significa cada campo

```yaml
app:                    # serviceName — nombre del servicio dentro de este repo
  src: backend          # Carpeta del repo donde vive el código de este servicio
  lang: nodejs          # Lenguaje — selecciona qué Template (catálogo de Build) aplica
  framework: nestjs     # Framework — junto a "lang", determina el jobImage y la convención de arranque
  environment:          # Variables de entorno — el mismo bloque sirve para build y para runtime
    NODE_ENV: production
    API_KEY: ${SECRET_NAME}     # Referencia a un secret — ver más abajo
  database:              # Opcional — si no se declara, no se monta base de datos
    enable: true
    URL: ${DB_URL}                    # Si se rellena, esta gana sobre los campos sueltos de abajo
    database: ${APP_DATABASE}         # — o, en su lugar, los campos sueltos —
    user: ${APP_DATABASE_USER}
    password: ${APP_DATABASE_PASSWORD}
    host: ${APP_DATABASE_HOST}
    port: ${APP_DATABASE_PORT}

frontend:
  src: frontend
  lang: nodejs
  framework: react
  environment:
    BACKEND_URL: ${app.url}    # Referencia a la URL pública de otro servicio del mismo repo
```

- **No hay campo de "comando".** Cada `Template` asume la convención de su lenguaje (Node → `npm run build && npm start`). Si el repo no sigue esa convención, el build falla con un mensaje de error claro — no hay nada que configurar para evitarlo.
- **`environment` no distingue build de runtime.** Es un único bloque; `Build` usa lo que necesita durante la construcción, y reenvía lo que le toca a `Deploy` para el contenedor en marcha. No hace falta declarar la misma variable dos veces aunque aplique a las dos fases (el caso típico es `NODE_ENV`).
- **`database`**: si se rellena `URL`, esa gana siempre — da igual lo que haya en los campos sueltos. No hace falta (ni existe) un campo que diga "usa el modo URL"; es una simple regla de precedencia por peso.

## Los secrets: por qué Podium los guarda, y cómo se referencian

**El repo es público.** Esa es la razón de todo lo demás: `podium.yaml` vive en un repositorio que cualquiera puede leer (Podium solo soporta repos públicos en el hackathon), así que **nunca puede contener un valor real** — ni una API key, ni una contraseña de base de datos. Si el valor estuviera en el yaml, estaría filtrado en el momento en que el commit se subiera a GitHub.

Por eso el valor real tiene que vivir **en otro sitio, dentro de Podium** — no en el repo del equipo. El equipo sube el secret una vez, a través del Dashboard (App Manager), y Podium lo guarda como un `Secret` nativo de Kubernetes, aislado en el namespace de ese `Project` (el mismo namespace donde ya corren `app` y `frontend`, con las `NetworkPolicy` y la `ResourceQuota` que ya se validaron). Al estar los servicios de un mismo repo compartiendo namespace, un secret subido una vez está disponible para cualquiera de sus servicios — no hay que subirlo por separado para `app` y para `frontend`.

En el yaml, `${SECRET_NAME}` **nunca es el valor — es solo el nombre** con el que se referencia ese secret ya subido. Dos consecuencias directas:

- **El dominio no resuelve el secret, solo valida su forma.** `BuildJob` comprueba que `${SECRET_NAME}` tiene una sintaxis válida (una cadena con esa forma concreta) — nunca comprueba si ese secret existe de verdad ni cuál es su valor. Esa resolución ocurre **fuera del dominio**, en el momento del despliegue: Kubernetes es quien sustituye la referencia por el valor real, vía su propio mecanismo de `secretKeyRef`, cuando el pod arranca.
- **Sin valores por defecto.** `${SECRET_NAME}` no admite una sintaxis tipo `${SECRET_NAME:-valor}` — si el secret no existe, el despliegue falla con un error claro, en vez de arrancar silenciosamente con un valor que nadie pidió.

*(Nota honesta: el mecanismo exacto de "el equipo sube un secret desde el Dashboard" viene de la conversación de arquitectura previa al discovery de dominio — no ha pasado por la misma interrogación rigurosa que el resto de este documento. Si quieres que quede modelado con el mismo nivel de detalle, es candidato a su propia sesión de discovery, probablemente dentro de Provisioning.)*
