-- Esquema reducido y con nombres genéricos (demo). Pensado para PostgreSQL 13+.

CREATE TABLE crews (
    id   uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name text NOT NULL
);

CREATE SEQUENCE work_orders_number_seq;

CREATE TABLE work_orders (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    number      text UNIQUE DEFAULT ('WO-' || lpad(nextval('work_orders_number_seq')::text, 6, '0')),
    title       text NOT NULL,
    priority    text NOT NULL DEFAULT 'medium'
                CHECK (priority IN ('low', 'medium', 'high', 'critical')),
    status      text NOT NULL DEFAULT 'pending'
                CHECK (status IN ('pending', 'assigned', 'accepted', 'in_progress',
                                  'completed', 'cancelled', 'rescheduled')),
    crew_id     uuid REFERENCES crews (id),
    created_at  timestamptz NOT NULL DEFAULT now(),
    assigned_at timestamptz,
    updated_at  timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX idx_work_orders_status ON work_orders (status);
CREATE INDEX idx_work_orders_crew   ON work_orders (crew_id);

-- Ofertas de despacho: a qué cuadrillas se les ofrece cada orden.
--   mode 'cascade': se ofrece en secuencia (una cuadrilla por vez, con vencimiento).
--   mode 'open'   : se ofrece a varias a la vez; gana la primera que la toma.
CREATE TABLE dispatch_offers (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    work_order_id   uuid NOT NULL REFERENCES work_orders (id) ON DELETE CASCADE,
    crew_id         uuid NOT NULL REFERENCES crews (id) ON DELETE CASCADE,
    mode            text NOT NULL CHECK (mode IN ('cascade', 'open')),
    sequence        integer NOT NULL DEFAULT 0,
    distance_meters double precision,
    status          text NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending', 'accepted', 'rejected', 'expired', 'cancelled')),
    offered_at      timestamptz NOT NULL DEFAULT now(),
    expires_at      timestamptz,
    responded_at    timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX idx_offers_order        ON dispatch_offers (work_order_id, status);
CREATE INDEX idx_offers_crew_status  ON dispatch_offers (crew_id, status);
CREATE INDEX idx_offers_expiry       ON dispatch_offers (status, expires_at);

-- Una sola oferta pendiente por (orden, cuadrilla): evita duplicados si el
-- despachador reintenta o dos procesos ofrecen la misma orden a la vez.
CREATE UNIQUE INDEX idx_offers_one_pending
    ON dispatch_offers (work_order_id, crew_id)
    WHERE status = 'pending';
