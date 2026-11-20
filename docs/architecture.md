# Arquitectura

Field Dispatch es una plataforma de gestión de servicio técnico para un ISP (en esta demo, el ficticio **Acme ISP**). Convierte reclamos y pedidos en **órdenes de trabajo (OT)**, las despacha a **cuadrillas** y permite seguirlas hasta el cierre.

> Esta documentación describe un diseño simplificado, basado en mi experiencia. Los nombres, los datos y los identificadores son ficticios.

## 1. Vista general

```mermaid
flowchart LR
    subgraph Cliente["Navegador"]
        SPA["Panel web<br/>React · Vite<br/>TypeScript<br/>TanStack Query"]
    end

    subgraph Servidor["Servidor"]
        APACHE["Apache<br/>hosts virtuales"]
        subgraph API["API PHP · MVC propio"]
            ROUTER["index.php<br/>router"]
            CTRL["Controladores<br/>órdenes,<br/>cuadrillas,<br/>empleados…"]
            AUTH["Auth<br/>tokens · roles"]
            SVC["Servicios<br/>despacho<br/>notificaciones"]
        end
        PG[("PostgreSQL")]
    end

    TECH["App de técnicos<br/>proyecto aparte"]

    SPA -- "HTTPS · JSON · polling" --> APACHE
    TECH -- "HTTPS · JSON" --> APACHE
    APACHE --> ROUTER
    ROUTER --> AUTH
    ROUTER --> CTRL
    CTRL --> SVC
    CTRL --> PG
    SVC --> PG
```

- **Panel web**: aplicación de una sola página. Se compila a archivos estáticos que sirve Apache.
- **API**: un front controller (`public/index.php`) registra las rutas `/v1/...` y delega en controladores. Las clases de apoyo (`Request`, `Response`, `Validator`, `Database`, `Auth`) mantienen consistentes la validación, el formato de error y el acceso a datos con PDO.
- **PostgreSQL**: única fuente de verdad. Los invariantes importantes viven en la base (restricciones `CHECK`, índices únicos parciales), no solo en el código.
- **App de técnicos**: cliente móvil de la misma API, documentado en su propio repositorio.

## 2. Del ticket a la orden de trabajo

```mermaid
flowchart TD
    T["Ticket, reclamo<br/>o alta rápida"] --> V{"¿Requiere<br/>validación?"}
    V -- "sí" --> VAL["Validar altas<br/>(supervisor)"]
    V -- "no" --> OT
    VAL --> OT["Crear orden<br/>de trabajo<br/>(pendiente)"]
    OT --> D["Despacho<br/>ofertas a<br/>cuadrillas"]
    D --> C["Una cuadrilla<br/>la toma<br/>(claim atómico)"]
    C --> E["Ejecución<br/>en campo"]
    E --> F["Cierre<br/>normal o sin<br/>disponibilidad"]
    F --> S["Ticket origen<br/>actualizado y<br/>SLA medido"]
```

## 3. Estados de una orden

Los estados permitidos están garantizados por una restricción `CHECK` en la base. El diagrama muestra el camino principal; `cancelada` y `reprogramada` pueden darse antes de la ejecución.

```mermaid
stateDiagram-v2
    [*] --> pendiente
    pendiente --> asignada: cuadrilla toma / asignan
    asignada --> aceptada: el técnico acepta
    aceptada --> en_proceso: llega al domicilio
    en_proceso --> completada: cierre
    pendiente --> cancelada
    asignada --> reprogramada
    reprogramada --> pendiente
    completada --> [*]
    cancelada --> [*]
```

La orden tiene una marca de tiempo por cada hito (`asignada_en`, `aceptada_en`, `llegada_en`, `completada_en`, `cancelada_en`), lo que permite medir tiempos de respuesta y de ejecución. El vencimiento (`fecha_vencimiento`) y `estado_sla` soportan el seguimiento de SLA.

## 4. Despacho: ofertas a cuadrillas

Cada orden se ofrece a una o más cuadrillas mediante filas en `ofertas_despacho`. El modelo de datos distingue dos modos:

| Modo | Idea | Columnas que lo sostienen |
|------|------|---------------------------|
| `cascada` | La oferta pasa de una cuadrilla a otra en orden, con vencimiento por oferta | `secuencia`, `distancia_metros`, `expira_en` |
| `abierta` | Se ofrece a varias cuadrillas a la vez; gana la primera que la toma | varias ofertas `pendiente` para la misma orden |

Cada oferta termina en `aceptada`, `rechazada`, `expirada` o `cancelada`.

```mermaid
sequenceDiagram
    autonumber
    participant A as Cuadrilla A
    participant B as Cuadrilla B
    participant API as API (OrdenesController)
    participant DB as PostgreSQL

    Note over API,DB: Orden pendiente con ofertas pendientes para A y B
    par A y B tocan "Tomar" al mismo tiempo
        A->>API: POST /v1/ordenes/{id}/despacho/claim
    and
        B->>API: POST /v1/ordenes/{id}/despacho/claim
    end
    API->>DB: BEGIN
    API->>DB: UPDATE ordenes SET estado='asignada' WHERE id=? AND estado='pendiente'
    DB-->>API: 1 fila (gana A)
    API->>DB: oferta de A = aceptada · ofertas de las demás = canceladas
    API->>DB: COMMIT
    API-->>A: 200 orden asignada
    Note over API,DB: la sentencia de B espera el commit de A,<br/>reevalúa el WHERE y afecta 0 filas
    API-->>B: 409 "ya fue tomada por otra cuadrilla"
```

El detalle de por qué esto es seguro y cómo se prueba está en [`examples/atomic-claim`](../examples/atomic-claim) y en [la decisión 2](decisions.md#2-claim-atómico-con-update-condicional-en-lugar-de-select--for-update).

## 5. Seguridad y acceso

Hay dos capas, con responsabilidades distintas:

```mermaid
flowchart LR
    REQ["Petición"] --> J["Token de acceso<br/>requireUser"]
    J --> R{"¿Rol<br/>permitido?"}
    R -- "no" --> X403["403"]
    R -- "sí" --> H["Acción"]
    UI["Panel<br/>menús y rutas<br/>por sección"] -. "solo interfaz" .-> REQ
```

- **API**: autenticación por token de acceso/refresco y autorización por **rol**.
- **Panel**: además filtra menús y rutas por las **secciones** habilitadas para cada empleado (lista configurable por un administrador).
- **Brecha conocida**: las secciones se aplican hoy en la interfaz, no en el servidor. El ejemplo [`section-permissions`](../examples/section-permissions) muestra un guard del lado servidor, propuesto como refuerzo. Ver [decisión 3](decisions.md#3-permisos-por-sección-en-la-interfaz-con-refuerzo-propuesto-en-el-servidor).

## 6. Modelo de datos (extracto)

```mermaid
erDiagram
    CLIENTES ||--o{ ORDENES : "tiene"
    CUADRILLAS ||--o{ ORDENES : "ejecuta"
    EMPLEADOS ||--o{ ORDENES : "responsable"
    ORDENES ||--o{ OFERTAS_DESPACHO : "se ofrece mediante"
    CUADRILLAS ||--o{ OFERTAS_DESPACHO : "recibe"

    ORDENES {
        uuid id PK
        text numero "OT-000123 (secuencia)"
        text estado "CHECK: 7 estados"
        text prioridad "CHECK: baja..critica"
        uuid cuadrilla_id FK
        timestamptz asignada_en
        timestamptz fecha_vencimiento
        text estado_sla
    }
    OFERTAS_DESPACHO {
        uuid id PK
        uuid orden_id FK
        uuid cuadrilla_id FK
        text modo "cascada | abierta"
        int secuencia
        text estado "CHECK: 5 estados"
        timestamptz expira_en
    }
    EMPLEADOS {
        uuid id PK
        text nombre
        jsonb secciones "lista blanca + índice GIN"
    }
```

Detalles de diseño en la base:

- Claves primarias `uuid` (`gen_random_uuid()`), con un `numero` legible por humanos generado desde una secuencia (`OT-000123`).
- `CHECK` en estados, prioridades y tipo de cierre: un valor inválido no entra aunque falle la validación de la aplicación.
- Índice único parcial `(orden_id, cuadrilla_id) WHERE estado = 'pendiente'`: no puede haber dos ofertas vivas de la misma orden para la misma cuadrilla.
- Índice único parcial sobre `ticket_externo_id` (solo cuando no es nulo): una orden por ticket externo.
- `secciones` como `jsonb` con índice GIN.

## 7. Refresco de datos en el panel

El panel consulta la API a intervalos con TanStack Query (`refetchInterval`), no usa WebSocket. Los intervalos van de 10 s (una orden recién creada sin cuadrilla) a 60 s (notificaciones), con 30 s para listados y dashboard. Ver [decisión 1](decisions.md#1-polling-en-lugar-de-websocket).
