# ZKs-RMS Defense Checklist

Setup: fresh install, `php artisan migrate:fresh --seed`, `php artisan db:seed --class=DemoSeeder`, `php artisan serve` and `php artisan schedule:work` running.

## Guest path (log in as a demo guest, password `password`)
- [ ] Home and Availability open without logging in; the calendar shows only Available / Reserved.
- [ ] Pick an open date while logged out: sent to log in, then back to the form.
- [ ] Register a new guest (contact number required).
- [ ] Submit a reservation: date, duration, Booking Type, pax, occasion. Result: **Slot Held** with a 24-hour countdown.
- [ ] Upload a GCash screenshot: **Verifying Payment**.
- [ ] Open My Reservations; see the status badge in plain words.
- [ ] Bell shows status changes; "No notifications yet." when empty.
- [ ] Ask the chat "how do I pay by GCash?" and "magkano?" (answers from the FAQ, says it cannot book or change anything).

## Admin path (log in with the `.env` Admin)
- [ ] Dashboard: Verifying Payment count first, filter by status, search by guest name.
- [ ] Open a reservation, view the proof, **Confirm payment** (Confirmed) or **Flag proof** (Action Needed); the guest re-uploads and the hold stays.
- [ ] Approve and decline a reschedule request; approve a cancellation (date is released).
- [ ] Enter a walk-in: Confirmed at once, no hold, no account.
- [ ] User List: search, deactivate, reactivate. A deactivated guest is logged out and cannot book.
- [ ] Activity Log: filters by date, type, name; actor shows Guest / Admin / System.
- [ ] Reports: pick a year; bookings, walk-ins, dates booked, occupancy.

## Edge cases to show
- [ ] **Hold expiry:** a Slot Held reservation with no proof becomes Expired after 24 hours and the date reopens by itself (scheduler running).
- [ ] **Date taken meanwhile:** two guests on the same date; the second is told the date was just taken.
- [ ] **One group per date:** a reserved date blocks every Booking Type.
- [ ] **Pax caps:** Transient 7, Room 12; Pool and Venue Hall have no limit set yet.
- [ ] **Notice window (5 days):** a reschedule or cancel request closer than that is blocked; the guest is told to contact the resort. Admin may still cancel and mark the downpayment forfeited.
- [ ] **Action Needed never expires.**
- [ ] A guest cannot open another guest's reservation (403); the Admin proof URL is refused for guests.
- [ ] Chat still answers with no AI key (keyword fallback).

## What the system deliberately does not do
No online payment gateway, no SMS/email/push, no password reset or email verification, no deleting or editing guests, no Staff/Manager roles, no per-room or partial booking, no revenue analytics, no payment amounts stored, no AI that books or reads guest data. Each one is a decision, not a gap: the design is *responsible, not over-engineered*, and every feature solves a problem the client named.
