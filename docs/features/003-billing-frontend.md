# Feature 003: Billing Frontend

## 1. Goal

Provide a web interface for billing invoice management using Laravel InertiaJS and React.

This feature must support:
- listing all billing invoices in the web interface
- filtering invoices by date range, type, and simulation flag
- client-side pagination
- single creation of real billing invoices
- batch creation of simulated billing invoices
- deletion of one real invoice at a time
- deletion of all simulated invoices at once

This feature must reuse the existing billing backend behavior and session-based web authentication.

---

## 2. Domain Language

- Billing Page: the web screen used to manage billing invoices
- Real Invoice Modal: the modal used to create one non-simulated billing invoice
- Simulation Modal: the modal used to create simulated invoices in batch
- Billing Table: the listing area for persisted billing invoices
- Filter Panel: the collapsible area used to refine the visible billing list

Allowed invoice types:
- `national`
- `international`

Allowed simulation filters:
- `all`
- `real`
- `simulation`

---

## 3. Delivery Scope

### Included

- dedicated Inertia page for billing management
- sidebar navigation entry for the billing page
- invoice list with desktop and mobile presentation
- filters for:
  `date_from`, `date_to`, `type`, `simulation`, `search`
- summary cards with current counts
- modal-based creation flows
- modal-based destructive confirmations
- client-side pagination
- loading, empty, filtered-empty, and mutation states

### Not Included

- backend billing business rule changes
- DAS calculations inside the billing screen
- inline editing of existing invoices
- frontend charts
- export features

---

## 4. Web and API Strategy

### Navigation Rule

- The billing frontend must use Laravel web routes
- The billing frontend must rely on the existing web authentication flow
- The frontend must not use `/api` routes as its primary integration path

### Controller Response Rule

- The same controller must support both response types
- Web requests must return Inertia responses
- Requests that explicitly expect JSON must return JSON
- Response negotiation must be based on request headers

### Current Route Contract

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/billing-invoices` | Render the billing Inertia page or return JSON list |
| `POST` | `/billing-invoices` | Create one real billing invoice |
| `POST` | `/billing-invoices/simulations` | Create simulated billing invoices in batch |
| `DELETE` | `/billing-invoices/{id}` | Delete one invoice following simulation rules |
| `DELETE` | `/billing-invoices/simulations` | Delete all simulated invoices |

---

## 5. Page Structure

### Main Layout

The billing page must contain:
- page header
- primary actions
- summary cards
- collapsible filter panel
- invoice list
- pagination controls

### Summary Cards

The page must display at least:
- total invoice count
- total real invoice count
- total simulation count
- filtered result count

### Filter Panel

The filter panel must support:

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `date_from` | date | no | Inclusive lower bound |
| `date_to` | date | no | Inclusive upper bound |
| `type` | string | no | `all`, `national`, `international` |
| `simulation` | string | no | `all`, `real`, `simulation` |
| `search` | string | no | Match customer name, external id, or CNAE |

### Pagination

- Pagination must be client-side
- Default page size must be `10`
- Current page must reset to `1` when filters change

---

## 6. Listing Rules

### Data Source

- The list must use billing invoices returned by the billing controller
- The list must include both real and simulated invoices
- The page must be able to receive initial invoice data through Inertia props
- The page may refresh the list through JSON requests to the same web endpoints

### Desktop Presentation

The desktop list must show at least:
- billing date
- type
- BRL amount
- customer name
- customer external id
- `cnae_annex`
- `cnae_calculation`
- simulation flag
- row actions

### Mobile Presentation

- The mobile layout must switch from a dense table to stacked cards
- The same invoice information and row actions must remain available

### Visual Indicators

- Invoice type must use badges
- Simulation state must use badges
- Destructive actions must be visually distinct

---

## 7. Modal Flows

### 7.1 Create Real Billing Invoice

Create one non-simulated invoice through a modal form.

#### Input

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `billing_date` | datetime | yes | |
| `type` | string | yes | `national` or `international` |
| `cnae` | string | yes | |
| `cnae_annex` | integer | yes | |
| `customer_name` | string | yes | |
| `customer_external_id` | string | yes | |
| `amount_brl` | float | yes | Must be greater than `0` |
| `amount_usd` | float | conditional | Required when `type = international` |
| `usd_brl_exchange_rate` | float | conditional | Required when `type = international` |

#### Rules

- The modal must submit to the existing billing create endpoint
- The modal must close after a successful request
- The list must refresh after a successful request
- Validation errors must be shown inline

### 7.2 Create Simulation Invoices in Batch

Create simulated invoices through a batch modal.

#### Input

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `type` | string | yes | `national` or `international` |
| `start_date` | date | yes | |
| `end_date` | date | yes | |
| `amount_brl` | float | yes | Must be greater than `0` |

#### Rules

- The modal must submit to the existing simulation batch endpoint
- The modal must close after a successful request
- The list must refresh after a successful request
- If the backend returns `created_count`, the UI should surface it in the success feedback

### 7.3 Delete One Real Invoice

- Only non-simulated invoices must expose the individual delete action
- Deletion must require confirmation in a modal
- The list must refresh after a successful request

### 7.4 Delete All Simulations

- The page must provide one dedicated destructive action for all simulations
- The action must require confirmation in a modal
- The UI should show the current simulation count when possible
- The list must refresh after a successful request

---

## 8. UI and Theme Rules

- The page must reuse the existing application layout
- The page must preserve the visual language already used by the project theme
- The page must prefer reusable UI primitives over one-off markup
- The page should use modals, collapsible sections, badges, cards, inputs, selects, buttons, and loading indicators
- The page must keep the interface clear and practical instead of decorative

Recommended states:
- loading
- empty
- filtered empty
- submit pending
- delete pending
- inline validation error
- success feedback

---

## 9. Frontend Architecture Rules

- The billing page must be self-contained and easy to mount through one Inertia route
- Filtering and pagination must be handled client-side
- The page must support receiving initial data from Inertia props
- Mutations may refresh the list using JSON requests to the same controller endpoints
- The implementation must not move business rules from backend to frontend

---

## 10. Acceptance Criteria

- The user can open a dedicated billing page from the sidebar
- The user can see all active billing invoices
- The user can filter invoices by date range
- The user can filter invoices by type
- The user can filter invoices by simulation status
- The user can search invoices by customer-related text
- The user can paginate through the filtered list
- The user can create one real invoice from the web interface
- The user can create simulation invoices in batch from the web interface
- The user can delete one real invoice from the web interface
- The user can delete all simulations from the web interface
- The page works with Inertia for web navigation
- The same billing controller still supports JSON responses when requested explicitly

---

## 11. Testing Scope

Minimum expected coverage for this feature:

### Frontend-Oriented Feature Tests

- billing page renders for authenticated web requests
- billing page receives initial invoice data through Inertia
- billing web create flow succeeds
- billing web simulation batch flow succeeds
- billing web delete flow succeeds
- billing web delete-all-simulations flow succeeds

### UI Validation Expectations

- filter state changes update the visible list
- pagination reacts correctly to filtering
- international real invoice flow requires USD fields
- destructive actions require confirmation

---

## 12. Out of Scope

- editing an existing billing invoice
- importing invoices from files or providers
- DAS reporting inside the billing page
- analytics dashboards
- bulk editing real invoices
