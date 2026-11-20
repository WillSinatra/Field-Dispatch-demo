-- Datos 100% ficticios para la demo.

INSERT INTO crews (id, name) VALUES
    ('00000000-0000-0000-0000-0000000000a1', 'Cuadrilla Norte'),
    ('00000000-0000-0000-0000-0000000000a2', 'Cuadrilla Sur'),
    ('00000000-0000-0000-0000-0000000000a3', 'Cuadrilla Centro');

INSERT INTO work_orders (id, title, priority) VALUES
    ('00000000-0000-0000-0000-0000000000b1', 'Instalación de ONT (ejemplo)', 'medium'),
    ('00000000-0000-0000-0000-0000000000b2', 'Revisión de señal (ejemplo)',  'high');

-- La orden b1 se ofrece en modo abierto a las tres cuadrillas.
INSERT INTO dispatch_offers (work_order_id, crew_id, mode, sequence, distance_meters) VALUES
    ('00000000-0000-0000-0000-0000000000b1', '00000000-0000-0000-0000-0000000000a1', 'open', 0, 850),
    ('00000000-0000-0000-0000-0000000000b1', '00000000-0000-0000-0000-0000000000a2', 'open', 0, 1900),
    ('00000000-0000-0000-0000-0000000000b1', '00000000-0000-0000-0000-0000000000a3', 'open', 0, 2600);
