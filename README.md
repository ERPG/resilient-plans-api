# Fever Code Challenge

> Enunciado original en `CHALLENGE.md`. El razonamiento extendido de cada decisión (borrador) vive
> en `README.private.md` (no versionado); la versión final de este README se curará desde ahí.

## Cómo levantar
```
make run
```
App + base de datos, en un comando.

## Sincronización con el proveedor
```
make sync          # equivale a: docker compose exec app php bin/console app:sync-plans
```
`app:sync-plans` baja el feed, filtra `sell_mode=online` y hace upsert idempotente en la BD local.
Agnóstico del disparo (sin cron ni worker en el stack): en producción lo lanza el scheduler del
entorno. Si el proveedor falla, sale con código ≠ 0 sin crashear.

## Búsqueda
```
GET /search?starts_at=2021-06-01T00:00:00Z&ends_at=2021-06-30T23:59:59Z
```
Devuelve los eventos online contenidos en el rango, con el envelope del contrato
(`{data:{events:[...]}, error:null}`; error → `{error:{code,message}, data:null}`, 400/500).
Solo lee de la BD local: **responde igual aunque el proveedor esté caído** (probado en el test
funcional inyectando un proveedor que lanza al llamarlo).

## Cómo testear
```
make test
```
Prepara una BD de test aislada (`fever_test`) y ejecuta la suite completa (unitarios + integración).

## Arquitectura
Capas **Domain / Application / Infrastructure**. La sincronización con el proveedor está
desacoplada del endpoint de búsqueda: el endpoint solo lee de la BD local, así responde aunque el
proveedor esté caído o lento.

## Decisiones clave
_(resumen; razonamiento y trade-offs completos en `README.private.md`)_

- **Identidad del evento = `(base_plan_id, plan_id)`** — `plan_id` no es único por sí solo.
- **`id` público = UUIDv5 determinista** de ese par (tipo `uuid` nativo de Postgres, es la PK).
- **Postgres sobre MySQL** — tablas heap: un `uuid` como PK no fragmenta datos.
- **Fechas en `TIMESTAMP WITHOUT TIME ZONE`** — se preserva la hora local del proveedor, sin
  conversión de zona.
- **`min/max` excluye zonas con `capacity=0`**; las zonas no se persisten (se guarda el cálculo).
- **Entidad Doctrine separada del dominio** (`EventRecord` + `EventMapper`); nunca se borra
  (`last_seen_at`).
- **Proveedor desacoplado**: todo fallo del feed → `ProviderUnavailable` (log-and-skip); el filtro
  `sell_mode=online` vive en la sincronización (Application), no en el parser.
- **Sync agnóstico del disparo y síncrono** — sin cron/worker en el stack ni job async; on-demand.
- **Contención de fechas**: sin `end_date`, contenido si `start_date ∈ [starts, ends]`; con
  `end_date`, contención completa (`start ≥ starts AND end ≤ ends`). Se ejecuta como `WHERE`
  indexado (`idx_events_dates`), no cargando filas a PHP.
- **Read side = puerto `EventFinder` sin use case, Doctrine ORM** — la búsqueda es una sola query
  (no hay orquestación que justifique una capa, a diferencia del sync); no se sale del ORM sin una
  medición de rendimiento que lo pida (DBAL descartado). Devuelve un `EventSummary` plano, no
  rehidrata el `Event` de dominio.
- **Errores = jerarquía `ApiException` + `ApiExceptionSubscriber`** (`kernel.exception`) como único
  traductor al envelope — el único punto que ve todo lo que puede fallar en la request.
- **Fechas de entrada con `Y-m-d\TH:i:sp`** (no `ATOM`): acepta el sufijo `Z` del ejemplo del spec.
- **Sin paginación (a propósito)**: a este volumen la query indexada responde en ms; el tope /
  paginación es palanca de producción documentada, no implementada (no se toca el contrato).

## Uso de IA
_(pendiente)_

## Extra mile
_(pendiente)_
