# Feature 004: Tax Dashboard Frontend

## 1. Goal

Provide the authenticated dashboard frontend for tax visibility using Laravel InertiaJS and React.

For this feature version, the dashboard must focus only on DAS calculation data.

The design must still allow future expansion for:
- pro-labore visualization
- pro-labore tax calculations
- broader tax reporting widgets

This feature must support:
- rendering the main authenticated dashboard as a tax dashboard
- consuming the current DAS timeline structure
- showing past and future monthly DAS values
- distinguishing legal and accounting comparison scenarios
- using Laravel web routes for frontend navigation

---

## 2. Domain Language

- Tax Dashboard: the main authenticated dashboard page used to visualize tax data
- DAS Timeline: the month-by-month series returned by the DAS timeline endpoint
- Real DAS Scenario: the scenario calculated according to the current legal rule implementation
- Accounting DAS Scenario: the comparison scenario mirroring the accounting interpretation currently used for reference
- Timeline Month Item: one month entry in the timeline response

Current dashboard scope:
- DAS only

Future dashboard scope:
- pro-labore
- labor taxes
- additional tax summaries

---

## 3. Delivery Scope

### Included

- frontend dashboard focused on DAS
- use of the current timeline endpoint structure
- month cards, charts, or summary widgets derived from timeline data
- selectable timeline window
- timeline centered on the current month by default when the default window is loaded
- dual month highlighting:
  one highlight for the selected month and another persistent visual marker for the current month
- clear comparison between `das_real` and `das_contabilidade`
- visibility of `rbt12_national_brl`, `rbt12_international_brl`, and `rbt12_total_brl`
- future-facing financial placeholders for pro-labore, personal taxes, company costs, and assessed profit
- responsive dashboard layout

### Not Included

- new DAS business rules
- changes to tax formulas
- pro-labore calculation screens
- invoice management screens
- tax export files

---

## 4. Web and API Strategy

### Authentication Rule

- The dashboard frontend must use the standard Laravel web authentication flow already integrated with Fortify
- The frontend must not rely on `/api` routes for primary browser navigation

### Routing Rule

- The authenticated dashboard route must remain web-based
- The main entry point should be the current dashboard route:
  `GET /dashboard`
- Router changes may be made only when required to mount the dashboard through Inertia in the correct place

### Controller Response Rule

- Backend controllers may still serve JSON for explicit debug requests
- Web navigation must return Inertia responses
- Requests that explicitly expect JSON must return JSON
- Response negotiation must continue to be based on request headers

---

## 5. Timeline Endpoint Contract

The frontend must be designed on top of the current DAS timeline endpoint structure.

### Web Endpoint

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/das-calculations/timeline` | Return the DAS timeline for the dashboard |

### Query Parameters

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `reference_month` | date or `YYYY-MM` | no | Defaults to the current month |
| `months_before` | integer | no | Defaults to `12` |
| `months_after` | integer | no | Defaults to `12` |
| `rule_version` | string | no | Uses the default rule when omitted |

### Request Behavior

- When `reference_month` is sent alone, the endpoint returns only that month
- When `reference_month` is omitted, the endpoint returns a default window of 12 months before and 12 months after the current month
- The frontend may request a custom window using `months_before` and `months_after`

---

## 6. Timeline Response Structure

### Top-Level Response

The endpoint returns:

```json
{
  "data": [
    {
      "reference_month": "2026-04-01",
      "das_total_brl": 0,
      "monthly_revenue_brl": 0,
      "is_projection": false,
      "rule_version": "simples_nacional_service_2018",
      "das_calculation_id": null,
      "rbt12_national_brl": 0,
      "rbt12_international_brl": 0,
      "rbt12_total_brl": 0,
      "das_real": {
        "rbt12_brl": 0,
        "monthly_revenue_brl": 0,
        "das_total_brl": 0,
        "tax_breakdowns": []
      },
      "das_contabilidade": {
        "rbt12_brl": 0,
        "monthly_revenue_brl": 0,
        "das_total_brl": 0,
        "tax_breakdowns": []
      }
    }
  ]
}
```

### Timeline Month Item Fields

| Field | Type | Notes |
| --- | --- | --- |
| `reference_month` | string | First day of the month |
| `das_total_brl` | float | Mirrors the main DAS total for the month |
| `monthly_revenue_brl` | float | Revenue considered in the main scenario |
| `is_projection` | boolean | Indicates future or simulation-driven context |
| `rule_version` | string | Applied DAS rule version |
| `das_calculation_id` | integer or null | Persisted DAS calculation id when available |
| `rbt12_national_brl` | float | National revenue base for 12-month rolling view |
| `rbt12_international_brl` | float | International revenue base for 12-month rolling view |
| `rbt12_total_brl` | float | Combined rolling revenue base |
| `das_real` | object | Legal scenario payload |
| `das_contabilidade` | object | Accounting comparison scenario payload |

### Scenario Object Fields

Both `das_real` and `das_contabilidade` currently follow this shape:

| Field | Type | Notes |
| --- | --- | --- |
| `rbt12_brl` | float | Scenario-specific rolling base |
| `monthly_revenue_brl` | float | Scenario monthly revenue |
| `das_total_brl` | float | Scenario DAS total |
| `tax_breakdowns` | array | Scenario tax components |

### Tax Breakdown Item Fields

Current breakdown items may contain:

| Field | Type | Notes |
| --- | --- | --- |
| `tax_component_code` | string | Example: `irpj`, `csll`, `cofins`, `pis_pasep`, `cpp`, `iss` |
| `annex_used` | integer or null | Effective annex used |
| `invoice_type` | string or null | `national` or `international` |
| `calculated_amount_brl` | float | Raw calculated amount |
| `adjusted_amount_brl` | float or null | Present on stored persisted results |
| `rate_percentage` | float or null | Applied component rate |

The frontend must tolerate missing optional fields depending on whether the month item comes from a preview calculation or a stored persisted calculation.

---

## 7. Dashboard UI Rules

### Main Focus

The page must prioritize understanding the DAS position quickly.

Recommended primary blocks:
- current month DAS summary
- timeline comparison view
- rolling revenue summary
- tax component breakdown for the selected month
- future financial placeholders for pro-labore and company cash-out items

### Timeline Visual Rules

- The timeline should open already centered around the current month when using the default window
- The selected month must have the primary active highlight
- The current month must keep a secondary persistent highlight even when another month is selected
- Projection months should remain visually distinguishable from real months

### Comparison Requirement

The dashboard must clearly show both:
- `DAS_real`
- `DAS_contabilidade`

The UI must make it obvious that:
- `das_real` is the legal-rule scenario implemented by the system
- `das_contabilidade` is a comparison scenario for accounting interpretation

### RBT12 Visibility

The dashboard must surface:
- `rbt12_national_brl`
- `rbt12_international_brl`
- `rbt12_total_brl`

This information must be visible at least for the currently selected month.

### Selected Month Detail

When a month is selected, the dashboard should show:
- reference month
- monthly revenue
- rule version
- projection status
- legal DAS total
- accounting DAS total
- per-tax breakdown for both scenarios

### Future Financial Placeholders

The dashboard must already reserve space for future personal and company cash-flow widgets.

For this feature version, these widgets may use fake values only and must not perform real calculation.

Required placeholder groups:
- Pro-labore
- Personal taxes
- Company costs
- Assessed profit

#### Pro-labore Placeholder

The pro-labore block must visually show:
- `Pro-labore base`
- `INSS discount`
- `IRPF discount`
- `Net pro-labore`

Rules:
- `INSS` and `IRPF` must appear inside the pro-labore section, not as a separate area
- The visual model should support the future business rule that the company owner uses `28%` of the month revenue as pro-labore basis
- This feature version must not calculate that percentage yet

#### Company Costs Placeholder

The company cost block must visually show fake values for at least:
- card costs
- other debit purchases
- accounting subscription

#### Profit Placeholder

- The dashboard must include a placeholder for assessed profit
- The layout should suggest that this amount represents what remains for future transfer decisions to the personal account

---

## 8. Frontend Architecture Rules

- The page must be implemented inside the Laravel Inertia React frontend
- The dashboard page should remain the main authenticated dashboard page instead of creating an unrelated second dashboard concept
- The frontend may fetch timeline data through JSON requests to the same web endpoint
- The dashboard should be designed so future pro-labore widgets can be added without redesigning the full page
- The frontend must not duplicate DAS business logic; it must only interpret and present the timeline payload

---

## 9. Suggested Interaction Model

### Default Load

- Open the dashboard on the current month
- Request the default timeline window unless a more focused query is intentionally chosen
- Keep the current month visually centered in the timeline scroller when possible

### Month Navigation

- Allow moving through the timeline
- Allow selecting one month for detailed inspection

### Range Controls

- Allow requesting a smaller or larger window
- Keep the default experience simple for first render

### Empty and Loading States

- The dashboard must handle months without invoices
- The dashboard must handle projected months
- The dashboard must handle loading and refresh states gracefully

---

## 10. Acceptance Criteria

- An authenticated user can open the dashboard and see DAS information
- The dashboard is focused on DAS in this version
- The dashboard is built on top of the current timeline response structure
- The dashboard shows both `das_real` and `das_contabilidade`
- The dashboard shows `rbt12_national_brl`, `rbt12_international_brl`, and `rbt12_total_brl`
- The dashboard can show a selected month in detail
- The dashboard works through Laravel web authentication and Inertia
- The related backend controller still supports JSON responses for explicit debug requests
- The page structure remains extensible for future pro-labore features

---

## 11. Testing Scope

Minimum expected coverage for this feature:

### Feature Tests

- authenticated web request renders the dashboard through Inertia
- timeline JSON endpoint remains available for explicit JSON requests
- dashboard receives and renders timeline-based props or fetch results correctly

### UI Behavior Expectations

- selected month changes the visible detail section
- legal and accounting scenarios render independently
- months without revenue render zero-state values without breaking the page
- projection months render clearly as projections
- the current month remains visually marked even when another month is selected
- the future pro-labore and cost cards render as placeholders with fake values only

---

## 12. Out of Scope

- pro-labore tax calculations
- payroll tax rules
- invoice editing
- tax filing submission flows
- legislation changes for DAS calculation
