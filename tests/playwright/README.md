# iLungu Club Playwright Tests

The maintained iLungu Club browser suite is
`tests/playwright/smoke/ilungu-branding-smoke.spec.ts`.

Run the current smoke suite with:

```sh
npm run test:ilungu-smoke
```

`tests/playwright/playwright.config.ts` sets `testDir` to `tests/playwright` and
matches only `smoke/ilungu-branding-smoke.spec.ts`. This keeps the maintained
existing-install, authenticated-admin, portal/workspace, and fresh-install
fixture checks separate from historical diagnostics.

Use `.env.example` as the placeholder reference. Put local site credentials only
in `.env.local`; it is ignored. Generated `test-results`, Playwright reports, and
blob reports are also ignored. The full `tests/playwright` directory is excluded
from public release packages.

## Historical diagnostics

The original May 2026 FlexiClub dashboard and payment investigation scripts were
removed from the working tree because they contained hard-coded local login
details, targeted a retired local site, and provided no unique maintained
coverage. They remain available in Git history for forensic reference only; do
not restore or run them as regression tests.