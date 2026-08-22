# Dayflow HRMS

Dayflow is a role-based Human Resources Management System (HRMS) for managing employee profiles, attendance, leave requests, and payroll information.

The project uses a Laravel web frontend, a Flask REST API backend, and PostgreSQL for persistent data. All services run with Docker Compose.

## Features

### Employees

- Employee registration and login
- Employee dashboard
- Employee profile viewing and editing
- Check-in and check-out attendance
- Attendance history
- Leave application and leave history
- Payroll viewing

### Administrators

- Admin dashboard with live HR metrics
- Total employee count
- Present and absent attendance counts
- Pending leave count
- Leave type summary
- Department headcount
- Employee profile management
- Complete attendance records
- Leave request approval and rejection
- Payroll management

## Architecture

```text
Browser
   |
   | http://localhost:8080
   v
Laravel frontend
   |
   | http://backend:5000/api/*
   v
Flask backend
   |
   | PostgreSQL
   v
PostgreSQL database
```

| Service | Technology | Container port | Host port |
|---|---|---:|---:|
| Frontend | Laravel 13, PHP 8.3 | 80 | 8080 |
| Backend | Flask 3, Python 3.12 | 5000 | 5000 |
| Database | PostgreSQL 16 | 5432 | 5432 |

## Requirements

Install the following tools before running the project:

- Docker Desktop
- Git
- Git Bash on Windows
- A modern web browser

Docker Desktop must be running before executing the setup or start commands.

## Project Structure

```text
.
├── backend/
│   ├── app/
│   │   ├── app.py
│   │   ├── blueprints/       # Flask API modules
│   │   ├── models/            # SQLAlchemy models
│   │   ├── migrations/        # Flask-Migrate migrations
│   │   └── migrate.sh
│   ├── dockerfile
│   └── requirements.txt
├── frontend/
│   ├── dockerfile
│   └── src/
│       ├── app/               # Laravel controllers, services, models
│       ├── resources/views/   # Blade templates
│       ├── routes/web.php
│       └── composer.json
├── db/init/                   # PostgreSQL initialization scripts
├── docker-compose.yml
├── win-scaffold-laravel.sh    # Laravel scaffold helper
└── README.md
```

## First-Time Setup

Clone the repository and enter the project directory:

```bash
git clone https://github.com/karthisankar21/odoo_nmit_hackthon_2026.git
cd odoo_nmit_hackthon_2026
```

### Scaffold Laravel

Run this only when Laravel has not yet been scaffolded, or when you intentionally want to recreate the frontend source:

```bash
chmod +x win-scaffold-laravel.sh
./win-scaffold-laravel.sh
```

The script:

1. Clears the contents of `frontend/src/`.
2. Creates Laravel in a temporary directory inside a PHP Docker container.
3. Copies the generated Laravel project into `frontend/src/`.
4. Creates or patches the Laravel `.env` file.

**Warning:** The scaffold script deletes existing files inside `frontend/src/`. Do not run it after adding application code unless you have committed or backed up your changes.

## Running the Application

Run all commands below from the repository root in Git Bash.

### 1. Build and start containers

```bash
docker compose up -d --build
```

Check service status:

```bash
docker compose ps
```

The containers are intentionally kept alive with Bash. Start the application processes manually in separate Git Bash terminals.

### 2. Start the Flask backend

```bash
docker compose exec backend flask run --host=0.0.0.0 --port=5000
```

Keep this terminal open.

Test the backend:

```bash
curl http://localhost:5000/health
```

Expected response:

```json
{"status":"ok"}
```

### 3. Start the Laravel frontend

Open a second Git Bash terminal:

```bash
cd /d/work/project/odoo_nmit_hackthon_2026
docker compose exec frontend php artisan config:clear
docker compose exec frontend php artisan serve --host=0.0.0.0 --port=80
```

Keep this terminal open.

Open the application in your browser:

```text
http://localhost:8080
```

The root page redirects to the login page.

## Stopping the Application

Stop the Laravel and Flask processes with `Ctrl+C` in their terminals, then stop the containers:

```bash
docker compose down
```

To stop containers and delete the PostgreSQL data volume:

```bash
docker compose down -v
```

The `-v` option permanently deletes local database data.

## Environment Configuration

The main Laravel environment file is:

```text
frontend/src/.env
```

Important values include:

```dotenv
APP_NAME=Dayflow
APP_ENV=local
APP_DEBUG=true
BACKEND_URL=http://backend:5000
SESSION_DRIVER=file
CACHE_STORE=file
```

The Flask backend receives its Docker Compose settings from `docker-compose.yml`:

```text
DATABASE_URL=postgresql://appuser:apppassword@db:5432/appdb
JWT_SECRET_KEY=dayflow-jwt-secret-2026
FLASK_APP=app.py
FLASK_ENV=development
```

For production use, replace development credentials and secrets through environment variables. Do not commit real secrets to Git.

## Application URLs

| URL | Purpose |
|---|---|
| `http://localhost:8080` | Laravel web application |
| `http://localhost:8080/login` | Login page |
| `http://localhost:8080/register` | Employee registration |
| `http://localhost:5000/health` | Flask health check |
| `http://localhost:5000/` | Flask API status |

## Web Routes

### Public routes

- `GET /` - Redirects to login
- `GET /login` - Login form
- `POST /login` - Authenticate user
- `GET /register` - Registration form
- `POST /register` - Register user
- `POST /logout` - Log out

### Employee routes

All employee routes require employee authentication.

- `GET /employee/dashboard`
- `GET /employee/profile`
- `POST /employee/profile`
- `GET /employee/attendance`
- `POST /employee/attendance/checkin`
- `POST /employee/attendance/checkout`
- `GET /employee/leave`
- `POST /employee/leave`
- `GET /employee/payroll`

### Admin routes

All admin routes require admin authentication.

- `GET /admin/dashboard`
- `GET /admin/employees`
- `GET /admin/employees/{id}`
- `POST /admin/employees/{id}`
- `GET /admin/attendance`
- `GET /admin/leave`
- `POST /admin/leave/{id}/approve`
- `POST /admin/leave/{id}/reject`
- `GET /admin/payroll`
- `POST /admin/payroll/{id}`

## Backend API Areas

The Flask API is mounted under `/api` and uses JWT bearer authentication for protected endpoints.

- `/api/auth` - Login, registration, and authentication
- `/api/employees` - Employee profiles and employee administration
- `/api/attendance` - Check-in, check-out, and attendance records
- `/api/leave` - Leave applications and approval actions
- `/api/payroll` - Payroll records
- `/api/analytics` - Admin dashboard metrics

The Laravel `ApiClient` automatically:

- Adds the `/api` prefix
- Reads the backend URL from `BACKEND_URL`
- Sends the JWT from the Laravel session
- Converts backend responses into a consistent result format
- Handles API errors and expired authentication sessions

## Database and Migrations

PostgreSQL data is stored in the Docker volume `postgres_data`.

The backend migration script waits for PostgreSQL and applies Flask-Migrate migrations:

```bash
docker compose exec backend /migrate.sh
```

Useful migration commands:

```bash
docker compose exec backend flask db current
docker compose exec backend flask db upgrade
docker compose exec backend flask db history
```

If the database is reset with `docker compose down -v`, start the containers again and run the migration script.

## Testing

### Backend tests

Run the backend test suite inside the backend container:

```bash
docker compose exec backend pytest
```

Run a specific test file:

```bash
docker compose exec backend pytest path/to/test_file.py -v
```

### Laravel tests

Run the Laravel test suite inside the frontend container:

```bash
docker compose exec frontend php artisan test
```

Check PHP syntax for a modified file:

```bash
docker compose exec frontend php -l app/Http/Controllers/ExampleController.php
```

## Troubleshooting

### Browser cannot connect to `localhost:8080`

Check that the frontend container is running:

```bash
docker compose ps
```

Then start Laravel inside the container:

```bash
docker compose exec frontend php artisan serve --host=0.0.0.0 --port=80
```

The Laravel terminal must remain open.

### Laravel returns HTTP 500 with a database driver error

The frontend uses file-backed sessions and cache. Check `frontend/src/.env`:

```dotenv
SESSION_DRIVER=file
CACHE_STORE=file
```

Clear the Laravel configuration cache:

```bash
docker compose exec frontend php artisan config:clear
```

### Flask returns `Internal server error`

Read the backend output in the Flask terminal:

```bash
docker compose logs --tail=100 backend
```

Check that PostgreSQL is healthy:

```bash
docker compose ps db
```

Run migrations if necessary:

```bash
docker compose exec backend /migrate.sh
```

### Git Bash reports a Bash syntax or line-ending error

Convert the script to Unix line endings in Git Bash:

```bash
sed -i 's/\r$//' win-scaffold-laravel.sh
```

Then check syntax:

```bash
bash -n win-scaffold-laravel.sh
```

### Docker path-conversion errors on Windows

The scaffold script already sets `MSYS_NO_PATHCONV=1`. If Docker still reports a path conversion error, run:

```bash
MSYS_NO_PATHCONV=1 ./win-scaffold-laravel.sh
```

### Inspect Laravel logs

```bash
tail -n 100 frontend/src/storage/logs/laravel.log
```

## Development Workflow

1. Start Docker Desktop.
2. Run `docker compose up -d --build` after dependency or Dockerfile changes.
3. Start Flask in one terminal.
4. Start Laravel in another terminal.
5. Edit source files under `backend/app` or `frontend/src`.
6. Run focused tests or syntax checks after changes.
7. Check `git diff` and `git status` before committing.

The source directories are mounted into the containers, so most PHP and Python code changes are immediately visible without rebuilding the images. Rebuild when changing `dockerfile`, `requirements.txt`, Composer dependencies, or system packages.

## Git Branch Workflow

Check the current branch and working tree:

```bash
git status
git branch --show-current
```

Before pushing a branch that has diverged from its remote, integrate remote changes first:

```bash
git pull --rebase origin sankar
git push origin sankar
```

If a merge is already in progress, resolve conflicts, stage the resolved files, create the merge commit, and then push:

```bash
git status
git add <resolved-file>
git commit
git push origin sankar
```

Never use force push unless you intentionally want to replace the remote branch history.

## Security Notes

- Change the default database password before production deployment.
- Replace the development JWT secret with a strong secret stored outside Git.
- Set `APP_DEBUG=false` in production.
- Do not expose PostgreSQL publicly in production.
- Use HTTPS and secure cookie settings in production.
- Validate authorization on both the Laravel and Flask sides.

## License

No project license has been specified yet.
