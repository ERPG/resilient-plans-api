Fever Code Challenge

> El enunciado original de Fever se conserva en `CHALLENGE.md` (renombrado desde el README
> que trajo el repo). Este README documenta las decisiones de diseño, tal como pide el
> criterio de evaluación de documentación.

## Cómo levantar el proyecto
```
make run
```
Levanta la app, el worker de sincronización y la base de datos con un único comando.

## Arquitectura
[Diagrama/explicación de capas: Domain / Application / Infrastructure]
[Por qué la sincronización está desacoplada del endpoint de búsqueda]

## Decisiones de diseño y trade-offs
- **Identidad de un evento = par compuesto `(base_plan_id, plan_id)`**, no `plan_id` solo. Los
  datos reales del proveedor lo obligan: en una misma respuesta, `plan_id=1642` aparece bajo
  `base_plan` 322 y 1591 — `plan_id` no es único ni dentro de una respuesta. Cada `<plan>` hijo
  es un evento (un `base_plan` puede tener varias fechas). En BD será `UNIQUE(base_plan_id,
  plan_id)`. El `id` público del endpoint = `base_plan_id`.
- [min_price/max_price excluye zonas con capacity=0 — por qué]
- [filtro de fechas por contención completa, no solapamiento — por qué]
- [MySQL sobre Postgres — por qué]
- [Loop simple en vez de cron real para la sincronización — por qué, y cómo se haría en producción]

## Integración con el proveedor (capa Infrastructure/Provider)
- El endpoint de búsqueda **nunca** llama al proveedor. `HttpPlanProvider` (adaptador del port
  `Application\Provider\PlanProvider`) sólo lo usa la sincronización. Timeout + `max_duration`
  explícitos: un proveedor lento no puede colgar el proceso.
- Todo fallo del feed (red, timeout, HTTP no-2xx, XML malformado) se traduce a una única
  excepción `ProviderUnavailable`: el llamante hace log-and-skip, nunca revienta.
- **Parseo con SimpleXML** manual y explícito (defendible línea a línea) frente a la magia del
  Serializer. Trade-off: SimpleXML carga el documento entero en memoria; para el "extra mile"
  de miles de planes se migraría a `XMLReader` en streaming.
- **Fechas:** se parsean en UTC con `createFromFormat` + `getLastErrors()`, no con el
  constructor de `DateTimeImmutable` — éste no lanza con fechas imposibles (`2021-09-31` se
  desborda silenciosamente a 1-oct). Un plan con fecha imposible se descarta, el feed sobrevive.
- **El filtro `sell_mode=online` NO vive en el parser**, sino en la capa de sincronización: el
  provider mapea todos los eventos preservando el `SellMode`; la regla de negocio queda en un
  único sitio evidente.

## Uso de IA
[Documentar aquí cómo se usó Claude/IA: para qué partes, cómo se revisó el código generado]

## Testing
[Cómo ejecutar los tests: make test. Qué cubren los unitarios vs los de integración]

## Extra mile (si hay tiempo)
[Escalabilidad, alto tráfico, optimización — implementado o descrito]