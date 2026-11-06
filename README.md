# ZKs-RMS

Reservation Management System for ZKs Private Resort. One shared, real-time calendar that replaces Messenger, the paper logbook, the wall calendar and GCash screenshots.

Built for Assessment A2 (PCCBSIT 006), Group 04, BSIT 3A. Stack: Laravel 12, PHP 8.2, MySQL, Blade + Tailwind 4 + a little plain JS (Vite). Auth is hand-written (no Breeze).

**Read first:** `docs/PROJECT-LOG.md` (what is built and every locked decision) and `docs/ZKs_RMS_System_Context.md` (the source of truth; it wins over everything else). AI tools: also read `AGENTS.md`.

## Setup (Windows + XAMPP + PowerShell)

1. Start **MySQL** in XAMPP (Apache is not needed). Create an empty database named `zks_rms` (phpMyAdmin).
2. Clone the repo and open the folder in VS Code.
3. In PowerShell:
   ```
   composer install
   npm install
   copy .env.example .env
   php artisan key:generate
   ```
4. Edit **your own** `.env` (it is never committed): set `DB_CONNECTION=mysql`, `DB_DATABASE=zks_rms`, `DB_USERNAME=root`, `DB_PASSWORD=` (XAMPP default), and your own `ADMIN_EMAIL` and `ADMIN_PASSWORD`.
5. Create the tables and the Admin, then the demo data:
   ```
   php artisan migrate --seed
   php artisan db:seed --class=DemoSeeder
   npm run build
   ```
6. Run it (each in its own terminal tab):
   ```
   php artisan serve
   php artisan schedule:work
   ```
   Open http://127.0.0.1:8000.

**Why `schedule:work`?** The 24-hour hold expires, and the "hold expires in 2 hours" nudge is sent, by scheduled commands that run every minute. Without that terminal, holds never expire locally. Online this needs a real cron: `* * * * * php artisan schedule:run`.

**Demo logins:** the Admin is the email and password you put in `.env`. Demo guests are `ana@demo.zks.test`, `ben@...`, `carla@...` and so on, all with the password `password`. Demo data is for local use only; the seeder refuses to run in production and refuses to run twice (start over with `php artisan migrate:fresh --seed`).

## Your own settings (each person)

- `.env` is yours. Never paste it, commit it or share it (it holds `APP_KEY`). Never run `php artisan key:generate` again once real accounts exist.
- **Chat AI (optional):** create your own free Google AI Studio key and set `CHAT_BASE_URL`, `CHAT_MODEL`, `CHAT_API_KEY` in your `.env` (examples in `.env.example`). Do not share one key between people. With no key the chat still answers from the built-in keyword matcher. If it falls back every time, check the model name against the provider's current list.
- **Resort contact and GCash number:** `RESORT_CONTACT` and `RESORT_GCASH` in `.env`. Empty means the FAQ and chat say "contact the resort". Real numbers never go in git.
- After changing `.env` or dropping files in: `php artisan optimize:clear` (MySQL must be running). Do not run `config:cache` or `route:cache` locally.

## Tests

```
php artisan test
```

Read the last line. Tests use in-memory SQLite and fake the chat provider, so they need no MySQL, no build and no key. All tests must pass before a pull request.

## Gotchas

- Query date columns with `whereDate(...)`, never `where(col, 'Y-m-d')` (SQLite stores `2026-11-15 00:00:00`, MySQL plain dates).
- Payment proofs are private: stored on the private disk and shown only through `/admin/proofs/{proof}` (Admin only). Never link them from a public path.
- Never name a test helper `status()`. `actingAs()` stays logged in for the rest of a test, so do logged-out checks first. Keep page-wide extra text out of every page or older `assertDontSee` tests fail. Use one `Http::sequence()`, not two `Http::fake()` calls.
- If tests say a route is not defined, a stale `bootstrap/cache/*.php` is the cause: `php artisan optimize:clear`.
- After changing views or CSS: `npm run build` (or keep `npm run dev` running).
- Status words are exactly: Slot Held, Verifying Payment, Confirmed, Action Needed, Expired, Cancelled. Never "Pending", "Approved" or "Rejected".

## Folder map

| Path | What is there |
|---|---|
| `app/Services/ReservationService.php` | All reservation rules (hold, proof, confirm, flag, cancel, reschedule, walk-in, expiry). Start here for business logic. |
| `app/Services/ActivityLogger.php`, `Notifier.php` | The only places that write the activity log and in-app notifications. |
| `app/Services/ChatService.php`, `app/Support/Faq.php` | The chat AI and the single FAQ source (built from `config/zk.php`). |
| `app/Services/ReportService.php` | Reports counts. |
| `app/Http/Controllers/` (`Admin/` inside) | Thin controllers. `routes/web.php` shows every route and its middleware. |
| `app/Enums/` | Statuses, booking types, durations, roles. |
| `app/Console/Commands/` | `reservations:expire-holds`, `reservations:send-hold-reminders`. Scheduled in `routes/console.php`. |
| `config/zk.php` | Pax caps, hold hours, notice window, contact, chat settings. One place for every business number. |
| `resources/views/` | Blade pages. `components/layouts` has the app and auth layouts. |
| `resources/js/` | `chat.js`, `faq-match.js`, `app.js`. |
| `database/seeders/` | `AdminSeeder` (from `.env`), `DemoSeeder` (fake data, run on purpose). |
| `tests/` | Feature tests per area. |
| `docs/` | Project log, System Context, defense checklist. |
| `tools/make-zk-zip.bat` | Optional helper, see below. |

## How to change things

- **UI and UX tweaks** (layout, wording that is not a rule, spacing, colors, accessibility, security hardening that changes no rule): go ahead, open a pull request.
- **Business rules or the database schema** (statuses, caps, notice window, who can do what, new fields, new features): needs agreement from the team first, then a logged decision in `docs/PROJECT-LOG.md`. The design principle is *responsible, not over-engineered*: a feature exists only if it solves a problem the client named. If something is missing, write TBD and ask.
- The System Context wins when documents disagree.

## Branch and pull request workflow

1. `git switch main`, `git pull`, then `git switch -c short-topic-name`.
2. Make the change, run `php artisan test`, click through it in the browser.
3. Update `docs/PROJECT-LOG.md` in the same change (what changed; mark things verified only after you tested them).
4. Push the branch, open a pull request, fill in the checklist. The developer reviews and merges.
5. Nobody pushes directly to `main`. (GitHub can enforce this with branch protection, but on a private repo that may need a paid plan. If it is not available, this is a group rule.)

## Optional: `tools\make-zk-zip.bat` and AI chats

The helper makes `zk-clean.zip` in Downloads (no `vendor`, `node_modules`, `.git`, any `.env` except `.env.example`, logs, uploaded proofs, caches, lock files). It never changes your project folder, and it stops if it finds a `.env` in the copy. Tips to save AI tokens: upload the zip **once per chat**, then send only the files that changed; give the AI `docs/PROJECT-LOG.md` first so it does not ask for things already written there.

## Still waiting on the client

Venue Hall pax cap, notice window (3 or 5 days, one number in `config/zk.php`), the real contact and GCash numbers. Until they are set the FAQ and chat say "contact the resort". Before going online: use a strong `ADMIN_PASSWORD` (set it in `.env`, run `php artisan optimize:clear`, then `php artisan db:seed`), set `APP_ENV=production` and `APP_DEBUG=false`, and set up the cron above.
