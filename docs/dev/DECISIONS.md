# Decisions Log
## NACSA Toolkit Baseline System

**Companion to:** `PRD.md` v1.1, `TECH_SPEC.md` v1.1
**Date:** 24 September 2026
**Status:** Accepted by author (spec review, 24 Sep 2026)

These decisions resolve gaps and contradictions found while reviewing the PRD and Tech Spec against the actual project and the official toolkit (`docs/reference/NCSB-SelfAssessment-v1.4.xlsx`). **Where this log conflicts with the PRD or Tech Spec, this log wins.**

---

## 1. Platform

| ID | Decision | Supersedes |
|---|---|---|
| D-01 | **Laravel 13.x on PHP 8.3** (the installed project). Nothing in the spec depends on Laravel 11. | TS §0, PRD §5 |
| D-02 | **No Laravel Breeze.** Breeze does not support Laravel 13. Authentication (login, OTP, password change/expiry, password reset) is hand-written in `AuthController` and related classes. Password reset uses Laravel's built-in password broker. | TS §2.2, PRD §5 |
| D-03 | **MariaDB 11.4** (WampServer) on port **3307**, Laravel `mariadb` driver, `utf8mb4` / `utf8mb4_unicode_ci`. Tests run against a separate MariaDB database `nacsa_toolkit_test`, not SQLite. The connection forces **InnoDB** (`DB_ENGINE`, default `InnoDB`) because WampServer's MariaDB `my.ini` defaults to MyISAM, which has no foreign keys or transactions and a 1000-byte key limit. *Revised 11 Oct 2026 — was MySQL 8 on 3306.* | PRD v1.1 §9.4, TS v1.1 §1.3 (confirmed) |
| D-04 | **Bootstrap 5.3 + Alpine.js 3 + Chart.js 4 bundled through Vite** (npm). Tailwind removed. Bundling means the demo works offline. | TS §13.1 (CDN) |
| D-05 | Development server is `php artisan serve` (project lives at `P:\LARAVEL\nacsa-toolkit-baseline`, not `C:\wamp64\www`). | TS §1.5 |
| D-06 | Laravel Boost installed as a dev dependency (per repository `CLAUDE.md`). | — |
| D-07 | `maatwebsite/excel` 3.1 and `barryvdh/laravel-dompdf` 3.x as specified — both confirmed compatible with Laravel 13. The parser reads fixed cell addresses through the PhpSpreadsheet reader bundled with `maatwebsite/excel`. | — |
| D-08 | Tests use **PHPUnit** (installed), not Pest. | PRD §5 |

## 2. Toolkit structure and parsing

| ID | Decision | Supersedes |
|---|---|---|
| D-10 | **Template identification.** The workbook has **no version marker cell** — "v1.4" appears only in the filename. A file is accepted as NCSB v1.4 when: (a) all six sheet names match exactly; (b) the questionnaire sheet has 125 question rows at `G3:G127`; (c) each question text matches the seeded text (whitespace-normalised); (d) each element name appears at its expected start row in column E. Errors name the first failing item, e.g. *"Sheet 'CS Baseline Questionnaires' missing"*, *"Question 37 (row 39) does not match the NCSB v1.4 template"*. | PRD §7.2 step 2, FR-11, TS §7.2 `config/ncsb.php` |
| D-11 | **Element codes** are our convention: domain prefix + sequence within the domain, in questionnaire order (`GV-01`…`GV-07`, `ID-01`, `PR-01`…`PR-19`, …). The workbook has no codes. | PRD §6.2, §7.2 step 2d |
| D-12 | **Accepted answers:** `Yes` / `No`, case-insensitive, trimmed — matching the toolkit's `COUNTIF(…,"Yes")`. Any other non-blank value is treated as **No** and raises a parse warning. | TS §7.2 `normalise()` |
| D-13 | **Yes-count = every "Yes" in the element's range**, as the toolkit's scoring does. If a "Yes" appears after a "No" in the same element (the toolkit instructions say to stop at the first "No"), a warning is raised but the answer is still counted. | PRD §7.2 step 3 |
| D-14 | **Defaulted cells** = blank cells that come *before* the first "No" in an element. Blanks after a "No" are the toolkit's intended skips and are not counted. A fully blank element is **Unanswered** (FR-13) and its blanks are not counted as defaulted. | FR-12 |
| D-15 | **Thresholds come from the Scoring Dashboard formulas** (column E), not the Model sheet text. Two elements disagree between the two — see Appendix A §A.4. We follow the formulas so our scores match NACSA's own tool. | PRD R2 |
| D-16 | Question texts are stored on `ncsb_elements.questions` (JSON array) — used for template validation (D-10) and heatmap drill-down. | PRD §9.1 |

## 3. Scoring and simulation

| ID | Decision | Supersedes |
|---|---|---|
| D-20 | **Overall score = elements with ≥ 1 "Yes" ÷ 33**, exactly the toolkit's `COUNTIF(D3:D35,">0")/33`. Unanswered elements count as not implemented; the divisor stays 33. | PRD §7.3 (confirmed), FR-13 wording |
| D-21 | **Simulator "implemented" rule:** for an element whose slider is untouched, keep the baseline status (≥ 1 Yes). For an element the user has moved, implemented = level ≥ 1. With no changes, the projection equals the baseline exactly. | PRD §7.7 |
| D-22 | **Unanswered elements in the simulator** are left out of domain averages (as in scoring) until the user moves their slider. | TS §7.5 (`?? 0`) |
| D-23 | **Live projection runs in the browser** (same algorithm in JS). `POST /simulator/project` stays as the authoritative server calculation, used when saving and in tests. Meets FR-62's 300 ms budget. | TS §13.2 |

## 4. Assessments

| ID | Decision | Supersedes |
|---|---|---|
| D-30 | **Uploads are processed synchronously** in the request. No "Validating" state. | Fig 5.2 pills, PRD §5.2 `ParseAssessmentJob` |
| D-31 | **Rejected uploads are not persisted as assessments.** Each rejection writes an `audit_logs` row (`assessment.rejected`, with filename and reason). The upload screen lists recent rejections from the audit log. The `validation_status` column is dropped. | PRD §9.1, FR-17 |
| D-32 | **Assessment dates must be strictly later than the latest existing cycle.** Back-dated uploads are rejected, so trend and alert history never goes stale. | PRD Assumption 3 |
| D-33 | **Duplicate files (same SHA-256) are blocked** with *"This exact file was already uploaded as {cycle} on {date}."* | PRD §13.1, TS §14 |
| D-34 | **Degradation alert emails go to all active Compliance Officers.** | FR-19 |
| D-35 | **A Compliance Officer can permanently delete the latest cycle only** ("undo last upload"), giving a required reason. The assessment, its elements, trend, alerts and stored file are removed; an `audit_logs` row (`assessment.deleted`) keeps the cycle label, date, file name, score, band and reason. Earlier cycles cannot be deleted, so no other cycle's trend is ever recomputed; once the latest is deleted, the previous cycle becomes deletable. The same file and label can be uploaded again. *Added 11 Oct 2026.* | PRD §4.2 (new capability) |
| D-36 | **Degradation is detected per domain and per element.** Each element stores `level_delta` (current level − previous level; null when either cycle left it Unanswered). Every element whose level fell raises its own `DegradationAlert` (`ncsb_element_id` set, `score_drop` = levels lost), alongside the existing one-per-domain alerts, and is listed in the alert email. This catches an element decline hidden by a flat domain average. The trend dashboard shows all 33 elements' levels in every cycle, and officers can acknowledge all active alerts at once (one audit row per alert). Matches proposal Table 3.2 ("flags control elements whose maturity score has declined") and the Table 5.2 metrics. *Added 11 Oct 2026.* | PRD §7.4, FR-19, FR-21 |
| D-37 | **Executive Reviewers do not see assessment history or cycle results** (`/assessments`, `/assessments/{id}`): proposal Table 3.4 limits them to "dashboards and PDF reports only". They keep their dashboard and the trend dashboard (read-only, no links to cycle results). *Added 11 Oct 2026.* | PRD §4.2 row "View assessment history", §10 |

## 5. Deadlines

| ID | Decision | Supersedes |
|---|---|---|
| D-40 | **"Met":** a validated upload marks every open deadline (not overdue, due date ≥ upload date) as met, linking `met_by_assessment_id`. The officer can also mark any deadline met manually. Met or acknowledged deadlines never escalate. | FR-42 |
| D-41 | **Status rules:** *Met* if met; else *Overdue* if due date has passed; else *Due Soon* if days remaining ≤ the smallest lead day (default 7); else *Upcoming*. | FR-44 |
| D-42 | **Escalation notifications are stored with `lead_day = 0`** (not NULL), so the unique index guarantees exactly-once escalation on MySQL. | TS §5.8 |

## 6. Reporting and settings

| ID | Decision | Supersedes |
|---|---|---|
| D-50 | **Cyber Security Act 2024 compliance status** (FR-33) — author design rule, stated as indicative, not a legal determination: **Non-compliant** if any domain is Critical or the overall band is Initial; **Compliant** if the overall band is Advanced, every domain is Moderate or Acceptable, and no element is unanswered; otherwise **Partially compliant**. The report footnote says so. | FR-33 |
| D-51 | **Settings** live in a key–value `settings` table editable by the Admin: organisation name (used on the PDF cover) and report footer text. NCSB reference data is seed-only; mail server settings stay in `.env`. | PRD §4.2 "System configuration" |
| D-52 | **Encryption at rest covers the raw responses, report payloads and simulation responses** (`encrypted:array` casts). Scores, bands and trend deltas stay plain numeric columns because the trend charts, heatmap and sorting query them. Thesis Table 3.4 is to be reworded to say this. *Added 11 Oct 2026.* | SEC-05, proposal Table 3.4 wording |
| D-53 | **User management (FR-07)** at `/admin/users`: the Admin creates accounts with name, email and role; the account gets an unusable random password and the user is emailed a password-broker link to set their own. The Admin can edit name, email and role, deactivate or reactivate, and resend the password link. Accounts are never deleted. An Admin cannot deactivate themselves or change their own role, so one active Admin always remains. Deactivated users are signed out on their next request. All changes are audited (`user.*`, technical category). *Added 11 Oct 2026, built as a Sprint 1 follow-up.* | PRD §14 (FR-07 had no sprint) |
| D-54 | **The Admin's audit log view shows the technical category only** (sign-in, OTP, password, access-denied, account and settings events), filterable by action, user and date. The compliance trail stays with the Compliance Officer (`/audit-trail`, Sprint 3). Application errors are not written to `audit_logs`; the Admin dashboard shows queued and failed job counts instead. *Added 11 Oct 2026.* | PRD §4.2 "View technical audit logs (logins, errors)" |
| D-55 | **Executive reports are generated synchronously** in the request, the same way uploads are (D-30). A report takes about 1 s in DomPDF, inside FR-35's 5 s limit. Each generation records its time as `duration_ms` in the `report.generate` audit row, which is the evidence for FR-35 / NFR-01. No `GenerateExecutiveReportJob`, no `reports` queue, no progress state. *Added 16 Oct 2026.* | FR-35 (queued branch), TS §8 `GenerateExecutiveReportJob` |
| D-56 | **Every generation is a new report version; earlier versions are kept unchanged.** The PDF is rendered once from the encrypted payload, stored on the private disk and always served as stored. It is never re-rendered, so a later settings change does not alter an old PDF. Generating for a cycle that already has a report requires ticking a confirmation box. Downloads are audited (`report.downloaded`). Deleting a cycle (D-35) also deletes its reports and their PDF files. Executives see only cycles that have a report, with download links and no links to cycle results (D-37). *Added 16 Oct 2026.* | FR-34 |
| D-57 | **Narratives use plain-language area names** instead of NCSB domain names (FR-31): GOVERN → *Cybersecurity governance*, IDENTIFY → *Asset and risk identification*, PROTECT → *Protective security*, DETECT → *Threat detection*, RESPOND → *Incident response*, RECOVER → *Recovery and continuity*. The NCSB domain name appears only in small text in the report's risk table. *Added 16 Oct 2026.* | PRD §7.6 `{DOMAIN}` placeholder |
| D-58 | **A domain with no score (every element Unanswered) is "Not assessed"**, in grey and never Critical (as FR-52 does for the heatmap). Its narrative says exposure cannot be judged until the area is assessed. Unanswered elements are left out of the remediation ranking (TS §7.6) and listed in their own section of the report. *Added 16 Oct 2026.* | PRD §7.6 (no row for a null score) |
