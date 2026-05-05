# RMU Internship & Attachment Portal

A web portal for **Regional Maritime University** that coordinates the
industrial attachment lifecycle: from student request → departmental
approval → official letter generation → weekly logbook submission →
final evidence and grading.

This is the **v2 working branch** (`claude/review-internship-tracker-v2-6euYr`).
v1 was a flat single-folder script-set; v2 is a role-organised, vanilla
PHP 8 / MySQL application.

---

## 1. Tech stack

| Layer | Choice |
|---|---|
| Language | Vanilla PHP 8 (no Composer, no framework) |
| DB | MariaDB 10.4+ / MySQL 8 (named `internship_system`) |
| Server | WAMP (dev) / any LAMP-style host (prod) |
| Frontend | HTML, CSS, plain JS — no build step |
| PDFs | FPDF (vendored at `lib/fpdf.php`) |
| Auth | session cookies + `password_hash()` |

---

## 2. User roles & seed numbers

The seed dump (`internship_system.sql`) ships with **61 user accounts**:

| Role | Count | Purpose |
|---|---:|---|
| **Admin** | 1 | Manages users, departments, programs, registry, settings |
| **HOD** (Head of Department) | 8 | Approves attachment requests, signs letters, reviews logbooks |
| **Secretary** | 7 *(after migration 002)* | Per-department approvals on behalf of HOD |
| **Student** | 45 | Submits requests, weekly logbooks, final evidence |

> Six secretary rows shipped with `role=''` and could not log in.
> **Migration 002** backfills `role='secretary'` for them.

After running migrations 001–005 you also get:

- **7 departments** (ICT, Marine Engineering, Nautical Science, Transport,
  Electrical, Mechanical, Accounting)
- **8 programmes** (e.g. BSc. Information Technology under ICT,
  BSc. Marine Engineering, BSc. Computer Science, etc.)
- **11 job-titles** (HOD, Acting HOD, Department Secretary, Senior Lecturer, …)
- **A backfilled student registry** containing every student account that has
  a valid index number and recognisable dept/programme.

---

## 3. Repository layout

```
/                          login (index.php), logout.php, change_password.php
/admin/                    dashboard, users, edit_user, add_user,
                           registry, programs, bulk_upload, settings, profile
/hod/                      dashboard, profile, logbook_review
/secretary/                dashboard
/student/                  dashboard, profile, logbook, docs, submit_evidence
/api/                      generate_letter (PDF), download_template,
                           download_registry_template, process_request
/includes/                 db.php, auth.php, sidebar.php
/lib/                      fpdf.php + font/
/assets/css/               style.css, login.css, layout.css,
                           registry.css, programs.css, auth.css
/assets/images/            logo, login art, profiles/, signatures/
/assets/js/                main.js
/templates/                weekly_log_template.pdf,
                           final_evaluation_form.pdf,
                           student_import_template.csv
/uploads/                  logbooks/, evidence/   (gitignored at runtime)
/migrations/               001 … 005 SQL files (run in order)
/tools/                    generate_hash, reset (dev-only)
internship_system.sql      one-shot seed for v1 schema + sample data
```

`includes/db.php` auto-detects `BASE_URL` from `$_SERVER['SCRIPT_NAME']`,
so the app works at both `/` and `/rmu_internship/` without config.

---

## 4. The four user flows

### Admin
1. Logs in at `/index.php`.
2. **Manages users** (`admin/users.php`) — view, edit, delete *(archive in upcoming sprint)*.
3. **Manages registry** (`admin/registry.php`) — uploads CSV from the registry
   office, downloads a template, or adds students one by one. Department
   ⇒ programme dropdown auto-filters.
4. **Manages programmes & departments** (`admin/programs.php`) — full CRUD
   with safe-delete (won't drop a row that's still referenced).
5. **Global search** (`admin/dashboard.php`) — filter all attachment requests
   by name/index, department, status.
6. **Generates letters** for any approved request.

### HOD
1. Lands on `hod/dashboard.php` showing only their department's requests.
2. Must upload a **digital signature** (PNG, ideally transparent) once via
   `hod/profile.php` before approvals are unlocked.
3. **Approves / rejects** student attachment requests — rejections require a reason.
4. Generates the official PDF letter (filename + signature embedded).
5. Reviews student weekly logbooks (`hod/logbook_review.php`).

### Secretary
1. Lands on `secretary/dashboard.php` for their department only.
2. Sees pending attachment requests.
3. Can approve **only if** their department's HOD has uploaded a signature
   (the secretary acts on behalf of the HOD).
4. Generates letters for approved requests.

### Student
1. Logs in. If their account was created with a temporary password
   (`must_change_password=1`), they're forced through `change_password.php`
   before any other page renders.
2. Submits an attachment request from the dashboard.
3. Uploads **weekly logbooks** at `student/logbook.php` — date inputs
   block past dates and validate server-side.
4. Uploads **final evidence** (signed performance sheet) at
   `student/submit_evidence.php`.
5. Downloads provided templates from `student/docs.php`.

---

## 5. Migrations

Each migration is **idempotent** and **safe to re-run**. Apply them in
order from phpMyAdmin → SQL tab → paste the file → Go.

| File | What it does |
|---|---|
| `001_v2_foundation.sql` | Creates `departments`, `programs`, `student_registry`. Seeds 7 depts and 8 programmes from existing data. |
| `002_fix_secretary_roles.sql` | Backfills `role='secretary'` for the 6 seed rows that shipped with empty role and couldn't log in. |
| `003_v2_user_admin.sql` | Adds `users.must_change_password`, `users.is_archived`, `users.archived_at`. Creates `job_titles` table seeded with 11 standard titles. |
| `004_consolidate_profile_pic.sql` | Collapses two parallel columns (`profile_pic` + `profile_path`) into one and drops the `'default.png'` sentinel that was 404-ing in sidebars. |
| `005_backfill_student_registry.sql` | Populates `student_registry` from existing student accounts. Reports any user rows that couldn't be auto-mapped to a dept/programme. |

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

# 3. Apply the v2 migrations (in order)
mysql -u root internship_system < migrations/001_v2_foundation.sql
mysql -u root internship_system < migrations/002_fix_secretary_roles.sql
mysql -u root internship_system < migrations/003_v2_user_admin.sql
mysql -u root internship_system < migrations/004_consolidate_profile_pic.sql
mysql -u root internship_system < migrations/005_backfill_student_registry.sql

# 4. Drop the project into your web root (e.g. C:\wamp64\www\rmu_internship)
# 5. Visit http://localhost/rmu_internship/
```

DB credentials live at the top of `includes/db.php`. Update `$user`/`$pass`
for production. (Move to a real `.env` is on the v3 list.)

---

## 7. Test accounts

All seeded users share one bcrypt hash. Use **`tools/reset.php`** to seed
two known test accounts with password `123456`:

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
| Student | `kwame.m@student.rmu.edu.gh` |

> **RMU email rule** *(supervisor #7, helper ready, enforcement coming)*:
> staff must be `@rmu.edu.gh`, students must be `@st.edu.rmu.gh`.

---

## 8. v2 progress vs supervisor's review

| # | Supervisor item | Status |
|---|---|---|
| 1 | CSS sprawl | 🟡 Partial — registry / programs / auth pages have dedicated CSS files; older pages still mix inline styles |
| 2 | Department → programmes auto-filter on user creation | 🟢 Done in registry; pending in `add_user.php` |
| 3 | Secretary registers students | 🔴 Pending |
| 4 | Admin can add programmes | ✅ `admin/programs.php` |
| 5 | Registry table + secretary lookup | ✅ table + admin UI; secretary side pending |
| 6 | Student self-registration | 🔴 Pending |
| 7 | Only RMU emails | 🟡 Helper exists in `includes/auth.php`; not yet wired into the user forms |
| 8 | "Generate temp password" button | 🟡 Helper exists; UI pending |
| 9 | Newest user shown first | 🔴 Pending (admin/users.php rewrite) |
| 10 | Search system users | 🔴 Pending (admin/users.php rewrite) |
| 11 | Secretary roles updated | ✅ migration 002 |
| 12 | Archive instead of delete | 🟡 column exists; UI pending |
| 13 | Job-title dropdown | 🟡 table + seed exist; UI pending |
| 14 | Force-change temp password | ✅ full flow |
| 15 | No past dates in calendar | ✅ logbook |

Legend: ✅ done · 🟡 partial · 🔴 pending

---

## 9. Suggested additions beyond the supervisor list

These haven't been requested but would round out v2/v3:

### Tier 1 — high-impact, foundational
- **Email notifications** — request approved / rejected, logbook commented, weekly reminder, password reset.
- **Industry-supervisor evaluation** — token-based link emailed to the company supervisor (no account) where they fill in a short evaluation. Currently we have no way to validate the student's logbook reflects reality.
- **Academic year / cohort tagging** — every request, logbook, submission tagged with `academic_year_id`. Needed before yearly reports can work.
- **Audit log** — letters are official documents; track who approved/rejected what, when, from which IP.
- **Letter reference numbers + stored PDFs** — every approved letter gets a serial (`RMU/ICT/INT/2026/0042`) and the generated PDF is *stored*, not regenerated each click. Prevents backdating disputes.
- **`.htaccess` hardening** — block direct web access to `/uploads`, `/tools`, `/lib`, `/includes`, `/migrations`.

### Tier 2 — reporting & coverage
- Company / Organisation registry (free-text company names today).
- HOD/admin reports — placement rate, weeks completed, students still without placement, CSV/PDF export.
- More document templates — final evaluation, mid-term review, certificate of completion.
- Comment threads on requests / logbooks (instead of single `rejection_reason` field).

### Tier 3 — quality-of-life
- Student progress dashboard ("Week 4 of 12 logbooks · evidence pending").
- Calendar / deadline page tied to the academic year.
- Mobile responsiveness pass at 360px.
- Multi-attachment per logbook week.
- Inline PDF preview on file links.
- "View as student" mode for admin debugging.

### Tier 4 — security maturity (do before releasing to real students)
- CSRF tokens on every POST form.
- Login throttling / lockout.
- 2FA for staff (HOD, secretary, admin).
- Force HTTPS + security headers.
- Backup / export tool — admin downloads a zip of all submissions for an academic year.

---

## 10. Known data caveats

- The seed dump assigns **two HODs to the ICT department** (rows 4 + 11).
  Migration 005 cannot tell which is "real". The letter-generator now picks
  the one with a signature; admin should still archive the duplicate.
- Several student rows have `department='Computer Science'` — this is a
  programme name, not a department. Migration 005 reports them as
  unmappable. Fix by editing the user's department to `'ICT'` then
  re-running 005.
- `users.profile_path` column is left in place after migration 004 even
  though it's no longer written. A future migration can drop it.
- `lib/fpdf.php` is vendored (not pulled via Composer) by design — vanilla
  stack, no build step.

---

## 11. Branch / commit history (v2 high-points)

```
ba670f5  bug fixes from first round of testing (admin + HOD)
cbbe47d  force-password-change flow + RMU-email helpers + logbook date guard
282bb9a  fix avatar 404s — consolidate profile_pic columns
7de107f  admin Programs & Departments manager + fix empty-role secretaries
730786a  clean up assets/, relocate templates+uploads, fix file paths
9159efe  fix sidebar overlap and hide nav items users can't reach
f6cf2c4  reorganize codebase into role-based folder structure
10dd689  add Student Registry (CSV upload, template, single entry)
```

---

## 12. Contributing / dev notes

- **Always commit on the v2 branch** (`claude/review-internship-tracker-v2-6euYr`)
  until v2 is green-lit; only then merge to `main`.
- **`tools/`** is dev-only. `tools/generate_hash.php` and `tools/reset.php`
  must not be exposed to production users — protect with `.htaccess`
  or delete before deploying.
- New migrations go in `/migrations/NNN_short_description.sql`. Number
  them sequentially; make every migration idempotent (CREATE TABLE IF
  NOT EXISTS, INSERT IGNORE, conditional ALTER).
- Page-specific CSS lives in `assets/css/<page>.css`. Avoid inline styles
  in new code — the supervisor flagged "css all over the place" in v1.
- All cross-page links go through `<?= BASE_URL ?>` or the `url()` /
  `asset()` helpers in `includes/db.php`. Never hard-code `/admin/...`.

---

*Last updated: see `git log -1` on this branch.*
