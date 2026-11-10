# The Harmonic System — Product Requirements Document

Nov 9, 2026 · @Just Speak

## 1. Overview

The Harmonic System is a multi-tenant web platform that lets any organization run its operations on a six-section operating framework: Direction, Measurements, Team, Resolution, Procedures, and Discipline. It is built by Claude Code from this document, hosted on InfinityFree, and its entire UI is in English.

**Who uses it**

| Persona | Context | Primary jobs |
| --- | --- | --- |
| Leader | Laptop, Monday morning before the Weekly Meeting | See what is off-track, run the meeting, assign work, resolve problems |
| Member | Phone or laptop, a few minutes a week | Enter metric values, update Missions and tasks, raise problems |
| Org Admin | Occasional | Manage people, seats, settings, onboarding and offboarding |
| Super Admin | The platform owner | Approve and suspend organizations, monitor the platform |

**Product principles** (apply to every screen and every line of code)

1. One source of truth. Teams are derived from Winning Formation; tasks exist once as RACI+; dates live in one calendar.
2. Nothing is orphaned. Any change to people or seats that would leave work without an owner must pass through Handover.
3. History does not change. Completed work, past scores and past assessments keep their original authors and values.
4. Open on work, private on judgment of people. Operational data is visible company-wide; evaluations of people are restricted and anonymous.
5. Less is more. A feature is added as a view, an event type, or a field on an existing entity before it is ever added as a new module.

**Scope: "80% ideal", not minimum.** Every module listed in this document ships complete: full flows, edge cases, empty states, validation, responsive mobile layouts, and email notifications. Modules are built in the order of section 21, and each phase must pass its test gate before the next starts.

**Explicitly out of scope**

- Dotted-line or matrix reporting (one person reporting to two managers).
- Versioning of Winning Formation; the activity log covers change history.
- Multi-language UI.
- Billing, plans, and payments.
- Real-time sync (WebSockets); the Weekly Meeting uses light polling instead.
- Inbound API, webhooks, and mobile apps; the hosting's browser check blocks non-browser clients.
- Any use of the names "EOS", "Entrepreneurial Operating System", "Traction", "V/TO", "Level 10", "L10", "Rocks", "People Analyzer", "GWC", "IDS" or other EOS Worldwide trademarks in UI, code identifiers, seed data, or documentation.

## 2. Decision log

These decisions are settled. Claude Code must not reopen them; a conflict between this log and any later section is a bug in the later section.

| ID | Decision | Consequence |
| --- | --- | --- |
| D1 | Multi-tenant platform from day one | Every tenant-owned row carries `organization_id`; cross-tenant access is the top security risk |
| D2 | Code is written by Claude Code from this PRD | Requirements are explicit; `CLAUDE.md` and `DESIGN.md` accompany this document |
| D3 | Initial scale under 20 users | Fits InfinityFree limits; code stays portable to paid hosting |
| D4 | Two onboarding paths: Super Admin invite and self-signup | Self-signup requires email verification and Super Admin approval |
| D5 | No billing | Organization status is `pending`, `active`, `suspended`, or `deleted` |
| D6 | English-only UI | No i18n layer |
| D7 | RACI+ replaces simple to-dos | Quick-add requires title, Responsible, output, due date; full form required when created from I-S.O.L.V.E Validate |
| D8 | Heart & Head Reflection = reflection time plus leader self-check | Implemented as a private guided event |
| D9 | The Main Stage has four parts | Target market, three uniques, proven process, guarantee |
| D10 | Belief System replaces core values | It is a Fundamental and the rating criterion in Personnel Evaluator |
| D11 | Music metaphor at section level, functional instrument names | Every instrument has a full name and a short nav label |
| D12 | Company Diagnostic and Leadership Assessment 360 are separate modules | Both store structured scores per cycle for trend charts |
| D13 | Handover defaults to the direct superior | Walks up the chart to the nearest filled seat, then Org Admin |
| D14 | Full transparency of operational data | Everyone in the organization can view; edit rights are limited by position |
| D15 | All assessments are anonymous at the data level | Assessor identity is never stored with answers; results always show as averages; a warning appears below 3 assessors |
| D16 | Target is the complete product, built in gated phases | Phases set build order, not feature cuts |
| D17 | Teams are derived from Winning Formation | No manual team management screen exists |
| D18 | One seat holds exactly one person; one person may hold several seats | Seat-owned items always have a single owner |
| D19 | Leaderboard, Project Seats, Leadership Duo and Personal Fundamentals are dropped | Not built |
| D20 | Quarterly Check-in, Belief Shoutout, Heart & Head Reflection, Passion-Performance Matrix are guided events | No dedicated modules or tables |

## 3. Glossary and naming

Every term below has one UI name, one short nav label, and one code identifier. Code identifiers are used for routes, tables, models, and permissions; never use synonyms.

**The six sections** (navigation groups)

| Section | Tagline (shown under the nav group, optional) | Code |
| --- | --- | --- |
| Direction | The score everyone plays from | `direction` |
| Measurements | The tempo keeping you in rhythm | `measurements` |
| Team | The musicians in their right seats | `team` |
| Resolution | Detecting and resolving dissonance | `resolution` |
| Procedures | How all the parts arrange together | `procedures` |
| Discipline | The actual performance | `discipline` |

**Main instruments** (two per section)

| Section | Full name | Nav label | Code | What it does |
| --- | --- | --- | --- | --- |
| Direction | The Fundamentals | Fundamentals | `fundamentals` | Six-part company direction: Belief System, The Why & Way, Long-Term Objective, The Main Stage, The 3-Year Snapshot, Annual Priority |
| Direction | Alignment Check | Alignment | `alignment` | Confirms every member understands and accepts the Fundamentals |
| Measurements | Metrics Dashboard | Dashboard | `metrics` | Weekly grid of team metrics with goals and red/green status |
| Measurements | The Numbers | My Numbers | `numbers` | Each person's own metrics, at least one per person |
| Team | Winning Formation | Formation | `formation` | Seat tree that defines structure, teams, and ownership |
| Team | Personnel Evaluator | Evaluator | `evaluator` | Rates people against the Belief System and Seat Fit Check |
| Resolution | The Problem Queue | Problems | `problems` | Lists of open problems per team and company |
| Resolution | I-S.O.L.V.E | Solve | `solve` | Structured resolution: Ignite, State, Observe, Leverage, Validate, Evaluate |
| Procedures | Standardization | Procedures | `procedures` | Documented core procedures at 20/80 depth |
| Procedures | Synchronization Cycle | Sync | `sync` | Confirms everyone follows the procedures and audits them |
| Discipline | Quarterly Breakthrough Missions | Missions | `missions` | Three to seven 90-day priorities per team and person |
| Discipline | Meeting Cadence | Meetings | `meetings` | Weekly Meeting runner plus quarterly and annual sessions |

**Core engine, proprietary modules, and guided events**

| Name | Nav label | Code | Kind |
| --- | --- | --- | --- |
| RACI+ | Tasks | `tasks` | Core engine: the only task entity, shown as list and board |
| Unified Calendar | Calendar | `calendar` | Core engine: all dates in one place, plus guided events |
| Company Diagnostic | Diagnostic | `diagnostic` | Proprietary module with trend over cycles |
| Leadership Assessment 360 | 360 | `assessment` | Proprietary module with trend over cycles |
| Seat Fit Check | (inside Evaluator) | `seat_fit` | Three yes/no checks: Understands the seat, Wants the seat, Can do the seat |
| Quarterly Check-in | (calendar) | `guide_checkin` | Guided event |
| Belief Shoutout | (calendar) | `guide_shoutout` | Guided event |
| Heart & Head Reflection | (calendar) | `guide_reflection` | Guided event, private |
| Passion-Performance Matrix | (calendar) | `guide_passion` | Guided event |
| Handover | (dialog) | `handover` | Required flow whenever work could lose its owner |

**Other terms**: *Seat* is a position in Winning Formation. *Team* is a seat that has direct child seats, together with those children. *Leadership Team* is the one team flagged as such. *Membership* is the link between an account and an organization. *Quarter* is a 13-week period that starts on the organization's configured quarter start date.

## 4. Platform constraints and tech stack

The app is a Laravel monolith rendered client-side with Inertia and React, built on the developer's machine and uploaded to InfinityFree by FTP. Every choice below exists because of a hosting limit; do not add anything that needs a server process, a scheduler, or a symlink.

**Stack**

| Layer | Choice | Notes |
| --- | --- | --- |
| Backend | Laravel 13, PHP 8.3+ | InfinityFree free servers run PHP 8.3 or newer |
| Database | MySQL (InfinityFree) | `utf8mb4`, InnoDB, foreign keys on; local dev also MySQL for parity |
| Frontend | Inertia 2, React 19, TypeScript, Tailwind CSS 4 | Client-side rendering only, no SSR build |
| Build | Vite, run locally | `npm run build` output is uploaded; no Node on the server |
| Auth | Laravel session auth (Breeze-style) | Session driver `file` |
| Tests | Pest | Run locally before every upload |
| Email | SMTP to an external provider (Brevo, Gmail, Mailgun) | No mail service on the host |

**Hosting limits and the required response**

| Limit on InfinityFree | Required implementation |
| --- | --- |
| No cron or scheduled tasks | "Lazy scheduler": a middleware runs due jobs on any authenticated request, at most once per 10 minutes per organization, guarded by a cache lock |
| No queue workers | `QUEUE_CONNECTION=sync`; emails send during the request; keep each email batch under 20 recipients |
| No Redis or Memcached | `CACHE_STORE=file`, `SESSION_DRIVER=file` |
| Web root is `htdocs`, not `public` | Root `.htaccess` rewrites to `public/` and blocks `.env`, `storage`, `vendor` |
| No symlinks | No `storage:link`; the app stores no user-uploaded files in v1 (links only) |
| PHP files over 1 MB are silently dropped | No generated PHP file may exceed 1 MB |
| No SSH or Composer on server | `composer install --no-dev` locally; Super Admin "Maintenance" page runs pending migrations and clears caches |
| Browser check blocks non-browser clients | No inbound API; all requests come from the app's own pages |
| 50,000 hits per day per account | Polling only inside a live Weekly Meeting, every 15 s, paused when the tab is hidden; no other polling |
| Daily CPU limit | Paginate every list (25 rows default), eager-load relations, index every foreign key and filter column |

Source: [How to install a Laravel site on InfinityFree](https://forum.infinityfree.com/t/how-to-install-a-laravel-site-on-infinityfree/118578) and [Free hosting PHP upgrade](https://forum.infinityfree.com/t/free-hosting-is-now-upgraded-to-php-8-3/109714).

**Time rules**

- Store every timestamp in UTC; display in the organization's timezone (setting, default `Asia/Jakarta`).
- Each organization sets its week start day (default Monday) and the start date of its current quarter. A quarter is 13 weeks; the next quarter starts the day after week 13 ends.
- Week labels are `W1`–`W13` within the quarter, and `Q1`–`Q4` count quarters within the organization's year, which starts on the first quarter start date entered.

**First deliverable of Phase 0**: a "walking skeleton" deployed to InfinityFree (login page, one migration, one email sent) before any feature work, to prove the hosting setup end to end.

## 5. Roles, permissions, and visibility

Only two roles are set by hand: Super Admin (platform) and Org Admin (organization). Every other right comes from a person's position in Winning Formation, computed at request time, so moving a seat changes rights immediately.

**Role predicates** (implement once in a single `Access` service; Laravel Policies call it)

| Predicate | True when |
| --- | --- |
| `superAdmin` | `users.is_super_admin = 1` |
| `member(org)` | The user has a membership in `org` with status `active` and the org status is `active` |
| `orgAdmin(org)` | `member(org)` and `memberships.is_org_admin = 1` |
| `leadership(org)` | `member(org)` and the user holds the head seat or a child seat of the Leadership Team |
| `leads(team)` | The user holds the head seat of `team` |
| `inTeam(team)` | `leads(team)` or the user holds a child seat of `team` |
| `above(seat)` | The user holds any ancestor seat of `seat` |
| `owner(item)` | The item's owner membership is the user's membership |

Super Admin manages organizations but cannot open a tenant's operational data through the UI. A Super Admin who also belongs to an organization uses that membership like anyone else.

**Permission matrix** ("all" = any active member of the organization)

| Module | View | Create | Edit | Delete or archive |
| --- | --- | --- | --- | --- |
| Fundamentals | all | leadership | leadership | leadership |
| Alignment Check | all (aggregate) | leadership launches a round | each member answers once per round | leadership |
| Metrics Dashboard | all | leads(team) or orgAdmin | definition: leads(team); values: metric owner or leads(team) | leads(team), archive only |
| The Numbers | all | — (derived from metrics) | — | — |
| Winning Formation | all | orgAdmin | orgAdmin | orgAdmin, through Handover |
| Personnel Evaluator | subject, above(subject's seat), orgAdmin | above(subject's seat) | author of the evaluation, same day only | never; superseded by newer evaluations |
| Problem Queue | all | all, into any team's queue | inTeam(team) or the problem's owner | leads(team) |
| I-S.O.L.V.E | all | inTeam(team) | inTeam(team) | leads(team) |
| Standardization | all | leads(owning team) or orgAdmin | procedure owner, leads(owning team) | leads(owning team), archive only |
| Synchronization Cycle | all | leads(owning team) starts an audit | auditor of that audit | — |
| Missions | all | company: leadership; team: leads(team); personal: owner or above(owner) | owner, or the creator | creator or leads(team) |
| Meetings | all | leads(team) | inTeam(team) during a live meeting; leads(team) edits notes after | leads(team), only if never started |
| RACI+ tasks | all | all | Responsible, Accountable, creator, leads(task team) | creator, Accountable, leads(task team) |
| Calendar events | all, except private | org events: leadership or orgAdmin; team events: inTeam(team); private events: self | creator | creator |
| Company Diagnostic | all (aggregate only) | leadership or orgAdmin opens a cycle | each member answers once per cycle | — |
| Leadership Assessment 360 | results: the subject, above(subject's seat), orgAdmin | leadership or orgAdmin | assessors answer once per assignment | orgAdmin, only before the first answer |
| People, settings | orgAdmin | orgAdmin | orgAdmin | orgAdmin |

**Visibility exceptions** (everything else is open to all members, D14)

| Data | Visible to |
| --- | --- |
| Personnel Evaluator ratings and Seat Fit Check | The subject, holders of any ancestor seat, Org Admin |
| Leadership Assessment 360 per-person results | The subject, holders of any ancestor seat, Org Admin; never per-assessor values |
| Heart & Head Reflection events and notes | Only the creator |
| Quarterly Check-in notes | The two participants |
| Company Diagnostic | Aggregates for all; individual answers are never retrievable |

Every denied action returns HTTP 403 with the copy "You don't have access to this." Every record from another organization returns 404, never 403, so tenant existence is not revealed.

## 6. Data model

The schema below is the contract; migrations must match it exactly, and any new column needs a reason written in the migration. Teams have no table: a team is identified by its head seat's id (`team_seat_id`).

**Conventions**

- Primary keys are `BIGINT UNSIGNED` auto-increment, except answer tables marked `UUID`, which use random UUIDv4 and no timestamps so insertion order cannot reveal who answered.
- Every tenant table has `organization_id` (FK, indexed); a global Eloquent scope filters by the current organization, and every write sets it from the session, never from the request.
- "Member" columns reference `memberships.id`, never `users.id`, so history survives when people leave.
- `timestamps` = `created_at`, `updated_at`. `soft` = `deleted_at`. Enums are MySQL `ENUM`. Money and metric values are `DECIMAL(15,4)`.
- Every FK and every column used in a `WHERE` or `ORDER BY` is indexed.

```text
users               id, name VARCHAR(120), email VARCHAR(190) UNIQUE, email_verified_at, password,
                    is_super_admin BOOL=0, email_opt_out BOOL=0, anonymized_at NULL, timestamps
organizations       id, name VARCHAR(120), slug VARCHAR(80) UNIQUE,
                    status ENUM(pending,active,suspended,deleted), signup_source ENUM(invite,self),
                    timezone VARCHAR(64)='Asia/Jakarta', week_start_day TINYINT(1..7)=1,
                    quarter_start_date DATE, currency CHAR(3)='IDR', evaluator_bar_max_mixed TINYINT=1, evaluator_bar_max_minus TINYINT=0,
                    approved_at NULL, purge_after DATE NULL, timestamps
memberships         id, organization_id, user_id, status ENUM(invited,active,departed), is_org_admin BOOL=0,
                    joined_at NULL, departed_at NULL, timestamps; UNIQUE(organization_id,user_id)
invitations         id, organization_id, email, token_hash CHAR(64), make_org_admin BOOL=0, seat_id NULL,
                    invited_by (member), expires_at, accepted_at NULL, revoked_at NULL, timestamps
seats               id, organization_id, parent_id NULL (seats), title VARCHAR(120), responsibilities JSON (3-7 strings),
                    holder_id NULL (member), team_name VARCHAR(60) NULL, is_leadership_team BOOL=0, sort_order INT, meeting_weekday TINYINT NULL, meeting_time TIME NULL, meeting_minutes SMALLINT=90, timestamps
                    -- exactly one seat per org may have is_leadership_team=1; it must have children
activity_log        id, organization_id, actor_id NULL (member), action VARCHAR(60), subject_type, subject_id,
                    data JSON, created_at
email_outbox        id, organization_id NULL, to_email, subject VARCHAR(200), template VARCHAR(60), data JSON,
                    attempts TINYINT=0, last_error VARCHAR(255) NULL, sent_at NULL, created_at
scheduler_runs      id, organization_id, job VARCHAR(60), last_run_at; UNIQUE(organization_id,job)

beliefs             id, organization_id, name VARCHAR(80), description TEXT, sort_order, archived_at NULL, timestamps
fundamentals        id, organization_id, version INT, status ENUM(draft,published), content JSON, published_by NULL (member),
                    published_at NULL, timestamps; UNIQUE(organization_id,version)
alignment_rounds    id, organization_id, fundamentals_id, opened_by (member), opened_at, closes_at, closed_at NULL
alignment_completions  id, round_id, member_id, completed_at; UNIQUE(round_id,member_id)
alignment_answers   UUID id, round_id, part_key VARCHAR(30), clear BOOL, committed BOOL NULL, comment TEXT NULL

metrics             id, organization_id, team_seat_id, owner_seat_id, name VARCHAR(120), description TEXT NULL,
                    unit ENUM(number,percent,currency,hours), decimals TINYINT(0..2)=0,
                    operator ENUM(gte,lte,eq,gt,lt,between), goal_value DECIMAL, goal_value_max DECIMAL NULL,
                    quarter_rollup ENUM(sum,average,latest)=sum, auto_problem_after TINYINT NULL=2, sort_order, archived_at NULL, timestamps
metric_values       id, metric_id, week_start DATE, value DECIMAL NULL, operator, goal_value, goal_value_max NULL,
                    status ENUM(on,off) NULL, note VARCHAR(280) NULL, entered_by (member), timestamps;
                    UNIQUE(metric_id,week_start)   -- goal columns are a snapshot taken when the value is saved

missions            id, organization_id, level ENUM(company,team,personal), team_seat_id NULL, parent_id NULL (missions),
                    owner_id (member), title VARCHAR(160), done_definition TEXT, quarter_start DATE,
                    status ENUM(on_track,off_track,done,not_done)=on_track, status_updated_at NULL, carried_from_id NULL (missions), closed_at NULL, created_by (member), timestamps, soft
mission_milestones  id, mission_id, title VARCHAR(160), due_date DATE NULL, done_at NULL, sort_order

problems            id, organization_id, team_seat_id, raised_by (member), owner_id NULL (member), title VARCHAR(160),
                    description TEXT NULL, horizon ENUM(short,long)=short, rank TINYINT NULL,
                    status ENUM(open,solving,solved,dropped)=open, source_type NULL, source_id NULL,
                    solved_at NULL, meeting_id NULL, timestamps, soft
solve_records       id, problem_id UNIQUE, mode ENUM(quick,full), ignite TEXT NULL, statement TEXT, observe TEXT NULL,
                    leverage TEXT NULL, solution TEXT, evaluate_on DATE NULL, evaluation TEXT NULL,
                    evaluation_result ENUM(worked,partly,failed) NULL, timestamps

tasks               id, organization_id, team_seat_id NULL, board_id NULL, board_column_id NULL, position INT,
                    title VARCHAR(160), output TEXT, definition_of_done TEXT NULL, due_date DATE,
                    status ENUM(todo,doing,done,cancelled)=todo, responsible_id (member), accountable_id (member),
                    source_type NULL, source_id NULL, completed_at NULL, created_by (member), timestamps, soft
task_people         task_id, member_id, role ENUM(consulted,informed); PRIMARY(task_id,member_id,role)
task_steps          id, task_id, title VARCHAR(200), done_at NULL, sort_order
boards              id, organization_id, team_seat_id NULL, title VARCHAR(120), created_by (member), archived_at NULL, timestamps
board_columns       id, board_id, title VARCHAR(60), is_done_column BOOL=0, sort_order

procedures          id, organization_id, team_seat_id, owner_seat_id, title VARCHAR(160), purpose TEXT,
                    body MEDIUMTEXT (markdown draft), audience ENUM(team,organization)=team, version INT=0, published_at NULL, reviewed_at NULL, review_every_months TINYINT=12,
                    archived_at NULL, timestamps
procedure_versions  id, procedure_id, version, body MEDIUMTEXT, published_by (member), published_at; UNIQUE(procedure_id,version)
procedure_acks      procedure_id, version, member_id, acknowledged_at; PRIMARY(procedure_id,version,member_id)
sync_audits         id, procedure_id, procedure_version, auditor_id (member), audited_on DATE,
                    result ENUM(followed,partly,not_followed), findings TEXT NULL, timestamps

meetings            id, organization_id, team_seat_id, type ENUM(weekly,quarterly,annual), event_id NULL,
                    scheduled_for DATETIME, started_at NULL, ended_at NULL, segment TINYINT=0,
                    notes TEXT NULL, cascade_message TEXT NULL, timestamps
meeting_attendance  meeting_id, member_id, present BOOL; PRIMARY(meeting_id,member_id)
meeting_ratings     meeting_id, member_id, score TINYINT(1..10); PRIMARY(meeting_id,member_id)
meeting_headlines   id, meeting_id, member_id, kind ENUM(customer,employee), text VARCHAR(280)

event_series        id, organization_id, rule ENUM(weekly,biweekly,monthly,quarterly), until DATE NULL, timestamps
events              id, organization_id, series_id NULL, team_seat_id NULL, scope ENUM(org,team,pair,private),
                    type ENUM(custom,weekly_meeting,quarterly_session,annual_session,guide_checkin,
                              guide_shoutout,guide_reflection,guide_passion,diagnostic,assessment),
                    title VARCHAR(160), starts_at, ends_at, all_day BOOL=0, partner_id NULL (member),
                    notes TEXT NULL, created_by (member), timestamps, soft

evaluations         id, organization_id, subject_id (member), seat_id, evaluator_id (member), evaluated_on DATE,
                    understands BOOL, wants BOOL, capacity BOOL, notes TEXT NULL, timestamps
evaluation_scores   evaluation_id, belief_id, score ENUM(plus,mixed,minus); PRIMARY(evaluation_id,belief_id)

diagnostic_cycles   id, organization_id, opened_by (member), opened_at, closes_at, closed_at NULL
diagnostic_completions id, cycle_id, member_id, completed_at; UNIQUE(cycle_id,member_id)
diagnostic_answers  UUID id, cycle_id, question_key VARCHAR(20), score TINYINT(1..5)

assessment_types    id, organization_id, name VARCHAR(80), sort_order, archived_at NULL
assessment_items    id, type_id, text VARCHAR(255), sort_order, archived_at NULL
rubric_levels       id, organization_id, score TINYINT(1..5), label VARCHAR(40), description VARCHAR(255)
assessment_cycles   id, organization_id, name VARCHAR(120), opens_on DATE, closes_on DATE,
                    status ENUM(draft,open,closed)=draft, timestamps
assessment_assignments id, cycle_id, subject_id (member), type_id; UNIQUE(cycle_id,subject_id,type_id)
assessment_assessors   id, assignment_id, assessor_id (member), is_self BOOL, completed_at NULL;
                       UNIQUE(assignment_id,assessor_id)
assessment_answers  UUID id, assignment_id, item_id, score DECIMAL(3,2) (1.00..5.00), is_self BOOL
```

Guide texts, Diagnostic questions, and seed rubric levels live in PHP config files (`config/guides.php`, `config/diagnostic.php`), not in the database.

## 7. Platform foundation

One account can belong to many organizations; every organization page lives under `/o/{slug}/…`, and middleware rejects the request with 404 unless the user has an active membership there. Stories below use the format: story, then acceptance criteria (AC).

**F-01 Self-signup.** As a founder, I create my organization myself.

- Form fields: your name (2–120 chars), email, `password (min 10 chars, with letters and numbers; no breached-password API check, to avoid external calls)`, organization name (2–120 chars). Slug is generated from the name and made unique with a numeric suffix.
- Creates the user (unverified), the organization (`pending`, `self`), and an active membership with `is_org_admin = 1`.
- A verification email is sent; the link expires after 24 hours and can be resent once per minute.
- After verification the user sees "Your organization is waiting for approval. We'll email you when it's ready." and every Super Admin receives an email.
- Rate limit: 5 signups per IP per hour.

**F-02 Super Admin creates an organization.** Super Admin enters the organization name and the first admin's email; the organization is `active` at once and an invitation (F-03) with `make_org_admin = 1` is sent.

**F-03 Invitations.** As an Org Admin, I invite people by email.

- Inputs: one or more emails (max 20 per submit), optional seat (vacant seats only), optional Org Admin flag.
- Token: 32 random bytes, stored as SHA-256 hash; expires after 7 days. Resend issues a new token and voids the old one; revoke voids it.
- Accepting: an existing account logs in, then accepts; a new person registers with the email locked to the invited address.
- If the person has a `departed` membership in this organization, it is reactivated instead of creating a new one, and the activity log records `member.rejoined`.
- If the invited seat was filled meanwhile, the membership is created without a seat and the admin sees "Seat was already filled, so it wasn't assigned."
- Inviting an email that already has an active membership shows "Already a member." and sends nothing.

**F-04 Sign-in.** Email + password, "Remember me", password reset by email link (60 minutes). Rate limit: 10 attempts per minute per email+IP. All sessions of a user are invalidated on password change, role change, departure from any organization, and account anonymization.

**F-05 Organization switcher.** A user with more than one active membership sees a switcher in the top bar; with exactly one, sign-in goes straight to that organization's Home. A user with none sees "You're not part of an organization yet." with the option to sign up a new one.

**F-06 Organization settings** (Org Admin): name, timezone, week start day, current quarter start date, Personnel Evaluator bar (max "mixed" ratings, max "minus" ratings). Changing the quarter start date shows "This changes how weeks are numbered. Past values stay on their dates." and requires confirmation.

**F-07 People** (Org Admin): a table of members with name, email, seats held, status, admin flag, and joined date, filterable by status. Actions: invite, resend or revoke invitation, grant or remove Org Admin, deactivate (opens Handover, section 8), reactivate.

- The last active Org Admin cannot be removed as admin or deactivated: "Make someone else an admin first."

**F-08 Profile.** Change name; change email (the new address must be verified before it takes effect, and the old address gets a notice); change password; email opt-out for non-essential emails.

- Delete account: blocked while the user is the last Org Admin anywhere. Otherwise each active membership goes through Handover, then the user row is anonymized (name "Former member", email `deleted+{id}@invalid.local`, random password, `anonymized_at` set). Their records stay.

**F-09 Super Admin console** (`/admin`): organization list with name, status, member count, created date, and last activity; actions approve, reject, suspend, reactivate, delete, restore.

- Suspend: members of that organization see "This organization is suspended. Contact your administrator." on every page of it; data is untouched.
- Delete: status `deleted`, `purge_after` = today + 30 days, restorable until then; the lazy scheduler purges all its rows afterward.
- Reject (pending organizations only): the organization and its single membership are deleted at once, and the founder is emailed.
- Maintenance page: PHP version, pending migrations with a "Run migrations" button, "Clear caches" button, last scheduler run per job.
- The first Super Admin is created by the `app:make-super-admin {email}` command run locally against the production database before upload, or by setting `SUPER_ADMIN_EMAIL` in `.env`, which flags that account on its next sign-in.

**F-10 Activity log** (Org Admin): newest first, 25 per page, filter by person and action. Recorded actions: `member.invited`, `member.joined`, `member.rejoined`, `member.departed`, `member.role_changed`, `seat.assigned`, `seat.vacated`, `seat.moved`, `seat.deleted`, `handover.completed`, `org.settings_changed`.

## 8. Winning Formation, teams, and Handover

Winning Formation is the single source of structure: seats form one tree, teams are computed from it, and every change that could orphan work runs through Handover first. Add `team_name VARCHAR(60) NULL` to `seats`: the display name of the team a seat heads (default: "{seat title} team").

**Structure rules**

- Each organization has exactly one root seat, created with the organization (title "Chief Executive", editable).
- A team = a seat with at least one child seat, plus those children. Its leader is the head seat's holder.
- The Leadership Team = the flagged seat's team plus all ancestor seats of the flagged seat (so a founder seat above an operator seat is included). Default flag: the root.
- A seat has 0–7 responsibilities, each 3–120 chars; fewer than 3 shows the hint "Most seats have 3–7 responsibilities."
- A seat holds one person; one person may hold several seats.

**W-01 View the chart.** All members see a top-down tree: each seat card shows title, holder name or "Vacant", and the first 3 responsibilities with "+N more". The Leadership Team's seats carry a small "Leadership" label. On screens under 768 px the tree becomes an indented list. A search box highlights seats by title or holder name.

**W-02 Add or edit a seat** (Org Admin). Fields: title (required, 2–120), parent seat (required except root), responsibilities, team name (only shown when the seat has children). Siblings are ordered by drag handle; order is saved as `sort_order`.

**W-03 Assign, replace, or vacate a holder** (Org Admin). Choose any active member. Replacing or vacating opens Handover for the outgoing holder; seat-owned items (metrics, procedures) stay with the seat and pass to the new holder with no action.

**W-04 Move a seat.** Choose a new parent; descendants move with it; moving under its own descendant is rejected: "A seat can't move under its own child." Everyone whose team membership changes goes through Handover for items in teams they leave.

**W-05 Delete a seat.** Blocked while it has children: "Move or delete its child seats first." If it owns metrics or procedures, the dialog requires a replacement owner seat. A holder goes through Handover.

**W-06 Team dissolution.** When a head seat loses its last child, its team ends. If that team has active metrics, open problems, active missions, unarchived boards, or future meetings, the action requires a destination team, and all of them move there in the same transaction.

**W-07 Teams page.** Lists every derived team with leader, members, and counts of off-track metrics, open problems, and active missions; each links to the team's view of those instruments.

**W-08 Vacancy warnings.** Home shows leaders a warning for any vacant seat in their subtree that owns metrics or procedures: "3 metrics have no owner because Sales Rep is vacant."

**Handover**

Handover is one dialog reused by every trigger: deactivating a member, removing someone from a seat, moving a seat, deleting a seat, and deleting an account. The triggering action does not happen unless Handover completes; Cancel aborts both.

| Item | Included when | Receiver gets |
| --- | --- | --- |
| Tasks (status todo or doing) | Person is Responsible or Accountable | Same role; if they were both, the receiver becomes both |
| Missions (on track or off track) | Person is owner | Ownership |
| Problems (open or solving) | Person is owner | Ownership |
| Future events (org or team scope) | Person is creator | Creator rights |
| Consulted or Informed on tasks | Person departs | Nothing; the person is removed from the task |
| Pending 360 assessor slots | Person departs | Nothing; the uncompleted slot is removed |
| Private events and Quarterly Check-ins | Person departs | Nothing; private events stay private and frozen |

- Scope: on departure, every open item in the organization; on a seat change, only items whose team the person will no longer belong to.
- Default receiver: the new holder of the seat if the action assigns one; otherwise the nearest ancestor seat that has a holder (D13); otherwise the Org Admin performing the action.
- The dialog groups items by type with counts, shows "Hand everything to \[person\]" with the default selected, and lets each item be reassigned individually. The receiver must be an active member other than the person leaving.
- No items: the dialog says "Nothing to hand over." with a single Confirm button.
- On confirm, all changes and the triggering action run in one database transaction, the log records `handover.completed` with counts per type, and each receiver gets one email listing what they received.

## 9. Core engine: RACI+ tasks

RACI+ is the only task entity in the product; the Weekly Meeting, I-S.O.L.V.E, Missions, procedures, and events all create tasks here, and the list and board are two views of the same rows. Task comments and attachments are out of scope.

**T-01 Quick add.** From any task list, meeting, problem, or event, a one-line form creates a task with title (2–160), Responsible (default: me), output (2–280, "What will exist when this is done?"), and due date (default: today + 7 days in the organization's timezone). Accountable defaults to Responsible. Team defaults to the context's team, else none.

**T-02 Full form.** Adds Accountable (one member), Consulted and Informed (any number of members, no overlap with R or A), steps (ordered checklist, max 30, each 1–200 chars), definition of done, team, and board with column. Opening a quick-added task shows the full form.

**T-03 Required fields by source**

| Created from | Required |
| --- | --- |
| Anywhere (quick add) | Title, Responsible, output, due date |
| I-S.O.L.V.E Validate step | Also Accountable, definition of done, at least one step |
| Mission milestone "Create task" | Quick-add fields; due date cannot be after the Mission's quarter end |

**T-04 Status.** `todo` → `doing` → `done`, or `cancelled` from any open state; Responsible, Accountable, or the team leader may change it.

- Marking done with unchecked steps asks "2 steps aren't checked. Mark as done anyway?"
- `completed_at` is set on done and cleared on reopen; reopening returns the task to `todo`.
- Overdue = due date before today and status `todo` or `doing`; shown in oxblood with "3 days overdue".

**T-05 List view.** Tabs: Mine (I am R or A), Team (a selected team), All. Filters: status, due (overdue, this week, next week, later), person, team, source. Default sort: due date ascending, then created date. 25 rows per page.

**T-06 Board view.** Any member can create a board (title 2–120, optional team). New boards get columns To do, Doing, Done; Done is the board's single done column.

- Drag a card between columns with native HTML5 drag and drop; every card also has a "Move to…" menu for keyboard and touch users.
- Column determines status: the done column sets `done`; the first column sets `todo`; any other column sets `doing`. Changing status in the list moves the card to the first matching column.
- A task belongs to at most one board. Card shows title, Responsible initials, due date, and step progress "2/5".
- Columns: add, rename, reorder, delete when empty; 2–8 columns; exactly one done column.
- Archive a board: hidden from the board picker, its tasks stay in lists.

**T-07 Completion rate.** For a team and a week: tasks with due date in that week that reached `done` on or before the due date, divided by tasks with due date in that week that were not cancelled. Shown in the Weekly Meeting and on the team page, with 90% as the reference line.

**T-08 Emails.** Assigned as Responsible or Accountable by someone else, or added as Consulted or Informed: one email with title, output, due date, and link. Respects `email_opt_out`.

**T-09 Source link.** A task created from another item shows "From: {item type} · {item title}" linking back; the source item lists its tasks with status.

**T-10 Visibility.** Tasks are always visible to the whole organization. Creating one from a private event shows "Tasks are visible to everyone in your organization."

## 10. Core engine: Unified Calendar and guided events

The calendar holds every scheduled moment in the organization, and guided events replace what would otherwise be separate modules: an event type carries a built-in guide, a notes field, and a "Create task" button. Email reminders and calendar export (ICS) are out of scope because the host cannot run timed jobs reliably.

**C-01 Views.** Month (default on desktop), week, and agenda list (default under 768 px). Filters: Mine, a team, Organization. A toggle "Show due dates" overlays my tasks' due dates, Mission quarter ends, and milestone dates as all-day markers; these are computed, never stored as events.

**C-02 Event types**

| Type | Scope | Who creates | Guide | Notes visible to |
| --- | --- | --- | --- | --- |
| Custom | org, team, or private | org: leadership or Org Admin; team: team members; private: self | none | same as scope |
| Weekly Meeting | team | created by the team's meeting cadence (section 16) | none; opens the meeting runner | team page shows meeting notes |
| Quarterly session | team | leads(team) | agenda | all |
| Annual session | team | leads(team) | agenda | all |
| Quarterly Check-in | pair | a seat holder, with the holder of a direct child seat | yes | the two participants |
| Belief Shoutout | team or org | any member | yes | all |
| Heart & Head Reflection | private | self | yes | creator only |
| Passion-Performance Matrix | private | self | yes | creator only |
| Diagnostic, 360 | org | created automatically when a cycle opens | none; links to the cycle | all |

**C-03 Recurrence.** Rules: none, weekly, every 2 weeks, monthly (same day number, clamped to month end), quarterly (same week number of the next quarter). Optional end date. Occurrences are stored as rows 26 weeks ahead; the lazy scheduler job `calendar.extend` tops them up. Editing or deleting a recurring occurrence asks "This event" or "This and following events"; the second splits the series.

**C-04 Event panel.** Clicking an event opens a side panel (full screen on mobile) with time, scope, participants, the guide as numbered steps, a notes field (autosaved on blur, max 10,000 chars), and "Create task" (quick add with the event as source).

**C-05 Guide content.** Stored in `config/guides.php` as an ordered list of steps per type, each with a title and one or two sentences, written in plain original English (anti-slop copy rules). Required outlines:

1. Quarterly Check-in (45 minutes). Each person notes what's working and what isn't; review the person's Missions and Numbers; discuss where the Belief System shows in their work, with examples rather than scores; check the seat still fits (understands it, wants it, can do it); each side names one thing they'll do differently, and the leader asks "What should I do differently for you?"; turn commitments into tasks.
2. Belief Shoutout (10 minutes). Pick one belief; name the person and the specific thing they did; say why it matters; post it in the notes so it shows in the Weekly Meeting headlines.
3. Heart & Head Reflection (60–120 minutes, alone, no phone). Head: the most important problem in your area, and what you're avoiding. Heart: did I give clear direction, give people the tools they need, let them own the work, act for the whole team, and hold people to their commitments with respect? End with one decision written in the notes.
4. Passion-Performance Matrix (45 minutes). List what you did in the last two weeks; place each item in one of four boxes: love and great at, like and good at, don't like but good at, don't like and not good at; plan who could take the bottom two boxes; create tasks for the handoffs.
5. Quarterly session (one day). Check in; close last quarter's Missions as done or not done; review metric trends; reread the Fundamentals; set the new quarter's Missions; work the long-term Problem Queue; agree on the message to cascade.
6. Annual session (two days). Day 1: review the year, update the 3-Year Snapshot and the Annual Priority. Day 2: set first-quarter Missions and work the long-term Problem Queue.

**C-06 Private data.** Private events and their notes are filtered out of every query for anyone but the creator, including Org Admins and leaders; pair events are filtered to the two participants.

## 11. Direction: Fundamentals and Alignment Check

The Fundamentals are one versioned document per organization, edited as a draft by the Leadership Team and published as an immutable version; Alignment Check measures, anonymously, whether members find each part clear and commit to it. Add `currency CHAR(3) = 'IDR'` to `organizations` for every money field in the product.

**`fundamentals.content` JSON**

| Key | Shape | Limits |
| --- | --- | --- |
| `beliefs` | snapshot of `[{id, name, description}]` from the `beliefs` table at publish | 3–7 items; name 2–80, description up to 400 |
| `why` | text: why the organization exists | 10–300 chars |
| `way` | text: what it does better than anyone | 10–300 chars |
| `objective` | `{statement, target_year}` | statement 10–200; year 5–30 years ahead, default +10 |
| `main_stage` | `{target_market, uniques[3], proven_process[{title, description}], guarantee}` | market up to 1,000; exactly 3 uniques, each up to 120; 3–7 process steps; guarantee optional, up to 300 |
| `snapshot_3y` | `{target_date, revenue, profit, measurables[{label, value}], picture[]}` | up to 5 measurables; 3–15 picture lines, each up to 200, written in present tense |
| `annual` | `{target_date, revenue, profit, measurables[{label, value}], goals[]}` | up to 5 measurables; 3–7 goals, each up to 200 |

**D-01 Edit the draft** (leadership). One draft exists at a time; it starts as a copy of the latest published version. Fields autosave on blur. Beliefs are edited in place in the `beliefs` table (add, rename, reorder, archive); archiving never deletes past evaluation scores.

**D-02 Publish.** Validates every limit above, lists missing items inline ("Add 1 more unique"), then saves the draft as version N+1 with `status = published`. Published versions are read-only forever.

**D-03 View.** All members see the latest published version as two pages: Vision (beliefs, why and way, objective, main stage, 3-year snapshot) and Plan (annual priority, live list of active company Missions, live list of long-term company problems). A print stylesheet fits each page on one A4 sheet.

**D-04 History.** A version list with publisher and date; any version opens read-only. Changes between two versions show as a side-by-side view per part.

**Alignment Check**

**A-01 Open a round** (leadership). Picks the latest published version; `closes_at` defaults to 14 days later (range 3–30). Every active member gets an email and a Home banner until they answer or the round closes. Only one round can be open at a time.

**A-02 Answer.** For each of the six parts: "This is clear to me" (yes or no) and an optional comment (up to 1,000). Then once: "I'm committed to this direction" (yes or no). One submission per member per round, final once sent. The completion row and the answer rows are written in one transaction to separate tables (D15).

**A-03 Results.** Visible to all: responses / members at round open, percent clear per part, percent committed. Comments, shown without names and in random order, are visible to leadership, who can turn any comment into a long-term problem in the Leadership Team's Problem Queue. Under 3 responses the results carry "Fewer than 3 people answered, so answers may be identifiable."

**A-04 Completion.** Leadership sees who has and hasn't answered (names only, never answers) and can send one reminder email per round.

**A-05 Close.** Leadership can close early; the lazy scheduler closes rounds past `closes_at`.

**A-06 Trend.** A line chart of percent clear (average of six parts) and percent committed across all rounds.

## 12. Measurements: Metrics Dashboard and The Numbers

A metric is owned by a seat, belongs to a team, and is judged weekly against a goal with an explicit comparison operator; the goal in force is copied onto each saved value, so changing a goal never rewrites past weeks. Add `auto_problem_after TINYINT NULL = 2` to `metrics` (consecutive off weeks before a problem is created; NULL turns it off).

**Operators**

| Code | Shown as | On-track when | Goal inputs |
| --- | --- | --- | --- |
| `gte` | ≥ 40 | value ≥ goal | one |
| `lte` | ≤ 5 | value ≤ goal | one |
| `eq` | = 100 | value = goal | one |
| `gt` | > 0 | value > goal | one |
| `lt` | < 3 | value < goal | one |
| `between` | 20–30 | goal ≤ value ≤ goal\_max (inclusive) | two; goal must be less than goal\_max |

- Both sides are rounded to the metric's `decimals` before comparing, so `eq` works for decimals (49.995 with 2 decimals equals 50.00).
- No value yet for a week that has ended = **missing**: an empty cell with an oxblood dashed outline, counted in "needs attention" but never stored as off.
- A week that hasn't started cannot be entered.

**Units and formatting** (Intl.NumberFormat, locale `en-US`)

| Unit | Stored | Displayed |
| --- | --- | --- |
| `number` | 1500 | 1,500 |
| `percent` | 25 (meaning 25%) | 25% |
| `currency` | 1500000 | IDR 1,500,000 (organization currency) |
| `hours` | 2.5 | 2.5 h |

**M-01 Create or edit a metric** (leads(team) or Org Admin). Fields: name (2–120), description (optional, up to 500), owner seat (the team's head seat or one of its child seats), unit, decimals (0–2), operator, goal (and goal max for `between`), quarter roll-up (sum, average, latest), auto-problem threshold (off, 1–4 weeks). Live preview shows how the goal will read: "On track when ≥ 40". More than 15 active metrics in a team shows the hint "Most teams track 5–15 numbers."

**M-02 Grid.** Rows grouped by owner seat (holder name shown, or "Vacant"), drag to reorder. Columns: metric, goal, W1–W13 of the selected quarter, then the roll-up value. The current week's header is brass; a double bar line closes the quarter. A "Last 13 weeks" toggle shows the trailing 13 weeks across a quarter boundary, with the double bar between quarters. Cells: verdigris text when on, oxblood when off, dashed outline when missing; a dot marks a note.

**M-03 Enter values.** Click or focus a cell to edit inline. Enter saves and moves down, Tab saves and moves right, Escape cancels. Accepts digits, one decimal point, minus sign; strips thousand separators and the `%` sign. Saving snapshots the metric's current operator and goals onto the row and stores the status.

- The metric's owner may enter or change values in the current and previous quarter; the team leader may change any week.
- Each value may have a note (up to 280), added from the cell's menu.
- Clearing a cell deletes the value row.

**M-04 Goal changes.** Editing the goal affects weeks saved afterward. The edit dialog offers "Also apply to this quarter's saved weeks", which re-snapshots and recalculates those rows and logs it.

**M-05 Auto-problem.** After saving a value, if the last N saved weeks (N = `auto_problem_after`) are all off and the team has no open problem whose source is this metric, create one: title "{metric} off-track for N weeks", owner = owner seat's holder (or none if vacant), horizon short, source the metric.

**M-06 Metric detail.** Clicking a metric name opens a line chart of the last four quarters with the goal drawn as a line (or band for `between`), plus the list of notes.

**M-07 Archive.** Hides the metric from the grid and keeps all values; an "Archived" filter shows it again with "Restore".

**The Numbers**

**N-01 My Numbers.** Every member sees the metrics owned by seats they hold, across all teams, with a single "Enter this week" form: one field per metric, one submit (one request), designed for phones.

**N-02 Coverage.** Leaders see on Home: "2 people in your teams have no numbers yet", listing seat holders in their subtree who own no active metric.

## 13. Team: Personnel Evaluator

A superior rates a person against each belief and the three Seat Fit Check questions for one seat; the system computes whether the person is the right person and in the right seat using the organization's bar. Evaluations of external job candidates are out of scope.

**Rating scale**

| Value | Label shown | Meaning |
| --- | --- | --- |
| `plus` | Lives it | Shows this belief most of the time |
| `mixed` | Sometimes | Shows it inconsistently |
| `minus` | Rarely | Seldom shows it |

**Result rules**

- Right person = number of `minus` ≤ `evaluator_bar_max_minus` (default 0) and number of `mixed` ≤ `evaluator_bar_max_mixed` (default 1).
- Right seat = Understands the seat, Wants the seat, and Can do the seat are all yes.
- Result is one of: Right person, right seat · Right person, wrong seat · Wrong person, right seat · Wrong person, wrong seat. Only the first is shown in verdigris; the other three in ink with an oxblood marker.

**E-01 Evaluate.** Available to anyone holding an ancestor seat of one of the subject's seats. Choose subject and seat (if they hold several), rate every active belief (all required), answer the three Seat Fit Check questions (all required), add notes (optional, up to 2,000). The result appears live before saving.

**E-02 Team grid.** For leaders: rows are people in seats below theirs, columns are beliefs, the three Seat Fit Check answers, result, and evaluation date, showing each person's latest evaluation per seat. Filter by team. A line above the grid states the bar: "The bar: no Rarely, at most 1 Sometimes."

**E-03 Person history.** All evaluations of one person, newest first, with result and evaluator name. The subject sees their own history (D14); evaluator identity is visible here because a superior's rating cannot be anonymous.

**E-04 Edit window.** The author can edit an evaluation until the end of the day it was created (organization timezone); afterward it is permanent. A new evaluation supersedes it in the grid.

**E-05 Next steps.** A result other than "Right person, right seat" shows two buttons: "Schedule a Quarterly Check-in" (creates a pair event, prefilled) and "Create task".

**E-06 Belief changes.** Archived beliefs disappear from new evaluations; past evaluations still show their scores for them, labeled "Archived belief".

## 14. Resolution: Problem Queue and I-S.O.L.V.E

Every team has one Problem Queue split into short-term (worked weekly) and long-term (worked at quarterly sessions); anyone in the organization can add to any team's queue, and the team resolves it with I-S.O.L.V.E in quick or full mode.

**P-01 Raise a problem.** Fields: team (default: the user's first team, any team selectable), title (2–160), description (optional, up to 2,000), horizon (short or long, default short), owner (optional, a member of that team). Problems also arrive automatically from metrics (M-05), off-track Missions ("Raise problem" button), Alignment comments (A-03), and meeting headlines.

**P-02 Queue view.** Tabs: Short-term, Long-term, Solved, Dropped. Open items show rank, title, owner, source icon, and age ("opened 9 days ago"). Default order: rank, then oldest first.

**P-03 Prioritize.** Team members drag up to three problems into ranks 1, 2, 3; ranks are unique per team and horizon, and moving a fourth into the top three pushes rank 3 out. Ranks reset to empty after each Weekly Meeting ends.

**P-04 Maintain.** Team members can: switch horizon; move to another team's queue (logged, keeps history); drop with a required reason (up to 280); mark as a duplicate of another open problem, which drops it with the reason "Duplicate of {title}" linking to it.

**I-S.O.L.V.E**

**S-01 Start.** "Solve" on any open problem sets status `solving` and asks for the mode. Quick mode is for problems a team can settle in minutes; full mode for anything with a real cause to find. Quick can be upgraded to full at any point; the reverse is not allowed.

**S-02 Quick mode.** State (one sentence, 10–280), Solution (up to 2,000), tasks by quick add (zero or more). "Mark solved" sets status `solved`.

**S-03 Full mode.** A six-step stepper; each step saves on Next and can be revisited until solved.

| Step | Field | Prompt shown | Rule |
| --- | --- | --- | --- |
| 1. Ignite | `ignite` | "Who needs to be here, and how long will you give this?" | optional text, up to 500 |
| 2. State | `statement` | "Say the problem in one sentence. Not the symptom, not the fix." | required, 10–280 |
| 3. Observe | `observe` | "Ask why until you reach a cause you can act on." | required, up to 2,000 |
| 4. Leverage | `leverage` | "Which belief, procedure, or proven approach applies here?" | optional, up to 2,000; may link procedures |
| 5. Validate | `solution` + tasks | "What will you do, and who owns it?" | solution required; at least one task, created with the full RACI+ form (T-03) |
| 6. Evaluate | `evaluate_on`, later `evaluation`, `evaluation_result` | "Did it work?" | date required, default today + 14 days |

- Finishing step 5 sets the problem to `solved`; evaluation happens later.
- From `evaluate_on` onward, the problem appears under "To evaluate" in the team's Weekly Meeting and on its owner's Home until a result is saved: worked, partly, or failed, with a note.
- "Failed" offers "Raise it again", which creates a new open problem with the old one as its source.

**S-04 Solve during a meeting.** The Weekly Meeting's Solve segment (section 16) opens the same quick or full flow inline; problems solved there store `meeting_id`.

## 15. Procedures: Standardization and Synchronization Cycle

Standardization documents each core procedure at 20/80 depth in three steps and publishes it as a version; the Synchronization Cycle proves it is followed through acknowledgements, audits, and periodic reviews. Schema additions: `procedures.audience ENUM(team, organization) = team`, `procedures.reviewed_at NULL`, and a new table `procedure_versions (id, procedure_id, version, body MEDIUMTEXT, published_by (member), published_at; UNIQUE(procedure_id, version))`. `procedures.body` holds the working draft.

**PR-01 Library.** All members see procedures grouped by owning team, each with title, owner seat, current version, sync status, and next review date. The organization-wide count above 10 shows the hint "Most organizations run on 5–10 core procedures."

**PR-02 Create in three steps** (leads(owning team) or Org Admin).

1. Identify: title (2–160), purpose (10–500), owning team, owner seat (in that team), audience (the owning team and every team beneath it, or the whole organization), review every 3, 6, or 12 months.
2. Document and simplify: markdown body in a textarea with live preview (headings, lists, links; no images). A word counter shows the hint "Capture the 20% of steps that produce 80% of the result" above 1,200 words.
3. Package and roll out: preview as members will see it, then Publish.

**PR-03 Publish.** Copies the draft into `procedure_versions` as version N+1, sets `published_at` and `reviewed_at`, and emails everyone in the audience. Earlier versions stay readable from a version list.

**PR-04 Acknowledge.** Each audience member sees the procedure on Home under "To read" until they press "I've read this and will follow it", which writes `procedure_acks` for the current version. A new version requires a new acknowledgement.

**Synchronization Cycle**

**SY-01 Audit.** The owning team's leader or the owner seat's holder records an audit: date (not in the future), result (followed, partly, not followed), findings (required unless followed, up to 2,000). "Partly" or "not followed" offers "Raise problem" and "Create task".

**SY-02 Review.** When `reviewed_at` + review interval has passed, the procedure shows "Review due" and appears on the owner's and team leader's Home. Reviewing means publishing a new version or pressing "Still current", which sets `reviewed_at` to today.

**SY-03 Sync status.** A procedure is **In sync** when all three hold: every active audience member acknowledged the current version; the latest audit is within the review interval and its result is "followed"; the review is not overdue. Otherwise it is **Out of sync**, listing which condition failed ("4 of 9 haven't acknowledged v3").

**SY-04 Sync board.** One table for the organization: procedure, team, acknowledged (x of y), last audit (date and result), review due, status. Sortable by status.

## 16. Discipline: Missions and Meeting Cadence

Missions are 90-day priorities at company, team, and personal level, each with a clear definition of done; the Weekly Meeting runner is the place where metrics, Missions, tasks, and problems meet every week. Schema additions: `missions.status_updated_at NULL`, `missions.carried_from_id NULL`, and team cadence fields on the head seat: `seats.meeting_weekday TINYINT NULL`, `seats.meeting_time TIME NULL`, `seats.meeting_minutes SMALLINT = 90`.

**Missions**

**MI-01 Create.** Company level by leadership; team level by leads(team); personal level by the owner or anyone above their seat. Fields: title (5–160), definition of done (10–500, "How will everyone know it's done?"), owner (for team Missions, a member of that team), quarter (current or next), parent Mission (optional: team → company, personal → team or company), milestones (0–10, each with title and due date inside the quarter).

- More than 7 active Missions for one owner, team, or the company in a quarter shows "Fewer is better: most teams carry 3–7." Saving is still allowed.

**MI-02 Weekly status.** The owner sets On track or Off track; `status_updated_at` records it. A Mission not updated in the last 7 days shows "Not updated this week". Off track offers "Raise problem".

**MI-03 Close a quarter.** After a quarter ends, its open Missions show "Needs closing" on leaders' Home (never closed automatically). Closing asks Done or Not done for each; "Carry into next quarter" closes it as Not done and creates a copy in the next quarter with `carried_from_id`.

**MI-04 Views.** Filter by quarter, level, team, owner. A cascade view shows company Missions with their linked team and personal Missions indented beneath. Each Mission shows milestone progress ("3 of 5").

**Meeting Cadence**

**ME-01 Team cadence** (leads(team)). Pick weekday, start time, and length (60–120 minutes, default 90); saving creates or updates the team's weekly event series. Quarterly and annual sessions are scheduled as events (section 10).

**ME-02 Weekly Meeting runner.** Opened from the event or the team page. The team leader starts it; if the leader's seat is vacant, any team member can. Starting creates the `meetings` row and marks every team member present (toggle to absent).

| # | Segment | Default min | What happens |
| --- | --- | --- | --- |
| 1 | Check-in | 5 | Timer only; each person shares one personal and one work highlight |
| 2 | Metrics | 5 | The team grid for the current week; off and missing cells highlighted; "Drop to problems" per metric |
| 3 | Missions | 5 | Team and members' personal Missions; owners set on or off track; "Drop to problems" |
| 4 | Headlines | 5 | Add short customer or people news (up to 280 each); any headline can become a problem |
| 5 | Tasks | 5 | Tasks owned by team members due in the last 7 days or overdue; mark done; completion rate (T-07) |
| 6 | Solve | 60 | Short-term queue in rank order, plus "To evaluate"; solve with quick or full I-S.O.L.V.E |
| 7 | Wrap-up | 5 | Review tasks created today, write the cascade message, everyone present rates the meeting 1–10 |

- Segment minutes scale proportionally when the cadence length isn't 90; Solve always gets the remainder.
- Each segment shows a countdown; overtime shows "+2:15" in oxblood. Only the facilitator advances or goes back; the current segment is stored in `meetings.segment`.
- While a meeting is live, other participants' pages poll `GET …/meetings/{id}/state` every 15 seconds (paused when the tab is hidden) and update the segment and lists. No other page polls.
- Ratings: one per present member, editable until the meeting ends. The average is shown with one decimal.
- End meeting: sets `ended_at`, clears Problem Queue ranks for the team, and emails every team member a recap: attendance, average rating, tasks created, problems solved, and the cascade message.
- A weekly event that passes without a started meeting shows "Skipped" in the team's meeting history.

**ME-03 Meeting history.** Per team, newest first: date, duration, average rating, tasks created, problems solved; each opens a read-only recap. A chart shows average rating over the last 13 meetings.

## 17. Proprietary module: Company Diagnostic

Company Diagnostic is an anonymous survey of 24 statements, four per section, that scores the organization's health from 0 to 100 per section and tracks the scores across cycles. Results appear only after a cycle closes, so late answers cannot be inferred from changing totals.

**DG-01 Open a cycle** (leadership or Org Admin). Close date 7–30 days ahead (default 14). Creates an org-scope calendar event, emails all active members, and shows a Home banner until each member answers. One open cycle at a time; the screen suggests running one per quarter.

**DG-02 Answer.** 24 statements grouped by section, each on a 5-point scale: Strongly disagree (1), Disagree (2), Neutral (3), Agree (4), Strongly agree (5). All required; one submission, final. Completion and answers are written to separate tables in one transaction (D15).

**DG-03 Results** (after close, visible to all). Section score = (average − 1) ÷ 4 × 100, rounded to a whole number; overall = mean of the six section scores. Shows response count, a bar per section, the overall score, and the three lowest-scoring statements as "Biggest gaps". Fewer than 3 responses adds the D15 warning. Leadership can raise any section or statement as a long-term problem in the Leadership Team's queue.

**DG-04 Trend.** A line chart of the overall score and each section across all closed cycles, with the cycle close dates on the x-axis.

**Statements** (`config/diagnostic.php`, keys `d1`–`x4`; original wording)

| Key | Section | Statement |
| --- | --- | --- |
| d1 | Direction | Everyone here could explain where we're going in the next three years. |
| d2 | Direction | Our beliefs guide who we hire, reward, and let go. |
| d3 | Direction | We turn down opportunities that don't fit what we do best. |
| d4 | Direction | This year's priorities are written down and known by everyone. |
| m1 | Measurements | We review a small set of numbers every week. |
| m2 | Measurements | Every person in a seat owns at least one number. |
| m3 | Measurements | Our numbers warn us about problems before customers notice. |
| m4 | Measurements | The goal for each number is clear and agreed. |
| t1 | Team | Everyone knows who is accountable for what. |
| t2 | Team | People in key seats live our beliefs. |
| t3 | Team | People in key seats understand, want, and can do their roles. |
| t4 | Team | Leaders hold regular one-on-one check-ins with their people. |
| r1 | Resolution | We raise problems openly instead of working around them. |
| r2 | Resolution | When we solve a problem, it stays solved. |
| r3 | Resolution | Our leaders say what they really think in meetings. |
| r4 | Resolution | Every open problem has an owner. |
| p1 | Procedures | Our core procedures are written down. |
| p2 | Procedures | People follow our core procedures consistently. |
| p3 | Procedures | We review and improve our procedures on a schedule. |
| p4 | Procedures | A new hire could learn how we work from our documentation. |
| x1 | Discipline | Each team has three to seven clear priorities this quarter. |
| x2 | Discipline | Our weekly meetings start on time, follow one agenda, and end on time. |
| x3 | Discipline | Most commitments made in meetings are done by their due date. |
| x4 | Discipline | Each person knows how their work connects to company priorities. |

## 18. Proprietary module: Leadership Assessment 360

Leadership Assessment 360 rates each leader on organization-defined leadership types and items, by themselves and by several anonymous assessors; results appear only after the cycle closes and are tracked across cycles.

**LA-01 Setup** (leadership or Org Admin). Leadership types (name 2–80, ordered), items per type (statement 5–255, ordered), and five rubric levels with label and description. New organizations are seeded with levels 1 Not yet, 2 Developing, 3 Solid, 4 Strong, 5 Role model, and no types. Archiving a type or item hides it from new cycles only.

**LA-02 Create a cycle.** Name, opens on, closes on (after opens on). A matrix of members (rows) by types (columns) creates assignments by ticking cells; "Tick row" and "Tick column" shortcuts exist.

- Each assignment gets a default assessor set: the subject (self), the holder of the parent seat of each of their seats, holders of sibling seats in the same team, and holders of direct child seats.
- Leadership can add any active member as an assessor or remove one before they answer.
- Fewer than 3 non-self assessors shows the D15 warning beside the assignment.

**LA-03 Open.** On the open date (lazy scheduler) or by "Open now", status becomes `open`, assessors are emailed, an org-scope event is created, and each assessor's Home lists "Assessments to complete".

**LA-04 Answer.** Per assignment, every active item is rated from 1.00 to 5.00: pick a rubric level, or enter a custom value in steps of 0.25. All items required; one submission, final. `assessment_assessors.completed_at` and the answer rows (UUID keys, no timestamps, `is_self` set only for self) are written in one transaction; answer rows never reference the assessor.

**LA-05 Results** (after close). Visible per D14. For each subject: item score = mean of non-self answers; type score = mean of its items; overall = mean of type scores; all shown to two decimals, with self scores in a separate column and the gap (self − others) highlighted when it is 1.00 or more in either direction. Shows the number of non-self assessors and the D15 warning when under 3.

**LA-06 Trend.** Per person, a line chart of overall and per-type scores across closed cycles.

**LA-07 Changes after opening.** Assessors can be added while open; completed assessors cannot be removed; assignments cannot be removed once any answer exists; the cycle can be deleted only while no answer exists.

## 19. Navigation, Home, and email

Home answers one question for each person, "what needs me this week?", in a single request; email is used only for things a person must act on or would otherwise miss, and all of it goes through an outbox so no request ever sends more than 20 messages.

**H-01 Navigation.** Desktop: a left sidebar with Home, then the six sections as groups (two instruments each, in the order of section 3), then Tasks and Calendar, then Diagnostic and 360, then Teams; Org Admins also see People, Settings, Activity. Under 768 px: a bottom bar with Home, Tasks, Calendar, My Numbers, and Menu (the full list). The organization switcher and profile menu sit in the top bar.

**H-02 Home**, top to bottom; a block with nothing to show is omitted, never shown empty:

1. Action banners: an open Alignment round, Diagnostic cycle, or 360 assignment I haven't answered; procedures I haven't acknowledged.
2. This week: my numbers not yet entered (with "Enter this week"), my tasks overdue or due this week, my Missions not updated in 7 days.
3. Meetings: the next meeting of each of my teams, with Start or Join when live.
4. Leaders only, for their subtree: off-track and missing metrics, problems to evaluate, Missions needing closing, reviews due, vacant seats that own items, people without numbers.
5. Today: my calendar items for today.

A brand-new organization instead sees a setup checklist: add seats, invite people, set the quarter start date, write the Fundamentals, add metrics, set a meeting cadence. Each item links to its screen and ticks itself when done.

**H-03 Email outbox.** Add table `email_outbox (id, organization_id NULL, to_email, subject VARCHAR(200), template VARCHAR(60), data JSON, attempts TINYINT=0, last_error VARCHAR(255) NULL, sent_at NULL, created_at)`. Every email is inserted here. After the response is built, the same request sends up to 20 unsent messages under a cache lock; failures retry on later requests up to 3 attempts. Super Admin Maintenance shows the unsent count.

**H-04 Email catalogue**

| Trigger | Recipients | Ignores opt-out |
| --- | --- | --- |
| Verify email, password reset, email change notice | The user | yes |
| Invitation | Invitee | yes |
| Organization approved, rejected, suspended | Org Admins | yes |
| New self-signup awaiting approval | Super Admins | yes |
| Handover received | Each receiver | yes |
| Task assigned (R, A, C, I) | The assigned member | no |
| Procedure published | Audience | no |
| Alignment round, Diagnostic cycle, 360 cycle opened | Members asked to answer | no |
| Alignment reminder | Members who haven't answered | no |
| Weekly Meeting recap | Team members | no |

Emails are plain HTML with the product's paper and ink colors, one heading, two to four short lines, and one button; subject lines are sentence case with no exclamation marks.

## 20. Non-functional requirements

Security and tenant isolation outrank every other requirement; after that, the product must stay fast on free hosting, accessible at WCAG 2.2 AA, and consistent in states and copy on every screen.

**Security**

- Tenant isolation: a global scope on every tenant model plus scoped route-model binding (`/o/{slug}/metrics/{metric}` resolves only inside that organization). Each model gets a test proving another organization's id returns 404.
- Every controller action calls a Policy backed by the `Access` service (section 5); the permission matrix is covered by tests, one per row and role.
- Models declare `$fillable` explicitly; `organization_id`, owner, and status fields are never mass-assigned from the request.
- Markdown (procedures) is rendered server-side with `league/commonmark`, `html_input: strip`, `allow_unsafe_links: false`.
- Sessions: encrypted, `HttpOnly`, `Secure` on HTTPS, `SameSite=Lax`, regenerated at sign-in. Passwords: bcrypt.
- Headers: Content-Security-Policy allowing self plus `fonts.googleapis.com` and `fonts.gstatic.com`; `X-Frame-Options: DENY`; `X-Content-Type-Options: nosniff`; `Referrer-Policy: strict-origin-when-cross-origin`; HSTS when served over HTTPS.
- Anonymity (D15): answer tables have no member column, no timestamps, UUID keys; a test asserts their schema contains no column that references `memberships` or `users`.
- Root `.htaccess` denies `.env`, `composer.*`, `storage/`, `vendor/`, `database/`, `tests/`.

**Performance**

- 95% of requests under 500 ms on InfinityFree with 20 users; JavaScript under 300 KB gzipped on first load, split per page.
- `Model::preventLazyLoading()` in local and test environments; lists paginate at 25.
- Hit budget estimate: 20 users × 150 requests a day = 3,000, plus a 90-minute meeting with 8 people polling every 15 s = about 2,900; well under 50,000.

**Accessibility** (WCAG 2.2 AA)

- Contrast of every token pair checked (see `DESIGN.md`); never use color alone for status: off-track also carries an icon and accessible label.
- Everything works by keyboard, including a "Move to…" alternative for every drag action; visible brass focus ring.
- Touch targets at least 44 × 44 px; form fields have visible labels; the meeting timer announces each new segment once via `aria-live="polite"`, not every second.
- `prefers-reduced-motion` disables transitions.

**States on every screen**

| State | Rule |
| --- | --- |
| Loading | Skeleton rows in the final layout; no spinners over 300 ms |
| Empty | An invitation: a heading naming the space, one sentence, one action ("Add your first metric") |
| Error | What happened and what to do ("Couldn't save. Check your connection and try again."), keeping the user's input |
| Validation | Message under the field, field outlined in oxblood, focus moves to the first error |
| Destructive action | Confirmation naming the item ("Archive Qualified leads?") |
| Autosave | A quiet "Saved" next to the field for 2 s |

**Responsive.** Every screen works at 360 px wide. Wide tables scroll inside their own container; the metrics grid keeps the metric column sticky.

**Copy.** Sentence case everywhere; no "successfully", "please", or exclamation marks; buttons start with a verb; dates as "Mon, 9 Nov 2026"; times in 24-hour format; numbers in `en-US` format.

**Visual design.** Governed by `DESIGN.md`: paper `#FAF7F0`, ink `#1D2027`, staff line `#E3DCCB`, brass `#7A5718` (the only action color), verdigris `#2F6F62` (on track), oxblood `#8E2F2F` (off track); Instrument Serif for page titles, Instrument Sans for everything else with tabular figures; 4 px radius; hairline borders instead of shadows; dials Energy 2, Rhythm 3, Motion 1. Light mode by default, dark mode through the same tokens, switchable in the profile.

## 21. Build phases and test gates

Claude Code builds in six phases; a phase is finished only when its gate passes locally and on InfinityFree, and no work on the next phase starts before that. Phases order the build; they do not cut scope (D16).

| Phase | Builds | Gate (all must pass) |
| --- | --- | --- |
| 0. Foundation | Walking skeleton on InfinityFree; auth; organizations; memberships; invitations; self-signup with approval; Super Admin console and Maintenance; settings; activity log; email outbox; lazy scheduler; `Access` service and tenant scope; design tokens, layout, navigation; Home setup checklist | Deployed and reachable; email delivered from production via SMTP; migrations run from Maintenance; cross-tenant 404 tests pass; last-admin rule tested |
| 1. Structure and engine | Winning Formation; derived teams; Handover; RACI+ list and board; Unified Calendar with recurrence; guided events and `config/guides.php` | Handover tested for every trigger and item type; team derivation tested for add, move, delete, dissolve; board status mapping tested; recurrence split tested; private events invisible to Org Admin in tests |
| 2. Weekly rhythm | Metrics Dashboard and The Numbers; Missions; Problem Queue and I-S.O.L.V.E; Meeting Cadence and Weekly Meeting runner; Home blocks | Every operator tested, including rounding, `between` edges, and missing weeks; goal snapshot tested; auto-problem tested; one end-to-end meeting test from start to recap in the outbox; one real team runs a Weekly Meeting on production |
| 3. Direction and procedures | Fundamentals with versions; Alignment Check; Standardization; Synchronization Cycle | Publish validation tested; anonymity schema test passes; sync status rules tested for each failing condition |
| 4. People and proprietary | Personnel Evaluator; Company Diagnostic; Leadership Assessment 360 | Visibility tested for subject, ancestor, Org Admin, and an unrelated member; results hidden before close; score formulas tested with fixed data |
| 5. Hardening | Accessibility pass; anti-slop audit in "after" mode; performance check against section 20; security headers check; `RUNBOOK.md` with deploy and backup steps | Zero high-priority audit findings open; every page usable at 360 px; backup and restore rehearsed once |

**Definition of done for every story**: all acceptance criteria met; feature tests for its rules and permissions; loading, empty, and error states built; works at 360 px; copy follows section 20; no lazy-loading violations; nothing from the out-of-scope list.

**Files that travel with this PRD**: `CLAUDE.md` (working rules for Claude Code: stack, hosting limits, the order ponytail → anti-slop → `DESIGN.md` → UI/UX Design Pro, and "never use EOS trademarks"), `DESIGN.md` (tokens, type scale, components, dials), and later `RUNBOOK.md`.

## 22. Assumptions and open questions

The build can start on Phase 0 with the assumptions below; the open questions must be answered before the phase named in each.

**Assumptions**

- New organizations default to currency IDR, timezone `Asia/Jakarta`, week starting Monday.
- No user-uploaded files anywhere in v1; procedures and tasks use links.
- Weekly Meeting segment names are Check-in, Metrics, Missions, Headlines, Tasks, Solve, Wrap-up.
- Alignment comments are visible to leadership only, while aggregates are visible to all.

**Open questions**

- [ ] Is "The Harmonic System" free to register at PDKI in classes 9, 35, and 42? (before public launch)
- [ ] Which SMTP provider and sender address, with SPF and DKIM set up? (Phase 0)
- [ ] Own domain or InfinityFree subdomain, and is free SSL enabled? (Phase 0)
- [ ] Should self-signup ask for extra vetting fields such as company size or website? (Phase 0)
- [ ] Should Alignment comments be visible to everyone instead of leadership only? (Phase 3)
