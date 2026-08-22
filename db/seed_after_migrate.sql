-- =============================================================================
-- db/seed_after_migrate.sql
-- -----------------------------------------------------------------------------
-- Run this MANUALLY after flask db upgrade has created all tables.
--
-- Usage (from host):
--   docker exec -i oodo-nmit-db-1 psql -U appuser -d appdb < db/seed_after_migrate.sql
--
-- Or from inside the db container:
--   psql -U appuser -d appdb -f /seed_after_migrate.sql
--
-- Creates:
--   1 admin user  → admin@dayflow.com  / Admin123
--   1 employee    → emp@dayflow.com    / Emp123
-- =============================================================================

DO $$
BEGIN

  -- Only seed if the table is empty
  IF NOT EXISTS (SELECT 1 FROM users LIMIT 1) THEN

    -- ── Admin user ────────────────────────────────────────────────────────────
    INSERT INTO users (name, email, role, password_hash, is_active, created_at, updated_at)
    VALUES (
      'HR Admin',
      'admin@dayflow.com',
      'admin',
      '$2b$12$9u28Jwkd/WOsKK.ZMvoZlu5aJtb1sJ5leSS6krQhAzUfdmaL.LmhK',
      TRUE,
      NOW(), NOW()
    );

    INSERT INTO employees (user_id, phone, address, job_title, department, created_at, updated_at)
    VALUES (
      (SELECT id FROM users WHERE email = 'admin@dayflow.com'),
      '9000000001', '1 Admin Lane, Bangalore', 'HR Manager', 'HR',
      NOW(), NOW()
    );

    -- ── Employee user ─────────────────────────────────────────────────────────
    INSERT INTO users (name, email, role, password_hash, is_active, created_at, updated_at)
    VALUES (
      'Alice Employee',
      'emp@dayflow.com',
      'employee',
      '$2b$12$x2JzYAw4MYxFUfOWGRy7XOaCTxFq4elOYvxk75YAbZBKKWB6mYZsa',
      TRUE,
      NOW(), NOW()
    );

    INSERT INTO employees (user_id, phone, address, job_title, department, created_at, updated_at)
    VALUES (
      (SELECT id FROM users WHERE email = 'emp@dayflow.com'),
      '9000000002', '42 Worker Street, Bangalore', 'Software Engineer', 'Tech',
      NOW(), NOW()
    );

    -- ── Sample payroll (current month) ───────────────────────────────────────
    INSERT INTO payroll (employee_id, basic_salary, allowances, deductions, net_salary, month, year, created_at, updated_at)
    VALUES (
      (SELECT id FROM employees WHERE user_id = (SELECT id FROM users WHERE email = 'emp@dayflow.com')),
      50000.00, 5000.00, 2000.00, 53000.00,
      EXTRACT(MONTH FROM NOW())::INT,
      EXTRACT(YEAR  FROM NOW())::INT,
      NOW(), NOW()
    );

    -- ── Sample attendance (today) ─────────────────────────────────────────────
    INSERT INTO attendance (employee_id, date, check_in, check_out, status, created_at)
    VALUES (
      (SELECT id FROM employees WHERE user_id = (SELECT id FROM users WHERE email = 'emp@dayflow.com')),
      CURRENT_DATE, '09:00:00', '18:00:00', 'present', NOW()
    );

    -- ── Sample pending leave request ──────────────────────────────────────────
    INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, remarks, status, created_at, updated_at)
    VALUES (
      (SELECT id FROM employees WHERE user_id = (SELECT id FROM users WHERE email = 'emp@dayflow.com')),
      'sick',
      CURRENT_DATE + INTERVAL '3 days',
      CURRENT_DATE + INTERVAL '4 days',
      'Doctor appointment',
      'pending',
      NOW(), NOW()
    );

    RAISE NOTICE 'Seed data inserted successfully.';

  ELSE
    RAISE NOTICE 'Users table already has data — skipping seed.';
  END IF;

END $$;
