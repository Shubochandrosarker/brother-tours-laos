# Security notes

- Do not commit credentials, API keys, signing secrets, passwords, or database
  exports.
- Use HTTPS everywhere and require strong administrator authentication with 2FA
  through an approved security control.
- Grant the lowest WordPress capability required for each operator.
- Keep Operations API endpoints behind authenticated sessions and an explicit
  trusted-origin allowlist. Never use credentialed wildcard CORS.
- Validate nonces, capabilities, request data, webhook signatures, and rate
  limits at every write boundary.
- Keep payment gateways and webhooks disabled until provider-specific tests are
  complete.
- Review WordPress, PHP, theme, plugin, and Hostinger update policies before
  launch.
- Keep staging inaccessible to search engines and preferably protected by host
  authentication.
- Log important events without storing passwords, tokens, full payment data, or
  unnecessary personal information.
