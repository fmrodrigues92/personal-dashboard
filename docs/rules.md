# AI Development Rules

## 1. Objective

Build the system using a simple and consistent flow:

Controller -> UseCase -> Service -> RepositoryInterface -> Domain Entity

Persistence must be implemented separately through Laravel Eloquent models in `app/Models`.

Focus on:
- simplicity
- readability
- maintainability
- testability

---

## 2. Feature-First Development

- Features must be developed one at a time
- Each feature specification must live in `docs/features/001-featureName.md`
- The implementation must follow the explicit rules described in the feature file
- Do NOT anticipate future features unless the current feature explicitly requires it
- When in doubt, prioritize the current feature scope over generic architecture

---

## 3. Core Rules

- Do NOT create unnecessary abstractions
- Do NOT use event-driven architecture unless explicitly required
- Do NOT place business logic in controllers
- Do NOT access repositories directly from controllers
- Do NOT create generic or base classes without clear need
- Do NOT develop the frontend until explicitly authorized

Prefer:
- direct code
- small services
- clear responsibilities
- incremental delivery

---

## 4. Layer Responsibilities

### Controller
- Handle HTTP request/response
- Detect response format based on request headers
- Call UseCase
- Return Inertia response when the request is for the web interface
- Return JSON when the request is for API consumption
- Use the same controller to support both InertiaJS and API consumption whenever applicable
- Keep controllers free from business logic

### UseCase
- Orchestrate flow
- Call Service
- Return result

### Service
- Contain business rules
- Perform calculations

### RepositoryInterface
- Define persistence contract only

### Domain/Model
- Domain must be a pure entity class
- Domain classes must not extend Laravel Eloquent models
- Domain classes must represent business data and behavior only
- Laravel Eloquent models must live in `app/Models`
- Infrastructure repositories must map Eloquent models to domain entities

---

## 5. Frontend and API Strategy

- During development, API consumption may be used before the frontend is approved
- Backend endpoints should be ready to serve both InertiaJS and API consumers
- Frontend implementation must only begin after explicit authorization
- Until then, focus on domain, application flow, persistence, and API-compatible responses

---

## 6. Folder Structure

app/
 ├── Models/
 └── Src/
      ├── DomainA/
      ├── DomainB/
      └── ...

docs/
 └── features/
      ├── 001-featureName.md
      ├── 002-featureName.md
      └── ...

Each domain:
- UseCases/
- Services/
- Contracts/
- Domain/
- Infrastructure/

Rules:
- `Domain/` contains pure entity classes only
- `app/Models` contains Laravel Eloquent models only
- Do NOT mix Laravel persistence concerns into domain entities

---

## 7. Testing (Pest)

Minimum required for each feature:

### Feature Tests
- endpoint success
- validation error
- persistence
- JSON response contract
- Inertia response contract when the feature is authorized for frontend delivery

### Unit Tests
- business rule coverage for the current feature only

Rules:
- Do NOT overtest
- Prefer simple tests
- Avoid unnecessary mocks

---

## 8. Final Rule

When in doubt:
- use fewer files
- write simpler code
- follow the current feature document
- do not build frontend without authorization
- avoid overengineering
