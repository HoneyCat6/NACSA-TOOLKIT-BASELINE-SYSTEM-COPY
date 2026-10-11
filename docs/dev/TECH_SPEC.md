# Technical Specification
## NACSA Toolkit Baseline System

**Version:** 1.1 — *database changed from MySQL 8 to MariaDB (§0, §1.1, §1.3, §2.3, §5.7, §15.1, Appendix A)*
**Date:** 11 October 2026
**Companion to:** `PRD.md` v1.1
**Target environment:** Local development — WampServer (Windows)
**Author:** Muhammad Zarif Irfan bin Zalali (1230551)

---

## 0. Decisions Locked for This Spec

| Decision | Value | Note |
|---|---|---|
| Framework | Laravel 11 | Laravel 12 is structurally identical — swap the version constraint, nothing else in this spec changes |
| PHP | 8.2 minimum, 8.3 preferred | WampServer ships multiple versions; switch via the tray icon |
| Database | **MariaDB 10.6+ / 11.x** | WampServer bundles MySQL *and* MariaDB on different ports — §1.3 shows how to confirm you are on the right one |
| DB connection driver | `mariadb` (Laravel 11+) | **Not** `mysql` — Laravel 11 added a dedicated MariaDB driver; see §2.3 |
| Web server | Apache (WampServer) | Not nginx |
| Front-end | Blade + Bootstrap 5.3 + Alpine.js 3 + Chart.js 4 | No build step, no npm required (CDN acceptable for local) |
| Queue driver | `database` | Requires a manual worker process — §12.4 |
| Scheduler | `schedule:work` (manual) or Windows Task Scheduler | No real cron on local — §12.5 |
| Mail | Mailtrap for development, Gmail SMTP for UAT | §12.6 |
| HTTPS | **Not available locally** | SEC-08 deferred to deployment; record as limitation |
| Tenancy | Single organisation | No `organizations` table |

---

## 1. Environment Setup (WampServer)

### 1.1 Required stack

| Component | Version | Check |
|---|---|---|
| PHP | ≥ 8.2 | `php -v` |
| **MariaDB** | 10.6+ / 11.x | `mysql -u root -P <port> -e "SELECT VERSION();"` — must contain `-MariaDB` |
| Composer | ≥ 2.6 | `composer -V` |
| Node.js | Optional | Only if you compile assets instead of using CDN |
| Git | Any | For version control / supervisor submission |

> **Make PHP available in the terminal.** WampServer's PHP is not on the Windows PATH by default. Add `C:\wamp64\bin\php\php8.2.x` to your system PATH, then reopen the terminal and confirm `php -v` works. Without this, every `php artisan` command fails.

### 1.2 Required PHP extensions

WampServer disables several of these by default. Enable them via **tray icon → PHP → PHP extensions**, or uncomment in `php.ini`.

| Extension | Needed for |
|---|---|
| `openssl` | Encryption (SEC-05), HTTPS mail transport |
| `pdo_mysql` | Database — **this is the correct extension for MariaDB too**; there is no `pdo_mariadb` |
| `mbstring` | Laravel core |
| `fileinfo` | Upload MIME validation (SEC-07) |
| `zip` | PhpSpreadsheet — **xlsx files are zip archives** |
| `gd` | DomPDF image rendering |
| `curl` | Composer, mail APIs |
| `xml`, `simplexml` | PhpSpreadsheet |
| `intl` | Number/date formatting |

Verify all at once:

```bash
php -m | findstr /I "openssl pdo_mysql mbstring fileinfo zip gd curl xml intl"
```

### 1.3 MariaDB setup

WampServer runs **MySQL and MariaDB simultaneously** on different ports. MySQL is typically `3306` and MariaDB `3307`, but WampServer sometimes reverses this — never assume.

**Step 1 — find the MariaDB port.** WampServer tray icon → MariaDB → the version submenu shows the port. Or test both:

```bash
mysql -u root -P 3306 -e "SELECT VERSION();"
mysql -u root -P 3307 -e "SELECT VERSION();"
```

The one returning something like `10.11.x-MariaDB` or `11.4.x-MariaDB` is your target. A result like `8.0.x` is MySQL — wrong port.

**Step 2 — make MariaDB the default in WampServer** (optional but prevents confusion): tray icon → Tools → "Switch default DBMS to MariaDB". The service then answers on `3306` and you can drop the `-P` flag everywhere.

**Step 3 — create the database:**

```sql
CREATE DATABASE nacsa_toolkit
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

> Use `utf8mb4_unicode_ci` explicitly. MariaDB's default is `utf8mb4_general_ci` and MySQL 8's is `utf8mb4_0900_ai_ci` — which **does not exist in MariaDB**. Any migration or SQL dump copied from a MySQL 8 tutorial that names that collation will fail outright.

**Step 4 — record the port in `.env`** and do not switch engines mid-project. The two produce different `SHOW CREATE TABLE` output, which would make your Chapter 4 schema evidence inconsistent with your running system.

### 1.3.1 MariaDB constraints affecting this build

Five differences from MySQL 8 affect the schema. Each fails at migration or runtime, not at design time — build against them from the start. (Mirrors PRD §9.4.)

| # | Difference | Required action in this spec |
|---|---|---|
| 1 | **No native JSON type.** MariaDB's `JSON` is an alias for `LONGTEXT` with a `json_valid()` CHECK constraint. | Encrypted columns use `text`, never `json` — ciphertext is not valid JSON and the CHECK rejects it. Already applied in §5.4, §5.9. |
| 2 | **No expression defaults on JSON columns.** | `lead_days` is declared nullable with the default set in the model — §5.7. |
| 3 | **JSON/longtext columns cannot be indexed directly.** | No index is placed on `lead_days` or `metadata`. If you later need a JSON query, add a generated column and index that. |
| 4 | **Default collation differs.** | Database and connection pinned to `utf8mb4_unicode_ci` — §1.3, §2.3. |
| 5 | **767-byte index limit on non-DYNAMIC row format.** | Confirm `innodb_default_row_format = dynamic`. On a "Specified key was too long" error, add `Schema::defaultStringLength(191);` to `AppServiceProvider::boot()`. |

Unaffected and behaving identically: `ENUM`, `DECIMAL`, foreign keys, `nullableMorphs`, composite unique indexes, and `UniqueConstraintViolationException` (which carries the FR-41 exactly-once guarantee — MariaDB raises the same error code 1062).

### 1.4 php.ini adjustments

Edit via **tray icon → PHP → php.ini** (this edits the Apache-loaded ini; the CLI uses a separate file at `C:\wamp64\bin\php\php8.2.x\php.ini` — **change both**).

| Setting | Value | Reason |
|---|---|---|
| `upload_max_filesize` | `12M` | 10 MB file limit + overhead (FR-10) |
| `post_max_size` | `16M` | Must exceed `upload_max_filesize` |
| `memory_limit` | `512M` | PhpSpreadsheet is memory-hungry; DomPDF more so |
| `max_execution_time` | `120` | Parsing 125 cells + scoring |
| `max_input_time` | `120` | Large upload handling |

Restart all WampServer services after editing.

### 1.5 Virtual host (recommended)

Running from `http://localhost/nacsa/public` causes asset-path and session-cookie problems. Create a proper vhost instead.

**WampServer tray → Apache → httpd-vhosts.conf:**

```apache
<VirtualHost *:80>
    ServerName nacsa.test
    DocumentRoot "c:/wamp64/www/nacsa-toolkit/public"
    <Directory "c:/wamp64/www/nacsa-toolkit/public/">
        Options +Indexes +Includes +FollowSymLinks +MultiViews
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
```

Add to `C:\Windows\System32\drivers\etc\hosts` (edit as Administrator):

```
127.0.0.1  nacsa.test
```

Restart Apache. The app is then at `http://nacsa.test`.

> **Alternative:** skip Apache entirely during development and run `php artisan serve` (serves on `http://127.0.0.1:8000`). Simpler, but you should still get Apache working before your demo since that's the realistic deployment path.

---

## 2. Project Bootstrap

### 2.1 Create the project

```bash
cd C:\wamp64\www
composer create-project laravel/laravel nacsa-toolkit "11.*"
cd nacsa-toolkit
```

### 2.2 Install packages

```bash
composer require maatwebsite/excel:^3.1        # Excel parsing (PhpSpreadsheet wrapper)
composer require barryvdh/laravel-dompdf:^3.0  # PDF generation
composer require laravel/breeze:^2.0 --dev     # Auth scaffolding
php artisan breeze:install blade               # Choose: Blade with Alpine
```

Breeze's Blade stack ships Alpine.js and Tailwind. **You are using Bootstrap, not Tailwind** — after install, strip Tailwind from `resources/css/app.css` and the Vite config, and load Bootstrap + Alpine + Chart.js via CDN in your layout (§14.1). Keep Breeze's auth controllers and routes; you will extend them with OTP.

### 2.3 `.env` configuration

```ini
APP_NAME="NACSA Toolkit Baseline System"
APP_ENV=local
APP_KEY=                     # php artisan key:generate
APP_DEBUG=true
APP_TIMEZONE=Asia/Kuala_Lumpur
APP_URL=http://nacsa.test

DB_CONNECTION=mariadb        # Laravel 11+ dedicated driver — NOT "mysql"
DB_HOST=127.0.0.1
DB_PORT=3307                 # MariaDB port — VERIFY per §1.3, WampServer varies
DB_DATABASE=nacsa_toolkit
DB_USERNAME=root
DB_PASSWORD=                 # WampServer default root password is empty

QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=15          # minutes — SEC-04 / FR-05
CACHE_STORE=database

FILESYSTEM_DISK=local        # private disk — SEC-07

MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="noreply@nacsa-toolkit.test"
MAIL_FROM_NAME="${APP_NAME}"

# Application-specific
OTP_LENGTH=6
OTP_TTL_MINUTES=5
OTP_MAX_ATTEMPTS=5
PASSWORD_EXPIRY_DAYS=90
ASSESSMENT_MAX_SIZE_KB=10240
NCSB_TEMPLATE_VERSION=v1.4
REPORT_TOP_ACTIONS=10
```

> **Use the `mariadb` driver, not `mysql`.** Laravel 11 added a dedicated MariaDB connection. Pointing `DB_CONNECTION=mysql` at a MariaDB server still *works*, but Laravel then applies MySQL 8 version detection and will emit MySQL-only SQL for some schema operations. Confirm `config/database.php` contains a `mariadb` entry (Laravel 11 skeletons do; if yours predates it, copy the `mysql` block, rename the key to `mariadb`, and nothing else).

Pin the collation in `config/database.php` under the `mariadb` connection:

```php
'charset'   => 'utf8mb4',
'collation' => 'utf8mb4_unicode_ci',   // NOT utf8mb4_0900_ai_ci — MySQL-only
```

```bash
php artisan key:generate
php artisan session:table
php artisan queue:table
php artisan migrate
```

> **`APP_KEY` warning.** Encryption (SEC-05) is tied to this key. If you regenerate it after storing encrypted assessment data, **every encrypted field becomes unreadable**. Back up `.env` and never run `key:generate` twice on a database with real data.

---

## 3. Architecture

### 3.1 Three-layer structure (Table 4.4)

| Layer | Contents | Rule |
|---|---|---|
| **Presentation** | Blade views, Bootstrap components, Alpine behaviours, Chart.js renders | No business logic. No direct model queries in views. |
| **Business Logic** | Controllers (thin), Services (all logic), Jobs, Policies, Form Requests | No SQL. No `echo`/rendering. Services are framework-agnostic where practical. |
| **Data** | Eloquent models, migrations, seeders, encrypted casts | No business rules beyond relationships, casts, scopes. |

### 3.2 Request lifecycle — assessment upload

```
Browser (drag-drop .xlsx)
   │ POST /assessments  (multipart, CSRF)
   ▼
UploadAssessmentRequest          ← validates mime, size, cycle_label uniqueness
   ▼
AssessmentController@store       ← thin: calls service, redirects
   ▼
ExcelParserService::parse()      ← template validation → 125 booleans + unanswered flags
   │  (throws TemplateValidationException → redirect back with specific error)
   ▼
ScoringService::score()          ← element levels, domain scores, overall score + band
   ▼
[DB transaction]
   Assessment + 33 AssessmentElement rows persisted
   ▼
TrendService::computeTrend()     ← deltas vs previous cycle → AssessmentTrend
   ▼
TrendService::detectDegradation()← DegradationAlert rows for declining domains
   ▼
dispatch(SendDegradationAlertJob)  ← queued email
   ▼
AuditService::log('assessment.upload')
   ▼
redirect → /assessments/{id}  (results screen)
```

### 3.3 Module dependency graph

```
                    ┌──────────────────────────┐
                    │  M1 Assessment & Trend   │  ← the only writer of maturity data
                    │  ExcelParser, Scoring,   │
                    │  Trend                   │
                    └────────────┬─────────────┘
                                 │ reads
         ┌───────────────┬───────┴───────┬────────────────┐
         ▼               ▼               ▼                ▼
   ┌───────────┐  ┌────────────┐  ┌────────────┐  ┌─────────────┐
   │ M2 Report │  │ M4 Heatmap │  │ M5 Simulator│  │ M6 Dashboards│
   └───────────┘  └────────────┘  └─────┬──────┘  └─────────────┘
                                        │ writes only
                                        ▼
                                 SimulationSession
                                 (never touches Assessment)

   ┌──────────────────┐
   │ M3 Alert Service │  ← independent; reads Assessment only to mark a deadline "met"
   └──────────────────┘
```

**Enforced rule:** M2, M4, M5, M6 are **read-only** consumers of M1's data. Only `ScoringService` writes to `assessments` / `assessment_elements`. This is what makes FR-63 (non-destructive simulation) testable.

---

## 4. Enums

`app/Enums/Domain.php`

```php
<?php

namespace App\Enums;

enum Domain: string
{
    case GOVERN   = 'GOVERN';
    case IDENTIFY = 'IDENTIFY';
    case PROTECT  = 'PROTECT';
    case DETECT   = 'DETECT';
    case RESPOND  = 'RESPOND';
    case RECOVER  = 'RECOVER';

    /** Element count per domain — Table 4.2, sums to 33. */
    public function elementCount(): int
    {
        return match ($this) {
            self::GOVERN => 7,
            self::IDENTIFY, self::DETECT, self::RECOVER => 2,
            self::PROTECT => 19,
            self::RESPOND => 1,
        };
    }

    /** Tie-break weight for remediation ranking (§7.5 PRD). Author design decision. */
    public function riskWeight(): int
    {
        return match ($this) {
            self::GOVERN, self::RESPOND => 2,
            default => 1,
        };
    }

    public function elementCodePrefix(): string
    {
        return match ($this) {
            self::GOVERN => 'GV',
            self::IDENTIFY => 'ID',
            self::PROTECT => 'PR',
            self::DETECT => 'DE',
            self::RESPOND => 'RS',
            self::RECOVER => 'RC',
        };
    }

    public function dbColumn(): string
    {
        return strtolower($this->value) . '_score';
    }
}
```

`app/Enums/MaturityLevel.php`

```php
<?php

namespace App\Enums;

enum MaturityLevel: int
{
    case INITIAL      = 0;
    case BASIC        = 1;
    case INTERMEDIATE = 2;
    case ADVANCED     = 3;

    public const TARGET = self::ADVANCED;

    public function label(): string
    {
        return match ($this) {
            self::INITIAL => 'Initial',
            self::BASIC => 'Basic',
            self::INTERMEDIATE => 'Intermediate',
            self::ADVANCED => 'Advanced',
        };
    }

    /** Gap from the Level 3 target — drives heatmap severity. */
    public function gapFromTarget(): int
    {
        return self::TARGET->value - $this->value;
    }

    public function severity(): Severity
    {
        return match ($this->gapFromTarget()) {
            0 => Severity::COMPLIANT,
            1 => Severity::LOW,
            2 => Severity::HIGH,
            default => Severity::CRITICAL,
        };
    }
}
```

`app/Enums/Severity.php`

```php
<?php

namespace App\Enums;

enum Severity: string
{
    case COMPLIANT  = 'compliant';
    case LOW        = 'low';
    case HIGH       = 'high';
    case CRITICAL   = 'critical';
    case UNANSWERED = 'unanswered';

    public function label(): string
    {
        return match ($this) {
            self::COMPLIANT => 'Compliant',
            self::LOW => 'Low Risk',
            self::HIGH => 'High Risk',
            self::CRITICAL => 'Critical Risk',
            self::UNANSWERED => 'Unanswered',
        };
    }

    /** Bootstrap-compatible hex values. Colour is never the only signal (FR-54). */
    public function colour(): string
    {
        return match ($this) {
            self::COMPLIANT => '#198754',
            self::LOW => '#ffc107',
            self::HIGH => '#fd7e14',
            self::CRITICAL => '#dc3545',
            self::UNANSWERED => '#adb5bd',
        };
    }
}
```

`app/Enums/Band.php`

```php
<?php

namespace App\Enums;

enum Band: string
{
    case INITIAL      = 'Initial';
    case BASIC        = 'Basic';
    case INTERMEDIATE = 'Intermediate';
    case ADVANCED     = 'Advanced';

    /** §7.1 PRD — maps overall score (0.00–1.00) to a band. */
    public static function fromScore(float $score): self
    {
        return match (true) {
            $score >= 0.75 => self::ADVANCED,
            $score >= 0.50 => self::INTERMEDIATE,
            $score >= 0.25 => self::BASIC,
            default => self::INITIAL,
        };
    }
}
```

`app/Enums/Role.php`

```php
<?php

namespace App\Enums;

enum Role: string
{
    case ADMIN              = 'admin';
    case COMPLIANCE_OFFICER = 'compliance_officer';
    case EXECUTIVE_REVIEWER = 'executive_reviewer';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::COMPLIANCE_OFFICER => 'Compliance Officer',
            self::EXECUTIVE_REVIEWER => 'Executive Reviewer',
        };
    }

    public function dashboardRoute(): string
    {
        return match ($this) {
            self::ADMIN => 'dashboard.admin',
            self::COMPLIANCE_OFFICER => 'dashboard.officer',
            self::EXECUTIVE_REVIEWER => 'dashboard.executive',
        };
    }
}
```

Also create: `RiskCategory` (Critical/High/Moderate/Acceptable), `DeadlineStatus` (upcoming/due_soon/overdue/met), `NotificationType` (reminder/escalation), `AuditCategory` (technical/compliance).

---

## 5. Database Migrations

Generate in this order — foreign keys depend on it.

```bash
php artisan make:migration create_ncsb_elements_table
php artisan make:migration add_fields_to_users_table
php artisan make:migration create_assessments_table
php artisan make:migration create_assessment_elements_table
php artisan make:migration create_assessment_trends_table
php artisan make:migration create_degradation_alerts_table
php artisan make:migration create_compliance_deadlines_table
php artisan make:migration create_deadline_notifications_table
php artisan make:migration create_simulation_sessions_table
php artisan make:migration create_executive_reports_table
php artisan make:migration create_audit_logs_table
```

### 5.1 `ncsb_elements`

```php
Schema::create('ncsb_elements', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10)->unique();          // GV-01, PR-14, …
    $table->enum('domain', ['GOVERN','IDENTIFY','PROTECT','DETECT','RESPOND','RECOVER']);
    $table->string('category');
    $table->string('name');
    $table->unsignedTinyInteger('question_count'); // sums to 125 across all rows
    $table->unsignedTinyInteger('threshold_l1');
    $table->unsignedTinyInteger('threshold_l2');
    $table->unsignedTinyInteger('threshold_l3');
    $table->text('requirement_l1');
    $table->text('requirement_l2');
    $table->text('requirement_l3');
    $table->unsignedTinyInteger('domain_risk_weight')->default(1);
    $table->unsignedSmallInteger('sort_order');
    $table->timestamps();

    $table->index(['domain', 'sort_order']);
});
```

### 5.2 `users` (additions to Breeze's table)

```php
Schema::table('users', function (Blueprint $table) {
    $table->enum('role', ['admin','compliance_officer','executive_reviewer'])
          ->default('compliance_officer')->after('password');
    $table->boolean('is_active')->default(true)->after('role');
    $table->timestamp('password_changed_at')->nullable()->after('is_active');
    $table->string('otp_code')->nullable();          // hashed, never plaintext
    $table->timestamp('otp_expires_at')->nullable();
    $table->unsignedTinyInteger('otp_attempts')->default(0);
    $table->timestamp('mfa_verified_at')->nullable();
});
```

### 5.3 `assessments`

```php
Schema::create('assessments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
    $table->date('assessment_date');
    $table->string('cycle_label', 50)->unique();
    $table->string('original_filename');
    $table->string('stored_path');
    $table->char('file_hash', 64)->index();          // SHA-256 duplicate detection
    $table->enum('validation_status', ['validated','rejected'])->default('validated');

    $table->decimal('overall_score', 4, 3);          // 0.000–1.000
    $table->enum('overall_band', ['Initial','Basic','Intermediate','Advanced']);

    foreach (['govern','identify','protect','detect','respond','recover'] as $d) {
        $table->decimal("{$d}_score", 3, 2)->nullable();   // 0.00–3.00
    }

    $table->unsignedTinyInteger('unanswered_count')->default(0);
    $table->unsignedSmallInteger('defaulted_cell_count')->default(0);
    $table->timestamps();

    $table->index('assessment_date');
});
```

### 5.4 `assessment_elements`

```php
Schema::create('assessment_elements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('ncsb_element_id')->constrained()->restrictOnDelete();
    $table->enum('domain', ['GOVERN','IDENTIFY','PROTECT','DETECT','RESPOND','RECOVER']);
    $table->string('element_code', 10);              // denormalised for fast heatmap query
    $table->unsignedTinyInteger('yes_count')->default(0);
    $table->unsignedTinyInteger('maturity_level')->nullable();   // null = unanswered
    $table->enum('status', ['scored','unanswered'])->default('scored');
    $table->text('responses');                       // ENCRYPTED json array of booleans
    $table->timestamps();

    $table->unique(['assessment_id', 'element_code']);
    $table->index(['assessment_id', 'domain']);
});
```

> `responses` is `text`, not `json` — Laravel's `encrypted:array` cast stores ciphertext, which is not valid JSON and will be rejected by a `json` column.

### 5.5 `assessment_trends`

```php
Schema::create('assessment_trends', function (Blueprint $table) {
    $table->id();
    $table->foreignId('assessment_id')->unique()->constrained()->cascadeOnDelete();
    $table->foreignId('prev_assessment_id')->constrained('assessments')->cascadeOnDelete();

    foreach (['govern','identify','protect','detect','respond','recover'] as $d) {
        $table->decimal("{$d}_delta", 4, 2)->nullable();   // signed
    }
    $table->decimal('overall_delta', 4, 3);                // signed
    $table->timestamps();
});
```

### 5.6 `degradation_alerts`

```php
Schema::create('degradation_alerts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('assessment_trend_id')->constrained()->cascadeOnDelete();
    $table->enum('domain', ['GOVERN','IDENTIFY','PROTECT','DETECT','RESPOND','RECOVER']);
    $table->decimal('score_drop', 4, 2);              // positive magnitude
    $table->boolean('acknowledged')->default(false);
    $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('acknowledged_at')->nullable();
    $table->timestamp('triggered_at');
    $table->timestamps();

    $table->index(['acknowledged', 'triggered_at']);
});
```

### 5.7 `compliance_deadlines`

```php
Schema::create('compliance_deadlines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('officer_id')->constrained('users')->restrictOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->date('due_date');
    $table->json('lead_days')->nullable();       // MariaDB: no expression default — set in the model
    $table->foreignId('secondary_contact_id')->nullable()->constrained('users')->nullOnDelete();
    $table->enum('status', ['upcoming','due_soon','overdue','met'])->default('upcoming');
    $table->timestamp('acknowledged_at')->nullable();
    $table->foreignId('met_by_assessment_id')->nullable()->constrained('assessments')->nullOnDelete();
    $table->timestamps();

    $table->index(['status', 'due_date']);
});
```

> **MariaDB: no SQL-level default on a JSON column.** `->default(new Expression("(JSON_ARRAY(30,14,7))"))` works on MySQL 8 and fails on MariaDB, because its `JSON` is a `LONGTEXT` alias and `LONGTEXT` cannot take a default. Set the default in the model instead:

```php
// app/Models/ComplianceDeadline.php
protected $attributes = [
    'lead_days' => '[30,14,7]',    // raw JSON string — the cast decodes it
];

protected function casts(): array
{
    return ['lead_days' => 'array', 'due_date' => 'date'];
}
```

> Also note: **no index may be placed on `lead_days`.** MariaDB cannot index a `LONGTEXT` column without a prefix length or a generated column. The schema above places none — keep it that way.

### 5.8 `deadline_notifications`

```php
Schema::create('deadline_notifications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('compliance_deadline_id')->constrained()->cascadeOnDelete();
    $table->enum('type', ['reminder','escalation']);
    $table->unsignedSmallInteger('lead_day')->nullable();   // null for escalation
    $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
    $table->dateTime('dispatched_at');
    $table->boolean('delivered')->default(false);
    $table->text('failure_reason')->nullable();
    $table->timestamps();

    // Guarantees FR-41 exactly-once dispatch per interval.
    $table->unique(['compliance_deadline_id','type','lead_day'], 'deadline_dispatch_unique');
});
```

### 5.9 `simulation_sessions`, `executive_reports`, `audit_logs`

```php
Schema::create('simulation_sessions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->foreignId('baseline_assessment_id')->constrained('assessments')->cascadeOnDelete();
    $table->string('scenario_name');
    $table->text('modified_responses');              // ENCRYPTED {element_code: level}
    $table->decimal('simulated_score', 4, 3);
    $table->enum('simulated_band', ['Initial','Basic','Intermediate','Advanced']);
    $table->timestamps();
});

Schema::create('executive_reports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
    $table->string('file_path');
    $table->text('payload');                         // ENCRYPTED narratives + actions
    $table->timestamp('generated_at');
    $table->timestamps();
});

Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('action', 100);                   // login.success, assessment.upload, …
    $table->nullableMorphs('auditable');
    $table->enum('category', ['technical','compliance']);
    $table->string('ip_address', 45)->nullable();
    $table->string('user_agent')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamp('created_at');

    $table->index(['category', 'created_at']);
    $table->index(['action', 'created_at']);
});
```

---

## 6. Models

### 6.1 Casts that matter

```php
// app/Models/AssessmentElement.php
protected function casts(): array
{
    return [
        'responses'      => 'encrypted:array',    // SEC-05
        'maturity_level' => MaturityLevel::class, // null stays null
        'domain'         => Domain::class,
        'status'         => ElementStatus::class,
    ];
}

// app/Models/Assessment.php
protected function casts(): array
{
    return [
        'assessment_date' => 'date',
        'overall_band'    => Band::class,
        'overall_score'   => 'decimal:3',
    ];
}

// app/Models/SimulationSession.php
protected function casts(): array
{
    return ['modified_responses' => 'encrypted:array'];
}

// app/Models/ExecutiveReport.php
protected function casts(): array
{
    return ['payload' => 'encrypted:array', 'generated_at' => 'datetime'];
}
```

### 6.2 Key relationships

```php
// Assessment
public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }
public function elements(): HasMany    { return $this->hasMany(AssessmentElement::class); }
public function trend(): HasOne        { return $this->hasOne(AssessmentTrend::class); }
public function reports(): HasMany     { return $this->hasMany(ExecutiveReport::class); }

/** The cycle immediately before this one. */
public function previous(): ?Assessment
{
    return static::where('assessment_date', '<', $this->assessment_date)
        ->orderByDesc('assessment_date')
        ->orderByDesc('id')
        ->first();
}

public function scopeChronological($q) { return $q->orderBy('assessment_date'); }

// AssessmentTrend
public function alerts(): HasMany { return $this->hasMany(DegradationAlert::class); }

// ComplianceDeadline
public function notifications(): HasMany { return $this->hasMany(DeadlineNotification::class); }
public function officer(): BelongsTo     { return $this->belongsTo(User::class, 'officer_id'); }
public function secondaryContact(): BelongsTo
{ return $this->belongsTo(User::class, 'secondary_contact_id'); }
```

---

## 7. Service Layer

### 7.1 Data transfer objects

`app/DataObjects/` — plain readonly classes, no package needed.

```php
final readonly class ParsedAssessment
{
    public function __construct(
        /** @var array<string, bool[]>  element_code => list of boolean responses */
        public array $responsesByElement,
        /** @var string[] element codes with no answered questions */
        public array $unansweredCodes,
        public int $defaultedCellCount,
        /** @var string[] non-fatal parse warnings */
        public array $warnings,
    ) {}
}

final readonly class ScoredElement
{
    public function __construct(
        public string $code,
        public Domain $domain,
        public int $yesCount,
        public ?MaturityLevel $level,   // null = unanswered
        public ElementStatus $status,
        public array $responses,
    ) {}
}

final readonly class ScoredAssessment
{
    public function __construct(
        /** @var ScoredElement[] keyed by element code */
        public array $elements,
        /** @var array<string, ?float> domain value => 0.00–3.00 */
        public array $domainScores,
        public float $overallScore,     // 0.000–1.000
        public Band $overallBand,
        public int $unansweredCount,
    ) {}
}

final readonly class SimulationResult
{
    public function __construct(
        public array $projectedDomainScores,   // domain => float
        public array $domainDeltas,            // domain => signed float
        public float $projectedOverallScore,
        public Band $projectedBand,
        public float $overallDelta,
        public int $elementsAtTarget,          // count at level 3
    ) {}
}
```

### 7.2 `ExcelParserService`

```php
final class ExcelParserService
{
    /**
     * @throws TemplateValidationException  Nothing is persisted when this throws.
     */
    public function parse(UploadedFile $file): ParsedAssessment
    {
        $sheets = $this->load($file);

        $this->assertTemplateVersion($sheets);   // cell marker == NCSB_TEMPLATE_VERSION
        $this->assertSheetsPresent($sheets);     // all expected sheet names exist
        $this->assertElementCodes($sheets);      // 33 codes match the seeded set, in order

        $responsesByElement = [];
        $unansweredCodes    = [];
        $defaulted          = 0;

        foreach (NcsbElement::orderBy('sort_order')->get() as $element) {
            $cells   = $this->cellsFor($element, $sheets);   // Appendix A mapping
            $answers = [];
            $blank   = 0;

            foreach ($cells as $raw) {
                $normalised = $this->normalise($raw);        // returns bool
                if ($this->isBlank($raw)) { $blank++; $defaulted++; }
                $answers[] = $normalised;
            }

            $responsesByElement[$element->code] = $answers;

            // FR-13: every question blank → unanswered, excluded from the band
            if ($blank === count($cells)) {
                $unansweredCodes[] = $element->code;
            }
        }

        return new ParsedAssessment($responsesByElement, $unansweredCodes, $defaulted, []);
    }

    /** FR-12: anything not clearly affirmative is No. */
    private function normalise(mixed $raw): bool
    {
        $v = strtoupper(trim((string) $raw));
        return in_array($v, ['YES','Y','TRUE','1','✓'], true);
    }

    private function isBlank(mixed $raw): bool
    {
        return trim((string) $raw) === '';
    }
}
```

> **`cellsFor()` is the Appendix A blocker.** It must return the exact cell values belonging to an element. Until the real workbook is mapped, implement it against a fixture and keep the mapping in `config/ncsb.php`, not hardcoded in the service.

**Suggested `config/ncsb.php`:**

```php
return [
    'template_version' => env('NCSB_TEMPLATE_VERSION', 'v1.4'),
    'version_cell'     => ['sheet' => 'Cover', 'cell' => 'C4'],   // TBD Appendix A
    'sheets'           => ['GOVERN','IDENTIFY','PROTECT','DETECT','RESPOND','RECOVER'],
    'element_map'      => [
        // 'GV-01' => ['sheet' => 'GOVERN', 'cells' => ['D5','D6','D7']],   ← TBD
    ],
];
```

### 7.3 `ScoringService`

```php
final class ScoringService
{
    public function score(ParsedAssessment $parsed): ScoredAssessment
    {
        $elements = [];

        foreach (NcsbElement::orderBy('sort_order')->get() as $ref) {
            $responses = $parsed->responsesByElement[$ref->code];
            $yes       = count(array_filter($responses));
            $unanswered = in_array($ref->code, $parsed->unansweredCodes, true);

            $level = $unanswered ? null : $this->levelFor($yes, $ref);

            $elements[$ref->code] = new ScoredElement(
                code: $ref->code,
                domain: $ref->domain,
                yesCount: $yes,
                level: $level,
                status: $unanswered ? ElementStatus::UNANSWERED : ElementStatus::SCORED,
                responses: $responses,
            );
        }

        return new ScoredAssessment(
            elements: $elements,
            domainScores: $this->domainScores($elements),
            overallScore: $this->overallScore($elements),
            overallBand: Band::fromScore($this->overallScore($elements)),
            unansweredCount: count($parsed->unansweredCodes),
        );
    }

    private function levelFor(int $yes, NcsbElement $ref): MaturityLevel
    {
        return match (true) {
            $yes >= $ref->threshold_l3 => MaturityLevel::ADVANCED,
            $yes >= $ref->threshold_l2 => MaturityLevel::INTERMEDIATE,
            $yes >= $ref->threshold_l1 => MaturityLevel::BASIC,
            default => MaturityLevel::INITIAL,
        };
    }

    /** Domain score = mean element level within the domain. 0.00–3.00. */
    private function domainScores(array $elements): array
    {
        $scores = [];
        foreach (Domain::cases() as $domain) {
            $levels = collect($elements)
                ->filter(fn ($e) => $e->domain === $domain && $e->level !== null)
                ->map(fn ($e) => $e->level->value);

            $scores[$domain->value] = $levels->isEmpty()
                ? null
                : round($levels->avg(), 2);
        }
        return $scores;
    }

    /** Overall = share of the 33 elements with at least one control implemented. 0.000–1.000. */
    private function overallScore(array $elements): float
    {
        $implemented = collect($elements)
            ->filter(fn ($e) => $e->status === ElementStatus::SCORED && $e->yesCount >= 1)
            ->count();

        return round($implemented / 33, 3);
    }
}
```

> **Do not conflate the two measures.** `overallScore` is a 0–1 coverage ratio; domain scores are 0–3 averages. Label both explicitly in every view (PRD R7).

### 7.4 `TrendService`

```php
final class TrendService
{
    public function computeTrend(Assessment $current): ?AssessmentTrend
    {
        $prev = $current->previous();
        if (! $prev) return null;

        $deltas = [];
        foreach (Domain::cases() as $domain) {
            $col = $domain->dbColumn();
            $deltas["{$col}_delta"] = ($current->$col !== null && $prev->$col !== null)
                ? round($current->$col - $prev->$col, 2)
                : null;
        }

        return AssessmentTrend::create([
            'assessment_id'      => $current->id,
            'prev_assessment_id' => $prev->id,
            'overall_delta'      => round($current->overall_score - $prev->overall_score, 3),
            ...$deltas,
        ]);
    }

    /** @return DegradationAlert[] */
    public function detectDegradation(AssessmentTrend $trend): array
    {
        $alerts = [];
        foreach (Domain::cases() as $domain) {
            $delta = $trend->{$domain->dbColumn() . '_delta'};
            if ($delta === null || $delta >= 0) continue;

            $alerts[] = DegradationAlert::create([
                'assessment_trend_id' => $trend->id,
                'domain'              => $domain,
                'score_drop'          => abs($delta),
                'triggered_at'        => now(),
            ]);
        }
        return $alerts;
    }
}
```

### 7.5 `SimulationService`

```php
final class SimulationService
{
    /**
     * Pure computation. Touches no persistent assessment state.
     * @param array<string,int> $overrides  element_code => hypothetical level 0..3
     */
    public function project(Assessment $baseline, array $overrides): SimulationResult
    {
        $working = $baseline->elements
            ->mapWithKeys(fn ($e) => [$e->element_code => $e->maturity_level?->value ?? 0])
            ->toArray();

        foreach ($overrides as $code => $level) {
            if (! isset($working[$code])) continue;              // ignore unknown codes
            $working[$code] = max(0, min(3, (int) $level));      // clamp
        }

        $projected = $this->domainAverages($working);
        $deltas    = [];
        foreach (Domain::cases() as $d) {
            $base = $baseline->{$d->dbColumn()};
            $deltas[$d->value] = $base === null ? null
                : round($projected[$d->value] - $base, 2);
        }

        $implemented  = count(array_filter($working, fn ($l) => $l >= 1));
        $overallScore = round($implemented / 33, 3);

        return new SimulationResult(
            projectedDomainScores: $projected,
            domainDeltas: $deltas,
            projectedOverallScore: $overallScore,
            projectedBand: Band::fromScore($overallScore),
            overallDelta: round($overallScore - (float) $baseline->overall_score, 3),
            elementsAtTarget: count(array_filter($working, fn ($l) => $l === 3)),
        );
    }
}
```

### 7.6 `ReportService` — remediation ranking

```php
/** @return array<int, array{code:string, level:int, gap:int, action:string}> */
public function prioritise(Assessment $assessment, int $limit = 10): array
{
    return $assessment->elements
        ->filter(fn ($e) => $e->maturity_level !== null
                         && $e->maturity_level !== MaturityLevel::ADVANCED)
        ->sortBy([
            fn ($a, $b) => $b->maturity_level->gapFromTarget()      // 1. gap DESC
                        <=> $a->maturity_level->gapFromTarget(),
            fn ($a, $b) => $b->domain->riskWeight()                 // 2. GOVERN/RESPOND first
                        <=> $a->domain->riskWeight(),
            fn ($a, $b) => strcmp($a->element_code, $b->element_code), // 3. deterministic
        ])
        ->take($limit)
        ->map(fn ($e) => [
            'code'   => $e->element_code,
            'level'  => $e->maturity_level->value,
            'gap'    => $e->maturity_level->gapFromTarget(),
            'action' => $e->ncsbElement->{'requirement_l' . ($e->maturity_level->value + 1)},
        ])
        ->values()
        ->all();
}
```

---

## 8. Jobs and Scheduled Tasks

| Class | Trigger | Queue | Retry |
|---|---|---|---|
| `SendDegradationAlertJob` | After degradation detected (FR-19) | `default` | 3× backoff 60s |
| `DispatchDeadlineRemindersJob` | Daily scheduler sweep (FR-41) | `default` | 3× |
| `EscalateOverdueDeadlinesJob` | Daily scheduler sweep (FR-42) | `default` | 3× |
| `GenerateExecutiveReportJob` | On-demand if generation exceeds 5s (FR-35) | `reports` | 2× |

### 8.1 Scheduler registration

Laravel 11 — `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DispatchDeadlineRemindersJob)->dailyAt('08:00')
    ->name('deadline-reminders')->withoutOverlapping();

Schedule::job(new EscalateOverdueDeadlinesJob)->dailyAt('08:15')
    ->name('deadline-escalations')->withoutOverlapping();

Schedule::command('deadlines:refresh-status')->hourly();   // recompute status column
```

### 8.2 Exactly-once reminder logic (FR-41)

```php
foreach (ComplianceDeadline::whereIn('status', ['upcoming','due_soon'])->get() as $deadline) {
    $daysRemaining = now()->startOfDay()->diffInDays($deadline->due_date, false);

    foreach ($deadline->lead_days as $lead) {
        if ($daysRemaining !== $lead) continue;

        // The unique index (deadline, type, lead_day) is the real guarantee —
        // firstOrCreate alone races under concurrency.
        try {
            $notification = DeadlineNotification::create([
                'compliance_deadline_id' => $deadline->id,
                'type'                   => NotificationType::REMINDER,
                'lead_day'               => $lead,
                'recipient_id'           => $deadline->officer_id,
                'dispatched_at'          => now(),
                'delivered'              => false,
            ]);
        } catch (UniqueConstraintViolationException) {
            continue;   // already sent for this interval
        }

        Mail::to($deadline->officer)->send(new DeadlineReminderMail($deadline, $lead));
        $notification->update(['delivered' => true]);
    }
}
```

---

## 9. Authentication and MFA Flow

```
POST /login  (email, password)
   │
   ├─ user inactive?        → 403, audit login.blocked
   ├─ credentials invalid?  → back with error, audit login.failed, throttle
   │
   ├─ password older than PASSWORD_EXPIRY_DAYS?
   │      → session('force_password_change'), redirect /password/change
   │
   └─ valid
        │ generate 6-digit OTP, store Hash::make($otp), expires_at = now()+5min
        │ reset otp_attempts = 0
        │ Mail::to(user)->send(new OtpMail($otp))
        │ session(['mfa_pending_user_id' => $user->id])
        │ DO NOT call Auth::login() yet
        ▼
   redirect /otp

POST /otp  (code)
   │
   ├─ no mfa_pending_user_id in session? → redirect /login
   ├─ otp_expires_at passed?             → error "expired", offer resend
   ├─ otp_attempts >= OTP_MAX_ATTEMPTS?  → lock, audit mfa.locked, force re-login
   ├─ Hash::check fails?                 → increment attempts, error
   │
   └─ valid
        │ Auth::login($user)
        │ session()->regenerate()          ← session fixation defence
        │ clear otp_code, otp_expires_at, otp_attempts
        │ mfa_verified_at = now()
        │ audit login.success
        ▼
   redirect → role dashboard
```

**`EnsureMfaVerified` middleware** applies to every authenticated route and rejects any session where `mfa_verified_at` is null or predates the session start. Register it in the `web` group after `auth`.

**Rate limiting** (SEC-10) — `routes/web.php`:

```php
Route::post('/login', [AuthController::class,'login'])->middleware('throttle:5,15');
Route::post('/otp',   [AuthController::class,'verifyOtp'])->middleware('throttle:5,15');
Route::post('/otp/resend', [AuthController::class,'resendOtp'])->middleware('throttle:3,15');
```

---

## 10. Authorisation

One policy per model. Register via Laravel 11's auto-discovery (`App\Policies\AssessmentPolicy` for `App\Models\Assessment`).

```php
final class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::COMPLIANCE_OFFICER, Role::EXECUTIVE_REVIEWER], true);
    }

    public function create(User $user): bool
    {
        return $user->role === Role::COMPLIANCE_OFFICER;   // FR-10: officer only
    }

    public function view(User $user, Assessment $a): bool
    {
        return $this->viewAny($user);
    }
}
```

**Route-level role gate** — `app/Http/Middleware/EnsureRole.php`:

```php
public function handle(Request $request, Closure $next, string ...$roles): Response
{
    if (! in_array($request->user()?->role->value, $roles, true)) {
        AuditService::log('access.denied', category: AuditCategory::TECHNICAL,
            metadata: ['route' => $request->route()?->getName()]);
        abort(403);
    }
    return $next($request);
}
```

Usage:

```php
Route::middleware(['auth','mfa','role:compliance_officer'])->group(function () {
    Route::get('/assessments/upload', [AssessmentController::class,'showUpload']);
    Route::post('/assessments',       [AssessmentController::class,'store']);
    Route::resource('deadlines',      DeadlineController::class);
});

Route::middleware(['auth','mfa','role:compliance_officer,executive_reviewer'])->group(function () {
    Route::get('/heatmap',   [HeatmapController::class,'index']);
    Route::get('/simulator', [SimulatorController::class,'index']);
    Route::get('/trends',    [TrendController::class,'index']);
});

Route::middleware(['auth','mfa','role:admin'])->prefix('admin')->group(function () {
    Route::resource('users', UserController::class);
    Route::get('/audit-logs', [AuditController::class,'technical']);
});
```

---

## 11. JSON Contracts (Alpine ↔ Laravel)

### 11.1 `POST /simulator/project`

**Request**

```json
{
  "baseline_assessment_id": 12,
  "overrides": { "PR-09": 3, "DE-01": 2, "RS-01": 1 }
}
```

**Response `200`**

```json
{
  "projected": {
    "domain_scores": {
      "GOVERN": 3.00, "IDENTIFY": 3.00, "PROTECT": 2.84,
      "DETECT": 2.00, "RESPOND": 1.00, "RECOVER": 1.00
    },
    "domain_deltas": {
      "GOVERN": 0.00, "IDENTIFY": 0.00, "PROTECT": 0.21,
      "DETECT": 1.00, "RESPOND": 1.00, "RECOVER": 0.00
    },
    "overall_score": 0.879,
    "overall_band": "Advanced",
    "overall_delta": 0.061,
    "elements_at_target": 27
  },
  "baseline": {
    "overall_score": 0.818,
    "overall_band": "Advanced"
  }
}
```

**Errors:** `422` invalid override level (outside 0–3) or unknown element code; `403` role not permitted; `404` baseline assessment not found.

### 11.2 `GET /heatmap/element/{code}?cycle={assessment_id}`

```json
{
  "code": "PR-09",
  "name": "Privileged Access Management",
  "domain": "PROTECT",
  "category": "Identity Management and Access Control",
  "maturity_level": 1,
  "level_label": "Basic",
  "gap_from_target": 2,
  "severity": "high",
  "severity_label": "High Risk",
  "yes_count": 2,
  "question_count": 5,
  "remediation_note": "Implement time-bound privileged session approval and quarterly access recertification to reach Level 2."
}
```

### 11.3 `GET /trends` — Chart.js payload

Embedded server-side in the Blade view as a JSON script tag (no extra request):

```json
{
  "labels": ["Cycle 2024 H2","Cycle 2025 H1","Cycle 2025 H2","Cycle 2026 H1"],
  "datasets": [
    { "label": "GOVERN",  "data": [2.1, 2.4, 2.6, 2.9], "declining": false },
    { "label": "PROTECT", "data": [2.6, 2.5, 2.4, 2.2], "declining": true }
  ],
  "overall": [0.70, 0.76, 0.79, 0.82]
}
```

> `declining` drives FR-21: declining series render in the alert colour with a warning marker.

---

## 12. Local Operation on WampServer

### 12.1 Processes you must run

Local development needs **three terminals** open simultaneously. This is the main friction of a local-only deployment — plan your demo around it.

| Terminal | Command | Purpose |
|---|---|---|
| 1 | Apache via WampServer tray (or `php artisan serve`) | Serves the app |
| 2 | `php artisan queue:work --tries=3` | Processes emails and PDF jobs |
| 3 | `php artisan schedule:work` | Runs the daily deadline sweep |

Without terminal 2, **no email ever sends** — the job sits in the `jobs` table. Without terminal 3, deadline reminders never fire. Both are silent failures, so check them first when something "doesn't work".

### 12.2 Demo-day simplification

For a live demo where you cannot keep workers running, set `QUEUE_CONNECTION=sync` in `.env`. Jobs then execute inline during the request — slower responses, but nothing is silently queued. **Switch back to `database` for your UAT and performance tests**, since NFR-01 (5-second response) assumes queued background work.

### 12.3 Making the scheduler survive a closed terminal (optional)

Windows Task Scheduler entry:

| Field | Value |
|---|---|
| Trigger | Daily, repeat every 1 minute, indefinitely |
| Action | Start a program |
| Program | `C:\wamp64\bin\php\php8.2.x\php.exe` |
| Arguments | `artisan schedule:run` |
| Start in | `C:\wamp64\www\nacsa-toolkit` |

This mirrors what a real cron does and is worth setting up before UAT so you can demonstrate FR-41 without hand-waving.

### 12.4 Forcing a deadline reminder for demonstration

Do not wait 30 days. Add a dev-only command:

```php
// php artisan deadlines:simulate-sweep --days=14
Artisan::command('deadlines:simulate-sweep {--days=}', function () {
    $this->info('Running sweep as if today were ' . now()->addDays(...)->toDateString());
    // travel time, run DispatchDeadlineRemindersJob, travel back
})->purpose('Dev only: fire the deadline sweep at an arbitrary offset');
```

Guard it with `if (! app()->environment('local')) return;`.

### 12.5 Mail configuration

| Stage | Driver | Setup |
|---|---|---|
| Development | Mailtrap | Free account → SMTP credentials into `.env`. Emails appear in the Mailtrap inbox, nothing reaches real people. |
| Screenshot/thesis evidence | Mailtrap | The inbox view is a clean screenshot for your Chapter 5 evidence |
| UAT with MAHB | Gmail SMTP | Requires a Google App Password (not your account password), `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls` |
| Emergency fallback | `MAIL_MAILER=log` | Emails written to `storage/logs/laravel.log`. Proves the dispatch logic without a mail server. |

### 12.6 Local-only limitations to declare in your thesis

| Limitation | Affected requirement | How to present it |
|---|---|---|
| No HTTPS | SEC-08 | "Transport encryption is a deployment-stage control; the application enforces it via `URL::forceScheme('https')` and HSTS headers when `APP_ENV=production`." |
| No real uptime | NFR-04 (99% availability) | Not measurable locally — state as out of scope for the prototype evaluation |
| Single machine, no load balancing | NFR-03 (concurrent users) | Test with 20 concurrent sessions via a simple script; report response times, note that horizontal scaling is future work |
| Scheduler depends on a running process | FR-41 | Mitigated by the Task Scheduler entry in §12.3 — demonstrate that, not the manual terminal |

---

## 13. Front-End Implementation

### 13.1 Base layout

`resources/views/layouts/app.blade.php`

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'NACSA Toolkit Baseline System')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-light">
    @include('partials.navbar')
    <main class="container-fluid py-4">
        @include('partials.flash')
        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    @stack('scripts')
</body>
</html>
```

> **CDN vs bundled.** CDN is fine for local development and your demo. If your demo venue has no internet, download the three files into `public/vendor/` and reference them locally — do this *before* demo day, not on it.

### 13.2 Scenario simulator — Alpine component

```blade
<div x-data="simulator({
        baselineId: {{ $assessment->id }},
        elements: {{ Js::from($elements) }},
        projectUrl: '{{ route('simulator.project') }}'
     })">

    {{-- Projected posture panel --}}
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h4" x-text="projected.overall_band"></h2>
            <p class="text-muted mb-0">
                Overall score
                <strong x-text="projected.overall_score.toFixed(3)"></strong>
                <span class="badge"
                      :class="projected.overall_delta >= 0 ? 'bg-success' : 'bg-danger'"
                      x-text="(projected.overall_delta >= 0 ? '+' : '') + projected.overall_delta.toFixed(3) + ' vs baseline'">
                </span>
            </p>
            <small class="text-muted">
                Share of the 33 elements with at least one control implemented
            </small>
        </div>
    </div>

    {{-- Sliders --}}
    <template x-for="el in elements" :key="el.code">
        <div class="d-flex align-items-center gap-3 mb-2">
            <span class="badge" :style="`background:${el.colour}`" x-text="el.code"></span>
            <span class="flex-grow-1" x-text="el.name"></span>
            <input type="range" min="0" max="3" step="1" class="form-range" style="width:160px"
                   x-model.number="overrides[el.code]" @input.debounce.250ms="project()">
            <strong style="width:1.5rem" x-text="overrides[el.code]"></strong>
        </div>
    </template>

    <button class="btn btn-outline-secondary" @click="reset()">Reset</button>
    <button class="btn btn-primary" @click="save()">Save scenario</button>
</div>

@push('scripts')
<script>
function simulator({ baselineId, elements, projectUrl }) {
    return {
        elements,
        baseline: Object.fromEntries(elements.map(e => [e.code, e.level ?? 0])),
        overrides: Object.fromEntries(elements.map(e => [e.code, e.level ?? 0])),
        projected: { overall_score: 0, overall_band: '', overall_delta: 0, domain_scores: {} },

        init() { this.project(); },

        async project() {
            const res = await fetch(projectUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({
                    baseline_assessment_id: baselineId,
                    overrides: this.overrides,
                }),
            });
            if (!res.ok) return;                       // keep last good state
            this.projected = (await res.json()).projected;
        },

        reset() { this.overrides = { ...this.baseline }; this.project(); },
        // save() → POST /simulator/sessions
    };
}
</script>
@endpush
```

> **FR-62 (300 ms) note.** The 250 ms input debounce plus a local round trip keeps you inside budget. If the projection ever feels laggy, move the computation client-side and use the server only for saving — the algorithm in §7.5 is trivial to duplicate in JS. Keep the server endpoint authoritative for saved sessions either way.

### 13.3 Heatmap grid

```blade
@foreach($byDomain as $domain => $elements)
    <h3 class="h6 text-uppercase text-muted mt-4">{{ $domain }}</h3>
    <div class="d-flex flex-wrap gap-2">
        @foreach($elements as $el)
            <button type="button"
                    class="btn p-2 text-white text-center"
                    style="width:64px; background:{{ $el->severity->colour() }}"
                    @click="showDetail('{{ $el->element_code }}')"
                    aria-label="{{ $el->element_code }}, {{ $el->severity->label() }}">
                <div class="small">{{ $el->element_code }}</div>
                {{-- FR-54: numeric level always shown, never colour alone --}}
                <strong>{{ $el->maturity_level?->value ?? '—' }}</strong>
            </button>
        @endforeach
    </div>
@endforeach
```

---

## 14. Error Handling

| Failure | Handling | User sees |
|---|---|---|
| Template validation failure | `TemplateValidationException` caught in controller | Specific reason: *"Template version is v1.1, expected v1.4"* — never a generic "upload failed" |
| Corrupt / unreadable xlsx | PhpSpreadsheet exception → wrapped | *"The file could not be read. Confirm it is an unmodified .xlsx toolkit."* |
| Duplicate file hash | Checked before parse | *"This exact file was already uploaded as Cycle 2025 H2 on 14 Jan 2026."* |
| Missing `ncsb_elements` seed | Guard clause in `ScoringService` | *"Reference data not seeded. Run `php artisan db:seed --class=NcsbElementSeeder`."* (local only) |
| Mail dispatch failure | Job retries 3×, then marks `delivered = false` | Dashboard warning badge on the notification history |
| PDF generation timeout | Job queued, status polled | Progress indicator, then download link |
| Unauthorised route access | `EnsureRole` middleware | 403 page, entry written to `audit_logs` |

**Wrap the whole upload in a transaction** so a scoring failure never leaves a half-written assessment:

```php
DB::transaction(function () use ($scored, $file, $request) {
    $assessment = Assessment::create([...]);
    $assessment->elements()->createMany($this->rowsFrom($scored));
    $trend = $this->trends->computeTrend($assessment);
    if ($trend) {
        foreach ($this->trends->detectDegradation($trend) as $alert) {
            SendDegradationAlertJob::dispatch($alert)->afterCommit();
        }
    }
    return $assessment;
});
```

`->afterCommit()` matters: without it, a queued job can run before the transaction commits and find no record.

---

## 15. Testing Setup

### 15.1 Configuration

`phpunit.xml` — use a separate database, never your dev one:

```xml
<env name="DB_CONNECTION" value="mariadb"/>
<env name="DB_DATABASE" value="nacsa_toolkit_test"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="MAIL_MAILER" value="array"/>
```

```sql
CREATE DATABASE nacsa_toolkit_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

> **Test against real MariaDB, not SQLite.** SQLite in-memory is much faster, but it has no `ENUM` type (Laravel silently degrades it to `varchar`), different JSON semantics, and no `json_valid()` CHECK constraint. Every one of those differences hides a bug this schema can actually hit — the encrypted-column-in-a-JSON-field failure in §1.3.1 passes cleanly on SQLite and fails on your real database. Use the same engine you deploy on.

### 15.2 Priority test list

| Test | Asserts | PRD ref |
|---|---|---|
| `ScoringTest::element_level_from_yes_count` | Each threshold boundary maps to the right level | FR-14 |
| `ScoringTest::blank_responses_default_to_no` | Blank cells counted as No, `defaulted_cell_count` correct | FR-12 |
| `ScoringTest::fully_blank_element_is_unanswered` | Level null, status unanswered, excluded from overall | FR-13 |
| `ScoringTest::overall_score_is_coverage_ratio` | 0–1 ratio, not a 0–3 average | §7.3 |
| `TrendTest::detects_decline_per_domain` | Negative delta → alert; zero/positive → none | FR-19 |
| `SimulationTest::never_mutates_assessment` | DB snapshot identical before/after projecting and saving | **FR-63** |
| `ReportTest::ranking_respects_govern_tiebreak` | Equal gaps ordered with GOVERN/RESPOND first | §7.5 |
| `DeadlineTest::reminder_fires_exactly_once` | Second sweep on the same day creates no duplicate | **FR-41** |
| `DeadlineTest::escalates_to_secondary_contact` | Unacknowledged overdue → escalation mail | FR-42 |
| `AuthTest::routes_unreachable_before_otp` | Every authed route redirects pre-OTP | **SEC-02** |
| `PolicyTest::role_matrix` | Data provider over every role × route from §4.2 PRD | SEC-03 |
| `EncryptionTest::responses_are_ciphertext` | Raw DB value is not readable JSON | SEC-05 |
| `UploadTest::rejects_renamed_php_file` | `.php` renamed to `.xlsx` is rejected | SEC-07 |

### 15.3 Fixtures

Store two real NCSB v1.4 files in `tests/Fixtures/` — one complete, one with a fully blank element. **Do not commit real organisational assessment data**; use a sanitised or self-authored file.

---

## 16. Build Order

Follow this sequence — each step is testable before the next begins.

| # | Step | Done when |
|---|---|---|
| 1 | Environment per §1, project bootstrap per §2 | `php artisan --version` runs, app loads at `nacsa.test`, **`php artisan db:show` reports MariaDB** |
| 2 | Enums, migrations, models | `php artisan migrate:fresh` succeeds against MariaDB with no CHECK-constraint or default errors |
| 3 | **`NcsbElementSeeder` from the real xlsx** | 33 rows, `question_count` sums to 125 |
| 4 | Auth + OTP + RBAC + audit | All three roles log in; policy tests pass |
| 5 | `ExcelParserService` against a fixture | Real file parses to 125 booleans |
| 6 | `ScoringService` | Scoring unit tests pass |
| 7 | Upload controller + results screen | End-to-end upload works |
| 8 | `TrendService` + degradation alerts | Second cycle produces deltas and an alert |
| 9 | Trend dashboard (Chart.js) | Line chart renders across cycles |
| 10 | `ReportService` + DomPDF | PDF downloads with correct risk categories |
| 11 | Deadlines + scheduler + mail | Reminder fires once via simulated sweep |
| 12 | Heatmap | 33 cells match scored data exactly |
| 13 | Simulator | Projection correct, FR-63 regression test green |
| 14 | Three dashboards | Empty states render on a fresh database |
| 15 | Full test pass, UAT, comparative evaluation | Sprint 5 exit criteria |

> **Step 3 gates steps 5 onward.** Everything downstream is unverifiable until the 33 elements carry real thresholds. Do it first.

---

## Appendix A — Preflight Checklist

```bash
php -v                                   # expect 8.2+
php -m                                   # expect openssl, pdo_mysql, zip, gd, fileinfo, mbstring, intl
composer -V                              # expect 2.6+
mysql -u root -P 3306 -e "SELECT VERSION();"   # which engine is on 3306?
mysql -u root -P 3307 -e "SELECT VERSION();"   # which engine is on 3307?
```

After `composer install` and `.env` setup, confirm Laravel agrees:

```bash
php artisan db:show        # Connection should read "mariadb", Version "10.x/11.x-MariaDB"
```

| Check | Expected | Fix if wrong |
|---|---|---|
| PHP on PATH | `php -v` works from any directory | Add `C:\wamp64\bin\php\php8.2.x` to system PATH |
| `zip` extension | Listed in `php -m` | Enable via WampServer tray → PHP → PHP extensions |
| **MariaDB, not MySQL** | `SELECT VERSION()` contains `-MariaDB` | Change `DB_PORT` to the other port (§1.3) |
| **`DB_CONNECTION=mariadb`** | `php artisan db:show` reports connection `mariadb` | Fix `.env`; confirm `config/database.php` has a `mariadb` block (§2.3) |
| **Collation** | `utf8mb4_unicode_ci` on database and connection | Recreate the database; never use `utf8mb4_0900_ai_ci` (MySQL-only) |
| **Row format** | `innodb_default_row_format = dynamic` | On "key too long", add `Schema::defaultStringLength(191);` to `AppServiceProvider::boot()` |
| CLI and Apache php.ini both edited | `upload_max_filesize` = 12M in both | They are separate files (§1.4) |
| `storage/` writable | No permission errors on upload | Usually fine on Windows |
| `APP_KEY` set and backed up | `.env` has a value | `php artisan key:generate` — once, then back up |
