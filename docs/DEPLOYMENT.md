# Deployment

## Docker development

```bash
docker compose up --build
docker compose exec app php artisan migrate --seed
```

The compose stack provides PHP-FPM, Nginx, MySQL, Redis, a queue worker, and a scheduler loop. Run Vite on the host for hot reload or `npm run build` before serving static assets.

## VPS production checklist

1. Set production environment variables through the host secret manager; never copy `.env` into version control.
2. Build assets with `npm ci && npm run build`, install PHP dependencies using `composer install --no-dev --classmap-authoritative`, and cache config/routes/views.
3. Point Nginx only at `public/`, enable HTTPS/HSTS, set `APP_DEBUG=false`, and use secure/same-site cookies.
4. Run `php artisan migrate --force` once per release before rolling workers.
5. Supervise queue workers and `php artisan schedule:work`; restart workers after releases.
6. Back up MySQL and private object storage, encrypt backups, test restoration, and define retention.
7. Monitor `/up`, application errors, queue failures, disk, DB capacity, certificate expiry, and backup freshness.

Inventory photos currently use the application's private local disk and are delivered through tenant-authorized API routes. `.env.example` includes empty Wasabi/S3 placeholders, but Wasabi is not active; configure and validate a private S3-compatible disk before deploying media storage off-host.

## External B2B portal

Configure these values in the application environment, never in frontend code:

- `B2B_PORTAL_API_BASE_URL`: HTTPS base URL for the B2B service. Laravel appends the endpoint path, so do not include an endpoint path here.
- `B2B_PORTAL_API_TOKEN`: service credential. Keep it in the host secret manager and rotate it through the service owner.
- `B2B_PORTAL_CONNECT_TIMEOUT` and `B2B_PORTAL_TIMEOUT`: outbound connection and total request limits in seconds (defaults: 5 and 20). Production base URLs must use HTTPS; upstream redirects are rejected so the service token is not forwarded to another host.
- Optional endpoint overrides: `B2B_PORTAL_ACCOUNTS_ENDPOINT`, `B2B_PORTAL_ACCOUNT_DETAILS_ENDPOINT`, `B2B_PORTAL_ADDRESSES_ENDPOINT`, `B2B_PORTAL_PRODUCTS_ENDPOINT`, `B2B_PORTAL_SINGLE_PRODUCT_ENDPOINT`, `B2B_PORTAL_FAVORITE_ENDPOINT`, `B2B_PORTAL_ORDERS_ENDPOINT`, `B2B_PORTAL_FILTERS_ENDPOINT`, and `B2B_PORTAL_STOCK_ANALYSIS_ENDPOINT`.
- `B2B_PORTAL_DEMO_MODE`: defaults on only in `local` and `testing`, and only when the service URL/token are not configured. Set it to `false` in production. Set it to `true` locally only when you intentionally need the temporary sample catalog.

After configuring the URL/token, clear Laravel's cached configuration, reload `/api/v1/portal/accounts` while signed in, and confirm the expected customer account list. A live-service failure returns an error; production must not fall back to sample products. Verify every configured endpoint from the application host/container before enabling checkout. Checkout remains disabled until the order, address, shipping, and payment API contracts have been integrated and verified.
