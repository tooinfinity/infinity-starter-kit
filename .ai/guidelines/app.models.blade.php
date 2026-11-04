# App/Models guidelines

- Models are schemas, relationships, query scopes, and lightweight predicates only. Never place workflow orchestration or multi-model mutation logic inside Models.
- Always use the modern `protected function casts(): array` method instead of the legacy `$casts` property.
- Keep strict N+1 lazy loading prevention enabled. Always eager-load relations needed by frontend views.
- Control model serialization: declare what leaves the server using `#[Visible]` with an explicit allowlist or `#[Hidden]` covering sensitive columns.
- Keep business mutations in Actions (`app/Actions`) and complex read queries in Queries (`app/Queries`).
