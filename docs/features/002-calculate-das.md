# Feature 002: Calculate DAS

## 1. Goal

Allow a Simples Nacional company to calculate:
- monthly gross revenue
- the DAS total amount to be paid for the month
- the tax breakdown of the DAS composition
- a month-by-month DAS timeline for past and future periods

This feature must support:
- calculation based on billing invoices
- annex-sensitive calculation for service invoices
- `Factor R` support
- versioned tax calculation rules
- tax amount correction at DAS tax component level
- projected future months based on simulation invoices

Frontend implementation is out of scope until explicit authorization is given.

---

## 2. Domain Language

- DAS: the Simples Nacional monthly tax payment
- Monthly Revenue: the BRL revenue considered for a specific month
- Tax Breakdown: the segmented amounts for each tax component included in the DAS composition
- Calculation Rule Version: the versioned implementation of the tax formula used for a calculation
- Factor R: the rule that may allow invoices from annex 5 to be calculated as annex 3
- Real Annex: the original service annex stored in `cnae_annex`
- Calculation Annex: the annex effectively used in the monthly closing, stored in `cnae_calculation`
- Monthly Closing: the operation that calculates the DAS for a month and records the calculation context
- Projection: a future month result produced from simulation invoices
- Tax Correction: a manual adjustment applied to a DAS tax component amount when a cent-level decimal issue must be fixed

Current supported annexes:
- `3`
- `5`

Current supported invoice types:
- `national`
- `international`

---

## 3. Business Context

- The DAS calculation depends on the invoice service annex
- A service invoice may be calculated under annex 3 or annex 5 depending on the invoice CNAE
- `cnae_annex` stores the real annex of the service provided
- `cnae_calculation` is not filled during invoice creation
- `cnae_calculation` must be filled during the monthly closing to record the annex effectively used in the DAS calculation
- If the company satisfies `Factor R`, invoices originally classified under annex 5 may be calculated as annex 3
- For this feature version, the `Factor R` function must always return `true`
- The DAS result must be segmented by tax component
- International service invoices do not pay some tax components
- The active rule version must decide which tax components are zero for international invoices
- Simulated invoices exist only to project future months
- Manual corrections are not needed for simulation invoices

---

## 4. Architecture Rules

### Mandatory Design

- The calculation logic must be versioned
- Each legislation version must be implemented as a new calculation class
- Old rule classes must not be edited when a new legislation version is introduced
- A selector or resolver must choose the correct calculation rule version
- A persisted DAS calculation must store which rule version was used

### Extension Rule

- Future legislation changes must be implemented by adding a new calculation function or class
- Recalculation of old periods must still be possible using the old rule version
- This feature must follow the Open/Closed Principle for tax calculation rules

### Factor R Rule

- The `Factor R` evaluation must be isolated from the DAS formula itself
- For this feature version, the `Factor R` function must always return `true`
- The architecture must allow replacing this temporary implementation in a future feature without changing previous DAS rule versions

---

## 5. Persistence

### 5.1 Existing Table Usage

`billing_invoices`

### Billing Invoice Rules

- `cnae_annex` must store the real annex of the service provided
- `cnae_calculation` must remain `null` when the invoice is created
- `cnae_calculation` must be populated only during the monthly closing
- `cnae_calculation` must store the annex effectively used in the DAS calculation for that invoice in that month
- If an annex 5 invoice is moved to annex 3 because `Factor R` is `true`, `cnae_calculation` must be stored as `3`
- This feature must not add a value-correction column to `billing_invoices`

### 5.2 New Table

`das_calculations`

### Columns

| Column | Type | Nullable | Notes |
| --- | --- | --- | --- |
| `id` | bigint | no | Primary key |
| `reference_month` | date | no | Month being calculated, stored as first day of month |
| `rule_version` | string | no | Identifier of the applied calculation rule version |
| `factor_r_applied` | boolean | no | Result of the factor R evaluation used in the calculation |
| `monthly_revenue_brl` | decimal(15,2) | no | Revenue considered for the month |
| `das_total_brl` | decimal(15,2) | no | Final DAS amount |
| `is_projection` | boolean | no | Indicates whether the result is projected from simulation invoices |
| `metadata` | json | yes | Extra calculation context required by the rule version |
| `created_at` | timestamp | no | Default Laravel timestamp |
| `updated_at` | timestamp | no | Default Laravel timestamp |

### 5.3 New Table

`das_calculation_tax_breakdowns`

### Columns

| Column | Type | Nullable | Notes |
| --- | --- | --- | --- |
| `id` | bigint | no | Primary key |
| `das_calculation_id` | bigint | no | Foreign key to `das_calculations` |
| `tax_component_code` | string | no | Example: `irpj`, `csll`, `cofins`, `pis_pasep`, `cpp`, `iss` |
| `annex_used` | integer | yes | Effective annex used for this tax composition |
| `invoice_type` | string | yes | `national` or `international` |
| `calculated_amount_brl` | decimal(15,2) | no | Original amount produced by the calculation engine |
| `adjusted_amount_brl` | decimal(15,2) | yes | Manual adjustment used to fix cent-level decimal issues |
| `rate_percentage` | decimal(10,6) | yes | Rate used for the component by the active rule version |
| `created_at` | timestamp | no | Default Laravel timestamp |
| `updated_at` | timestamp | no | Default Laravel timestamp |

### Persistence Rules

- The DAS calculation must persist the final result
- The DAS calculation must persist the tax segmentation
- The persisted result must store the applied rule version
- Tax components that do not apply must be stored with `0.00` when the active rule version explicitly evaluates them
- `calculated_amount_brl` stores the raw result generated by the rule version
- `adjusted_amount_brl` stores the manual correction when needed
- The effective tax component amount is:
  `adjusted_amount_brl` when present, otherwise `calculated_amount_brl`
- Manual correction must exist only in DAS tables, never in `billing_invoices`

---

## 6. Supported Operations

### 6.1 Calculate DAS for a Month

Calculate the DAS for a given reference month.

#### Input

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `reference_month` | date | yes | Stored as the first day of the month |
| `rule_version` | string | no | When omitted, use the current default rule version |

#### Rules

- The calculation must use non-deleted billing invoices from the requested month
- Simulated and non-simulated invoices may be included if they exist in the month
- The calculation must use `amount_brl` from the invoice
- Invoices must be grouped according to the effective annex used in the month calculation
- If an invoice is originally annex 5 and `Factor R` is `true`, it must be calculated as annex 3
- The monthly closing must populate `cnae_calculation` for every invoice used in the month
- The calculation must return and persist the DAS total
- The calculation must return and persist the tax breakdown
- The calculation must persist the rule version used
- The calculation must persist whether the result is a projection or not

### 6.2 List DAS Calculations

List persisted DAS calculations.

#### Rules

- Results must be ordered by `reference_month` descending
- This operation is required for API consumption

### 6.3 Show One DAS Calculation

Return one persisted DAS calculation with its tax breakdown.

#### Rules

- The response must include the tax components
- The response must include the rule version used
- The response must include whether `Factor R` was applied
- The response must include whether the result is a projection

### 6.4 Correct DAS Tax Component Amount

Correct the BRL amount of a DAS tax component.

#### Input

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `adjusted_amount_brl` | float | yes | Must be greater than or equal to `0` |

#### Rules

- This operation exists to correct cent-level decimal issues in the tax breakdown
- This operation must not overwrite `calculated_amount_brl`
- This operation must update the effective tax component amount through `adjusted_amount_brl`
- This operation is only allowed for non-projection DAS calculations
- Future projected months created from simulated invoices must not allow manual correction

### 6.5 Get DAS Timeline

Return the DAS value month by month for past and future periods.

#### Input

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `reference_month` | date | no | Defaults to the current month |
| `months_before` | integer | no | Defaults to `12` |
| `months_after` | integer | no | Defaults to `12` |

#### Rules

- The response must return an array of month items
- The default range must include 12 months in the past and 12 months in the future
- The request may customize the number of past and future months
- Past months should use persisted or calculable real data
- Future months should use simulation-based projected data when available
- Each item must include at least:
  `reference_month`, `das_total_brl`, `is_projection`

---

## 7. Calculation Rules

### 7.1 Invoice Revenue Source

- Revenue for the month must come from billing invoices
- The calculation month must use `billing_date`
- Deleted invoices must not be considered
- Revenue amount must use `amount_brl`

### 7.2 Annex Resolution

- Each invoice starts from `cnae_annex`
- The active calculation rule must determine the effective annex used in the DAS calculation
- The monthly closing must persist the effective annex into `cnae_calculation`
- For this feature version:
  invoices under annex 5 must be treated as annex 3 because `Factor R` always returns `true`

### 7.3 Tax Segmentation

- The DAS result must be segmented by tax component
- The initial rule version must support at least these components:
  `irpj`, `csll`, `cofins`, `pis_pasep`, `cpp`, `iss`
- The active rule version must decide the rate and amount of each component
- International service invoices must produce zero for non-applicable components according to the active rule version
- Decimal correction must happen only at the tax component level through `adjusted_amount_brl`

### 7.4 Rule Versioning

- The first implementation must be created as a named version
- The initial implementation for this feature must be:
  `simples_nacional_service_2018`
- Future changes must create new versions such as:
  `simples_nacional_service_2026`
- Historical recalculation must be able to explicitly request an older version

### 7.5 Initial Rate Source

- The initial implementation of `simples_nacional_service_2018` must use the annex tables and tax distribution percentages from these sources:
  [Contabilizei - Anexo III Simples Nacional, updated on January 13, 2026](https://www.contabilizei.com.br/contabilidade-online/anexo-3-simples-nacional/)
  [Contabilizei - Anexo V Simples Nacional, updated on January 28, 2026](https://www.contabilizei.com.br/contabilidade-online/anexo-5-simples-nacional/)
- The initial implementation must use the annex III nominal rates and deduction values for the six revenue brackets
- The initial implementation must use the annex V nominal rates and deduction values for the six revenue brackets
- The initial implementation must use the tax distribution tables for annex III and annex V as the base for DAS tax segmentation
- The initial implementation must treat the `Factor R` threshold as `28%`
- These source tables must be encoded into the rule version implementation instead of being fetched dynamically at runtime
- If the legislation or reference tables change in the future, a new rule version must be created instead of editing `simples_nacional_service_2018`

---

## 8. Validation Rules

- `reference_month` must be a valid date
- `reference_month` must be normalized to the first day of the month before persistence
- `rule_version` must exist in the available calculation rule registry when informed
- DAS calculation must fail when there are no invoices for the requested month
- `adjusted_amount_brl` must be greater than or equal to `0`
- Tax correction must fail for projected calculations
- `months_before` must be an integer greater than or equal to `0`
- `months_after` must be an integer greater than or equal to `0`

---

## 9. Controller Contract

The same controller must support both response types based on request headers whenever applicable.

### Response Strategy

- Return JSON for API requests
- Return Inertia responses for web requests
- Response negotiation must be based on request headers
- Controller business logic is not allowed

### Current Delivery Scope

- Backend behavior is required now
- Frontend pages are not authorized in this feature
- Inertia support must be considered in controller design, but no frontend implementation should be built yet

---

## 10. Suggested Endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `POST` | `/api/das-calculations` | Calculate and persist DAS for a month |
| `GET` | `/api/das-calculations` | List DAS calculations |
| `GET` | `/api/das-calculations/{id}` | Show one DAS calculation |
| `PATCH` | `/api/das-calculations/tax-breakdowns/{id}` | Correct a DAS tax component amount |
| `GET` | `/api/das-calculations/timeline` | Return past and future DAS values by month |

---

## 11. Acceptance Criteria

- The system can calculate monthly revenue from billing invoices
- The system can calculate and persist the DAS total for a month
- The system persists the tax breakdown of the DAS composition
- `cnae_calculation` is populated only during the monthly closing
- Annex 5 invoices are calculated as annex 3 in this feature version because `Factor R` always returns `true`
- International service invoices generate zero for non-applicable taxes according to the active rule version
- The persisted DAS result stores which rule version was used
- A new legislation rule can be added without changing the old rule class
- A non-projection DAS tax component can receive a manual decimal correction
- A projected future calculation can not receive a manual correction
- The API can return a month-by-month DAS array for past and future periods
- API requests return JSON
- Web requests remain compatible with the same controller strategy

---

## 12. Out of Scope

- frontend screens
- editing historical rule implementations
- replacing the temporary `Factor R` behavior
- payroll data integration for real `Factor R`
- automatic legal update ingestion
