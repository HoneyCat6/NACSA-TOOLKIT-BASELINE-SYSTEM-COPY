# Product Requirements Document (PRD)
## NACSA Toolkit Baseline System

**Version:** 1.1 — *database changed from MySQL 8 to MariaDB (§1, §5, §9.1, §9.4, §14, §15 R9)*
**Date:** 11 October 2026
**Author:** Muhammad Zarif Irfan bin Zalali (1230551)
**Supervisor:** PM Dr. Ir. Mohd Fared Abdul Khir
**Programme:** BCompSc (Hons) Information Security and Assurance, USIM
**Source:** Thesis Proposal — *NACSA Toolkit Baseline System* (Chapters 1–5)

---

## 1. Document Purpose

This PRD is the single build specification for the NACSA Toolkit Baseline System. It translates the thesis proposal's Chapter 3 requirements and Chapter 4 design into implementable detail: data model, algorithms, routes, acceptance criteria, and a sprint backlog. Every functional requirement traces back to a Research Objective (RO) so the system remains defensible during evaluation and viva.

**Build decisions locked for this PRD** (confirmed with author):

| Decision | Choice | Implication |
|---|---|---|
| Tenancy | **Single-organization deployment** | ERD stays as Figure 4.2. No `organizations` table. NFR "multi-organization support" is re-scoped to *concurrent multi-user*, and recorded as a limitation in §14. |
| NCSB v1.4 Excel template | **Available to author** | Parser contract is specified here as a placeholder; exact sheet/cell map is a Sprint 1 deliverable (§7.2, Appendix A). |
| Front-end | **Blade + Bootstrap 5 + Alpine.js + Chart.js** | Faithful to §3.6 of the proposal. Alpine drives heatmap interactions and simulator sliders; Chart.js draws trend lines. |
| Database | **MariaDB 10.6+ / 11.x** | Changed from MySQL 8. Five schema-level differences apply — see §9.4. Thesis Table 4.4 needs the same correction (§15, R9). |
| PRD depth | **Full build spec** | Schema, routes, pseudocode, acceptance criteria included. |

---

## 2. Problem Statement and Objectives

### 2.1 Gaps in the 2025 NCSBAS predecessor

| # | Gap | Consequence | Addressed by |
|---|---|---|---|
| P1 | **Point-in-time blindness** — maturity captured only during manual assessment cycles | Control degradation between cycles goes undetected | M1 Assessment & Trend Engine |
| P2 | **Governance communication failure** — technical domain scores are unreadable to boards | CISO must manually reinterpret; strategic delay | M2 Executive Summary Generator |
| P3 | **Reactive compliance management** — no deadline scheduling or reminders | Missed Cyber Security Act 2024 obligations | M3 Alert Service |
| P4 | **No diagnostic decision support** — no element-level exposure view, no what-if modelling | Budget and remediation proposals are undefensible | M4 Heatmap, M5 Scenario Simulator |

### 2.2 Research objectives → system mapping

| RO | Objective | Delivered by |
|---|---|---|
| **RO1** | Identify limitations of the 2025 NCSBAS and operational gaps for NCSB v1.4 | Chapter 2 gap analysis + MAHB survey (already complete; input to this PRD) |
| **RO2** | Develop the NACSA Toolkit Baseline System for continuous monitoring, executive reporting, diagnostic governance analytics | **This document** — modules M1–M5, Sprints 1–4 |
| **RO3** | Evaluate capability against the 2025 NCSBAS across four governance dimensions | §13 Test Plan, Sprint 5 |

### 2.3 Product goal (one sentence)

> Replace the spreadsheet-based NCSB v1.4 self-assessment workflow with a web system that scores automatically, tracks maturity across cycles, detects degradation, translates results into board-readable risk language, enforces compliance deadlines proactively, and lets executives model remediation before spending money.

---

## 3. Scope

### 3.1 In scope

| ID | Capability |
|---|---|
| S1 | Upload and validation of the official NCSB v1.4 Excel toolkit (.xlsx) |
| S2 | Automated extraction of 125 Yes/No responses across 33 elements, 15 categories, 6 domains |
| S3 | Element-level, domain-level, and overall maturity computation |
| S4 | Historical retention of all assessment cycles and cross-cycle trend computation |
| S5 | Degradation detection with in-app flags and email alerts |
| S6 | Business-risk translation and board-ready PDF executive summary generation |
| S7 | Compliance deadline scheduling with multi-interval email reminders and escalation |
| S8 | Control effectiveness heatmap over all 33 elements, with historical cycle replay |
| S9 | Cybersecurity compliance scenario simulator with saved sessions |
| S10 | Role-differentiated dashboards (Admin, Compliance Officer, Executive Reviewer) |
| S11 | RBAC, email OTP MFA, session expiry, encryption at rest, audit logging |

### 3.2 Out of scope (explicitly excluded, per §1.6 of the proposal)

| ID | Exclusion | Rationale |
|---|---|---|
| X1 | Real-time SIEM integration | Monitoring is governance-level, not operational-telemetry level |
| X2 | Live vulnerability scanning | Outside NCSB self-assessment boundary |
| X3 | Automated incident response | Outside NCSB self-assessment boundary |
| X4 | Predictive AI / ML maturity forecasting | Reserved for future work |
| X5 | Cross-framework mapping (NIST CSF, ISO/IEC 27001) | Reserved for future work |
| X6 | Manual in-browser questionnaire entry (replacing Excel) | Excel is the mandated NACSA input artefact |
| X7 | Multi-tenant organisation isolation | Single-organisation deployment (see §1) |

---

## 4. Users, Roles, and Permissions

### 4.1 Personas

| Role | Real-world title (MAHB context) | Primary need |
|---|---|---|
| **Admin** | IT / System Administrator | Keep the system running; manage accounts; inspect technical logs |
| **Compliance Officer** | Cybersecurity Manager / IT Risk Officer | Run assessment cycles, own the regulatory calendar, watch trends |
| **Executive Reviewer** | CISO / senior leadership | Understand exposure in business terms; make budget decisions |

### 4.2 RBAC permission matrix

| Capability | Admin | Compliance Officer | Executive Reviewer |
|---|:--:|:--:|:--:|
| Manage users (CRUD, role assignment, reset) | ✅ | ❌ | ❌ |
| System configuration (SMTP, thresholds, reference data) | ✅ | ❌ | ❌ |
| View technical audit logs (logins, errors) | ✅ | ❌ | ❌ |
| Upload NCSB v1.4 assessment file | ❌ | ✅ | ❌ |
| View assessment history & validation status | ❌ | ✅ | ✅ (read-only summary) |
| Configure compliance deadlines & lead times | ❌ | ✅ | ❌ |
| View notification history | ❌ | ✅ | ❌ |
| View compliance audit trail | ❌ | ✅ | ❌ |
| Acknowledge degradation alerts | ❌ | ✅ | ❌ |
| View trend dashboard | ❌ | ✅ | ✅ |
| Generate executive summary PDF | ❌ | ✅ | ❌ |
| Download executive summary PDF | ❌ | ✅ | ✅ |
| View control effectiveness heatmap | ❌ | ✅ | ✅ |
| Use scenario simulator | ❌ | ✅ | ✅ |
| Save / load simulation sessions | ❌ | ✅ | ✅ |

> **Implementation note:** enforce with Laravel Policies + a `role` middleware. Every controller action must be covered by a policy test (§13.1).

---

## 5. Technical Stack and Conventions

| Layer | Technology | Notes |
|---|---|---|
| Framework | Laravel 11 (PHP 8.2+) | MVC, migrations, queues, scheduler |
| Database | **MariaDB 10.6+ / 11.x** | InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`. See §9.4 for MariaDB-specific schema constraints. |
| Templating | Blade | Layout inheritance, components |
| CSS | Bootstrap 5.3 | Grid, cards, tables, modals |
| Interactivity | Alpine.js 3.x | Heatmap cell drill-down, simulator sliders, live recalculation |
| Charts | Chart.js 4.x | Multi-series line chart for domain trends |
| Excel parsing | `maatwebsite/excel` (PhpSpreadsheet) | Read-only; `.xlsx` only |
| PDF generation | `barryvdh/laravel-dompdf` | Blade-rendered report template |
| Queue driver | `database` | Email dispatch, PDF generation, heavy parsing |
| Scheduler | Laravel Scheduler (`schedule:run` via cron) | Daily deadline sweep |
| Auth | Laravel Breeze (Blade stack), extended with email OTP | Do not use social login |
| Encryption | Laravel `Crypt` facade (AES-256-CBC) | Applied via Eloquent `encrypted` casts |
| Testing | PHPUnit / Pest | Feature + unit tests |

### 5.1 Coding conventions

| Rule | Detail |
|---|---|
| Business logic location | Service classes under `app/Services/`, **never** in controllers |
| Controller size | Thin; validate → call service → return view/redirect |
| Naming | Services: `ScoringService`, `TrendService`, `HeatmapService`, `SimulationService`, `ReportService`, `NotificationService` |
| Form requests | Every write action uses a dedicated `FormRequest` |
| Enums | PHP 8.1 backed enums for `Role`, `MaturityLevel`, `Domain`, `Severity`, `DeadlineStatus` |
| Migrations | One concern per migration; never edit a shipped migration |
| No raw SQL | Use Eloquent / Query Builder |
| Audit | Every state-changing action writes an `audit_logs` row |

### 5.2 Suggested directory layout

```
app/
  Enums/            Domain.php, MaturityLevel.php, Role.php, Severity.php
  Models/           User.php, Assessment.php, AssessmentElement.php,
                    AssessmentTrend.php, DegradationAlert.php,
                    ComplianceDeadline.php, DeadlineNotification.php,
                    SimulationSession.php, NcsbElement.php, AuditLog.php
  Services/         ExcelParserService.php, ScoringService.php, TrendService.php,
                    HeatmapService.php, SimulationService.php, ReportService.php,
                    NotificationService.php, AuditService.php
  Http/
    Controllers/    AuthController, UserController, AssessmentController,
                    DashboardController, TrendController, ReportController,
                    DeadlineController, HeatmapController, SimulatorController
    Requests/       UploadAssessmentRequest.php, StoreDeadlineRequest.php, ...
    Middleware/     EnsureRole.php, EnsureMfaVerified.php
  Jobs/             ParseAssessmentJob.php, SendDeadlineReminderJob.php,
                    SendDegradationAlertJob.php, GenerateExecutiveReportJob.php
  Mail/             OtpMail.php, DeadlineReminderMail.php, EscalationMail.php,
                    DegradationAlertMail.php
database/
  migrations/
  seeders/          NcsbElementSeeder.php  ← the 33-element reference table
resources/
  views/
    layouts/app.blade.php
    auth/login, auth/otp
    dashboard/{admin,officer,executive}.blade.php
    assessments/{index,show,upload}.blade.php
    trends/index.blade.php
    heatmap/index.blade.php
    simulator/index.blade.php
    deadlines/{index,create}.blade.php
    reports/{index,pdf}.blade.php
```

---

## 6. NCSB v1.4 Reference Data Model

The framework structure is **fixed reference data**, seeded once and never user-editable.

| Level | Count | Notes |
|---|---|---|
| Domains | 6 | GOVERN, IDENTIFY, PROTECT, DETECT, RESPOND, RECOVER |
| Categories | 15 | Grouping layer between domain and element |
| Elements | 33 | The scored unit |
| Questions | 125 | Yes/No, distributed across the 33 elements |

### 6.1 Element distribution (Table 4.2 of the proposal)

| Domain | Elements | Domain risk weight (tie-break) |
|---|:--:|:--:|
| GOVERN | 7 | **High** |
| IDENTIFY | 2 | Normal |
| PROTECT | 19 | Normal |
| DETECT | 2 | Normal |
| RESPOND | 1 | **High** |
| RECOVER | 2 | Normal |
| **TOTAL** | **33** | |

### 6.2 `ncsb_elements` seed contract

Each of the 33 seeded rows must carry:

| Field | Type | Meaning |
|---|---|---|
| `code` | string | e.g. `GV-01`, `PR-14` — used as heatmap cell label |
| `domain` | enum | One of the six domains |
| `category` | string | One of the 15 categories |
| `name` | string | Element title from NCSB v1.4 |
| `question_count` | int | Number of Yes/No questions belonging to this element (sums to 125) |
| `threshold_l1` | int | Min Yes-count to reach Level 1 (Basic) |
| `threshold_l2` | int | Min Yes-count to reach Level 2 (Intermediate) |
| `threshold_l3` | int | Min Yes-count to reach Level 3 (Advanced) |
| `requirement_l1/l2/l3` | text | What the organisation must do to reach each level — used verbatim in remediation actions |

> **Sprint 1 blocker:** these values come from the official NCSB v1.4 toolkit. Populate `database/seeders/NcsbElementSeeder.php` from the real file before any scoring code is written. Record the exact sheet names and cell ranges in **Appendix A**.

---

## 7. Core Algorithms

### 7.1 Maturity levels and bands

| Level | Label | Heatmap gap from L3 | Severity | Colour |
|:--:|---|:--:|---|---|
| 3 | Advanced | 0 | Compliant | Green |
| 2 | Intermediate | 1 | Low Risk | Yellow |
| 1 | Basic | 2 | High Risk | Orange |
| 0 | Initial | 3 | Critical Risk | Red |

| Overall score range | Band |
|---|---|
| 0.00 – 0.24 | Initial |
| 0.25 – 0.49 | Basic |
| 0.50 – 0.74 | Intermediate |
| 0.75 – 1.00 | Advanced |

### 7.2 Excel parsing contract

```
ExcelParserService::parse(UploadedFile $file): ParsedAssessment

1. Reject if MIME/extension is not .xlsx, or size > 10 MB.
2. TEMPLATE VALIDATION (hard fail → reject with specific error):
   a. required sheet names present and unmodified
   b. version marker cell == "v1.4"
   c. question anchor cells resolve to the expected 125 positions
   d. element code column matches the seeded 33 codes, in order
   → on failure: do NOT persist an Assessment; return error to upload screen
3. RESPONSE EXTRACTION:
   for each of the 125 question cells:
       normalise value: "Yes"/"YES"/"Y"/TRUE → true
                        "No"/"NO"/"N"/FALSE/blank/unrecognised → false
   record which cells were blank/unrecognised (needed for step 4)
4. COMPLETENESS FLAGGING:
   for each of the 33 elements:
       if ALL its questions were blank → mark element status = UNANSWERED
   → unanswered elements are excluded from the overall band calculation
   → they are displayed individually on the upload results screen
5. Return: 125 booleans + per-element unanswered flags + parse warnings.
```

> **Design rule (from §4.2.2):** the system reads **only the raw Yes/No responses** and recomputes everything itself. Score cells inside the Excel file are ignored — users break those formulas.

### 7.3 Scoring

```
ScoringService::score(ParsedAssessment $p): ScoredAssessment

# --- element level ---
foreach element E in the 33 seeded elements:
    yes = count of true responses among E's questions
    if E.status == UNANSWERED:
        E.maturity_level = null
    else if yes >= E.threshold_l3: E.maturity_level = 3
    else if yes >= E.threshold_l2: E.maturity_level = 2
    else if yes >= E.threshold_l1: E.maturity_level = 1
    else:                          E.maturity_level = 0

# --- domain level (system enhancement, not part of NCSB v1.4) ---
foreach domain D in [GOVERN, IDENTIFY, PROTECT, DETECT, RESPOND, RECOVER]:
    scored = elements in D where maturity_level is not null
    D.score = scored.isEmpty() ? null : round(mean(scored.maturity_level), 2)   # 0.00–3.00

# --- overall ---
answered      = elements where status != UNANSWERED
implemented   = answered where yes_count >= 1
overall_score = count(implemented) / 33                                         # 0.00–1.00
overall_band  = band_of(overall_score)                                          # §7.1 table
```

> **Note two distinct measures** — keep them visually distinct in the UI:
> - `overall_score` (0–1) = *share of the 33 elements with at least one control implemented* → drives the band.
> - `domain score` (0–3) = *average element maturity within a domain* → drives trend lines and the simulator.

### 7.4 Trend and degradation detection

```
TrendService::computeTrend(Assessment $current): ?AssessmentTrend

prev = most recent Assessment before $current (by assessment_date); if none → return null

foreach domain D:
    delta_D = current.D.score - prev.D.score        # null-safe; skip if either is null
overall_delta = current.overall_score - prev.overall_score

direction(D) = delta_D > 0  → IMPROVED
               delta_D == 0 → STABLE
               delta_D < 0  → DECLINED

persist AssessmentTrend { assessment_id, prev_assessment_id, *_delta }

foreach domain D where direction(D) == DECLINED:
    create DegradationAlert { trend_id, domain: D, score_drop: abs(delta_D),
                              acknowledged: false, triggered_at: now }
    dispatch SendDegradationAlertJob → Compliance Officer
```

### 7.5 Remediation prioritisation (executive report)

```
ReportService::prioritise(ScoredAssessment $a): array

rank all 33 elements by:
  1. PRIMARY  — maturity gap from Level 3, descending
                (level 0 → gap 3, ranked highest; then 1, then 2; level 3 excluded)
  2. TIEBREAK — domain risk weight: GOVERN and RESPOND rank above all other domains
  3. STABLE   — element code ascending (deterministic output)

for each of the top N (default N = 10):
    action_text = requirement for (element.maturity_level + 1) from ncsb_elements
                  # i.e. exactly what is needed to reach the next level
```

### 7.6 Business-risk translation

| Domain score (0–3) | Risk category | Narrative template |
|---|---|---|
| 0.00 – 0.99 | **Critical** | "Controls in {DOMAIN} are largely absent. Operational disruption is likely and the organisation is materially exposed under the Cyber Security Act 2024." |
| 1.00 – 1.74 | **High** | "{DOMAIN} controls exist but are inconsistently applied. A disruptive incident would probably exceed the organisation's current response capacity." |
| 1.75 – 2.49 | **Moderate** | "{DOMAIN} controls are established but not yet optimised. Residual risk is tolerable but should be reduced within the next assessment cycle." |
| 2.50 – 3.00 | **Acceptable** | "{DOMAIN} controls meet the baseline. Maintain current practice and monitor for degradation." |

### 7.7 Scenario simulation

```
SimulationService::simulate(Assessment $baseline, array $overrides): SimulationResult

overrides = [ element_code => hypothetical_maturity_level (0..3) ]

working = baseline element levels, with overrides applied
recompute domain scores from `working`  (same formula as §7.3)
recompute overall:
    an element counts as "implemented" if its working level >= 1
    overall_score = count(working level >= 1) / 33
    overall_band  = band_of(overall_score)

return { per-domain projected scores, per-domain delta vs baseline,
         projected overall score + band, delta vs baseline,
         count of elements at or above target level 3 }

# Simulation NEVER writes to Assessment or AssessmentElement.
# Saving a session persists only { baseline_assessment_id, scenario_name, modified_responses(JSON), simulated_score }.
```

---

## 8. Functional Requirements

Each FR has an ID, the module, the RO it serves, and testable acceptance criteria.

### M0 — Authentication, RBAC, Audit

| ID | Requirement | Acceptance criteria |
|---|---|---|
| **FR-01** | Password authentication | Min 12 chars, must contain upper + lower + digit + symbol; rejected passwords return field-level errors; passwords hashed with bcrypt |
| **FR-02** | Email OTP MFA | After valid password, a 6-digit OTP is emailed; valid 5 minutes; max 5 attempts then lockout; session is not authenticated until OTP verified |
| **FR-03** | Password expiry | Passwords older than 90 days force a change on next login |
| **FR-04** | Role-based access | Each of the 3 roles sees only its permitted routes (§4.2); unauthorised access returns 403 and is logged |
| **FR-05** | Session expiry | Session invalidated after 15 minutes of inactivity; user redirected to login with a notice |
| **FR-06** | Audit logging | Every login attempt (success/fail), upload, report generation, deadline change, simulation save, and alert acknowledgement writes an `audit_logs` row with user, action, target, IP, timestamp |
| **FR-07** | User management | Admin can create, edit, deactivate users and assign roles; cannot delete a user who owns assessments (soft-deactivate instead) |

### M1 — Assessment & Trend Engine *(RO2 · Sprint 1 · addresses P1)*

| ID | Requirement | Acceptance criteria |
|---|---|---|
| **FR-10** | Excel upload | Compliance Officer uploads `.xlsx` ≤ 10 MB via drag-drop or browse; upload is rejected with a specific message for wrong type, wrong size, or failed template validation |
| **FR-11** | Template validation | A modified, renamed, or wrong-version file is rejected **before** any data is persisted; the error names what failed (e.g. "Sheet 'PROTECT' missing", "Template version is v1.1, expected v1.4") |
| **FR-12** | Response extraction | All 125 Yes/No responses are extracted; blank or unrecognised cells default to **No**; the count of defaulted cells is shown on the results screen |
| **FR-13** | Unanswered element flagging | An element whose questions are all blank is labelled **Unanswered**, listed on the results screen, and excluded from the overall band until a complete file is re-uploaded |
| **FR-14** | Element scoring | Each element receives level 0–3 from its Yes-count against its seeded thresholds; computed by the system, never read from the Excel score cells |
| **FR-15** | Domain & overall scoring | Domain scores (0–3) and overall score (0–1) + band computed per §7.3 and persisted |
| **FR-16** | Cycle labelling | Each assessment records an `assessment_date` and a user-supplied `cycle_label` (e.g. "Cycle 2026 H1"); labels are unique |
| **FR-17** | Assessment history | All past cycles listed with date, cycle label, file name, uploader, validation status, overall band; each row opens the full result |
| **FR-18** | Trend computation | On successful upload, deltas vs the previous cycle are computed for all six domains and overall, and persisted |
| **FR-19** | Degradation detection | Any domain with a negative delta raises a `DegradationAlert` and dispatches an email to the Compliance Officer within 1 minute |
| **FR-20** | Alert acknowledgement | Compliance Officer can acknowledge an alert; acknowledged alerts leave the active list but stay in history |
| **FR-21** | Trend dashboard | Multi-series line chart of the six domain scores plus overall across all cycles; declining domains rendered in the alert colour with a warning marker |

### M2 — Executive Summary Generator *(RO2 · Sprint 2 · addresses P2)*

| ID | Requirement | Acceptance criteria |
|---|---|---|
| **FR-30** | Risk categorisation | Every domain is assigned Critical / High / Moderate / Acceptable per §7.6 |
| **FR-31** | Narrative generation | Each domain gets a plain-language risk narrative containing no maturity numbers in the sentence body and no NCSB jargon |
| **FR-32** | Remediation prioritisation | Top 10 actions ranked per §7.5, each stating the element, its current level, and the concrete requirement for the next level |
| **FR-33** | PDF generation | Report renders to PDF with cover page (org name, cycle label, date, overall band), domain risk table, narratives, prioritised actions, and Cyber Security Act 2024 compliance status |
| **FR-34** | Download & retention | Compliance Officer and Executive Reviewer can download any generated report; reports are retained per assessment cycle and never regenerate silently |
| **FR-35** | Generation performance | PDF generation completes within 5 s, or is queued with a progress state if it exceeds that |

### M3 — Alert Service *(RO2 · Sprint 3 · addresses P3)*

| ID | Requirement | Acceptance criteria |
|---|---|---|
| **FR-40** | Deadline configuration | Compliance Officer creates a deadline with title, due date, primary contact, secondary (escalation) contact, and lead-time intervals (default 30 / 14 / 7 days) |
| **FR-41** | Scheduled reminder dispatch | A daily scheduled sweep dispatches reminder emails at each configured interval; each (deadline, interval) pair fires **exactly once** |
| **FR-42** | Escalation | If the due date passes with no assessment upload and no acknowledgement, an overdue alert is emailed to the secondary contact |
| **FR-43** | Acknowledgement | A reminder email contains a signed acknowledgement link; clicking it records acknowledgement and suppresses escalation |
| **FR-44** | Deadline status | Each deadline shows Upcoming / Due Soon / Overdue / Met; status is derived, not manually set |
| **FR-45** | Notification history | Every dispatched reminder and escalation is logged with type, recipient, dispatched timestamp, and delivery flag; visible to the Compliance Officer |
| **FR-46** | Failure resilience | A failed email is retried 3 times with backoff and marked `delivered = false` if still failing; failures surface on the dashboard |

### M4 — Control Effectiveness Heatmap *(RO2 · Sprint 4 · addresses P4)*

| ID | Requirement | Acceptance criteria |
|---|---|---|
| **FR-50** | Heatmap grid | All 33 elements rendered as coloured cells grouped under their six domains; colour per §7.1 |
| **FR-51** | Cell drill-down | Clicking a cell reveals element name, current level, gap from Level 3, severity, and a remediation priority note |
| **FR-52** | Unanswered handling | Unanswered elements render in a neutral grey with an "Unanswered" label, not as Critical |
| **FR-53** | Historical replay | A cycle selector re-renders the heatmap as it stood at any past assessment cycle |
| **FR-54** | Legend & accessibility | A legend maps every colour to its severity; colour is never the only signal — each cell also shows its numeric level |

### M5 — Scenario Simulator *(RO2 · Sprint 4 · addresses P4)*

| ID | Requirement | Acceptance criteria |
|---|---|---|
| **FR-60** | Baseline selection | Simulator opens on the latest assessment; user may select any past cycle as baseline |
| **FR-61** | Adjustable levels | Every element has a 0–3 slider, pre-set to its baseline level |
| **FR-62** | Live projection | Adjusting any slider recomputes projected domain scores, overall score, band, and delta vs baseline within 300 ms, without a page reload |
| **FR-63** | Non-destructive | No simulation action writes to `assessments` or `assessment_elements` — verified by a regression test |
| **FR-64** | Save session | A simulation can be saved with a scenario name; saved sessions are listable and reloadable |
| **FR-65** | Reset | A reset control returns every slider to the baseline level |

### M6 — Dashboards *(RO2 · Sprint 4)*

| ID | Requirement | Acceptance criteria |
|---|---|---|
| **FR-70** | Admin dashboard | Registered user count by role, system health indicators (queue depth, failed jobs, last scheduler run), recent technical audit log entries |
| **FR-71** | Compliance Officer dashboard | Latest assessment result, active deadline countdown timers, notification history summary, active degradation alerts, per-domain trend charts |
| **FR-72** | Executive Reviewer dashboard | Overall compliance posture indicator, domain-level risk summary, latest executive report download link, shortcuts to heatmap and simulator |
| **FR-73** | Empty states | Every dashboard renders meaningfully with zero assessments (first-run), with a clear call to action |

---

## 9. Data Model

Based on Figure 4.2 (ERD), extended with the reference and audit tables the implementation requires.

### 9.1 Tables

**`users`**

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| email | varchar(255) UNIQUE | |
| password | varchar(255) | bcrypt |
| role | enum | `admin`, `compliance_officer`, `executive_reviewer` |
| is_active | boolean | default true |
| password_changed_at | timestamp | drives FR-03 |
| otp_code | varchar(255) nullable | hashed |
| otp_expires_at | timestamp nullable | |
| otp_attempts | tinyint | default 0 |
| mfa_verified_at | timestamp nullable | |
| timestamps | | |

**`ncsb_elements`** *(reference data, seeded — 33 rows)*

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| code | varchar(10) UNIQUE | e.g. `GV-01` |
| domain | enum | 6 values |
| category | varchar(255) | one of 15 |
| name | varchar(255) | |
| question_count | tinyint | sums to 125 |
| threshold_l1 / l2 / l3 | tinyint | Yes-count thresholds |
| requirement_l1 / l2 / l3 | text | remediation action text |
| domain_risk_weight | tinyint | 2 for GOVERN/RESPOND, 1 otherwise |
| sort_order | smallint | heatmap ordering |

**`assessments`**

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| created_by | bigint FK → users | |
| assessment_date | date | |
| cycle_label | varchar(50) UNIQUE | e.g. "Cycle 2026 H1" |
| original_filename | varchar(255) | |
| stored_path | varchar(255) | private disk |
| file_hash | char(64) | SHA-256, duplicate-upload detection |
| validation_status | enum | `validated`, `rejected` |
| overall_score | decimal(4,3) | 0.000–1.000 |
| overall_band | enum | Initial / Basic / Intermediate / Advanced |
| govern_score … recover_score | decimal(3,2) nullable | 6 columns, 0.00–3.00 |
| unanswered_count | tinyint | |
| defaulted_cell_count | smallint | blank cells treated as No |
| timestamps | | |

**`assessment_elements`**

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| assessment_id | bigint FK | cascade delete |
| ncsb_element_id | bigint FK | |
| domain | enum | denormalised for fast heatmap query |
| element_code | varchar(10) | denormalised |
| yes_count | tinyint | |
| maturity_level | tinyint nullable | null = unanswered |
| status | enum | `scored`, `unanswered` |
| responses | text | **encrypted** — the raw Yes/No array for this element. Must be `text`, not `json`: ciphertext is not valid JSON and MariaDB's `json_valid()` CHECK constraint rejects it. |

Index: `(assessment_id, domain)`, `(assessment_id, element_code)`

**`assessment_trends`**

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| assessment_id | bigint FK UNIQUE | one trend per assessment |
| prev_assessment_id | bigint FK | |
| govern_delta … recover_delta | decimal(4,2) nullable | 6 columns, signed |
| overall_delta | decimal(4,3) | signed |
| timestamps | | |

**`degradation_alerts`**

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| assessment_trend_id | bigint FK | |
| domain | enum | |
| score_drop | decimal(4,2) | positive magnitude |
| acknowledged | boolean | default false |
| acknowledged_by | bigint FK nullable | |
| acknowledged_at | timestamp nullable | |
| triggered_at | timestamp | |

**`compliance_deadlines`**

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| officer_id | bigint FK → users | owner |
| title | varchar(255) | |
| description | text nullable | |
| due_date | date | |
| lead_days | json (nullable) | e.g. `[30,14,7]`. **No SQL-level default on MariaDB** — set `[30,14,7]` in the model's `$attributes`. See §9.4. |
| secondary_contact_id | bigint FK → users nullable | escalation target |
| status | enum | `upcoming`, `due_soon`, `overdue`, `met` |
| acknowledged_at | timestamp nullable | |
| met_by_assessment_id | bigint FK nullable | links the upload that satisfied it |
| timestamps | | |

**`deadline_notifications`**

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| compliance_deadline_id | bigint FK | |
| type | enum | `reminder`, `escalation` |
| lead_day | smallint nullable | which interval fired |
| recipient_id | bigint FK → users | |
| dispatched_at | datetime | |
| delivered | boolean | |
| failure_reason | text nullable | |

Unique: `(compliance_deadline_id, type, lead_day)` — guarantees FR-41 exactly-once.

**`simulation_sessions`**

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| created_by | bigint FK → users | |
| baseline_assessment_id | bigint FK | |
| scenario_name | varchar(255) | |
| modified_responses | text | **encrypted** — `{element_code: level}` (see note on `assessment_elements.responses`) |
| simulated_score | decimal(4,3) | projected overall |
| simulated_band | enum | |
| timestamps | | |

**`executive_reports`**

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| assessment_id | bigint FK | |
| generated_by | bigint FK → users | |
| file_path | varchar(255) | private disk |
| payload | text | **encrypted** — narratives + prioritised actions, for reproducibility |
| generated_at | timestamp | |

**`audit_logs`**

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK nullable | null for failed logins with unknown email |
| action | varchar(100) | `login.success`, `assessment.upload`, `report.generate`, … |
| auditable_type / auditable_id | morphs nullable | target record |
| category | enum | `technical` (Admin view) / `compliance` (Officer view) |
| ip_address | varchar(45) | |
| user_agent | varchar(255) | |
| metadata | json nullable | |
| created_at | timestamp | |

### 9.2 Relationship summary

| Relationship | Cardinality |
|---|---|
| User → Assessment | 1 : N (`created_by`) |
| Assessment → AssessmentElement | 1 : 33 |
| Assessment → AssessmentTrend | 1 : 0..1 |
| AssessmentTrend → DegradationAlert | 1 : N (0–6) |
| User → ComplianceDeadline | 1 : N (`officer_id`) |
| ComplianceDeadline → DeadlineNotification | 1 : N |
| User → SimulationSession | 1 : N |
| Assessment → SimulationSession | 1 : N (as baseline) |
| Assessment → ExecutiveReport | 1 : N |

### 9.3 Encryption at rest (Security Requirement, Table 3.4)

Apply Eloquent `encrypted` / `encrypted:array` casts to: `assessment_elements.responses`, `simulation_sessions.modified_responses`, `executive_reports.payload`. Uploaded `.xlsx` files are stored on a **private** disk, never in `public/`.

### 9.4 MariaDB compatibility notes

The system targets **MariaDB 10.6+ / 11.x**, not MySQL 8. Laravel supports MariaDB fully, but five differences affect this schema. Build against them from the start — each one fails at migration or runtime, not at design time.

| # | Difference | Effect on this schema | Required action |
|---|---|---|---|
| 1 | **No native JSON type.** MariaDB's `JSON` is an alias for `LONGTEXT` with a `json_valid()` CHECK constraint. | `lead_days`, `metadata` are stored as `longtext`; encrypted columns would be *rejected* by the CHECK constraint. | Encrypted columns use `text`, never `json` (already applied above). Plain JSON columns are fine as `json`. |
| 2 | **No expression defaults on JSON columns.** `->default(new Expression("(JSON_ARRAY(30,14,7))"))` fails. | `compliance_deadlines.lead_days` cannot carry a SQL default. | Declare `$table->json('lead_days')->nullable();` and set the default in the model: `protected $attributes = ['lead_days' => '[30,14,7]'];` |
| 3 | **JSON/longtext columns cannot be indexed directly.** | No index may be placed on `lead_days` or `metadata`. | Current schema places none. If a JSON-based query is ever needed, add a generated column and index that instead. |
| 4 | **Default collation differs.** MariaDB defaults to `utf8mb4_general_ci`; MySQL 8 to `utf8mb4_0900_ai_ci` (which MariaDB does not have). | A migration copied from a MySQL tutorial may fail. | Set the database and connection explicitly to `utf8mb4` / `utf8mb4_unicode_ci` — supported identically on both engines. |
| 5 | **`utf8mb4` + long unique indexes.** Older MariaDB defaults to a 767-byte index limit on non-DYNAMIC row format. | `users.email` unique index and `cycle_label` unique could fail on a misconfigured server. | Confirm `innodb_default_row_format = dynamic`. If a length error appears, call `Schema::defaultStringLength(191)` in `AppServiceProvider::boot()`. |

**Everything else is unchanged.** `ENUM`, `DECIMAL`, foreign keys, `nullableMorphs`, composite unique indexes, and Laravel's `UniqueConstraintViolationException` (used for the FR-41 exactly-once guarantee) all behave identically on MariaDB.

**Database creation:**

```sql
CREATE DATABASE nacsa_toolkit
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

**Verify which engine you are actually connected to** before migrating — WampServer runs MySQL and MariaDB simultaneously on different ports:

```bash
mysql -u root -P 3307 -e "SELECT VERSION();"
```

A result containing `-MariaDB` confirms the correct engine. Record the port in `.env` as `DB_PORT` and do not switch engines mid-project — the two produce different `SHOW CREATE TABLE` output, which will make your Chapter 4 schema evidence inconsistent.

---

## 10. Route Map

| Method | URI | Controller@action | Role |
|---|---|---|---|
| GET/POST | `/login` | AuthController@showLogin / login | guest |
| GET/POST | `/otp` | AuthController@showOtp / verifyOtp | pending-MFA |
| POST | `/logout` | AuthController@logout | any |
| GET/POST | `/password/change` | AuthController@... | any |
| GET | `/dashboard` | DashboardController@index *(dispatches by role)* | any |
| GET | `/admin/users` | UserController@index | admin |
| GET/POST | `/admin/users/create` | UserController@create/store | admin |
| PATCH/DELETE | `/admin/users/{user}` | UserController@update/deactivate | admin |
| GET | `/admin/audit-logs` | AuditController@technical | admin |
| GET | `/admin/settings` | SettingsController@edit | admin |
| GET | `/assessments` | AssessmentController@index | officer, executive |
| GET | `/assessments/upload` | AssessmentController@showUpload | officer |
| POST | `/assessments` | AssessmentController@store | officer |
| GET | `/assessments/{assessment}` | AssessmentController@show | officer, executive |
| GET | `/trends` | TrendController@index | officer, executive |
| POST | `/alerts/{alert}/acknowledge` | AlertController@acknowledge | officer |
| GET | `/deadlines` | DeadlineController@index | officer |
| GET/POST | `/deadlines/create` | DeadlineController@create/store | officer |
| PATCH/DELETE | `/deadlines/{deadline}` | DeadlineController@update/destroy | officer |
| GET | `/deadlines/acknowledge/{token}` | DeadlineController@acknowledgeLink | signed URL |
| GET | `/notifications` | NotificationController@index | officer |
| GET | `/audit-trail` | AuditController@compliance | officer |
| GET | `/reports` | ReportController@index | officer, executive |
| POST | `/reports/{assessment}/generate` | ReportController@generate | officer |
| GET | `/reports/{report}/download` | ReportController@download | officer, executive |
| GET | `/heatmap` | HeatmapController@index *(optional `?cycle=`)* | officer, executive |
| GET | `/heatmap/element/{code}` | HeatmapController@detail *(JSON)* | officer, executive |
| GET | `/simulator` | SimulatorController@index | officer, executive |
| POST | `/simulator/project` | SimulatorController@project *(JSON)* | officer, executive |
| POST | `/simulator/sessions` | SimulatorController@store | officer, executive |
| GET | `/simulator/sessions/{session}` | SimulatorController@show | officer, executive |

---

## 11. Screen Specifications

| Screen | Key elements | Source |
|---|---|---|
| **Login** | Branded left panel, email + password, "Access is determined by your assigned role" notice, forgot-password link | Fig 5.1 |
| **OTP verification** | 6-digit input, countdown to expiry, resend (rate-limited), attempts remaining | FR-02 |
| **Assessment Upload & History** | Drag-drop zone ("official template only · max 10 MB · .xlsx"), template version badge, past-cycles table with validation status pills (Validated / Validating / Rejected) | Fig 5.2 |
| **Upload results** | Overall band, per-domain scores, unanswered element list, defaulted-cell count, "re-upload" CTA | FR-12, FR-13 |
| **Compliance Trend Dashboard** | Three stat tiles (Overall Maturity · Improving Domains · Domains at Risk), multi-series Chart.js line chart across cycles, degradation alerts panel with acknowledge buttons, quick-access cards to report + deadline tracker | Fig 5.3 |
| **Control Effectiveness & Scenario Simulator** | Projected posture panel (left), domain progress bars with delta vs baseline, 33-cell maturity-gap heatmap grouped by domain, slider column for hypothetical levels, Reset + Save Scenario | Fig 5.4 |
| **Compliance Deadlines** | Deadline cards with countdown, status pill, lead-time chips, escalation contact; create/edit modal | FR-40–FR-44 |
| **Notification History** | Table: type, recipient, lead day, dispatched at, delivered flag | FR-45 |
| **Executive Report** | Generate button per cycle, list of generated reports with download links | FR-33–FR-34 |
| **Admin Console** | User table with role badges and activate/deactivate, system health tiles, technical audit log table with filters | FR-70, FR-07 |

**UI rules**

| Rule | Detail |
|---|---|
| Colour semantics | Green Compliant · Yellow Low · Orange High · Red Critical · Grey Unanswered — consistent everywhere |
| Never colour-only | Every heatmap cell shows its numeric level; every status pill carries a text label |
| Responsive | Bootstrap grid; heatmap scrolls horizontally below 768 px |
| Loading states | Upload, PDF generation, and simulator projection all show a spinner or skeleton |
| Error surface | Validation failures appear inline with the specific reason, never a generic "upload failed" |

---

## 12. Non-Functional and Security Requirements

### 12.1 Non-functional (Table 3.3)

| ID | Category | Requirement | Verification |
|---|---|---|---|
| NFR-01 | Performance | Dashboard load and report generation complete within 5 s under standard load | Timed test with 10 seeded cycles |
| NFR-02 | Performance | Simulator projection responds within 300 ms | Client-side timing test |
| NFR-03 | Scalability | Supports concurrent use by multiple users without degradation | Load test, 20 concurrent sessions |
| NFR-04 | Availability | ≥ 99% availability during assessment and reporting periods | Deployment/uptime note |
| NFR-05 | Usability | Operable by compliance officers and executives without specialised technical training | UAT task-completion rate ≥ 90% |
| NFR-06 | Compatibility | Correct on current Chrome, Firefox, Edge, Safari | Manual cross-browser matrix |
| NFR-07 | Maintainability | Business logic isolated in service classes; ≥ 70% unit-test coverage on `app/Services` | `php artisan test --coverage` |

### 12.2 Security (Table 3.4)

| ID | Element | Requirement | Verification |
|---|---|---|---|
| SEC-01 | Authentication | Min 12 chars, complexity enforced, 90-day expiry | Unit + feature test |
| SEC-02 | MFA | Email OTP enforced at every login | Feature test: no route reachable pre-OTP |
| SEC-03 | RBAC | Permissions strictly role-bound per §4.2 | Policy test per role × per route |
| SEC-04 | Session | Expiry after 15 min inactivity | Feature test with time travel |
| SEC-05 | Encryption at rest | AES-256-CBC via Laravel `Crypt` on sensitive fields | DB inspection test: ciphertext, not plaintext |
| SEC-06 | Audit logging | All specified actions logged | Feature test asserting `audit_logs` rows |
| SEC-07 | File security | Uploads stored on private disk; MIME + extension + size validated; no execution path | Feature test with a renamed `.php` file |
| SEC-08 | Transport | HTTPS enforced; `Strict-Transport-Security` header | Deployment config |
| SEC-09 | CSRF / XSS | Laravel CSRF on all forms; Blade escaping; no `{!! !!}` on user data | Static review + security test |
| SEC-10 | Rate limiting | Login and OTP endpoints throttled (5 attempts / 15 min) | Feature test |

---

## 13. Test Plan

### 13.1 Technical testing (§4.7)

| Level | Coverage |
|---|---|
| **Unit** | Template validation; response extraction and normalisation; per-element Yes-count → level mapping; overall band mapping; domain averaging; trend delta computation; degradation detection; heatmap severity classification; simulation recomputation; remediation ranking including the GOVERN/RESPOND tie-break |
| **Integration** | Upload → parse → score → persist → trend → alert chain; scores flowing into heatmap, simulator, and report generator; deadline sweep → notification → escalation → acknowledgement |
| **Security** | SEC-01 to SEC-10 above |
| **Regression** | Simulation never mutates assessment data (FR-63); re-upload of an identical file is detected by hash |

### 13.2 Functional testing (§3.7.1)

One test case per FR in §8, each documenting input condition, expected output, actual output, pass/fail. Run at the close of every sprint.

### 13.3 User Acceptance Testing (§3.7.2)

Conducted with the Senior Manager of Cybersecurity and IT Risk Management at MAHB.

| # | Task scenario | Measures |
|---|---|---|
| 1 | Upload a completed NCSB v1.4 file and review the resulting maturity scores | Completion, clarity |
| 2 | Examine the trend dashboard for historical posture change | Completion, clarity |
| 3 | Generate and interpret an executive summary report | Completion, clarity, usefulness |
| 4 | Configure a compliance deadline and verify email receipt | Completion |
| 5 | Interpret the heatmap to identify the highest-severity gaps | Completion, clarity |
| 6 | Use the simulator to model a proposed remediation investment | Completion, usefulness |

Feedback instrument captures: task completion rate, information clarity, interface usability, overall satisfaction.

### 13.4 Comparative evaluation — RO3 (§3.7.3, §5.3)

| Dimension | Metric | Method | Success criterion |
|---|---|---|---|
| **Operational continuity** | Detection of posture change between cycles | Binary presence/absence vs 2025 NCSBAS | System detects and flags decline; predecessor has no such capability |
| **Governance communication** | Executive output consumable without technical reinterpretation | UAT expert rating on report clarity | Rating ≥ 4/5; no manual reinterpretation needed |
| **Compliance adherence** | Proactive deadline management | Binary presence/absence + reminder dispatch log | Reminders and escalation fire correctly at all configured intervals |
| **Executive decision support** | Availability of diagnostic tools | Binary presence/absence + UAT usefulness rating | Heatmap + simulator present and rated useful for budget justification |

> Where the predecessor lacks the capability entirely (degradation detection, automated notification), record as **binary presence/absence**, consistent with Table 2.2 — do not force an equivalent measurement scale onto both systems.

---

## 14. Sprint Backlog

| Sprint | Duration | Module | Deliverables | Exit criteria |
|---|---|---|---|---|
| **0** *(setup)* | 1 week | Foundation | Laravel install, **MariaDB** database created per §9.4, auth scaffold, RBAC middleware, audit service, **`ncsb_elements` seeder populated from the official v1.4 file** | `SELECT VERSION()` confirms MariaDB; 33 elements seeded with correct thresholds; 3 roles log in with OTP; audit rows written |
| **1** | 6 weeks | M1 Assessment & Trend Engine | Excel upload, template validation, extraction, scoring engine, cycle history, trend engine, degradation detection and flagging | A real NCSB v1.4 file uploads and scores correctly; a second cycle produces trend deltas and raises an alert on any decline |
| **2** | 5 weeks | M2 Executive Summary Generator | Risk translation engine, narrative templates, prioritised remediation, board-ready PDF | PDF generates from a scored assessment with correct risk categories and a correctly ordered top-10 action list |
| **3** | 4 weeks | M3 Alert Service | Deadline CRUD, scheduler sweep, multi-interval reminders, escalation workflow, notification history | Reminders fire once per interval; escalation triggers on unacknowledged overdue; all dispatches logged |
| **4** | 6 weeks | M4 + M5 + M6 | 33-element heatmap with drill-down and cycle replay, scenario simulator with live projection and saved sessions, three role dashboards | Heatmap matches scored data exactly; simulator projects in < 300 ms and never mutates source data |
| **5** | 5 weeks | Integration & Evaluation | System integration, full functional test pass, security test pass, UAT with MAHB expert, comparative evaluation vs 2025 NCSBAS | All FRs pass; UAT completed; RO3 evaluation table populated with evidence |

**Total: 27 weeks + 1 setup week.**

---

## 15. Risks, Assumptions, Open Items

| ID | Item | Type | Mitigation / action |
|---|---|---|---|
| R1 | Exact NCSB v1.4 sheet/cell layout not yet mapped into the seeder | **Blocker** | Complete in Sprint 0; document in Appendix A before any scoring code |
| R2 | Element-level Yes-count thresholds for Levels 1–3 must come from the official framework, not be invented | **Blocker** | Extract from the toolkit; if a threshold is ambiguous, confirm with supervisor and record the assumption |
| R3 | NCSB may release a v1.5 mid-project | Risk | Version marker check in validation; thresholds live in seed data, not code |
| R4 | UAT limited to a single MAHB domain expert (restricted NCII access) | Limitation | Already disclosed in §5.3 of the proposal; supplement with functional test evidence |
| R5 | Single-organisation deployment vs the multi-org NFR in Table 3.3 | Limitation | Restate NFR-03 as concurrent multi-user; note multi-tenancy as future work in the thesis |
| R6 | Email deliverability in the demo environment | Risk | Use Mailtrap/log driver in development; document SMTP config for demo |
| R7 | "Overall score" (share of elements implemented) is easily confused with domain averages | Design risk | Label both explicitly in every UI surface and in the report |
| R8 | Domain risk weights (GOVERN, RESPOND ranked higher) are an author design decision, not NCSB doctrine | Assumption | State this explicitly in the thesis and in the report footnote |
| R9 | **Thesis proposal Table 4.4 states "MySQL database managed through Laravel migrations" — the build now uses MariaDB** | **Documentation inconsistency** | Update Table 4.4 in the thesis to read "MariaDB database managed through Laravel migrations". A reviewer comparing the proposal against the implemented system will otherwise flag it. |

### Assumptions

1. Only the official, unmodified NCSB v1.4 `.xlsx` will be uploaded; modified files are rejected by design.
2. A blank response genuinely means "not implemented" — hence the conservative default to No.
3. One assessment cycle per upload; cycles are chronologically ordered by `assessment_date`.
4. Level 3 (Advanced) is the target maturity for every element.
5. Email is a reliable channel for the target organisation's compliance workflow.

---

## Appendix A — NCSB v1.4 Excel Mapping *(completed 24 Sep 2026)*

Source: `docs/reference/NCSB-SelfAssessment-v1.4.xlsx` (blank official template, last modified 30 Sep 2025). Decisions referenced as D-xx are in `DECISIONS.md`.

### A.1 Workbook

| Item | Value |
|---|---|
| Workbook file name | `NCSB-SelfAssessment-v1.4.xlsx` |
| Version marker cell | **None exists** — "v1.4" is only in the filename. Template identity is checked structurally (D-10). |
| Sheet names (exact, in order) | `Using this CS Baseline Tool`, `CS Baseline Model`, `CS Baseline Questionnaires`, `CS Baseline Scoring Dashboard`, `CS Baseline Maturity Level ` *(trailing space)*, `FAQ` |
| Element code column | **None** — codes are our convention (D-11) |
| Questionnaire layout | `CS Baseline Questionnaires`: B = question # (1–125), C = domain, D = category, E = element (first row of each element only), F = description, G = question text, **H = response**. Rows 3–127. |
| Yes/No cell value conventions | Free-text `Yes` / `No`. Only the first question of each element has a dropdown list; the rest are typed. Custom validation shows an error when anything is entered after a "No" (the toolkit expects the remaining questions of that element to be skipped). Scoring counts only `"Yes"` (`COUNTIF`, case-insensitive). |
| Per-element threshold source | `CS Baseline Scoring Dashboard`, column **E** (rows 3–35): nested `IF` on the Yes-count in column D (`=COUNTIF('CS Baseline Questionnaires'!Hx:Hy,"Yes")`). |
| Requirement text source | `CS Baseline Model`, rows 4–36: I = Level 0, J = Level 1 (Basic), K = Level 2 (Intermediate), L = Level 3 (Advanced). |
| Overall score in toolkit | `Scoring Dashboard!J2 = COUNTIF(D3:D35,">0")/33`, band in K2 with the same cut-offs as §7.1. |

### A.2 Element map

L1/L2/L3 = minimum Yes-count to reach that level (from the Dashboard formulas).

| Code | Domain | Category | Element | Response range | Qs | L1 | L2 | L3 | Dashboard row |
|---|---|---|---|---|:--:|:--:|:--:|:--:|:--:|
| GV-01 | GOVERN | Cyber Security Policy & Objectives | Cyber Security Policy | `H3:H7` | 5 | 2 | 3 | 5 | 3 |
| GV-02 | GOVERN | Cyber Security Policy & Objectives | Cyber Security Objectives | `H8:H10` | 3 | 1 | 2 | 3 | 4 |
| GV-03 | GOVERN | Organisational Development | Resources (Manpower and Budget) | `H11:H14` | 4 | 2 | 3 | 4 | 5 |
| GV-04 | GOVERN | Organisational Development | Authorities and Special Interest Group | `H15:H17` | 3 | 1 | 2 | 3 | 6 |
| PR-01 | PROTECT | Organisational Development | Cyber Security Awareness and Training | `H18:H20` | 3 | 1 | 2 | 3 | 7 |
| GV-05 | GOVERN | Cyber Security Assurance | IT Acceptable Use Policy | `H21:H23` | 3 | 1 | 2 | 3 | 8 |
| GV-06 | GOVERN | Cyber Security Assurance | Cyber Security Audit | `H24:H28` | 5 | 2 | 3 | 5 | 9 |
| GV-07 | GOVERN | Cyber Security Assurance | Supplier Relationship Security | `H29:H31` | 3 | 1 | 2 | 3 | 10 |
| ID-01 | IDENTIFY | Resource Allocation and optimisation | Asset Management | `H32:H35` | 4 | 2 | 3 | 4 | 11 |
| PR-02 | PROTECT | Resource Allocation and optimisation | Capacity management | `H36:H39` | 4 | 1 | 2 | 4 | 12 |
| ID-02 | IDENTIFY | Risk Management | Risk Assessment | `H40:H44` | 5 | 2 | 3 | 5 | 13 |
| PR-03 | PROTECT | Risk Management | Risk Treatment | `H45:H48` | 4 | 2 | 3 | 4 | 14 |
| PR-04 | PROTECT | Operational Efficiency | Change Management | `H49:H52` | 4 | 2 | 3 | 4 | 15 |
| PR-05 | PROTECT | Data Security | Data Leakage Prevention (DLP) | `H53:H55` | 3 | 1 | 2 | 3 | 16 |
| PR-06 | PROTECT | Data Security | Data Privacy and Protection | `H56:H60` | 5 | 2 | 3 | 5 | 17 |
| PR-07 | PROTECT | Data Security | Software and Application Security | `H61:H64` | 4 | 2 | 3 | 4 | 18 |
| PR-08 | PROTECT | Data Security | Secure Configuration | `H65:H67` | 3 | 1 | 2 | 3 | 19 |
| PR-09 | PROTECT | Contractual Management | Non-Disclosure Agreement (NDA) | `H68:H70` | 3 | 1 | 2 | 3 | 20 |
| PR-10 | PROTECT | Physical Security | Physical Security Procedure | `H71:H75` | 5 | 2 | 3 | 5 | 21 |
| PR-11 | PROTECT | Physical Security | Security Perimeters | `H76:H78` | 3 | 1 | 2 | 3 | 22 |
| PR-12 | PROTECT | System and Network Security | Network Security Policies and Procedures | `H79:H83` | 5 | 3 | 4 | 5 | 23 |
| PR-13 | PROTECT | System and Network Security | Physical and Logical Network Diagrams | `H84:H87` | 4 | 2 | 3 | 4 | 24 |
| PR-14 | PROTECT | System and Network Security | Network Security Controls | `H88:H91` | 4 | 1 | 2 | 4 | 25 |
| DE-01 | DETECT | System and Network Security | Malware Detection and Prevention | `H92:H94` | 3 | 1 | 2 | 3 | 26 |
| PR-15 | PROTECT | Access Control | Identity and Access Management (IAM) | `H95:H98` | 4 | 2 | 3 | 4 | 27 |
| PR-16 | PROTECT | Access Control | Segregation of Duties | `H99:H101` | 3 | 1 | 2 | 3 | 28 |
| PR-17 | PROTECT | Access Control | Password Management System | `H102:H105` | 4 | 2 | 3 | 4 | 29 |
| PR-18 | PROTECT | Technical Vulnerability | Vulnerability Management Procedure | `H106:H109` | 4 | 2 | 3 | 4 | 30 |
| PR-19 | PROTECT | Technical Vulnerability | Vulnerability Assessment | `H110:H114` | 5 | 1 | 3 | 5 | 31 |
| DE-02 | DETECT | Cyber Security Event Management | Event Management | `H115:H117` | 3 | 1 | 2 | 3 | 32 |
| RS-01 | RESPOND | Cyber Security Incident Management | Incident Procedure and Handling | `H118:H120` | 3 | 1 | 2 | 3 | 33 |
| RC-01 | RECOVER | Business Continuity Management | Business Continuity Plan | `H121:H123` | 3 | 1 | 2 | 3 | 34 |
| RC-02 | RECOVER | Business Continuity Management | Testing and Simulation | `H124:H127` | 4 | 2 | 3 | 4 | 35 |

Totals: 33 elements, 125 questions, 15 categories. Domain counts: GOVERN 7, IDENTIFY 2, PROTECT 19, DETECT 2, RESPOND 1, RECOVER 2 — match §6.1.

### A.3 Name differences between sheets

| Code | Questionnaire (used) | Model sheet |
|---|---|---|
| PR-12 | Network Security Policies and Procedures | Network Security Policy and Procedure |
| RS-01 | Incident Procedure and Handling | Cyber Security Incident Procedure and Handling |

### A.4 Threshold inconsistencies inside the toolkit

| Code | Element | Dashboard formula (used, D-15) | Model sheet requirement text |
|---|---|---|---|
| PR-14 | Network Security Controls | L2 at **2** Yes | L2 lists **3** items |
| PR-19 | Vulnerability Assessment | L1 at **1** Yes | L1 lists **2** items |

RC-02 (Testing and Simulation) is frequency-based: each level's requirement is a single statement ("every 3 to 5 years", "biennially", "annually") rather than a cumulative list.

The machine-readable version of this mapping is `database/seeders/data/ncsb-v1.4.json`. `tests/Feature/NcsbReferenceDataTest.php` checks it against the workbook on every test run (sheet names, element names, question and requirement texts, response ranges, and thresholds obtained by evaluating each Dashboard formula for every Yes-count).

## Appendix B — Traceability Matrix

| Problem | Objective | Module | FRs | Sprint | Evaluation dimension |
|---|---|---|---|---|---|
| P1 Point-in-time blindness | RO2 | M1 | FR-10 – FR-21 | 1 | Operational continuity |
| P2 Governance communication failure | RO2 | M2 | FR-30 – FR-35 | 2 | Governance communication |
| P3 Reactive compliance management | RO2 | M3 | FR-40 – FR-46 | 3 | Compliance adherence |
| P4 No diagnostic decision support | RO2 | M4, M5 | FR-50 – FR-65 | 4 | Executive decision support |
| Cross-cutting (security, access) | RO2 | M0, M6 | FR-01 – FR-07, FR-70 – FR-73 | 0, 4 | All |
| Validation of improvement | RO3 | — | — | 5 | All four |
