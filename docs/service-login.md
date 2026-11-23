# Existing service sign-in

Coding Projects issues short-lived login tokens through `/insider/jwt` for the
existing service clients. It does not add login to otherwise public applications.

| Existing client | Registered HTTPS origin |
| --- | --- |
| GeekStorage | `https://storage.geekclass.ru` |
| GeekPaste | `https://paste.geekclass.ru` |
| GeekExam | `https://exam.geekclass.ru` |
| Arena | `https://arena.geekclass.ru` |
| GeekCodeBattle | `https://battle.geekclass.ru` |
| ClassScreenShare | `https://inga.codingprojects.ru` |
| GeekGuard | `https://guard.geekclass.ru` |
| GeekKaggle | `https://kaggle.codingprojects.ru` |

`SSO_RETURN_ORIGINS` can explicitly replace that list. Entries are complete origins,
including a nonstandard port when required; subdomains are not implicitly trusted.
Credentials and fragments in a return URL are rejected. A callback constructed
with HTTP behind a TLS proxy is upgraded only when its HTTPS origin is registered.
Tokens are never sent to an unregistered host by this compatibility correction.

The identity fields remain `id`, `name`, `role`, `avatar_url` and `iat`; tokens expire
after 120 seconds. They include `iss=codingprojects`, `purpose=service-login` and
the canonical `service_origin`. Legacy consumers still receive no `aud`, because
their existing PyJWT configuration does not accept an audience claim. Responses
are private, not cached, and use `Referrer-Policy: no-referrer`.

GeekExecutor's administrator console uses a separate existing protocol:
`/auth/login` → `/insider/settings/code-executor/login` → `/auth/callback`.
It uses its independent execution-client key, a 60-second token with the
`geekexecutor` audience, an admin role and a one-use state. Do not replace that
key with the legacy shared login key. Students keep the public service home page.

Verification covers untrusted hosts, nonstandard ports, credentials, fragments,
HTTP callbacks, canonical HTTPS/default ports and explicit localhost development
origins. Production checks confirmed the six existing consumers accept newly
issued tokens without modifying their user databases. The administrator flow of
GeekExecutor was also exercised through dashboard access and logout.

Opening the GeekPaste editor, GeekExam join page, Guard dashboard or Wi-Fi portal,
or GeekKaggle catalog starts sign-in automatically when the service has no session.
With an active Coding Projects session, the user returns directly to the requested
page. GeekPaste public snippets remain accessible without a session.

When a service opens the Coding Projects password form, that form keeps its own
encrypted return address. Opening a course or starting a second service login in
another tab preserves the first tab's destination. After sign-in, Coding Projects
checks the registered service origin at `/insider/jwt` before issuing the token.

ClassScreenShare starts sign-in when a participant opens an exam and when a teacher
opens the admin area. The public landing page asks for the exam code. GeekExecutor
starts its administrator sign-in on direct access to `/system/status`.

GeekGuard additionally receives the account catalogue (`id`, `name`, `role`) through
`php artisan guard:sync-identities`, scheduled every five minutes. Set `GUARD_URL`
and the independent `GUARD_IDENTITY_SECRET` in the server environment; the latter
matches Guard's `GUARD_CP_SERVICE_SECRET` and is not the SSO or execution key.
When not configured the command makes no requests. No email addresses, passwords,
account tokens or profile fields are exported. The `novice` role is mapped to
student permissions. Users absent from subsequent syncs lose cached identity
validity in Guard within its 15-minute TTL. Role changes reach Guard on the next
scheduled sync; run the command explicitly when an immediate refresh is needed.
If `SSO_RETURN_ORIGINS` is overridden, append Guard to the explicit existing list.
