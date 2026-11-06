# Rules for AI tools working on ZKs-RMS

Applies to any AI assistant (chat, IDE or agent) used on this repo.

1. **Read first, in this order:** `README.md`, `docs/PROJECT-LOG.md`, `docs/ZKs_RMS_System_Context.md`. Do not ask for things already written there.
2. **The System Context wins** over the docx, pptx, wireframes, older notes and this file's summaries. The Figma wireframes are a draft, not authoritative.
3. **Never invent** features, roles, fields, labels, prices, numbers or contact details. If something is missing, write TBD and ask the person.
4. **Design principle: responsible, not over-engineered.** A feature exists only if it solves a problem the client named. The "NOT building" list in the log is binding (no Staff/Manager roles, payment gateway, SMS/email/push, password reset, deleting users, editing guest info, AI that books or reads guest data, revenue analytics, and so on).
5. **Exact status words:** Slot Held, Verifying Payment, Confirmed, Action Needed, Expired, Cancelled. Never "Pending", "Approved", "Rejected" or variants.
6. **Business rules and schema changes need team agreement first.** Propose the change, wait for a yes, then log the decision in `docs/PROJECT-LOG.md`. UI/UX and security hardening that change no rule do not need that.
7. **One small step at a time.** State the plan in a few lines before building. Read the code you will touch first.
8. **Tests:** run `php artisan test` (plain, read the last line). Add or update tests with every change. Call something "verified" only when a person says they tested it; otherwise write "built, untested".
9. **Security:** payment proofs stay private and Admin-only; query date columns with `whereDate`; reservation logic goes through `ReservationService`; log and notify only through `ActivityLogger` and `Notifier`. Never write passwords, API keys, `APP_KEY`, tokens or real guest data in code, docs, tests, logs or commits. Never commit `.env`.
10. **Update `docs/PROJECT-LOG.md`** in the same change: edit in place, keep it short (latest 3 entries in full), no diaries, no new "Next steps" headings.
11. Do not copy decisions from other projects (for example the blotter system) into this one.
12. Use Laravel 12, PHP 8.2, Blade + Tailwind 4 + plain JS. Do not add packages or a JS framework without team agreement.
