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
- [id como plan_id string, no UUID sintético — por qué]
- [min_price/max_price excluye zonas con capacity=0 — por qué]
- [filtro de fechas por contención completa, no solapamiento — por qué]
- [MySQL sobre Postgres — por qué]
- [Loop simple en vez de cron real para la sincronización — por qué, y cómo se haría en producción]

## Uso de IA
[Documentar aquí cómo se usó Claude/IA: para qué partes, cómo se revisó el código generado]

## Testing
[Cómo ejecutar los tests: make test. Qué cubren los unitarios vs los de integración]

## Extra mile (si hay tiempo)
[Escalabilidad, alto tráfico, optimización — implementado o descrito]