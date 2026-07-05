# Decisiones pendientes

Registro de decisiones de arquitectura aún sin cerrar, para revisar en pareja antes de
implementar. Formato: **Pregunta → Contexto → Opciones → Estado**.

---

## Soportar un segundo proveedor de "plans"

### Pregunta 1 — Patrón para soportar N proveedores sin tocar Domain/Application/búsqueda

**Contexto.** El puerto `PlanProvider::fetchEvents(): Event[]`
(`src/Application/Provider/PlanProvider.php`) ya es agnóstico: devuelve `Event` de dominio y lo
específico del XML vive en `HttpPlanProvider`/`XmlPlanParser`. Lo único que hoy asume un solo
proveedor es `SyncPlansUseCase` (`src/Application/Sync/SyncPlansUseCase.php`), que recibe
`PlanProvider $provider` (singular).

**Opciones.**

- **A (recomendada) — Strategy + tagged iterator de Symfony.** Se conserva el puerto tal cual.
  `SyncPlansUseCase` pasa a recibir `iterable<PlanProvider> $providers` (vía
  `_instanceof: PlanProvider → tag app.plan_provider` en `services.yaml`) y hace un bucle externo
  por proveedor. Cada adapter contiene su propio formato y estampa su `providerName` y su
  `externalIdentity` (ver Pregunta 2).
  - *A favor:* mínimo; reutiliza el puerto ya limpio; sin DTO nuevo; añadir un proveedor = clase
    nueva + tag (automático por `_instanceof`) + config. Recolección en tiempo de compilación →
    no es registry dinámico.
  - *En contra:* refactor puntual (una vez) del use case a `iterable`.

- **B — DTO propio del proveedor.** El puerto devuelve un DTO crudo (`RawPlan`/`ProviderPlan`) y
  Application mapea DTO → `Event`, decidiendo identidad/`providerName` en un único sitio.
  - *A favor:* centraliza construcción de `Event` e identidad en Application; adapters más
    "tontos".
  - *En contra:* capa de mapeo extra + DTO con los mismos campos que `Event` = duplicación; hoy
    el parser ya construye `Event` directo, esto lo deshace. Sobre-ingeniería para 2 proveedores y
    contradice la decisión previa de "sin DTO".

**Recomendación:** A. No se proponen Command Bus / Event Bus / registry dinámico: el tagged
iterator de Symfony resuelve el "N proveedores" en compilación sin lógica extra.

**Aislamiento de fallos.** Con N proveedores, uno caído no debe tumbar el sync completo. El bucle
de `SyncPlansUseCase` envuelve cada proveedor en `try/catch (ProviderUnavailable)`: registra el
fallo de ese proveedor y sigue con el siguiente, mismo criterio ya aplicado al fallo de red de un
proveedor individual. (Decidir si el `SyncReport` acumula fallos por proveedor y si el exit code
refleja "alguno falló".)

**Estado:** ✅ Decidida e implementada. Opción A. `SyncPlansUseCase` recibe
`iterable<PlanProvider>` (tag `app.plan_provider` + `!tagged_iterator` en `services.yaml`) y aísla
cada proveedor con `try/catch (ProviderUnavailable)`. `SyncReport` acumula `failedProviders`; el
comando devuelve `FAILURE` si alguno falló, persistiendo igualmente lo de los sanos.

---

### Pregunta 2 — Colisión de identidad entre proveedores y cómo ampliar el UUID v5

**Contexto.** HOY `id = Uuid::v5(ns, "basePlanId:planId")` (`EventMapper::id()`) y unique
`(base_plan_id, plan_id)`, con `basePlanId`/`planId` como campos de dominio de `Event`. Dos
proveedores distintos pueden reutilizar los mismos identificadores → **mismo UUID** → el evento
del proveedor B pisa al de A (el upsert es por PK). Riesgo **real**, pero sólo aparece al añadir
el 2º proveedor. Se resuelve **ampliando** el cálculo, no rehaciéndolo: mismo namespace y mismo
algoritmo, sólo se antepone un discriminador `providerName` al *name string*.

**Diseño propuesto.**
- `Event` deja de tener `basePlanId`/`planId` como campos. Se sustituyen por un único
  **`externalIdentity: string`** (identidad opaca del evento en su proveedor de origen) más
  **`providerName: string`**. Domain/Application no vuelven a mencionar `basePlanId`/`planId`.
- **Cada adapter construye su propio `externalIdentity`** con la forma de su esquema. El proveedor
  actual lo compone como `"{base_plan_id}:{plan_id}"` **dentro de su parser/adapter**
  (`XmlPlanParser`), no en `Event` ni en `EventMapper`. `base_plan_id`/`plan_id` quedan como
  detalle local del XML.
- `EventMapper::id()` pasa a ser `Uuid::v5(namespace, "{providerName}:{externalIdentity}")`, sin
  mencionar `basePlanId`/`planId` en ningún punto.
- Unique constraint pasa a **`(provider_name, external_identity)`**.

**Opciones.**
- **A (recomendada) — `providerName` + `externalIdentity` como campos de dominio en `Event`,**
  estampados por cada adapter desde config (p.ej. `$providerName: 'provider-fever'`).
  `EventMapper::id()` los usa. Coherente con la Opción A de la Pregunta 1.
- **B — identidad fuera del `Event`.** Pasar `providerName` al `repository->save($event, ...)`.
  - *En contra:* ensucia el puerto de persistencia; la identidad no viaja con el evento.
    Rechazable.

**Coste.** Anteponer `providerName` cambia **todos** los UUID existentes (el propio comentario de
`EventMapper` ya lo advierte).
- *En este challenge:* el store es desechable y `app:sync-plans` es idempotente → **wipe +
  resync**. Válido **sólo** porque los datos son desechables y el sync es idempotente por diseño.
- *En producción real:* NO se haría con wipe + resync. Sería una **migración con backfill**:
  añadir `external_identity`/`provider_name`, calcular su valor fila a fila a partir de los datos
  existentes (reutilizando la misma lógica de concatenación que vive en el adapter), y **sólo
  entonces** cambiar el unique constraint — sin perder histórico de eventos ya sincronizados.

**Estado:** ✅ Decidida e implementada. Opción A. `Event` pasa a `providerName` +
`externalIdentity` (se eliminan `basePlanId`/`planId` del dominio); el adapter XML compone
`externalIdentity = "{base_plan_id}:{plan_id}"` en su parser; `EventMapper::id()` =
`Uuid::v5(ns, "{providerName}:{externalIdentity}")`; unique `(provider_name, external_identity)`.
Nombre del proveedor actual: `code-challenge` (env `PROVIDER_NAME`). Migración
`Version20260705000001` con **wipe + resync** para el challenge (el backfill de producción queda
sólo como nota, fuera del repo salvo esta línea).

---

### Coste real del cambio (implementado como A + A)

**Se toca:**
- `src/Domain/Event/Event.php` — **eliminar** `basePlanId`/`planId` (campos + getters);
  **añadir** `providerName` y `externalIdentity` (+ getters). Actualizar el comentario de
  identidad (ya no es "composite (basePlanId, planId)").
- `src/Infrastructure/Provider/Xml/XmlPlanParser.php` (+ `HttpPlanProvider.php`) — construir
  `externalIdentity = "{base_plan_id}:{plan_id}"` en el parser y estampar `providerName` desde
  config al crear cada `Event`.
- `src/Infrastructure/Persistence/Doctrine/EventMapper.php` — `id()` =
  `Uuid::v5(ns, "{providerName}:{externalIdentity}")`; `toRecord()`/`refresh()` dejan de pasar
  `basePlanId`/`planId` y pasan `providerName`/`externalIdentity`. Sin referencias a
  `basePlanId`/`planId`.
- `src/Infrastructure/Persistence/Doctrine/Entity/EventRecord.php` + nueva migración — columnas
  `provider_name`/`external_identity` en lugar de `base_plan_id`/`plan_id`; unique
  `(provider_name, external_identity)`.
- `config/services.yaml` — `_instanceof` tag para `PlanProvider`; `$providerName` en
  `HttpPlanProvider`.
- `src/Application/Sync/SyncPlansUseCase.php` — ctor `iterable $providers`, bucle externo por
  proveedor con `try/catch (ProviderUnavailable)` para aislar el fallo de un proveedor.
- (opcional) `SyncReport` desglosado por proveedor / con fallos.

**No se toca:** endpoint de búsqueda ni read-side (`GET /search`, `EventFinder`, `EventSummary`
— verificado: no dependen de `base_plan_id`/`plan_id`), regla de negocio `isOnline()`, mecanismo
de upsert (sigue por PK UUID).

**Migración BD:** SÍ (columnas `provider_name`/`external_identity` + reíndice). En el challenge:
resync; en producción: migración con backfill (ver arriba).

**Añadir el 2º proveedor después:** clase nueva `implements PlanProvider` (+ su parser si el
formato difiere, construyendo su propio `externalIdentity`) + service def con su
`$providerName`/URL + tag (automático). Cero cambios en Domain / Application / búsqueda.
