# RMU Internship & Industrial Attachment Portal

> A role-based web portal for **Regional Maritime University (RMU)**
> that coordinates the full industrial-attachment lifecycle —
> from a student's letter request through host-organisation
> placement, weekly logbook submissions, and the on-the-job
> supervisor's final evaluation.

This README is structured for two audiences in parallel:

- **Operators** running the system locally on WAMP. Skip to [§7 Setup](#7-setup) and [§13 Test accounts](#13-test-accounts).
- **Reviewers / Chapter-4 readers** who need a system blueprint to derive use-case, activity, sequence, and ER diagrams. The whole document is structured to make that derivation mechanical.

---

## 1. System at a glance

The portal automates a workflow that used to live in paper forms,
manila folders, and back-and-forth emails. The diagram below is the
end-to-end pipeline and corresponds 1-to-1 with the system's state
machine.

```
┌──────────┐   ┌───────────┐   ┌──────────────┐   ┌────────────────┐
│ Registry │ → │  Account  │ → │   Letter     │ → │   Placement    │
│  upload  │   │ creation  │   │   request    │   │  registration  │
└──────────┘   └───────────┘   └──────────────┘   └────────────────┘
                                       │                  │
                                       ▼                  ▼
                              ┌─────────────────┐ ┌────────────────┐
                              │  HOD approves   │ │ Weekly logbook │
                              │  → PDF letter   │ │   (digital)    │
                              └─────────────────┘ └────────────────┘
                                                          │
                                                          ▼
                                                 ┌─────────────────┐
                                                 │ Supervisor OTP  │
                                                 │ remarks + final │
                                                 │  evaluation     │
                                                 └─────────────────┘
```

Notable design properties:

| Property | Why it matters |
|---|---|
| **Vanilla PHP 8 / MySQL**, no Composer, no framework. | Deployable to any LAMP host with zero install steps; reviewable by a course supervisor without specialised tooling. |
| **Registry as source of truth.** | A student cannot create an account unless an admissions record exists for their index number. Prevents "phantom" accounts. |
| **OTP-based supervisor identity check.** | The on-the-job supervisor doesn't need an account. They verify via a 6-digit code emailed to them, type it on the student's device, and act. Server-side bcrypt-hashed code, 15-minute expiry, peek-verify on type, consume-verify on submit. |
| **Department isolation.** | HOD and Secretary queries are server-side scoped to their department; cross-department leakage is impossible from the UI. |
| **Forced password change on first login** for accounts created with a temp password. |
| **Real email** via PHPMailer + Gmail SMTP at every workflow gate (approval, OTP, account creation). |

---

## 2. Tech stack

| Layer | Choice | Notes |
|---|---|---|
| Server-side | PHP 8.x (procedural + light OO) | No Composer; libraries vendored under `lib/` |
| Database | MariaDB 10.4+ / MySQL 8 | Database name: `internship_system` |
| Web server | Apache 2.4 (WAMP) | Apache rewrites not required; `.htaccess` used for security only |
| Frontend | Hand-written HTML, vanilla CSS, vanilla JS | No build step, no bundler |
| PDF | FPDF 1.x — vendored at `lib/fpdf.php` | Only for the official RMU intro letter |
| Email | PHPMailer 6.9.x — vendored at `lib/PHPMailer/PHPMailer-6.9.3/` | STARTTLS to Gmail SMTP; CA-verify disabled for dev |
| Auth | Native sessions, `password_hash()` / `password_verify()` | bcrypt by default |
| Browser support | Modern evergreen (last-2 Chromium / Firefox / Edge) | No IE polyfills |

---

## 3. User roles, counts, and responsibilities

The system has **five distinct actors**. Four are authenticated users
(rows in `users`); the fifth is the on-the-job supervisor, who interacts
without an account through OTP-gated forms.

| # | Role | Seeded count | Authenticates? | What they do |
|---|---|---:|---|---|
| 1 | **Admin** | 1 | Yes (single sign-in) | Manages users, departments, programmes, registry roster, academic calendar, reports |
| 2 | **HOD** (Head of Department) | 8 (one per department) | Yes | Approves / rejects student letter requests within their department; reviews logbooks; uploads digital signature for letters |
| 3 | **Department Secretary** | 7 (one per department, after migration 002) | Yes | Approves on behalf of HOD; reviews logbooks; same department-scoped view as HOD |
| 4 | **Student** | 45 (seeded) | Yes | Requests letter, registers placement, files weekly logbooks, views final evaluation, prints transcript |
| 5 | **On-the-job Supervisor** | n/a (one per active placement) | **No** — token-less but OTP-verified | Signs weekly logbooks, submits the final 50-mark evaluation |

> Use-case-diagram tip: actors 1-4 are inside the system boundary; actor 5 is an external actor that crosses the boundary only through two use cases: *Sign weekly logbook* and *Submit final evaluation*.

### Department coverage (seeded)

7 departments (ICT, Marine Engineering, Nautical Science, Transport,
Electrical, Mechanical, Accounting) with 8 programmes (BSc.
Information Technology, BSc. Computer Science, BSc. Marine Engineering,
BSc. Nautical Science, BSc. Port & Shipping Administration, BSc.
Electrical & Electronic Engineering, BSc. Mechanical Engineering,
BSc. Accounting).

### Job titles (seeded)

11 canonical titles: Head of Department, Acting Head of Department,
Department Secretary, Senior Lecturer, Lecturer, Assistant Lecturer,
Professor, Associate Professor, Tutor, Industrial Liaison Officer,
System Administrator.

---

## 4. Functional modules — by role

Each module below maps to a single use case in a use-case diagram.
Group them under their actor.

### 4.1 Admin (`/admin/`)

| Module | File | What the admin can do |
|---|---|---|
| Dashboard / Global search | `dashboard.php` | Search every request across the university by name, index, department, status |
| Manage users | `users.php` | Search, filter by role/department, view active / archived / staff-only / student-only, newest first |
| Add new user | `add_user.php` | Create student / secretary / HOD / admin; auto-generate temp password; dept→programme dependent dropdown; auto-mirror students into the registry |
| Edit user | `edit_user.php` | Update any user's record; archive / restore |
| Student registry | `registry.php` | Single-student add, CSV bulk upload, per-row error reporting, downloadable template, drift alert (students in `users` missing from registry), broken-FK badge |
| Programmes & departments | `programs.php` | CRUD on departments and programmes with safe-delete (rejects if referenced) |
| Academic calendar | `academic_calendar.php` | Define academic years and their semesters; "set as current" mutex; archive past years |
| Reports | `reports.php` | System-wide placement pipeline view; filter by year/dept/stage; printable |
| Profile | `profile.php` | Edit own profile, change password, upload profile picture |

### 4.2 HOD (`/hod/`)

| Module | File | What the HOD can do |
|---|---|---|
| Dashboard | `dashboard.php` | View department requests, approve / reject (modal or row-level), download generated PDF letter, see semester-overlap warnings |
| Profile | `profile.php` | Upload digital signature (required before approvals are unlocked) |
| Logbook reviews | `logbook_review.php` | Tabular list of students with submitted counts; modal drill-down to each student's weekly entries; leave inline department comments |
| Final evaluations | `evaluations.php` | Tabular list of students with submitted scores; modal breakdown; filter by submitted / pending; printable |
| Reports | `reports.php` | Department-scoped placement pipeline; KPI cards; printable |

### 4.3 Department Secretary (`/secretary/`)

Same permissions as the HOD inside the department. Currently the
"Register Student" entry is paused per supervisor request (the page
exists, the sidebar link is commented out).

| Module | File | What the secretary can do |
|---|---|---|
| Dashboard | `dashboard.php` | Approve / reject requests on behalf of the HOD (requires HOD to have signature on file) |
| Logbook reviews | `hod/logbook_review.php` | Shared with HOD |
| Final evaluations | `hod/evaluations.php` | Shared with HOD |
| Reports | `hod/reports.php` | Shared with HOD |

### 4.4 Student (`/student/`)

| Module | File | What the student can do |
|---|---|---|
| Dashboard / letter request | `dashboard.php` | Submit attachment-letter request with date constraints (no past, no semester overlap, end ≥ start); duplicate open-letter block; view request history |
| My placement | `placement.php` | Register host organisation, supervisor name/email/title, dates (constraints mirror request); edit anytime; placement triggers a heads-up email to the supervisor |
| Weekly logbook | `logbook.php` | Pick a week from the placement-derived dropdown; per-day activities textarea (dates auto-fill); save draft or submit (locks week); supervisor OTP sign-off panel on submitted weeks |
| Final evaluation | `evaluation.php` | OTP-gated form for the on-the-job supervisor to enter scores (8 criteria, 50 marks); read-only once submitted |
| My transcript | `transcript.php` | One-page printable summary of the student's whole attachment record |
| Profile | `profile.php` | Edit profile, change password (strong-password enforced), upload profile picture |
| Force-change password | `/change_password.php` | First-login gate when account was created with a temp password |

### 4.5 On-the-job Supervisor (no account)

Doesn't access any URL of their own. They sit at the student's device
and act on these student-side pages:

- `student/logbook.php` — to sign each week's log
- `student/evaluation.php` — to submit the final evaluation

Each action follows the same micro-flow:

```
[Send OTP] → email arrives at supervisor's address
           → supervisor reads the 6-digit code
           → types it into the student's screen
           → server peek-verifies via api/check_supervisor_otp.php
           → fields unlock only on success
           → submit consumes the OTP via verify_supervisor_otp()
           → row is locked; neither student nor HOD can edit

## 5. System flows — narrative for diagram derivation
The flows below are written as numbered action sequences so they map
directly to activity / sequence diagrams.

### 5.1 Student self-registration (activity diagram)

1.  Student opens /index.php → clicks "Create your account"
2.  /register.php loads, email field is read-only
3.  Student types index number → JS calls /api/registry_lookup.php?public=1
4.  Server looks up student_registry by index_number
    - if found: returns name, dept, programme, registry email
    - if not found OR already claimed: error message
5.  JS auto-fills the (read-only) email field
6.  Student picks password (live checklist: 8+ / upper / lower / digit / symbol)
7.  Submit → server re-validates:
    - registry row exists, unclaimed
    - submitted email matches registry email
    - RMU domain (@st.rmu.edu.gh)
    - password strength
    - email not already in users
8.  Server creates users row, sets must_change_password = 0
9.  Server updates student_registry: is_claimed=1, claimed_user_id
10. Auto-logs the student in, redirects to /student/dashboard.php


### 5.2 Letter request (activity diagram)

```
1.  Student on /student/dashboard.php picks dates + (optional) company
2.  Client-side: blocks past dates / end < start / semester overlap
3.  Server-side re-validates same rules
4.  Server checks duplicate open-letter rule (item #9)
5.  INSERT INTO requests ... status='pending'
6.  Server queries: SELECT email FROM users WHERE role='hod' AND dept=...
7.  Email sent to HOD (subject: New attachment request from X)
8.  Student sees confirmation banner; request appears in My Requests list
```

### 5.3 HOD approval (sequence diagram — three actors)

```
Student → HOD: (asynchronously) request appears in HOD dashboard
HOD → System: clicks View → modal opens with full details
HOD → System: clicks Approve in modal footer
        System: checks HOD has uploaded signature
        - if no: returns "Upload signature first"
        - if yes: UPDATE requests SET status='approved'
                  Email student (approved + portal link)
                  Modal auto-closes, table refreshes
HOD → System: clicks Download PDF Letter (for any approved request)
        System: SELECT request + student + HOD info
                FPDF renders letter with HOD signature image
                Streams PDF to browser
HOD → Student: hands over the PDF (offline) so student can find a host org
```

### 5.4 Placement registration (activity diagram)

```
1.  Student finds a company (offline) → returns to /student/placement.php
2.  Fills: company name, address, dept/office, supervisor name/email/title/phone, dates
3.  Client + server validate dates (no past, no semester overlap, end > start)
4.  Server resolves academic_year_id (current) and semester_id (matching date)
5.  INSERT INTO placements ... status='active'
6.  Server emails the supervisor a heads-up explaining the OTP flow
7.  Student returns to placement.php; sees the placement card
```

### 5.5 Weekly logbook + supervisor sign-off (sequence diagram)

```
Student → System: opens /student/logbook.php
        System: pulls weeks_info from placement.start/end (1..N)
        System: shows week dropdown + auto-filled day rows

Student → System: picks Week N, types activities per day, hits Submit
        System: server-derives all dates from week_number+placement
                INSERT INTO logbooks (..., is_submitted=1)
                INSERT INTO logbook_days (one row per day)
                Row is now locked for the student

Student → System: clicks Send OTP on the locked week's sign-off panel
        System: random_int(0,999999) → bcrypt hash
                INSERT INTO supervisor_otps ... expires +15 minutes
                PHPMailer sends 6-digit code to placement.supervisor_email

Supervisor → email inbox → reads code
Supervisor → Student's screen: types code into otp input
        System: JS POST → /api/check_supervisor_otp.php (PEEK)
        System: returns {ok: true} if match
        System: JS unlocks the fieldset (otherwise it stays disabled)

Supervisor → System: types name, status, remarks → Submit
        System: verify_supervisor_otp() (consumes the code)
        System: UPDATE logbooks SET supervisor_remarks/_signed_at/_by_name
        System: redirects to view; week now read-only forever

HOD → System: opens /hod/logbook_review.php
        System: table of students; click → modal with weekly entries
        System: HOD can add staff_comment (marks reviewed)
```

### 5.6 Final evaluation (sequence diagram)

Same shape as 5.5 but at end of attachment, on
`/student/evaluation.php`. Form has 8 numeric inputs each capped at
their max (5 or 5 or 5 or 5 or 5 or 5 or 10 or 10), total ≤ 50.
Submitting flips `placements.status` to `completed`. Row in
`evaluations` is created with UNIQUE constraint on `placement_id` — so
exactly one evaluation per placement.

---

## 6. Data model

13 tables in `internship_system`. Lines marked with → indicate
foreign keys. Optional fields are nullable. Use this section as raw
material for an ER diagram.

```
academic_years (id PK, name UNIQUE, start_date, end_date,
                is_current, is_archived, created_at)

semesters (id PK,
           → academic_year_id,
           label, start_date, end_date, sort_order)

departments (id PK, name UNIQUE, code, created_at)

programs (id PK,
          → department_id,
          name, code, created_at,
          UNIQUE(department_id, name))

job_titles (id PK, name UNIQUE, applies_to ENUM,
            created_at)

users (id PK, full_name, email UNIQUE, password,
       role ENUM(admin|hod|secretary|student),
       department, program, level, gender, index_number,
       job_title, profile_pic, profile_path, signature_path,
       must_change_password, is_archived, archived_at, created_at)

student_registry (index_number PK, full_name, email,
                  → department_id, → program_id,
                  level, gender, date_of_birth, year_admitted,
                  → academic_year_id_admitted,
                  is_claimed, → claimed_user_id (→ users),
                  created_at, updated_at)

requests (id PK, → student_id, company_name, company_address,
          start_date, end_date,
          status ENUM(pending|approved|rejected),
          rejection_reason, → academic_year_id,
          request_date)

placements (id PK, → student_id, → request_id, company_name,
            company_address, company_department, supervisor_name,
            supervisor_email, supervisor_title, supervisor_phone,
            start_date, end_date,
            status ENUM(active|completed|cancelled),
            → academic_year_id, → semester_id,
            supervisor_token, supervisor_token_expires_at,
            created_at, updated_at)

logbooks (id PK, → student_id, → placement_id, week_number,
          start_date, end_date, activities, file_path,
          student_remarks, supervisor_remarks, supervisor_signed_at,
          supervisor_signed_by_name, supervisor_signed_by_status,
          is_submitted, is_reviewed, staff_comment,
          → academic_year_id, submission_date)

logbook_days (id PK, → logbook_id, day_label, day_date,
              activities, sort_order)

evaluations (id PK, → placement_id UNIQUE,
             score_responsibility, score_reliability,
             score_knowledge, score_output, score_quality,
             score_punctuality, score_overall_perf,
             score_overall_conduct, total_score,
             supervisor_name, organization, supervisor_title,
             submitted_at, submitted_ip)

supervisor_otps (id PK, → placement_id, email, purpose,
                 code_hash (bcrypt), expires_at, consumed_at,
                 created_at)

internship_submissions (legacy, unused going forward)
settings              (legacy key/value, superseded by academic_years)
letter_templates      (legacy, supervisor decided letter body stays hardcoded)
email_settings        (legacy, supervisor decided SMTP creds stay in code)
```

### Relationship cardinalities (ER hints)

- One **academic_year** has many **semesters**.
- One **department** has many **programs**.
- One **student_registry** row maps to zero or one **users** row.
- One **user** (student) has many **requests** and at most one active **placement** per academic year.
- One **placement** has many **logbooks** (one per week) and exactly zero or one **evaluations**.
- One **logbook** has up to 5 **logbook_days**.
- One **placement** has many **supervisor_otps** rows over time (one per OTP issuance).

---

## 7. Setup

```bash
# 1. Clone and switch to the v2 branch
git clone http://github.com/cardboo/rmu_internship.git
cd rmu_internship
git checkout claude/review-internship-tracker-v2-6euYr

# 2. Create the DB and load the seed
mysql -u root -e "CREATE DATABASE internship_system DEFAULT CHARSET utf8mb4;"
mysql -u root internship_system < internship_system.sql

# 3. Run every migration in order (001 → 015)
for m in migrations/*.sql; do mysql -u root internship_system < "$m"; done

# 4. Install PHPMailer (6.9.x, NOT 7.x)
tools\install_phpmailer.bat

# 5. Drop the project into your WAMP web root
#    (C:\wamp642\www\rmu_internship\) and visit http://localhost/rmu_internship/
```

DB credentials live in `includes/db.php`. SMTP credentials live in
`includes/email.php`. Both are intentional design choices for a
classroom-scale project; production deployment should externalise
them.

---

## 8. Repository layout

```
/                          index.php, logout.php, register.php,
                           change_password.php
/admin/                    dashboard, users, edit_user, add_user,
                           registry, programs, academic_calendar,
                           reports, settings (deprecated), profile
/hod/                      dashboard, profile, logbook_review,
                           evaluations, reports
/secretary/                dashboard (register_student page exists
                           but sidebar link is paused)
/student/                  dashboard, profile, placement, logbook,
                           evaluation, transcript
/api/                      generate_letter, registry_lookup,
                           check_supervisor_otp, download_registry_template,
                           process_request
/includes/                 db.php, auth.php, sidebar.php, email.php,
                           print_header.php + .htaccess
/lib/                      fpdf.php (PDF) + font/, PHPMailer/...
                           + .htaccess
/assets/css/               16 stylesheets: components, layout, login,
                           auth, dashboards, users, registry, programs,
                           calendar, student, logbook, evaluation,
                           reports, supervisor (now unused), style
/assets/images/            logo, login art, profiles/, signatures/
/assets/js/                main.js
/templates/                student_import_template.csv
/uploads/                  logbooks/, evidence/ (gitignored, .htaccess
                           blocks PHP execution)
/migrations/               15 SQL files (run in order)
/tools/                    generate_hash.php, reset.php, test_email.php,
                           test_smtp_socket.php, install_phpmailer.bat
                           (dev-only, .htaccess deny)
internship_system.sql      v1 schema + seed data
```

`includes/db.php` auto-detects `BASE_URL` from `$_SERVER['SCRIPT_NAME']`,
so the project works at `/` and at `/rmu_internship/` without configuration.

---

## 9. Migrations

15 idempotent migrations. Re-running any of them is safe.

| File | What it does |
|---|---|
| 001 v2 foundation | `departments`, `programs`, `student_registry` |
| 002 fix secretary roles | Backfill `role='secretary'` on seed rows that shipped with empty role |
| 003 v2 user admin | `users.must_change_password`, `is_archived`, `archived_at`; `job_titles` |
| 004 consolidate profile pic | Collapse two parallel columns; drop the `'default.png'` sentinel |
| 005 backfill student registry | Populate registry from existing student `users` rows |
| 006 registry email + domain fix | `student_registry.email`; rewrite `@st.edu.rmu.gh` → `@st.rmu.edu.gh` |
| 007 academic calendar | `academic_years` + `semesters`; tag existing rows |
| 008 placements | New `placements` table with supervisor token columns |
| 009 logbook digital | Logbook columns + new `logbook_days` child table |
| 010 evaluations | 8-criterion / 50-mark evaluation results |
| 011 letter templates | `letter_templates` (later deprecated — body re-hardcoded) |
| 012 email settings | `email_settings` key/value table (later deprecated — SMTP creds hardcoded) |
| 013 reconcile registry | Sync registry with users that drifted between 005 and now |
| 014 backfill registry fields | Copy email / name / level / gender from users → registry where empty |
| 015 supervisor OTPs | `supervisor_otps` table for the OTP flow |


## 10. Security posture

| Mechanism | Implementation |
|---|---|
| Passwords | bcrypt via `password_hash()` / `password_verify()` |
| Strong-password rule | `password_strength_error()` in `includes/auth.php` — 8+ chars, upper, lower, digit, symbol. Enforced on register, change-password |
| Force-change-temp-pw | `must_change_password` flag; gate runs in `includes/db.php` on every page load |
| Email validation | `rmu_email_error()` — students forced to `@st.rmu.edu.gh`, staff to `@rmu.edu.gh` |
| Department isolation | Every HOD / secretary query is filtered by `department = ?` from session, in PHP |
| Supervisor identity | 6-digit OTP, bcrypt-hashed, 15-minute expiry, peek-verify on type (`api/check_supervisor_otp.php`), consume-verify on submit |
| Locked records | Submitted logbook weeks and submitted evaluations are read-only; supervisor sign-off makes a logbook week immutable to even the student |
| SQL injection | All queries use PDO prepared statements |
| XSS | Every echoed value is wrapped in `htmlspecialchars()` |
| Direct-access protection | `.htaccess` in `tools/`, `migrations/`, `includes/`, `lib/`; `uploads/` blocks `.php` execution |
| Sessions | `session_regenerate_id(true)` on login |

**Outstanding for v3 / production:** CSRF tokens on every POST,
login rate-limit / lockout, 2FA for staff, force HTTPS + security
headers, audit log table.

---

## 11. Email & OTP design

```
PHPMailer 6.9.3 → Gmail SMTP (smtp.gmail.com:587, STARTTLS)
   ↳ creds in includes/email.php constants
   ↳ TLS chain verify disabled for dev (Windows PHP has no CA bundle)
   ↳ try_send_email() wrapper logs but never throws — business actions
     never blocked by SMTP failures
```

**Events that send mail:**

1. **Account creation** (admin or secretary) — temp password emailed to new user
2. **Letter request submitted** — HOD of the student's dept gets notified
3. **Letter approved / rejected** — student gets notified (with rejection reason)
4. **Placement registered** — supervisor gets a heads-up explaining the OTP flow
5. **Supervisor OTP requested** (logbook sign-off OR final evaluation) — 6-digit code emailed

**OTP lifecycle:**

```
Issue   → random_int(0, 999999), zero-padded to 6 digits
        → bcrypt-hashed before storage
        → expires_at = NOW() + 15 minutes
        → previous unconsumed OTPs for the same (placement, purpose)
          are marked consumed simultaneously (only one valid at a time)

Peek    → /api/check_supervisor_otp.php
        → matches against latest unconsumed unexpired hash
        → DOES NOT mark consumed (front-end can probe live)

Consume → verify_supervisor_otp() on form submit
        → same match logic + marks the row consumed_at = NOW()
        → single-use; the second submit with the same code fails

Two purposes are tracked:
   'evaluation'        — one final evaluation per placement
   'logbook:<log_id>'  — one sign-off per submitted weekly log
```

---

## 12. Reports

Each printable page includes an RMU-letterhead header (logo + university
name + report title + print timestamp) that's hidden on screen and shown
only by `@media print`.

| Page | Audience | Filters | KPIs |
|---|---|---|---|
| `admin/reports.php` | Admin | Year, department, lifecycle stage, free-text | Students, requested, approved, placed, logging, completed, rejected |
| `hod/reports.php` | HOD / Secretary | Year, stage, free-text (dept fixed) | Students, requested, placed, completed, average score |
| `hod/logbook_review.php` | HOD / Secretary | Free-text search | Tabular + per-student modal |
| `hod/evaluations.php` | HOD / Secretary | Submitted / pending, search | Per-student score modal |
| `student/transcript.php` | Student | (none) | One-page summary: identity, request, placement, logbook stats, evaluation |

The "pipeline stage" classification (used on both admin and HOD report
pages) reads off seven mutually-exclusive states:

```
none → requested → approved → placed → logging → completed
                                                ↑
                                            rejected (terminal)
```

---

## 13. Test accounts

After running migrations, the seed users in `internship_system.sql`
share a single bcrypt hash. The easiest way to get usable creds is:

```
php tools/reset.php          # CLI only (.htaccess blocks web access)
```

That sets up two test accounts:

| Role | Email | Password |
|---|---|---|
| Student | `student@test.com` | `123456` |
| HOD | `hod@test.com` | `123456` |

Other seeded emails (real domain, same shared hash):

| Role | Sample |
|---|---|
| Admin | `admin@rmu.edu.gh` |
| HOD ICT | `hod.ict@rmu.edu.gh` |
| Secretary ICT | `sec.ict@rmu.edu.gh` |
| Student | `kwame.m@st.rmu.edu.gh` |

---

## 14. Known limitations & v3 backlog

- **CSRF tokens** are not yet attached to POST forms.
- **No login rate-limit / lockout** — brute force protection deferred.
- **No 2FA for staff** — staff accounts rely on bcrypt + RMU email domain only.
- **No `audit_log` table** — admin actions aren't logged for retrospect.
- The legacy `logbooks.file_path` and `internship_submissions` table can be dropped once everyone is on the digital logbook flow.
- The TLS chain verification bypass in `includes/email.php` should be replaced by a `php.ini` `openssl.cafile` pointing at a real `cacert.pem` before deploying.
- Reports don't yet export CSV — printing only.
- Antivirus mail-protection on campus wifi intercepts outbound 587. The supervisor's workaround is to whitelist `php.exe` in the AV's exclusions or use a hotspot.

---

## 15. Commit history high-points (v2)

```
a8071eb  fix OTP gating — server-side peek-verify on type
74695a2  email: skip TLS chain verification for dev
6468c2f  tools: one-command PHPMailer install (forces 6.9.x stable)
dd1c24b  item 4 — universal button + input styles via components.css
3a0a1fe  HOD modal approve/reject + score hard-cap + printable RMU header
3ea35a0  item 10 — reports per role
d2020ba  item 6 — tabular HOD/Secretary reviews + modal drill-down
328e06a  item 4 — lock supervisor inputs until OTP entered
6e7dc4a  items 2, 3, 7 — strong password + readonly email + placement + logbook auto-fill
ab1e0a7  items 1, 5, 8, 9, 11 — letter date validation, sidebar trims, TWIMC, dup block
04c0a2f  finish registry reconciliation
bba3b20  reconcile registry drift (migration 013 + admin/add_user mirror)
b37b0e8  PHPMailer-equivalent SMTP + transactional notifications (Sprint 7, since superseded)
0220033  final evaluation (Sprint 5)
419769e  token-based supervisor portal (Sprint 4, since superseded by OTP flow)
26e15ef  digital weekly logbook (Sprint 3)
f404afe  split letter request from placement (Sprint 2)
8c15bba  academic calendar foundation (Sprint 1)
f6cf2c4  reorganise codebase into role-based folder structure
10dd689  add Student Registry
```

---

*Last updated: see `git log -1` on this branch.*
