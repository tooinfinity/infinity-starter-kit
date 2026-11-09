# Production Deployment Guide

This guide covers deployment procedures and production hardening for the Infinity Starter Kit.

---

## 1. Environment Configuration

Copy the production environment template:

```bash
cp .env.production.example .env
php artisan key:generate
```

### Critical Production Settings

- **`APP_ENV=production`**: Disables debug-specific mechanisms and enables production optimizations.
- **`APP_DEBUG=false`**: Never expose stack traces or detailed error messages to end users in production.
- **`APP_URL=https://your-domain.com`**: Must use `https://` to ensure generated URLs match secure protocol expectations.
- **`SESSION_SECURE_COOKIE=true`**: Enforces the `Secure` flag on session cookies so they are transmitted solely over HTTPS.
- **`TRUSTED_PROXIES`**: Required when hosting behind load balancers or reverse proxies (AWS ALB/ELB, Cloudflare, Traefik, Nginx, Caddy). Defined in `config/security.php` (`security.trusted_proxies`) and handled at request time by `App\Http\Middleware\TrustProxies` so it is safely preserved after `php artisan config:cache`.
  - Leave unset by default. Use `*` only when origin access is restricted to a trusted ingress that replaces client-supplied forwarding headers:
    ```ini
    TRUSTED_PROXIES=*
    ```
  - Otherwise, specify the trusted proxies' explicit comma-separated IP addresses or CIDR blocks:
    ```ini
    TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
    ```
- **`NOTIFICATION_RETENTION_DAYS=30`**: The number of days read notifications are retained before automatic pruning (set `<= 0` to disable pruning).
- **`NOTIFICATION_UNREAD_RETENTION_DAYS`**: (Optional) Number of days unread notifications are retained before pruning. Unset or null by default (unread notifications never expire).

---

## 2. Security Headers & CSP

The application includes `App\Http\Middleware\SecurityHeaders`, configured via `config/security.php`, which automatically adds defensive HTTP headers to responses:

- **`X-Content-Type-Options: nosniff`**: Prevents MIME-type sniffing.
- **`Referrer-Policy: strict-origin-when-cross-origin`**: Limits referrer information to the origin when navigating cross-origin over HTTPS.
- **`X-Frame-Options: DENY`**: Mitigates clickjacking.
- **`Permissions-Policy: camera=(), microphone=(), geolocation=()`**: Restricts access to sensitive browser capabilities.
- **`Strict-Transport-Security` (HSTS)**: Automatically emitted in production environments on HTTPS requests (`max-age=31536000; includeSubDomains`).
- **`Content-Security-Policy`**: Enforces strict origin restrictions and binds inline/external scripts using a cryptographically random per-request nonce (`Vite::useCspNonce()`).
  - **Development Relaxations**: Relaxations (`'unsafe-eval'` in `script-src` and the active Vite dev server HTTP/WebSocket origins in `connect-src`) apply **only** in non-production environments when the Vite development server is actively running (`Vite::isRunningHot()`, i.e. when `public/hot` exists). Production (`APP_ENV=production`), staging, preview, CI, and local builds without the Vite dev server enforce strict CSP without `'unsafe-eval'` or dev server connect origins.
  - Configure CSP via `config/security.php` or environment variables:
    - `SECURITY_CSP_ENABLED=true` (or `false` to disable CSP)
    - `SECURITY_CSP_REPORT_ONLY=true` (for CSP report-only mode)
    - `SECURITY_CSP_REPORT_TO=csp-endpoint` (CSP Level 3 reporting group name)
    - `SECURITY_CSP_REPORTING_ENDPOINTS=csp-endpoint="https://example.com/csp-reports"` (sets `Reporting-Endpoints` header)
    - `SECURITY_CSP_REPORT_URI=https://example.report-uri.com/r/d/csp/enforce` (legacy CSP report URI fallback)
    - `SECURITY_CSP_SCRIPT_SRC`, `SECURITY_CSP_CONNECT_SRC`, `SECURITY_CSP_IMG_SRC`, `SECURITY_CSP_STYLE_SRC`, `SECURITY_CSP_FONT_SRC`, `SECURITY_CSP_MEDIA_SRC`, `SECURITY_CSP_FRAME_SRC`: Comma-separated extra origins for CDNs, analytics, or embeds. Semicolons and newlines are sanitized automatically.

Self-hosted fonts are bundled at build time via `laravel-vite-plugin/fonts` (e.g. Instrument Sans) so no third-party font CDNs are queried at runtime.

---

## 3. Asset Compilation & Inertia SSR

### Client-Only Build

If not using Server-Side Rendering (SSR):

```bash
bun install --frozen-lockfile
bun run build
```

### SSR Build

When using Inertia SSR for enhanced initial page load performance and SEO:

```bash
bun install --frozen-lockfile
bun run build:ssr
```

This compiles both the client bundle in `public/build` and the SSR server in `bootstrap/ssr/ssr.js`.

### Running the SSR Server

Manage the SSR Node server with a process manager such as Supervisor or PM2:

```bash
node bootstrap/ssr/ssr.js
```

Or using PM2:

```bash
pm2 start bootstrap/ssr/ssr.js --name "infinity-ssr"
```

---

## 4. Queue Workers

Background jobs (such as notifications, exports, and audit processing) require running worker processes. Configure Supervisor to keep workers alive:

```ini
[program:infinity-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/storage/logs/worker.log
stopwaitsecs=3600
```

After deploying code updates, restart workers:

```bash
php artisan queue:restart
```

---

## 5. Task Scheduler

Add a single cron entry on your production server to trigger scheduled tasks every minute:

```crontab
* * * * * cd /var/www && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Production Deployment Checklist

Run the following Artisan commands during deployment to run migrations, synchronize roles and permissions, and cache routes, configuration, events, and views:

```bash
# Run database migrations
php artisan migrate --force

# Synchronize authorization permissions and roles (idempotent; required if Authorization is enabled)
php artisan authorization:setup

# Optimize and cache configuration & routes
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue workers to pick up new code
php artisan queue:restart
```

---

## 7. Initial Production Bootstrap: Roles & First Super Admin

On a fresh production database, the application contains no user accounts, and roles and permissions must be initialized before administrative access can be granted.

### 1. Synchronize Roles and Permissions

Ensure migrations have run and synchronize all permissions and roles defined in your PHP enums (`App\Enums\Permission` and `App\Enums\Role`):

```bash
php artisan migrate --force
php artisan authorization:setup
```

The `authorization:setup` command is idempotent. It creates any missing permissions and the `super-admin` role in the database, associates all permissions with the Super Admin role, and clears the permission cache. Running it multiple times has no side effects and is safe to execute on every deployment (including after introducing new enum cases).

### 2. Create or Assign the First Super Admin

Use the persistent runtime command `php artisan authorization:create-admin` to provision your initial administrator or promote an existing user. Unlike installer-only commands, `authorization:create-admin` remains available in production and does not depend on development packages (such as `laravel/tinker`).

To protect credentials, passwords are **never** accepted as command-line arguments (`--password`). In addition, the command refuses by default if a Super Admin already exists in the database, maintaining a minimal runtime attack surface unless `--force` is explicitly provided.

#### Option A: Interactive Administrator Creation

Run the command interactively to be prompted for email, name, and password with hidden input and confirmation:

```bash
php artisan authorization:create-admin
```

#### Option B: Non-Interactive Provisioning via STDIN (CI/CD & Automation)

For scripted deployments, provide `--name` and `--email`, and pipe the password securely via STDIN:

```bash
echo "your-secure-password" | php artisan authorization:create-admin --name="Administrator" --email="admin@your-domain.com" --no-interaction
```

> **Note:** The password is validated against `Password::defaults()`. When email verification is enabled, the administrator's email is automatically marked as verified upon creation.

#### Option C: Promote an Existing User by Email

If the user account already exists in the database:

```bash
php artisan authorization:create-admin --email="admin@your-domain.com"
```

No password is required when promoting an existing user. If a Super Admin already exists and you wish to promote an additional user, include `--force`:

```bash
php artisan authorization:create-admin --email="second-admin@your-domain.com" --force
```

