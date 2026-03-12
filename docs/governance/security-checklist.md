# V1 Security Checklist (OWASP Top 10)

**Date:** 2026-03-12
**Reviewer:** Automated + manual code audit

| # | Category | Status | Evidence |
|---|----------|--------|----------|
| A01 | Broken Access Control | PASS | Access policies on all entities, session-based auth, role checks |
| A02 | Cryptographic Failures | PASS | bcrypt password hashing, crypto-random tokens (bin2hex/random_bytes) |
| A03 | Injection (SQLi/XSS) | PASS | PDO prepared statements, Twig autoescape enabled globally |
| A04 | Insecure Design | PASS | Consent metadata, copyright filtering, role-based dashboards |
| A05 | Security Misconfiguration | PASS | Security headers, session cookie flags (HttpOnly, Secure, SameSite=Lax) |
| A06 | Vulnerable Components | VERIFY | `composer audit` — run at deploy time |
| A07 | Auth Failures | PASS | Rate limiting on login/forgot-password, session regeneration, CSRF on all forms |
| A08 | Data Integrity | PASS | No deserialization of user input, CSRF tokens on state-changing requests |
| A09 | Logging Failures | NOTE | Minimal logging in V1 — acceptable for initial release |
| A10 | SSRF | PASS | No user-controlled URL fetching, NorthCloud API URL is config-only |
