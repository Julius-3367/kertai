# Kertai

Group management for **KERTAI006 Self Help Group**: contributions, the merry-go-round, welfare, projects (coffee seedlings, sugarcane, tent and chair hire), fundraisers with a public M-Pesa page, meetings, and the books behind all of it.

Members sign in with their phone number. Every shilling is posted to a double-entry ledger, every payout needs two officials, and every important action is in an audit log nobody can edit.

## Contents

- [What it does](#what-it-does)
- [Who can do what](#who-can-do-what)
- [How money moves](#how-money-moves)
- [Running it locally](#running-it-locally)
- [Going live](#going-live)
- [Connecting SMS, email and M-Pesa](#connecting-sms-email-and-m-pesa)
- [Daily operations](#daily-operations)
- [Tests](#tests)
- [Code layout](#code-layout)
- [Still needed from the officials](#still-needed-from-the-officials)

## What it does

| Area            | What members and officials get                                                                                                                                                                                                                                                                                                                                          |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Members         | Register with nominee and welfare beneficiaries (spouse, children, parents). Phone or email login issued by the secretary with a one-time password sent by SMS. Exit with one month's notice; savings refunded less debts. Deaths open a welfare case and pay savings to the nominee.                                                                                   |
| Positions       | Chairperson, secretary, treasurer, coordinator, project coordinator, auditor and system administrator out of the box. Vice and other positions are created by copying one and adjusting its permissions. Terms have dates; acting appointments lapse on their own.                                                                                                      |
| Contributions   | Monthly dues raised automatically on the 1st. Each contribution fills the merry-go-round pot first, then welfare, then savings. Overpayments are held and used next month. Late fines are charged once after the grace period. Fines can be waived by the chair with a reason.                                                                                          |
| Payments        | M-Pesa prompts through SimulinkPay (members pay from the dashboard), paybill 247247 statement import (Equity or M-Pesa CSV) with automatic matching, cash at meetings, bank deposits. Unmatched lines wait for the treasurer. Wrong postings are reversed, never edited.                                                                                                |
| Payouts         | Every shilling out needs two approvals from different officials. The requester and the payee can never approve. Bulk approval for share-out batches. The treasurer marks it paid with the M-Pesa or bank reference, which moves the ledger.                                                                                                                             |
| Merry-go-round  | Ballot with a recorded seed anyone can re-run. Monthly turns. Members in arrears move to the back. Swaps need the other member's consent and the chair's approval.                                                                                                                                                                                                      |
| Welfare         | Cases for members and their families, default benefits per event, optional levy on all other members, payout from the welfare fund.                                                                                                                                                                                                                                     |
| Fundraisers     | Public page at `/f/<name>` with target, progress, an M-Pesa prompt and a list of supporters who agreed to be named. Pledges, CSV export, its own fund.                                                                                                                                                                                                                  |
| Projects        | Each project has its own fund, participants, contributions, income, expenses (as payouts), stock and sales. Profit is shared into members' savings by contribution or equally.                                                                                                                                                                                          |
| Assets and hire | Tents, chairs and equipment. Hire rates can be left blank until agreed. Bookings cannot overlap beyond what the group owns. Hire income goes to the assets fund.                                                                                                                                                                                                        |
| Meetings        | Notices by SMS and email, attendance, minutes, numbered resolutions, cash collection during the meeting. Online meetings: paste a Google Meet (or Zoom/Teams) link; members get a Join button, the link in their SMS, an "Add to Google Calendar" button and a calendar file with reminders a day and an hour before, and a reminder SMS on the morning of the meeting. |
| Messages        | Every SMS and email logged with delivery status and retry. Editable templates in English and Kiswahili. Members can switch off reminders but not receipts. Members text `BAL` to get their balances.                                                                                                                                                                    |
| Reports         | Fund movements, trial balance, contribution grid, arrears (CSV), member statements (screen, PDF, CSV), annual AGM report.                                                                                                                                                                                                                                               |
| Administration  | Group rules (each change tied to a resolution number), provider keys stored encrypted, audit log, data export, document vault and compliance calendar, December share-out.                                                                                                                                                                                              |

It installs on a phone as an app (PWA) and works on slow connections.

## Who can do what

Members always see their own money, pay, request merry-go-round swaps and read meetings and documents. Positions add the rest:

| Permission                           | Chair | Secretary | Treasurer | Coordinator | Project coord. | Auditor | System admin |
| ------------------------------------ | :---: | :-------: | :-------: | :---------: | :------------: | :-----: | :----------: |
| View register                        |   ✓   |     ✓     |     ✓     |      ✓      |       ✓        |    ✓    |      ✓       |
| Add / edit members, issue logins     |       |     ✓     |           |             |                |         |      ✓       |
| View finances and reports            |   ✓   |     ✓     |     ✓     |             |                |    ✓    |              |
| Record payments, import statements   |       |           |     ✓     |             |                |         |              |
| Raise dues and fines                 |       |           |     ✓     |             |                |         |              |
| Waive fines                          |   ✓   |           |           |             |                |         |              |
| Request payouts                      |       |           |     ✓     |             |       ✓        |         |              |
| Approve payouts                      |   ✓   |     ✓     |     ✓     |             |                |         |              |
| Mark payouts paid, ledger transfers  |       |           |     ✓     |             |                |         |              |
| Merry-go-round (run / approve swaps) | swaps |           |    run    |             |                |         |              |
| Welfare                              |   ✓   |     ✓     |     ✓     |             |                |         |              |
| Fundraisers                          |   ✓   |           |     ✓     |      ✓      |                |         |              |
| Projects (view / manage)             | view  |   view    |   view    |    view     |     manage     |  view   |              |
| Assets and hire                      |       |           |     ✓     |             |       ✓        |         |              |
| Meetings, messages, documents        |   ✓   |     ✓     |           |      ✓      |                |         |   messages   |
| Group rules, positions               |   ✓   |           |           |             |                |         |  positions   |
| Provider keys, backups               |       |           |           |             |                |         |      ✓       |
| Audit log                            |   ✓   |           |           |             |                |    ✓    |      ✓       |

The system administrator can never be given a money permission. Any position can be changed under **Positions**.

Payouts the rules produce (a merry-go-round turn, the share-out, an exit refund) have no requester, so any two officials other than the payee can approve them. A payout someone asks for (an expense, a welfare benefit) cannot be approved by the person who asked. If an official is the payee of a requested payout and only one other approver is left, appoint an acting or vice official to approve it.

## How money moves

Two kinds of ledger account:

- **Where the money is**: Equity Bank (paybill 247247), SimulinkPay M-Pesa collections, cash on hand.
- **What it is for**: member savings (per member), member prepayments (per member), merry-go-round pot, welfare fund, assets and hire fund, group fund, and one fund per project and per fundraiser.

The two totals are always equal; the ledger page shows a "Books balance" badge. Money is stored as whole cents. Entries are never edited or deleted. A mistake is corrected with a reversal that stays on record.

A KES 1,000 contribution paid by M-Pesa posts:

| Account                        | Debit | Credit |
| ------------------------------ | ----: | -----: |
| SimulinkPay M-Pesa collections | 1,000 |        |
| Merry-go-round pot             |       |    500 |
| Welfare fund                   |       |    200 |
| Member savings (that member)   |       |    300 |

The split, due day, fine, benefits and approval count are set under **Group rules**.

Payouts are recorded, not sent: SimulinkPay collects money but has no API for sending it, so the treasurer pays by M-Pesa or bank and enters the reference.

## Running it locally

Needs PHP 8.4+ (with `pdo_sqlite`, `zip`, `intl`, `gd`), Composer and Node 22.

```bash
git clone https://github.com/Julius-3367/kertai.git
cd kertai
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed      # demo group with 16 made-up members
npm install
composer run dev                # app on http://localhost:8000, queue and Vite included
```

Demo logins (password `password`): `0712000001` chair, `0712000002` secretary, `0712000003` treasurer, `0712000004` coordinator, `0712000005` project coordinator, `0712000006` auditor, `0712000007` system administrator, `0712000008` ordinary member.

With `PAYMENTS_DRIVER=fake`, M-Pesa prompts succeed when checked, except phones ending `0000` (no funds), `1111` (cancelled) and `2222` (timeout), the same rule SimulinkPay's own fake uses. SMS and email go to `storage/logs/laravel.log`.

To develop against PostgreSQL and Redis as in production: `docker compose up -d` and follow the comment at the top of `docker-compose.yml`.

## Going live

On the server (Ubuntu with Nginx, PHP-FPM 8.4+, PostgreSQL, Redis, Supervisor):

```bash
git clone https://github.com/Julius-3367/kertai.git /var/www/kertai
cd /var/www/kertai
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env    # set APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, QUEUE_CONNECTION=redis, CACHE_STORE=redis
php artisan key:generate
php artisan migrate --force
php artisan kertai:install --group="KERTAI006 Self Help Group" --name="<chair's full name>" --phone="<chair's phone>"
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`kertai:install` creates the group, accounts, positions and compliance calendar, and gives the first official the chair and system administrator positions. It prints a one-time password; they change it at first sign-in, then add everyone else from **Members**. Do not run `db:seed` on the live server; it loads demo data (and refuses to in production).

Background work:

```ini
# /etc/supervisor/conf.d/kertai.conf
[program:kertai-queue]
command=php /var/www/kertai/artisan queue:work redis --tries=3 --max-time=3600
user=www-data
autorestart=true
```

```cron
* * * * * www-data cd /var/www/kertai && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs, in Nairobi time: `kertai:daily` at 07:30 (raise dues, reminders, late fines, arrears nudges on Mondays, compliance reminders), `kertai:reconcile` every five minutes (asks SimulinkPay about prompts with no answer and expires them after an hour), `kertai:backup` at 02:15 (CSV export kept 30 days).

Also back up the database itself every night, for example `pg_dump -Fc kertai > /backups/kertai-$(date +%F).dump`, and copy it off the server.

After each deploy: `php artisan migrate --force`, rebuild the caches, then restart the queue worker (`php artisan queue:restart`).

## Connecting SMS, email and M-Pesa

Everything is set under **Administration → Integrations** (system administrator). Keys are encrypted with `APP_KEY`; secret fields are never shown again. Each section has a **Test** button. The same values can be set in `.env` instead (see `.env.example`); saved values win.

**SMS**: Onfon Media (default), Africa's Talking, Advanta, or any provider with a JSON HTTP API (enter the URL, headers and a body template using `{to}`, `{message}` and `{sender}`). Register the sender name `KERTAI006` with the provider. For `BAL`, set `INBOUND_SMS_TOKEN` in `.env` and point the provider's inbound URL at the address shown on the Integrations page.

**Email**: SMTP, Mailgun, Postmark, or Amazon SES (SMTP credentials). Without a saved provider, Laravel's `MAIL_*` settings are used.

**M-Pesa through SimulinkPay**:

1. In the SimulinkPay merchant portal, add and verify a receiving account in the group's name. Note its ID.
2. Create an API token with `payments:read` and `payments:write`.
3. Register a webhook endpoint at `https://<your domain>/webhooks/simulinkpay` for `payment.succeeded`, `payment.failed` and `payment.pending`. Copy the `whsec_...` secret (shown once).
4. Enter the base URL, token, receiving account ID and webhook secret under Integrations, and choose SimulinkPay.

Webhooks are verified with HMAC-SHA256 over the raw body, then the payment is read back from the SimulinkPay API before anything is posted. Duplicates are ignored. The five-minute reconcile job covers lost webhooks and SimulinkPay's `expired` status, which has no webhook. Keep a little balance in the SimulinkPay wallet; it pays SimulinkPay's fee per payment.

## Daily operations

- **Opening balances**: Ledger → Opening balance. Enter each member's savings brought forward from the books, then the welfare fund, group fund and any cash or bank balance.
- **Paybill payments** arrive outside SimulinkPay. Export the Equity statement (or the M-Pesa paybill statement) as CSV and import it under Payments → Import statement. Lines are matched by phone, then by a member number typed as the account (for example `M004`), then by full name. The rest wait under Payments → Unmatched.
- **Cash at a meeting**: Meetings → the meeting → Cash collected. Then Ledger → Transfer when it is banked.
- **Merry-go-round**: Merry-go-round → New cycle → Run ballot. Each month, Pay next turn raises the payout for two approvals.
- **December share-out**: Share-out shows each member's savings less what they owe, plus an optional share of the group fund. Raise payouts, approve them in bulk from Payouts, then mark each paid.
- **Rule changes**: Group rules, with the resolution number from the meeting that agreed it.

## Tests

```bash
php artisan test          # ledger, dues, payments, webhooks, statement import, payouts, members,
                          # positions, merry-go-round, welfare, projects, assets, meetings,
                          # messaging drivers, integrations, backups, and every page for every role
npm run types:check       # TypeScript
npm run check             # lint and formatting
```

Tests run on SQLite in memory and never call real providers. The same suite passes on PostgreSQL 17.

## Code layout

Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, Fortify (phone or email login, two-factor, passkeys), DomPDF, Pest. Domain code lives by module:

```
app/Domain/
  Access/        permissions and the standard positions
  Audit/         append-only audit log
  Assets/        assets and hire
  Documents/     document vault and compliance calendar
  Dues/          monthly dues, levies, fines and how payments are applied
  Fundraisers/
  Groups/        the group, its rules, and the current-group scope
  Integrations/  encrypted provider keys
  Ledger/        accounts, immutable journal, chart of accounts
  Meetings/
  Members/       register, balances, logins, exits
  Mgr/           merry-go-round ballot, turns, swaps
  Notifications/ notifier, templates, SMS drivers, email sender, send job
  Payments/      gateway (SimulinkPay and fake), webhooks, statement import, allocation
  Payouts/       requests, two-person approval, payment, share-out
  Projects/      participants, stock, sales, profit share
  Reports/       report queries and the data export
  Welfare/
app/Http/Controllers/   thin controllers by area
resources/js/pages/     one React page per screen
tests/Feature/Kertai/   feature tests by module
```

Every table carries `group_id`, so more groups can be hosted on the same install later.

## Still needed from the officials

Before go-live (phase 1, 9 November 2026):

- Official names and phone numbers for the chair, secretary and treasurer (and vice positions, if any).
- The member list: name, phone, ID number, date joined, nominee.
- Contribution amount, due day and late fine, and the merry-go-round and welfare shares, as agreed by the group.
- Welfare benefit amounts per event.
- The group's registration number and a copy of the constitution.
- Opening balances: each member's savings, and the welfare, group and bank balances.
- The empowerment day target and budget (fundraiser for 20 November).
- A SimulinkPay receiving account in the group's name, and an SMS sender ID registration.
