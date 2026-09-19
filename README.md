# Podium — Documentación de dominio

Plataforma de despliegue de HackBarna AI Summit 26: repo público → build → deploy → URL en segundos, con un agente que ensaya la demo y se auto-repara. Generada con discovery de dominio estilo `/speckit.bc` — interrogación conversacional, un BC a la vez, el arquitecto decide.

## Por dónde empezar

1. **`context-map.md`** — el mapa completo: los seis Bounded Contexts, sus eventos cruzados, los invariantes que los relacionan, y las convenciones de modelado que se fueron fijando por el camino (mismo término en contextos distintos, comunicación siempre por evento, un id técnico separado del identificador de negocio, etc.). Empieza aquí.
2. **`c4-diagrams.md`** — la misma información en C4: Nivel 1 (Podium en su contexto — GitHub, ArgoCD, el registro OCI, el clúster) y Nivel 2 (los contenedores dentro de Podium, un BC por contenedor).
3. Una carpeta por Bounded Context — el detalle de agregados, value objects, acciones y eventos de cada uno.

## Estado de cada Bounded Context

| BC | Carpeta | `discovery.md` | `model.md` |
|---|---|---|---|
| AppSource | `appsource/` | ✅ Cerrado | ⏳ Pendiente |
| Project | `project/` | ✅ Cerrado | ✅ |
| App Manager | `app-manager/` | ✅ Cerrado | ✅ |
| Build | `build/` | ✅ Cerrado | ✅ |
| Deploy | — | ⏳ No arrancado | ⏳ |
| Remediation | — | ⏳ No arrancado | ⏳ |

Provisioning y Notification están confirmados en `context-map.md` pero sin discovery propio — su alcance ya se intuye por las referencias que hacen los BC cerrados (Provisioning: namespace/quota/netpol/TTL, más la capacidad de BBDD vía CNPG; Notification: solo avisa, sin lógica de diagnóstico).

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

## Fuera del dominio, pero documentado

Dos carpetas hermanas, generadas en la misma sesión, cubren la parte de infraestructura que no es modelo de dominio:

- **`hackathon-plan/`** — plan de ataque del hackathon: fases, retos de los sponsors, riesgos, guion de pitch.
- **`hackathon-netpol/`** — las `NetworkPolicy` del namespace-por-tenant, validadas 11/11 con `test.sh`.
