# Data guidelines

Assume the database has already been leaked. Every column, row, cache entry, log line, and file we keep is something an attacker already has. The safest data is the data we never stored.

## Store as little as possible

- Do not add a column, table, cache key, or file unless the feature cannot work without it. "Might be useful later", analytics, debugging, and convenience are not reasons to store anything.
- Derive instead of storing: counts, flags, and anything computable from data we already hold are queried or computed, never persisted. Caching a computation is fine when the cache entry has a TTL and holds nothing sensitive.
- Keep only what is shown. If a value is fetched from a third party and never displayed or acted on, drop it on the spot instead of saving it "just in case".
- Prefer short-lived over permanent. Typing indicators, signature nonces, and sync markers live in the cache with a TTL, not in a table.
- Every new column needs three answers in the migration's pull request: why the feature cannot work without it, who is allowed to see it, and when it is deleted. If any answer is "not sure", do not add the column.

## Name timestamps after the moment

- Every date or time column ends in `_at` and names the moment it records: `created_at`, `updated_at`, `verified_at`, `expires_at`. Never `_since`, `_on`, `_date`, `_time`, or a bare noun, so every timestamp reads the same way across features.

## Never store secrets

- Never persist tokens, secrets, passwords, private keys, OAuth access or refresh tokens, API keys, session ids, raw signatures, or anything that grants access.
- Credentials the app needs come from the environment (`config/services.php`), never from a table, a seeder, a test fixture, or a committed file.
- Short-lived secrets that must be held between requests go in the cache, encrypted with `Crypt::encryptString()`, with a TTL shorter than their lifetime.
- Never log or report a secret, a request body, a message body, an email address, or a full request. Log identifiers (user id, status code) and nothing else.
- Never put a secret in a job payload, a queued notification, a flash message, a URL, a cookie, or an Inertia prop.

## Encrypt sensitive content

- Use encrypted Eloquent casts (`'encrypted'`) for stored content that contains or can reasonably contain sensitive information: private messages, personal notes, non-public personal or contact details.
- Ordinary operational metadata—timestamps, booleans, counters, foreign keys, status enums—uses normal column types and casts.
- Encrypted content must not have an unencrypted duplicate for searching or indexing.

## Expose nothing by default

- Every model that reaches the frontend or API declares what leaves the server: `#[Visible([...])]` with an explicit allowlist, or `#[Hidden([...])]` covering every column that is not public. New columns are hidden until proven necessary.
- Internal storage paths and third-party secret identifiers are never serialized. When a model needs a URL, expose a named route, not the storage path.
- Only the user themselves sees their own private settings and hidden fields. Error and validation messages must not reveal another user's hidden state either.

## Delete everything with the account

- Any new table, column, or stored file that relates to a user must cascade on delete or be removed explicitly when a user account is deleted, with test coverage ensuring clean removal.
