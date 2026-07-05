# Fever Code Challenge

> Enunciado original en `CHALLENGE.md`. El razonamiento extendido de cada decisión (borrador) vive
> en `README.private.md` (no versionado); la versión final de este README se curará desde ahí.

## Cómo levantar
```
make run
```
App + worker de sincronización + base de datos, en un comando.

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
  `sell_mode=online` vive en la sincronización.

## Uso de IA
_(pendiente)_

## Extra mile
_(pendiente)_
