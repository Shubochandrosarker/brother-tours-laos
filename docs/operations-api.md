# Operations API boundary

The Operations API is an authenticated REST adapter for the Brother Tours
operations app. WordPress remains the source of truth; the API does not own a
second tour or booking database.

Install it only after Tour Manager and Formistic are active. Confirm:

- the app origin is explicitly allowlisted;
- login, session expiry, CSRF, and capability checks pass;
- health and error responses do not expose secrets;
- audit and retry behavior is understood;
- the API is served over HTTPS; and
- the app has a separate production/staging configuration.

The namespace is `/wp-json/bridgistic/v1`. The optional Insightistic status
route is `GET /insightistic` and uses the same authenticated health capability.
Do not expose authenticated routes to anonymous users or use wildcard
credentialed CORS.
