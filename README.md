# PermitFlow — Business Permit Portal

A responsive, dependency-free prototype for applying for, renewing, tracking, and reviewing business permits. It supports two demonstration roles: Business Applicant and LGU Staff.

## Features

- Applicant dashboard with application summaries
- Three-step new permit application with field and file validation
- Four standard and ten conditional permit-document uploads based on the provided BPLO/BFP checklist
- Existing permit lookup and renewal flow
- Application tracking timeline
- LGU overview, searchable review queue, approval, and revision workflow
- Mobile navigation and accessible form states
- Browser persistence through `localStorage`
- Defensive handling of corrupt browser data and escaped user-provided content

## Run locally

No dependencies or build process are required.

```bash
python -m http.server 8080
```

Then open `http://localhost:8080`.

## Demo records

- Tracking reference: `BPL-2026-00124`
- Permit number for renewal: `BP-2026-01482`

## Important production note

This repository is a front-end prototype. A production LGU deployment must replace browser storage with an authenticated server, relational database, malware-scanned secure document storage, audit logs, backups, access controls, official payment integration, and privacy/security review. AI-assisted validation should remain subject to human review.
