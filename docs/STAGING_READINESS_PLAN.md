# ModInspect Staging Readiness Plan (NO DEPLOY)

Status: PROPOSAL / HUMAN_GATE. This document describes a future staging setup; it does not create hosting resources, upload files, apply a migration, or authorize deployment.

## 1. Verified baseline (13 October 2026)

- GitHub repository: thuliam/modinspect
- Current production-intended URL: https://modinspect.thuliamdev.com
- Existing production-intended custom document root: /home/thuliam/domains/thuliamdev.com/modinspect/public
- Existing production-intended account directory: /home/thuliam/domains/thuliamdev.com/modinspect
- Existing dedicated FTPS account is restricted to the modinspect account directory.
- HostAtom PHP 8.3 selected for the above subdomain; HTTPS and FTPS read-only checks previously passed.
- Repository packages only app/, config/ and public/ (tracked safe allowlist) into an offline ZIP; no server-write workflow exists.
- Cloud DEV MariaDB is shared by OFFICE/STUDIO. NEVER reuse it for destructive staging validation.

All the above are the *existing* ModInspect host. Do not reuse it as staging and do not repoint its document root.

## 2. Proposed isolated staging topology

A second subdomain is required, as a sibling of the original project, **not a subfolder of production**.

| Item | Production-intended (leave unchanged) | Proposed staging (not created) |
| --- | --- | --- |
| URL | modinspect.thuliamdev.com | modinspect-uat.thuliamdev.com |
| Root | /home/thuliam/domains/thuliamdev.com/modinspect | /home/thuliam/domains/thuliamdev.com/modinspect-uat |
| Document root | .../modinspect/public | .../modinspect-uat/public |
| PHP | 8.3 | 8.3 |
| FTP scope | Dedicated modinspect account root | New dedicated modinspect-uat account root |
| DB | Not provisioned / production DB undecided | Must provision separate staging DB + user |
| Runtime files | Original storage (preserve) | Independent staging storage |
| Public access | Existing placeholder/site | Password-protected before uploading any app |

Target structures:

    /home/thuliam/domains/thuliamdev.com/
    ├── modinspect/           # EXISTING — DO NOT TOUCH
    │   └── public/
    └── modinspect-uat/       # PROPOSED — NEW
        ├── app/
        ├── config/
        ├── storage/          # provision separately, not in GitHub ZIP
        ├── .env              # created on server only, secret
        └── public/           # future staging document root
            ├── index.php
            ├── .htaccess
            └── assets/

The staging ZIP may be built from an approved commit, but no FTP changes occur in this phase.

## 3. HostAtom / DirectAdmin preparation sequence (requires owner at UI)

**HUMAN_GATE H1 — create isolated staging subdomain.**
- DirectAdmin > Subdomain Management > Add New Subdomain.
- Name: modinspect-uat (under thuliamdev.com).
- Document Root: Custom. Proposed path: /domains/thuliamdev.com/modinspect-uat/public
- Confirm the displayed absolute path and that it does not intersect production.
- Do not choose Legacy/subfolder mode.
- Confirm PHP selector can assign **8.3 for staging only** without changing the main domain.
- After creation, confirm new directory exists, DNS resolves and HTTPS certificate validates; do not upload the app.

**HUMAN_GATE H2 — access security and staging-only FTPS.**
- Before deploying app code, protect staging web access using DirectAdmin Password Protected Directories or another supported verified restriction. Do not rely on robots.txt or unadvertised URLs for privacy.
- Create a new FTPS account under thuliamdev.com with Custom root restricted to:
  /domains/thuliamdev.com/modinspect-uat/
- Suggested local username: modinspect_uat (actual full login assigned by DirectAdmin must be verified).
- Require Explicit FTP over TLS, verify CA/hostname and encrypted data channel. Do not use plain FTP.
- Do not reuse MODINSPECT_FTPS_USERNAME / MODINSPECT_FTPS_PASSWORD (original site's credentials).
- Never paste FTP/HTTP credentials into chat, issue bodies, GitHub files, or screenshots.

**HUMAN_GATE H3 — isolated staging database and safe configuration.**
- Provision a separate database with minimum-privilege database user through DirectAdmin, only after owner approval.
- Do not reuse thuliam_modinspect, current shared Cloud DEV connections, or any future production DB.
- Server-local .env should set APP_ENV=staging, APP_URL=https://modinspect-uat.thuliamdev.com and its own DB credentials.
- Default MODINSPECT_LIVE_COLLECTION_ENABLED=false; MODINSPECT_PAID_PROVIDER_CALLS_ENABLED=false; GEMINI_PROVIDER_ENABLED=false.
- Confirm PHP 8.3 modules (at least pdo_mysql), server write permissions, and .htaccess rewrite handling.
- No schema migration, seeding, DB mutation tests or external providers unless separately reviewed and approved.

## 4. Deployment readiness gates (this PR does not deploy)

Gate A — CI: Package ZIP integrity + per-file SHA-256 manifest verified for an approved commit.
Gate B — Directory isolation: staging FTPS "/" points to modinspect-uat only; production tree is inaccessible to staging account.
Gate C — TLS: encrypted FTPS and HTTPS validated; staging site password protection tested.
Gate D — Runtime: PHP 8.3 + PDO MySQL, rewrite, storage import directory, and permissions confirmed.
Gate E — DB: isolated staging DB exists; schema bootstrap/migrations reviewed in separate human-gated request.
Gate F — Backup: snapshot the staging placeholder and current staging files before any replacement; no deletions without approval.
Gate G — Explicit approval for a staging-only deploy workflow; pinned paths, no destructive synchronization, no auto-trigger and no remote deletions.

Only after A–G pass can a **separate** manual staging deployment workflow be proposed for owner review. No production deployment is implied.

## 5. Proposed staging smoke test after a separately approved deploy

1. GET / with staging HTTP authentication; verify HTTPS, routing and styling.
2. Confirm direct URL requests for /.env, /config/, /app/, /database/ and /storage/ are rejected by document-root isolation.
3. Check /price, /methodology, /deal-checker and /login routes without destructive form submissions.
4. Validate DB connections using a read-only account / explicit read-only query if approved.
5. Confirm PHP errors and logs are not leaked to public web pages.
6. Validate disabled external collection settings and paid API gates.
7. Record Git SHA, manifest, checksums, application health and reviewer acceptance.

## 6. Non-executable staging rollback outline

- Pause further changes and identify the prior staging release SHA.
- Restore backed-up staging code, preserving .env and runtime storage.
- Restore or isolate staging DB only under a separately approved DB rollback plan.
- Recheck subdomain PHP, HTTPS, staging authentication and required routes.
- Never touch /domains/thuliamdev.com/modinspect or thuliamdev.com/public_html.

## 7. Next owner action

At this stage, review/approve the **staging infrastructure proposal only**. Once approved, the owner can open DirectAdmin and create the new subdomain with the above Custom root. Send a screenshot of the proposed path before clicking Add Subdomain if confirmation is needed.

Do not start deployment, modify existing production files, reuse production FTP secrets or change databases.
