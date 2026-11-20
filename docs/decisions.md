# Decisiones técnicas

Formato: contexto, decisión, consecuencias. Son decisiones de diseño basadas en mi experiencia con sistemas de este tipo; se anotan también sus límites.

## 1. Polling en lugar de WebSocket

**Contexto.** El panel necesita ver órdenes, tickets y tareas razonablemente al día. La API es PHP clásico detrás de Apache: cada petición es independiente y no hay procesos de larga duración.

**Decisión.** El panel hace *polling* con TanStack Query (`refetchInterval`): 10 s para una orden recién creada que aún no tiene cuadrilla, 30 s para listados y dashboard, 60 s para notificaciones y datos auxiliares. Las consultas se pausan cuando no hacen falta (por ejemplo, la conversación de una tarea solo consulta mientras está abierta).

**Consecuencias.**
- (+) Ninguna infraestructura extra: nada de servidor de WebSocket ni gestión de conexiones persistentes, y funciona igual detrás de cualquier proxy.
- (+) Fallos simples de razonar: si una consulta falla, la siguiente se recupera sola.
- (−) Latencia de hasta el intervalo configurado, y tráfico constante aunque no haya cambios.
- Si el volumen o la exigencia de inmediatez crecen, el siguiente paso natural es *server-sent events* o WebSocket solo para los eventos críticos, manteniendo el polling como respaldo.

## 2. Claim atómico con UPDATE condicional (en lugar de SELECT … FOR UPDATE)

**Contexto.** Varias cuadrillas pueden tocar "Tomar" sobre la misma orden casi al mismo tiempo. Solo una debe quedarse con ella; las demás deben enterarse con un error claro.

**Decisión.** Una única sentencia con el predicado de estado en el `WHERE`, dentro de una transacción:

```sql
UPDATE ordenes SET estado = 'asignada', cuadrilla_id = ?, asignada_en = now()
WHERE id = ? AND estado = 'pendiente';
```

Si afecta 0 filas, la orden ya no estaba pendiente y la API responde `409 Conflict`. En la misma transacción, la oferta de la cuadrilla ganadora pasa a `aceptada` y las demás ofertas pendientes se `cancelan`.

**Por qué funciona.** En `READ COMMITTED` (nivel por defecto de PostgreSQL), el `UPDATE` toma el bloqueo de la fila; la segunda transacción espera, reevalúa el `WHERE` sobre la fila ya modificada y no la encuentra pendiente. El predicado hace de *compare-and-set*.

**Alternativas consideradas.**
- `SELECT … FOR UPDATE` seguido de `UPDATE`: también es correcto, pero agrega un viaje de ida y vuelta y más superficie para errores (olvidar el bloqueo, bloquear de más). Aquí no aporta garantías adicionales.
- Leer el estado en PHP y escribir después (*check-then-act*): **incorrecto**, tiene una carrera. La demo incluye esa versión a propósito para probar que el test la detecta.

**Cómo se prueba.** [`examples/atomic-claim`](../examples/atomic-claim) lanza varios procesos con sesiones de Postgres distintas que intentan tomar la misma orden al mismo instante, repite 25 rondas y verifica: exactamente un ganador, el resto recibe 409, la orden queda asignada a la ganadora y las ofertas quedan consistentes.

## 3. Permisos por sección en la interfaz, con refuerzo propuesto en el servidor

**Contexto.** Los roles son pocos y gruesos (administrador, planificador, operador, pañol, técnico). Hacía falta algo más fino: que un administrador pueda decidir, empleado por empleado, qué secciones del panel ve.

**Decisión.** Cada empleado tiene una lista `secciones` (`jsonb`) validada contra una lista blanca al guardarla. El panel combina dos filtros independientes: por **rol** y por **secciones**, tanto para el menú como para el acceso directo por URL. Reglas: el administrador siempre pasa (para no poder dejarse afuera por error) y una lista vacía significa "todavía no configurado" y no restringe.

**Límite conocido.** Hoy las secciones se aplican en la interfaz y la API autoriza por rol. Un usuario con acceso a la API podría invocar un endpoint de una sección que no tiene habilitada si su rol lo permite. Es control de experiencia de uso, no una barrera de seguridad.

**Refuerzo propuesto.** [`examples/section-permissions`](../examples/section-permissions) incluye `SectionGuard::require()`, un guard que se invoca al inicio de cada acción, con modo compatible (lista vacía permite) y modo estricto (lista vacía deniega), y tests de 403. Está marcado como propuesta: no es el código que corre hoy.

## 4. Invariantes en la base de datos

**Decisión.** Las reglas que no deben romperse nunca se declaran en PostgreSQL además de validarse en la aplicación:
- `CHECK` en estado, prioridad y tipo de cierre de las órdenes, y en estado y modo de las ofertas;
- índice único parcial `(orden_id, cuadrilla_id) WHERE estado = 'pendiente'` para impedir ofertas vivas duplicadas;
- índice único parcial sobre el identificador del ticket externo (solo cuando existe), para que un ticket no genere dos órdenes;
- claves foráneas con la acción adecuada en cada caso (`ON DELETE RESTRICT` donde borrar sería un error, `CASCADE` donde los hijos no tienen sentido solos, `SET NULL` donde el historial debe sobrevivir).

**Consecuencia.** Una validación olvidada en un controlador, un script manual o una carrera entre procesos no puede dejar datos inválidos. El costo es mantener las migraciones al día con cualquier estado nuevo.

## 5. API PHP con MVC propio

**Contexto.** Una API de tamaño medio, desplegada en un servidor Apache existente, mantenida por muy pocas personas.

**Decisión.** Front controller (`public/index.php`) con un router mínimo, controladores por recurso y clases de apoyo para petición, respuesta, validación y acceso a datos con PDO. Formato de error uniforme (`{ code, message }` con el código HTTP correspondiente) mediante una excepción de API que el front controller traduce a respuesta.

**Consecuencias.**
- (+) Cero dependencias que actualizar, arranque rápido y despliegue trivial (copiar archivos).
- (+) Cada acción es legible de arriba abajo: autenticar, autorizar, validar, operar, responder.
- (−) Hay que mantener a mano lo que un framework da hecho (inyección de dependencias, migraciones, pruebas de integración). Los ejemplos de este repositorio muestran cómo se prueban las piezas críticas con tests de concurrencia y de permisos.
