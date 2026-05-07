# RMU Internship & Attachment Portal

A web portal for **Regional Maritime University** that coordinates the
industrial attachment lifecycle:

```
  letter request  →  HOD approval  →  official PDF letter
                                    ↓
  student finds a company offline
                                    ↓
  placement registered on the system  →  supervisor gets a secure link
                                    ↓
  weekly logbooks (digital)  →  supervisor adds remarks per week
                                    ↓
  final supervisor evaluation (digital, scored 0–50)
                                    ↓
  result visible to student (read-only) + HOD
```

This branch (`claude/review-internship-tracker-v2-6euYr`) is the
**v2 implementation**. v1 was a flat single-folder script-set; v2
is a role-organised, vanilla PHP 8 / MySQL application with no
Composer / framework dependency.

---

## 1. Tech stack

| Layer | Choice |
|---|---|
| Language | Vanilla PHP 8 (no Composer, no framework) |
| DB | MariaDB 10.4+ / MySQL 8 (named `internship_system`) |
| Server | WAMP (dev) / any LAMP-style host (prod) |
| Frontend | HTML, CSS, plain JS — no build step |
| PDF | FPDF (vendored at `lib/fpdf.php`) |
| Email | Hand-rolled minimal SMTP client at `lib/Mailer.php` (~250 LOC, supports STARTTLS / SSL / AUTH LOGIN) |
| Auth | Session cookies + `password_hash()` |

---

## 2. User roles

| Role | Count in seed | Responsibilities |
|---|---:|---|
| **Admin** | 1 | Manages users, departments, programs, registry, academic calendar, letter templates, email settings |
| **HOD** (Head of Department) | 8 | Approves attachment requests, signs official letters, reviews logbooks |
| **Secretary** | 7 | Per-department approvals on behalf of HOD; registers students from the registry roster |
| **Student** | 45 | Requests letter, registers placement, files weekly logbooks |
| **Industry supervisor** | n/a | NO account — token-gated portal at `supervisor.php?t=…` (60-day validity). Adds weekly remarks + submits the final evaluation |

---

## 3. Repository layout

```
/                          login (index.php), logout.php, register.php,
                           change_password.php, supervisor.php,
                           supervisor_evaluation.php
/admin/                    dashboard, users, edit_user, add_user,
                           registry, programs, academic_calendar,
                           letter_templates, email_settings,
                           settings, profile
/hod/                      dashboard, profile, logbook_review
/secretary/                dashboard, register_student
/student/                  dashboard, profile, placement, logbook
/api/                      generate_letter, registry_lookup,
                           download_registry_template,
                           process_request
/includes/                 db.php, auth.php, sidebar.php, email.php
                           + .htaccess (deny direct web access)
/lib/                      fpdf.php + font/, Mailer.php
                           + .htaccess (deny direct web access)
/assets/css/               style.css, login.css, layout.css,
                           registry.css, programs.css, users.css,
                           dashboards.css, calendar.css, student.css,
                           logbook.css, supervisor.css, evaluation.css,
                           letter_templates.css, email.css, auth.css
/assets/images/            logo, login art, profiles/, signatures/
/assets/js/                main.js
/templates/                student_import_template.csv
/uploads/                  logbooks/, evidence/  (gitignored at runtime;
                           PHP execution disabled via .htaccess)
/migrations/               001 … 012 SQL files (run in order)
                           + .htaccess (deny direct web access)
/tools/                    generate_hash, reset (dev-only,
                           .htaccess deny — never ship to prod)
internship_system.sql      one-shot v1 schema + seed data
```

`includes/db.php` auto-detects `BASE_URL` from `$_SERVER['SCRIPT_NAME']`,
so the app works at `/` and at `/rmu_internship/` without configuration.

---

## 4. The end-to-end flow

| Stage | Page | Who acts |
|---|---|---|
| 1. Setup | `admin/programs.php`, `admin/academic_calendar.php`, `admin/registry.php` | Admin (once) |
| 2. Account creation | `register.php` (self-serve) / `secretary/register_student.php` / `admin/add_user.php` | Student, secretary, or admin |
| 3. First login | `change_password.php` | Student (forced if temp pw) |
| 4. Letter request | `student/dashboard.php` | Student |
| 5. Approval | `hod/dashboard.php` / `secretary/dashboard.php` | HOD or secretary |
| 6. Letter PDF | `api/generate_letter.php` | HOD / secretary / admin |
| 7. Placement registration | `student/placement.php` | Student (after securing a host) |
| 8. Weekly logs | `student/logbook.php` | Student |
| 9. Supervisor remarks | `supervisor.php?t=TOKEN` | On-the-job supervisor (no account) |
| 10. HOD review | `hod/logbook_review.php` | HOD / secretary |
| 11. Final evaluation | `supervisor_evaluation.php?t=TOKEN` | On-the-job supervisor |
| 12. Score read-only | student dashboard, HOD review | Student, HOD |

---

## 5. Migrations

Each migration is **idempotent** and **safe to re-run**. Apply them
in order from phpMyAdmin → SQL → paste → Go.

| File | Purpose |
|---|---|
| `001_v2_foundation.sql` | `departments`, `programs`, `student_registry` |
| `002_fix_secretary_roles.sql` | Backfill `role='secretary'` for the 6 seeded rows that shipped with empty role |
| `003_v2_user_admin.sql` | `users.must_change_password`, `is_archived`, `archived_at`; `job_titles` table |
| `004_consolidate_profile_pic.sql` | Collapse `profile_pic` and `profile_path` columns; drop the `'default.png'` sentinel |
| `005_backfill_student_registry.sql` | Populate `student_registry` from existing `users` (best-effort) |
| `006_registry_email_and_domain_fix.sql` | `student_registry.email` column + fix the `@st.edu.rmu.gh` typo to `@st.rmu.edu.gh` |
| `007_academic_calendar.sql` | `academic_years` + `semesters`; tag existing dated rows |
| `008_placements.sql` | New `placements` table (company + supervisor + period + token) |
| `009_logbook_digital.sql` | Extend `logbooks` for the digital flow + new `logbook_days` child table |
| `010_evaluations.sql` | Final evaluation results (8 criteria, 50 marks) |
| `011_letter_templates.sql` | `letter_templates` per dept / year / semester |
| `012_email_settings.sql` | SMTP config for transactional notifications |

---

## 6. Setup

```bash
# 1. Clone & switch to the v2 branch
git clone http://github.com/cardboo/rmu_internship.git
cd rmu_internship
git checkout claude/review-internship-tracker-v2-6euYr

# 2. Create the DB and load the v1 dump
mysql -u root -e "CREATE DATABASE internship_system DEFAULT CHARSET utf8mb4;"
mysql -u root internship_system < internship_system.sql

# 3. Apply the v2 migrations in order (001 → 012)
for m in migrations/*.sql; do mysql -u root internship_system < "$m"; done

# 4. Drop the project into your web root (e.g. C:\wamp64\www\rmu_internship)
# 5. Visit http://localhost/rmu_internship/
```

DB credentials live at the top of `includes/db.php`.

### Email setup (optional but recommended)

For local dev, install [**Mailpit**](https://github.com/axllent/mailpit)
— a single Windows binary that catches every outgoing email on
`localhost:1025` and shows them at `http://localhost:8025`. The
default settings in `Admin → Email Settings` already point at it.
Flip "Enabled" to ON, hit **Send Test Email**.

For production, the same settings page accepts real SMTP creds
(Gmail with an app password, Office 365, RMU's mail server, etc.).
The transcript pane on the test page surfaces the actual SMTP
error if a port is firewalled.

---

## 7. Test accounts

All seeded users share one bcrypt hash. Use **`tools/reset.php`**
(CLI only — `.htaccess` blocks web access) to seed two known test
accounts with password `123456`:

| Role | Email | Password |
|---|---|---|
| Student | `student@test.com` | `123456` |
| HOD     | `hod@test.com`     | `123456` |

Or pick any from the seed:

| Role | Sample email |
|---|---|
| Admin | `admin@rmu.edu.gh` |
| HOD ICT | `hod.ict@rmu.edu.gh` |
| Secretary ICT | `sec.ict@rmu.edu.gh` |
| Student | `kwame.m@st.rmu.edu.gh` |

> **RMU email rule**: staff `@rmu.edu.gh`, students `@st.rmu.edu.gh`.
> Enforced server-side in `includes/auth.php :: rmu_email_error()`.

---

## 8. Sprint history (v2)

| Sprint | What | Key commits |
|---|---|---|
| 0 | Restructure / clean-up / sidebar layout / avatars / migrations 001–006 | `f6cf2c4` … `ff44d3d` |
| 1 | Academic calendar (years + semesters) — migration 007 | `8c15bba` |
| 2 | Lifecycle split: `placements` table — migration 008 | `f404afe` |
| 3 | Digital weekly logbook (mirrors PDF) — migration 009 | `26e15ef` |
| 4 | Token-based supervisor portal | `419769e` |
| 5 | Final evaluation (8 criteria) — migration 010 | `0220033` |
| 6 | Letter templates per dept/year/sem — migration 011 | `b694e0f` |
| 7 | PHPMailer-equivalent SMTP + transactional notifications — migration 012 | `b37b0e8` |
| 8 | Cleanup: drop deprecated PDF flow + .htaccess hardening | this commit |

---

## 9. Security posture

- DB credentials hardcoded in `includes/db.php` (move to env in v3).
- `bcrypt` for passwords via `password_hash()`.
- `must_change_password` flag forces temp-password rotation on
  first login.
- Email validation enforces RMU domains by role.
- `.htaccess` denies direct web access to `tools/`, `migrations/`,
  `includes/`, `lib/`. PHP execution is blocked under `uploads/`
  to neutralise upload-as-RCE.
- Supervisor tokens are 64 hex chars (256 bits) with a 60-day
  expiry, regenerable.

Still open (queued for v3):
- CSRF tokens on every POST.
- Login throttling / lockout.
- 2FA for staff.
- Force HTTPS + security headers.
- Audit log table.
- Data backup / yearly export.

---

## 10. Known caveats

- Two HODs are assigned to ICT in the seed (rows 4 + 11). The letter
  generator picks the one with a signature; the admin should still
  archive the duplicate via Manage Users.
- Some seed students have programme names where their department
  should be (e.g. `department='Computer Science'`). Migration 005
  reports them as unmappable; fix the user record to backfill
  them into the registry.
- The legacy `logbooks.file_path` column is left in place after
  migration 009 even though it's no longer written; a follow-up
  migration can drop it once historical entries are confirmed
  archived.

---

*Last updated: see `git log -1` on this branch.*
