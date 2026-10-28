# API v1

All endpoints return JSON except private image reads, which return the authorized image. Browser authentication uses Laravel Sanctum's same-origin session cookie. Mutations require a CSRF cookie (`GET /sanctum/csrf-cookie`). Authenticated tenant endpoints resolve `X-Company-ID` only when the current user has an active membership; otherwise they fall back to the user's valid selected company.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/v1/auth/register` | Create user, company, owner membership, and default stages |
| POST | `/api/v1/auth/login` | Start authenticated session |
| POST | `/api/v1/auth/logout` | Invalidate session |
| GET | `/api/v1/bootstrap` | User, company, memberships, role, and stage configuration |
| POST | `/api/v1/companies/{id}/switch` | Select an authorized company |
| GET | `/api/v1/dashboard` | Real scoped metrics, pipeline counts, tasks, recent leads |
| GET | `/api/v1/reports?period=month` | Period-scoped lead source, pipeline conversion, and deal outcome/value summaries (`view_reports` / `view_own_reports`); accepts month, quarter, or year |
| GET/POST | `/api/v1/leads` | Filtered paginated list / create |
| GET/PUT | `/api/v1/leads/{id}` | Scoped detail / update |
| POST | `/api/v1/leads/{id}/activities` | Add manual note or communication log |
| GET/POST | `/api/v1/tasks` | Scoped task list / create |
| PATCH | `/api/v1/tasks/{id}/complete` | Toggle completion |
| GET/POST | `/api/v1/deals` | List visible deals / create one from a lead (`view_deals` or `view_own_deals`; create permission required) |
| GET/PUT | `/api/v1/deals/{id}` | Read a deal and activity history / update deal status and details |
| GET/POST | `/api/v1/inventory` | Filtered paginated property catalogue / create listing (`view_inventory` / `manage_inventory`) |
| GET/PUT | `/api/v1/inventory/{id}` | Read listing and history / update listing |
| GET | `/api/v1/preferred-locations?active=1` | List this workspace's active locations for single-location selection |
| POST | `/api/v1/inventory/{id}/photos` | Upload a private image (JPEG, PNG, WebP, AVIF; up to 10 MB each, 20 per listing) |
| GET/DELETE | `/api/v1/inventory/{id}/photos/{photoId}` | Authorized private image read / remove from a listing |

Validation failures use Laravel's `{ message, errors }` envelope. Pagination uses Laravel's standard `{ data, current_page, last_page, ... }` response. A normalized phone collision returns `duplicate_warning` while still creating the separate enquiry.

Inventory money is submitted and returned as decimal EGP strings. The database stores integer piastres in `price_minor_units`; status, price, detail, and photo changes are recorded in listing activities and the audit log.

Property create/update requests use one tenant-owned `preferred_location_id`; new selections must be active. The response includes that ID and the current master location name. Older location text is retained as a snapshot if its master entry was removed.

Deal requests reference a company-owned `lead_id` and optionally a company-owned `property_listing_id`. `agreed_price_egp` is submitted as a decimal EGP string and stored as integer piastres. Deal value tracks the transaction and is not a commission or payment record.

Reports support `month` (month to date), `quarter` (quarter to date), and `year` (year to date). Lead and deal metrics are limited to records created in the selected period. Agents receive reports only for leads they created or are assigned; authorized management and finance roles receive company-wide reports. Won deal value is agreed deal value, not collected cash.

## External B2B portal catalog

- GET `/api/v1/portal/accounts`: authenticated session; proxies the current user's eligible external customer accounts. A sole account is selected automatically; multiple accounts require a choice; an empty list blocks catalog access.
- POST `/api/v1/portal/account` with `{ "account_code": "..." }`: selects only an account present in that session's fetched account list.
- GET `/api/v1/portal/account-details`: returns read-only contact and company information for the selected external account. The service token remains server-side.
- GET `/api/v1/portal/addresses`: returns read-only invoice and shipping addresses for the selected external account; address records are not copied into the local database.
- POST `/api/v1/portal/products`: verified, active tenant subscription, and selected external account required. Accepts a non-negative `max_id` cursor, `search`, `favorites`, `promotions`, and `category_list`; returns normalized product data, current/total counts, and the next cursor. The client appends unique products and sends the returned cursor for the next page.
- GET `/api/v1/portal/filters`: same access requirements; proxies the external product filters.
- GET `/api/v1/portal/products/{sku}`: returns the authoritative product details for the selected external account (or the selected demo item in demo mode).
- POST `/api/v1/portal/products/{sku}/favorite`: toggles the selected user's favorite for the selected external customer account through the B2B service.
- GET `/api/v1/portal/cart`: returns the session cart for the signed-in user and selected account.
- POST `/api/v1/portal/cart/items` with `{ "sku": "...", "quantity": 1 }`: adds an item after the server resolves its price, case quantity, and stock.
- POST `/api/v1/portal/cart/quick-order` with an `items` array of `{ "sku": "...", "quantity": 1 }`: validates up to 50 unique products and all quantities before atomically adding them to the basket.
- PATCH `/api/v1/portal/cart/items/{sku}` with `{ "quantity": 2 }`: changes a line after the same server-side checks. DELETE the item route removes one line; DELETE `/api/v1/portal/cart` clears the cart.
- GET `/api/v1/portal/orders?page=1&per_page=25&search=...`: returns paginated order history from the source `get_sales_orders` endpoint for the selected customer account. Orders are read-only in this page.
- Configure `B2B_PORTAL_API_BASE_URL`, `B2B_PORTAL_API_TOKEN`, and optional account/product/filter/stock/favorite endpoint names in the deployment environment. The API token is sent only from Laravel to the external service.
- External calls use configurable `B2B_PORTAL_CONNECT_TIMEOUT` and `B2B_PORTAL_TIMEOUT` limits, reject redirects, require HTTPS in production, and return a safe 503 for transport, HTTP, or malformed JSON failures. Live upstream integration must still be verified from the deployment environment.
- Platform owners can save global landing copy, enabled modules, promotion video/poster URLs and section cards through `POST /api/v1/platform/site-settings`; public `GET /api/v1/site-settings` returns the validated settings consumed by the welcome page. Promotion videos must be direct HTTPS MP4/WebM files.
- When the external base URL/token are not configured, local/testing environments may show ten static sample products with local SVG images, search, and category/favorite/promotion filters. `B2B_PORTAL_DEMO_MODE` can override this; production defaults it off and returns 503 until the service is configured. Demo mode bypasses the subscription gate only for catalog and basket testing; it cannot submit an order.
- The current source returns rendered HTML in the product `data` field. The client extracts `.add_to_basket_btn` data into native cards and keeps a script-disabled sandboxed iframe fallback for unknown markup. Structured product fields are preferred when available.
- The cart is stored in the authenticated server session and scoped to the signed-in user, active company, and selected account. The server determines price and enforces case quantity and stock; it does not create an order or payment.

## Support cases

- GET/POST /api/v1/support-cases: tenant-scoped, paginated case list and creation; optional search/status/priority filters; agents see cases they created or are assigned, management and finance roles see company cases.
- GET/PATCH /api/v1/support-cases/{supportCase}: read visible case/thread or, for case managers, update status, priority, and assignee.
- POST /api/v1/support-cases/{supportCase}/attachments: upload a private case or reply attachment (JPEG, PNG, WebP, PDF, DOC, DOCX, XLS, XLSX; 10 MB each; 20 per case).
- GET /api/v1/support-cases/{supportCase}/attachments/{attachment}: download a private attachment after tenant and case visibility checks.
- POST /api/v1/support-cases/{supportCase}/feedback: the case creator submits one 1-5 rating and optional comment after resolution.
- POST /api/v1/support-cases/{supportCase}/messages: add a reply; case managers may mark it as an internal note.

Cases accept subject, description, category, priority, optional business-system reference, support contract, serial number, contact details, and an active workspace assignee. Managers may assign only active workspace members. Feedback is limited to the original requester and can be submitted once after resolution. Internal notes are restricted to case managers and omitted from other members' case details and reply counts. Statuses are open, in progress, waiting, resolved, and closed. Closed cases must be reopened by a manager before another reply can be posted.
