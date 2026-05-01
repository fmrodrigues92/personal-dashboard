# Feature 005: Pro-Labore Registration

## 1. Goal

Allow the system to register pro-labore receipts and simulated future pro-labore values.

This feature must support:
- registration of past pro-labore receipts
- storage of past receipts in PostgreSQL
- generation and storage of future pro-labore simulations in Redis
- estimation of future pro-labore from billing simulations already stored in Redis
- listing real and simulated pro-labore entries for dashboard and API consumption

This feature must only register gross pro-labore amounts.

INSS and IRPF calculations are explicitly out of scope.

---

## 2. Domain Language

- Pro-Labore: the partner compensation amount for a reference month
- Pro-Labore Receipt: a real, past pro-labore record stored in PostgreSQL
- Pro-Labore Simulation: a projected future pro-labore record stored in Redis
- Reference Month: the month to which the pro-labore applies, stored as the first day of the month
- Billing Simulation: a future billing invoice simulation stored in Redis
- Gross Pro-Labore Amount: the pro-labore value before INSS, IRPF, or any other deduction

---

## 3. Business Rules

### 3.1 Real Receipts

- Past pro-labore receipts must be stored in PostgreSQL
- A real receipt must belong to the authenticated user
- A real receipt represents the gross pro-labore amount for one reference month
- A real receipt must not calculate or store INSS
- A real receipt must not calculate or store IRPF
- A real receipt may be manually informed by the user

### 3.2 Future Simulations

- Future pro-labore simulations must be stored in Redis
- Future simulations must belong to the authenticated user
- Future simulations must be derived from future billing simulations stored in Redis
- The simulation amount for a month must be:
  `28%` of the simulated billing revenue for that same month
- The result must be rounded up to the next higher ten BRL
- If the `28%` result is already a multiple of `10`, it must still move to the next ten
- Simulations must not be persisted in PostgreSQL
- Simulations must not calculate or store INSS
- Simulations must not calculate or store IRPF

### 3.3 Rounding Rule

The estimated pro-labore amount must be rounded up to the next higher multiple of `10`.

The rounding is strict: a value already ending in zero still moves to the next ten.

Formula:

```text
gross_pro_labore_brl = (floor((monthly_simulated_revenue_brl * 0.28) / 10) + 1) * 10
```

Examples:

| Simulated billing | 28% result | Stored pro-labore simulation |
| --- | --- | --- |
| `1000.00` | `280.00` | `290.00` |
| `1001.00` | `280.28` | `290.00` |
| `3575.00` | `1001.00` | `1010.00` |
| `10000.00` | `2800.00` | `2810.00` |
| `15000.00` | `4200.00` | `4210.00` |

---

## 4. Persistence

### 4.1 PostgreSQL Table

`pro_labore_receipts`

### Columns

| Column | Type | Nullable | Notes |
| --- | --- | --- | --- |
| `id` | bigint | no | Primary key |
| `user_id` | foreignId | no | Owner user |
| `reference_month` | date | no | First day of the month |
| `gross_amount_brl` | decimal(15,2) | no | Gross pro-labore amount |
| `notes` | text | yes | Optional user note |
| `created_at` | timestamp | no | Default Laravel timestamp |
| `updated_at` | timestamp | no | Default Laravel timestamp |
| `deleted_at` | timestamp | yes | Used for soft delete behavior |

### PostgreSQL Persistence Rules

- PostgreSQL must store only real pro-labore receipts
- Real receipts must be soft deleted
- Real receipt reads and writes must be scoped to the authenticated `user_id`
- The pair `user_id` + `reference_month` should be unique for active records
- Real receipts must never contain simulated billing metadata
- Tax deduction fields must not be added in this feature

### 4.2 Redis Storage

Future pro-labore simulations must be stored in Redis through a repository/cache abstraction.

Suggested cache key:

```text
pro_labore_simulations:{user_id}
```

Suggested stored record shape:

```json
{
  "id": -123456,
  "user_id": 1,
  "reference_month": "2026-05-01",
  "gross_amount_brl": 2810.00,
  "source_revenue_brl": 10000.00,
  "source": "billing_simulation",
  "is_simulation": true,
  "created_at": "2026-05-01T00:00:00.000000Z",
  "updated_at": "2026-05-01T00:00:00.000000Z"
}
```

### Redis Persistence Rules

- Redis must store only future pro-labore simulations
- Redis records must be scoped by authenticated `user_id`
- Simulations must use stable month-level data, one record per reference month
- Regenerating simulations for a month may replace the previous simulation for that same month
- Clearing simulations must affect only the authenticated user's Redis key
- Redis records must be returned through the same domain shape used by real receipts where practical

---

## 5. Supported Operations

### 5.1 Create Real Pro-Labore Receipt

Create one real pro-labore receipt.

#### Input

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `reference_month` | date | yes | Stored as the first day of the month |
| `gross_amount_brl` | float | yes | Must be greater than `0` |
| `notes` | string | no | Optional |

#### Rules

- This operation must create one PostgreSQL row
- The current authenticated `user_id` must be persisted
- `reference_month` must be normalized to the first day of the month
- This operation must not write to Redis
- This operation must not calculate INSS
- This operation must not calculate IRPF

### 5.2 Generate Future Pro-Labore Simulations

Generate future pro-labore simulations from billing simulations stored in Redis.

#### Input

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `start_month` | date | yes | Start month of the simulation range |
| `end_month` | date | yes | End month of the simulation range |

#### Rules

- This operation must read billing simulations from Redis
- This operation must aggregate simulated billing revenue by month
- This operation must create one pro-labore simulation per month that has simulated billing revenue
- The generated period is inclusive of the start month and end month
- Months without billing simulation revenue should not generate a pro-labore simulation
- Each generated amount must be `28%` of that month's simulated billing revenue, rounded up to the next higher ten BRL
- Exact multiples of `10` must still move to the next ten
- This operation must store generated records in Redis
- This operation must not write generated simulations to PostgreSQL
- This operation must not calculate INSS
- This operation must not calculate IRPF

### 5.3 List Pro-Labore Entries

List real receipts and future simulations.

#### Rules

- Real receipts must come from PostgreSQL
- Simulations must come from Redis
- Results must include only entries owned by the authenticated user
- Results must be ordered by `reference_month` descending
- Each item must include whether it is a simulation

### 5.4 Delete Real Pro-Labore Receipt

Delete one real receipt by `id`.

#### Rules

- The receipt must be soft deleted in PostgreSQL
- The operation must behave as not found when the receipt does not belong to the authenticated user
- This operation must not delete Redis simulations

### 5.5 Delete Future Pro-Labore Simulations

Delete generated pro-labore simulations.

#### Rules

- This operation must delete only Redis simulations for the authenticated user
- This operation must not affect PostgreSQL receipts
- The response should include the number of deleted simulation records

---

## 6. Validation Rules

- `reference_month` must be a valid date
- `reference_month` must be normalized to the first day of the month
- `gross_amount_brl` must be greater than `0`
- `start_month` must be a valid date
- `end_month` must be a valid date
- `start_month` must be less than or equal to `end_month`
- Generation must not fail when no billing simulations exist; it should return zero generated records

---

## 7. Controller Contract

The same controller should support both response types based on request headers.

### Response Strategy

- Return JSON for API requests
- Return Inertia redirects or responses for web requests
- Response negotiation must be based on request headers
- Ownership checks must be enforced before mutating or exposing records by identifier
- Controller business logic is not allowed

---

## 8. Suggested Endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/pro-labore` | List real receipts and simulations |
| `POST` | `/pro-labore` | Create one real receipt |
| `POST` | `/pro-labore/simulations` | Generate future simulations from billing simulations |
| `DELETE` | `/pro-labore/{id}` | Soft delete one real receipt |
| `DELETE` | `/pro-labore/simulations` | Delete generated simulations |

API routes may mirror the same paths under `/api` if API consumption is required.

---

## 9. Acceptance Criteria

- A user can create a real pro-labore receipt for a reference month
- Real receipts are persisted in PostgreSQL
- Future simulations are persisted in Redis
- Future simulations are generated from billing simulations already stored in Redis
- A simulated month with `10000.00` BRL of billing revenue generates `2810.00` BRL of pro-labore
- A simulated month with `15000.00` BRL of billing revenue generates `4210.00` BRL of pro-labore
- A simulated month with `1001.00` BRL of billing revenue generates `290.00` BRL of pro-labore
- Listing returns both real receipts and Redis simulations
- Listing is scoped to the authenticated user
- One user can not read or mutate another user's pro-labore records
- Deleting a real receipt uses soft delete
- Deleting simulations clears only Redis simulation records
- No INSS fields, calculations, or persistence are added
- No IRPF fields, calculations, or persistence are added

---

## 10. Out of Scope

- INSS calculation
- IRPF calculation
- net pro-labore amount
- payroll reports
- accounting export
- payslip PDF generation
- frontend screens unless explicitly requested
- editing existing receipts
- automatic monthly closing
