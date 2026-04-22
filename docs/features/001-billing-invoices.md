# Feature 001: Billing Invoices Registration

## 1. Goal

Allow the system to register billing invoices and simulated billing invoices.

This feature must support:
- billing invoice listing for API consumption
- single creation of real billing invoices
- batch creation of simulated billing invoices
- deletion rules based on the `is_simulation` flag
- the same controller serving InertiaJS or JSON responses based on request headers

Frontend implementation is out of scope until explicit authorization is given.

---

## 2. Domain Language

- Billing Invoice: a revenue invoice issued to a customer
- Real Billing Invoice: a persisted invoice with `is_simulation = false`
- Simulated Billing Invoice: a generated invoice with `is_simulation = true`

Allowed invoice types:
- `national`
- `international`

---

## 3. Persistence

### Table

`billing_invoices`

### Columns

| Column | Type | Nullable | Notes |
| --- | --- | --- | --- |
| `id` | bigint | no | Primary key |
| `user_id` | foreignId | yes | Owner user during the transition to user-scoped data |
| `billing_date` | datetime | no | Invoice billing date |
| `type` | string | no | Allowed values: `national`, `international` |
| `cnae` | string | yes | Required for real invoices |
| `cnae_annex` | integer | yes | Required for real invoices |
| `cnae_calculation` | integer | yes | Required for real invoices |
| `customer_name` | string | yes | Required for real invoices |
| `customer_external_id` | string | yes | Required for real invoices |
| `amount_brl` | decimal(15,2) | no | BRL amount |
| `amount_usd` | decimal(15,2) | yes | Required when `type = international` for real invoices |
| `usd_brl_exchange_rate` | decimal(15,6) | yes | Required when `type = international` for real invoices |
| `is_simulation` | boolean | no | Default `false` |
| `created_at` | timestamp | no | Default Laravel timestamp |
| `updated_at` | timestamp | no | Default Laravel timestamp |
| `deleted_at` | timestamp | yes | Used only for soft delete behavior |

### Persistence Rules

- Real invoices must use soft delete
- Simulated invoices must be physically deleted
- Batch deletion of simulations must physically remove every row where `is_simulation = true`
- Every billing invoice must belong to the authenticated user through `user_id`
- New records created by the application must persist the current authenticated `user_id`
- Reads, month-based queries, updates, and deletions must be scoped to the current authenticated `user_id`
- One authenticated user must never be able to read or mutate another user's billing invoices
- The Laravel Eloquent model must live in `app/Models/BillingInvoice.php`
- The billing invoice domain entity must be a pure class separated from the Laravel model
- Repository infrastructure must map the Eloquent model to the domain entity

---

## 4. Supported Operations

### 4.1 Create Real Billing Invoice

Create one real billing invoice.

#### Input

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `billing_date` | datetime | yes | |
| `type` | string | yes | `national` or `international` |
| `cnae` | string | yes | |
| `cnae_annex` | integer | yes | |
| `cnae_calculation` | integer | yes | |
| `customer_name` | string | yes | |
| `customer_external_id` | string | yes | |
| `amount_brl` | float | yes | Must be greater than `0` |
| `amount_usd` | float | conditional | Required when `type = international` |
| `usd_brl_exchange_rate` | float | conditional | Required when `type = international` |
| `is_simulation` | boolean | no | Must be stored as `false` for this operation |

#### Rules

- This operation creates exactly one row
- `is_simulation` must be persisted as `false`
- The current authenticated `user_id` must be persisted on the new row
- When `type = national`, `amount_usd` and `usd_brl_exchange_rate` must be `null`
- When `type = international`, `amount_usd` and `usd_brl_exchange_rate` are required

### 4.2 List Billing Invoices

List persisted billing invoices.

#### Rules

- This operation must return non-deleted billing invoices only
- This operation must return only invoices owned by the current authenticated user
- Results must be ordered by `billing_date` descending
- This operation is required for API consumption

### 4.3 List Simulated Billing Invoices

List persisted simulated billing invoices.

#### Rules

- This operation must return only rows where `is_simulation = true`
- This operation must return only rows owned by the current authenticated user
- This operation must return non-deleted rows only
- Results must be ordered by `billing_date` descending

### 4.4 Create Simulated Billing Invoices in Batch

Create simulated billing invoices in batch using a date range.

#### Input

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `type` | string | yes | `national` or `international` |
| `start_date` | date | yes | Start of the simulation range |
| `end_date` | date | yes | End of the simulation range |
| `amount_brl` | float | yes | Must be greater than `0` |

#### Rules

- This operation must always persist `is_simulation = true`
- The current authenticated `user_id` must be persisted on every generated row
- This operation must create one row per month within the informed period
- The generated period is inclusive of the start month and the end month
- The generated `billing_date` must use the first day of each month at `00:00:00`
- Generated simulation rows may keep these fields as `null`:
  `cnae`, `cnae_annex`, `cnae_calculation`, `customer_name`, `customer_external_id`, `amount_usd`, `usd_brl_exchange_rate`
- For international simulations, `amount_usd` and `usd_brl_exchange_rate` remain `null` unless a future feature explicitly changes this behavior

#### Example

Input:
- `type = national`
- `start_date = 2026-01-10`
- `end_date = 2026-03-25`
- `amount_brl = 1000.00`

Expected generated rows:
- `2026-01-01 00:00:00`
- `2026-02-01 00:00:00`
- `2026-03-01 00:00:00`

### 4.5 Delete One Billing Invoice

Delete one invoice by `id`.

#### Rules

- If `is_simulation = false`, apply soft delete
- If `is_simulation = true`, apply physical delete
- The operation must behave as not found when the invoice does not belong to the current authenticated user

### 4.6 Delete All Simulations

Delete every simulated invoice.

#### Rules

- This operation must physically delete all rows where `is_simulation = true`
- This operation must only affect rows owned by the current authenticated user
- This operation must not affect rows where `is_simulation = false`

---

## 5. Validation Rules

- `billing_date` must be a valid datetime
- `type` must be `national` or `international`
- `cnae_annex` must be an integer
- `cnae_calculation` must be an integer
- `amount_brl` must be greater than `0`
- `amount_usd` must be greater than `0` when informed
- `usd_brl_exchange_rate` must be greater than `0` when informed
- `start_date` must be less than or equal to `end_date`
- Batch simulation creation must fail when the informed range does not produce at least one month

---

## 6. Controller Contract

The same controller must support both response types based on request headers.

### Response Strategy

- Return JSON for API requests
- Return Inertia responses for web requests
- Response negotiation must be based on request headers
- Ownership checks must be enforced before mutating or exposing records by identifier
- Controller business logic is not allowed

### Current Delivery Scope

- Backend behavior is required now
- Frontend pages are not authorized in this feature
- Inertia support must be considered in controller design, but no frontend implementation should be built yet

---

## 7. Suggested Endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/login` | Authenticate and receive a Sanctum token |
| `GET` | `/api/billing-invoices` | List billing invoices |
| `GET` | `/api/billing-invoices/simulations` | List simulated billing invoices |
| `POST` | `/billing-invoices` | Create one real billing invoice |
| `POST` | `/billing-invoices/simulations` | Create simulated billing invoices in batch |
| `DELETE` | `/billing-invoices/{id}` | Delete one invoice following simulation rules |
| `DELETE` | `/billing-invoices/simulations` | Delete all simulated invoices |

---

## 8. Acceptance Criteria

- A real national invoice can be created with all required real invoice fields
- A real international invoice requires `amount_usd` and `usd_brl_exchange_rate`
- Billing invoices can be listed through the API
- Simulated billing invoices can be listed through the API
- Billing invoice responses only include the current authenticated user's records
- A batch simulation request creates one row per month in the informed range
- Every batch-generated row is stored with `is_simulation = true`
- Deleting a real invoice sets `deleted_at`
- Deleting a simulated invoice removes the row physically
- Deleting all simulations removes every row with `is_simulation = true`
- One authenticated user can not read or delete another user's billing invoice records
- API requests return JSON
- Web requests are handled by the same controller with Inertia-ready response branching

---

## 9. Out of Scope

- frontend screens
- reporting
- tax calculation logic
- import from external providers
- editing existing invoices
