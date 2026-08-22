-- =============================================================================
-- db/init/seed.sql
-- -----------------------------------------------------------------------------
-- Demo seed data for the Dayflow HRMS hackathon demo.
-- Runs automatically when the PostgreSQL container starts fresh
-- (placed in /docker-entrypoint-initdb.d/).
--
-- Creates:
--   1 admin user  → admin@dayflow.com  / Admin123
--   1 employee    → emp@dayflow.com    / Emp123
--
-- Passwords are bcrypt-hashed (cost=12).
-- Hash for "Admin123": $2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBpj2bWX8mDYtS
-- Hash for "Emp123":   $2b$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uFFpBKSzBNi
-- =============================================================================

-- ── Wait safety: only seed if tables exist and are empty ─────────────────────
-- The users table is created by Flask-Migrate AFTER the container starts.
-- This seed file runs at container init time, so the table may not exist yet.
-- We check pg_tables first; if the table isn't there yet we skip silently.
DO $$
BEGIN

  -- Guard: skip entirely if the users table hasn't been created yet
  IF NOT EXISTS (
    SELECT 1 FROM pg_tables
    WHERE schemaname = 'public' AND tablename = 'users'
  ) THEN
    RAISE NOTICE 'users table does not exist yet — skipping seed. Run flask db upgrade then re-seed manually.';
    RETURN;
  END IF;

  -- Only run seed if users table is empty (prevents duplicate seed on restart)
  IF NOT EXISTS (SELECT 1 FROM users LIMIT 1) THEN

    -- ── Insert admin user ─────────────────────────────────────────────────────
    INSERT INTO users (name, email, role, password_hash, is_active, created_at, updated_at)
    VALUES (
      'HR Admin',
      'admin@dayflow.com',
      'admin',
      '$2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBpj2bWX8mDYtS',
      TRUE,
      NOW(),
      NOW()
    );

    -- ── Insert admin employee profile ─────────────────────────────────────────
    INSERT INTO employees (user_id, phone, address, job_title, department, created_at, updated_at)
    VALUES (
      (SELECT id FROM users WHERE email = 'admin@dayflow.com'),
      '9000000001',
      '1 Admin Lane, Bangalore',
      'HR Manager',
      'HR',
      NOW(),
      NOW()
    );

    -- ── Insert employee user ──────────────────────────────────────────────────
    INSERT INTO users (name, email, role, password_hash, is_active, created_at, updated_at)
    VALUES (
      'Alice Employee',
      'emp@dayflow.com',
      'employee',
      '$2b$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uFFpBKSzBNi',
      TRUE,
      NOW(),
      NOW()
    );

    -- ── Insert employee profile ───────────────────────────────────────────────
    INSERT INTO employees (user_id, phone, address, job_title, department, created_at, updated_at)
    VALUES (
      (SELECT id FROM users WHERE email = 'emp@dayflow.com'),
      '9000000002',
      '42 Worker Street, Bangalore',
      'Software Engineer',
      'Tech',
      NOW(),
      NOW()
    );

    -- ── Insert sample payroll for employee (current month) ────────────────────
    INSERT INTO payroll (employee_id, basic_salary, allowances, deductions, net_salary, month, year, created_at, updated_at)
    VALUES (
      (SELECT id FROM employees WHERE user_id = (SELECT id FROM users WHERE email = 'emp@dayflow.com')),
      50000.00,   -- basic salary
      5000.00,    -- allowances
      2000.00,    -- deductions
      53000.00,   -- net = 50000 + 5000 - 2000
      EXTRACT(MONTH FROM NOW())::INT,
      EXTRACT(YEAR  FROM NOW())::INT,
      NOW(),
      NOW()
    );

    -- ── Insert sample attendance for employee (today) ─────────────────────────
    INSERT INTO attendance (employee_id, date, check_in, check_out, status, created_at)
    VALUES (
      (SELECT id FROM employees WHERE user_id = (SELECT id FROM users WHERE email = 'emp@dayflow.com')),
      CURRENT_DATE,
      '09:00:00',
      '18:00:00',
      'present',
      NOW()
    );

    -- ── Insert a pending leave request for employee ───────────────────────────
    INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, remarks, status, created_at, updated_at)
    VALUES (
      (SELECT id FROM employees WHERE user_id = (SELECT id FROM users WHERE email = 'emp@dayflow.com')),
      'sick',
      CURRENT_DATE + INTERVAL '3 days',
      CURRENT_DATE + INTERVAL '4 days',
      'Doctor appointment',
      'pending',
      NOW(),
      NOW()
    );

    RAISE NOTICE 'Seed data inserted successfully.';

  ELSE
    RAISE NOTICE 'Users table already has data — skipping seed.';
  END IF;

END $$;
