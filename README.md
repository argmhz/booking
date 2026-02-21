# Human Resource Booking System (MVP)

Laravel 12 + Inertia(Vue) + PostgreSQL + Redis i Docker setup.

## Features i denne første version

- Roller via `spatie/laravel-permission`: `admin`, `employee`, `company`
- Google login skeleton via Socialite
- Admin booking-kalender (`/bookings/calendar`) med oprettelse af booking
- Time-baseret booking (`starts_at`, `ends_at`) med:
  - antal medarbejdere
  - assignment mode (`specific_employees` / `first_come_first_served`)
  - valgfri visning af medarbejdernavne til virksomhed
- Datamodel for:
  - virksomheder, medarbejderprofiler, kompetencer
  - booking requests, assignments, venteliste
  - availability, timesheets, audit logs

## Docker services

- `web` (Nginx)
- `app` (PHP-FPM)
- `db` (PostgreSQL 16)
- `redis`
- `queue`
- `scheduler`
- `node` (Vite dev server)
- `mailpit`

## Kom i gang

1. Kopiér miljøfil:

```bash
cp .env.example .env
```

2. Sæt din admin-email i `.env`:

```env
SUPER_ADMIN_EMAIL=din-email@gmail.com
```

3. Konfigurer Google OAuth i `.env`:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8080/auth/google/callback
```

4. Start stack:

```bash
docker compose up -d --build
```

5. Kør migrations + seed:

```bash
docker compose exec app php artisan migrate --seed
```

6. Åbn:

- App: http://localhost:8080
- Mailpit: http://localhost:8025
- Vite dev (hot reload): http://localhost:5173

## Teststatus

`php artisan test` kører grønt bortset fra standard `RegistrationTest`, fordi public register-route er fjernet (invitation + Google-login flow).

