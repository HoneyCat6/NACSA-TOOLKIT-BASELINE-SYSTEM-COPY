# Decisions Log
## NACSA Toolkit Baseline System

**Companion to:** `PRD.md` v1.0, `TECH_SPEC.md` v1.0
**Date:** 24 September 2026
**Status:** Accepted by author (spec review, 24 Sep 2026)

These decisions resolve gaps and contradictions found while reviewing the PRD and Tech Spec against the actual project and the official toolkit (`docs/reference/NCSB-SelfAssessment-v1.4.xlsx`). **Where this log conflicts with the PRD or Tech Spec, this log wins.**

---

## 1. Platform

| ID | Decision | Supersedes |
|---|---|---|
| D-01 | **Laravel 13.x on PHP 8.3** (the installed project). Nothing in the spec depends on Laravel 11. | TS §0, PRD §5 |
| D-02 | **No Laravel Breeze.** Breeze does not support Laravel 13. Authentication (login, OTP, password change/expiry, password reset) is hand-written in `AuthController` and related classes. Password reset uses Laravel's built-in password broker. | TS §2.2, PRD §5 |
| D-03 | **MySQL 8** on port 3306. Tests run against a separate MySQL database `nacsa_toolkit_test`, not SQLite. | TS §1.3 (confirmed) |
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
