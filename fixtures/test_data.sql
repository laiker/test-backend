-- Create tables
CREATE TABLE partners (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    tier VARCHAR(50) NOT NULL,
    active BOOLEAN DEFAULT true
);

CREATE TABLE sales (
    id SERIAL PRIMARY KEY,
    partner_id INTEGER NOT NULL REFERENCES partners(id),
    amount DECIMAL(10,2) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_partner_status ON sales(partner_id, status);

CREATE TABLE bonuses (
    id SERIAL PRIMARY KEY,
    partner_id INTEGER NOT NULL REFERENCES partners(id),
    amount DECIMAL(10,2) NOT NULL,
    period VARCHAR(100) NOT NULL,
    calculated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Insert test data (1000 partners для проверки memory leak)
INSERT INTO partners (name, tier, active)
SELECT 
    'Partner ' || generate_series,
    CASE (random() * 2)::int
        WHEN 0 THEN 'gold'
        WHEN 1 THEN 'silver'
        ELSE 'bronze'
    END,
    true
FROM generate_series(1, 1000);

-- Добавим несколько партнеров с некорректным tier для проверки type juggling
INSERT INTO partners (name, tier, active) VALUES
    ('Partner Invalid 1', '0', true),
    ('Partner Invalid 2', '', true),
    ('Partner Invalid 3', 'unknown', true);

-- Insert sales for each partner (5-20 sales per partner)
INSERT INTO sales (partner_id, amount, product_name, status, created_at)
SELECT 
    p.id,
    (random() * 10000 + 100)::decimal(10,2),
    'Product ' || (random() * 50)::int,
    CASE (random() * 10)::int
        WHEN 0 THEN 'cancelled'
        WHEN 1 THEN 'pending'
        ELSE 'completed'
    END,
    CURRENT_TIMESTAMP - (random() * 30 || ' days')::interval
FROM partners p
CROSS JOIN generate_series(1, (5 + random() * 15)::int);
