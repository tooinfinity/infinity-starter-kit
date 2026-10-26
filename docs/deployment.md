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
- **`TRUSTED_PROXIES`**: Required when hosting behind load balancers or reverse proxies (AWS ALB/ELB, Cloudflare, Traefik, Nginx, Caddy).
  - Use `*` to trust all reverse proxies forwarding traffic to the container/server:
    ```ini
    TRUSTED_PROXIES=*
    ```
  - Or specify explicit comma-separated IP addresses or CIDR blocks:
    ```ini
    TRUSTED_PROXIES=10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
    ```

---

## 2. Security Headers & CSP

The application includes `App\Http\Middleware\SecurityHeaders`, which automatically adds defensive HTTP headers to responses:

- **`X-Content-Type-Options: nosniff`**: Prevents MIME-type sniffing.
- **`Referrer-Policy: strict-origin-when-cross-origin`**: Limits referrer information to the origin when navigating cross-origin over HTTPS.
- **`X-Frame-Options: DENY`**: Mitigates clickjacking.
- **`Permissions-Policy: camera=(), microphone=(), geolocation=()`**: Restricts access to sensitive browser capabilities.
- **`Strict-Transport-Security` (HSTS)**: Automatically emitted in production environments on HTTPS requests (`max-age=31536000; includeSubDomains`).
- **`Content-Security-Policy`**: Enforces strict origin restrictions and binds inline/external scripts using a cryptographically random per-request nonce (`Vite::useCspNonce()`).

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

## 6. Production Optimization Checklist

Run the following Artisan commands during deployment to cache routes, configuration, events, and views:

```bash
# Run database migrations
php artisan migrate --force

# Optimize and cache configuration & routes
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue workers to pick up new code
php artisan queue:restart
```
