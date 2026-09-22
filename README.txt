DECENTRALIZED TRUST ATTORNEYS — DEMO SYSTEM (v4)
=================================================

Working PHP + MySQL demo: public marketing site, KYC-style application
(any country/state, US SSN-last-4, ID upload), a full premium wallet
dashboard (balance, Send, Receive, Swap, Buy, Withdraw, live crypto
prices, transaction ledger), email notifications, and a full admin
panel (applications, users, balance editing, withdrawal approvals,
wallet transactions ledger).

--------------------------------------------------
IF YOU ALREADY HAVE v3 LIVE
--------------------------------------------------
Don't re-import sql/schema.sql — that recreates tables from scratch.
Instead:
  1. Upload/overwrite all the PHP, CSS and JS files from this zip over
     the existing ones (same folder).
  2. In phpMyAdmin (or Railway's MySQL plugin > Data > Query), run
     sql/migration_v10.sql. This adds the email and image_path columns
     to the wallet_connections table WITHOUT touching anything else.
     (If upgrading from v2, run migrations v3 through v10 in order.)
  3. Set the SMTP_* environment variables (see EMAIL SETUP below) so
     notification emails start sending.

--------------------------------------------------
IF YOU ALREADY HAVE v2 LIVE
--------------------------------------------------
Don't re-import sql/schema.sql — that recreates tables from scratch.
Instead:
  1. Upload/overwrite all the PHP, CSS and JS files from this zip over
     the existing ones (same folder).
  2. Run all migration files in order: sql/migration_v3.sql through
     sql/migration_v10.sql — each one is safe to run on a live database.
  3. Set the SMTP_* environment variables (see EMAIL SETUP below).

--------------------------------------------------
FRESH INSTALL — cPANEL
--------------------------------------------------
1. Upload the contents of this folder to your cPanel hosting (public_html/
   or a subfolder).
2. cPanel > MySQL Databases: create a database + user, grant all privileges.
3. phpMyAdmin > select the database > Import > sql/schema.sql
4. Edit config.php: replace the DB_* constants near the top with your
   actual DB_HOST / DB_NAME / DB_USER / DB_PASS, and the SMTP_* constants
   with your email provider's details (see EMAIL SETUP below).
5. Visit your domain.

--------------------------------------------------
FRESH INSTALL — RAILWAY
--------------------------------------------------
1. Create a new Railway project, add this repo/folder as a service
   (Railway auto-detects PHP), and add a "MySQL" plugin to the same
   project.
2. Railway automatically injects MYSQLHOST / MYSQLPORT / MYSQLDATABASE /
   MYSQLUSER / MYSQLPASSWORD into your PHP service — config.php already
   reads these, so you don't need to edit DB settings at all.
3. Open the MySQL plugin's "Data" tab (or connect with any MySQL client
   using its connection details) and run sql/schema.sql once to create
   the tables.
4. In your PHP service > Variables, add the SMTP_* variables (see EMAIL
   SETUP below) so the site can send emails — Railway has no built-in
   mail server, so this step is required for emails to work.
5. Generate a domain for the service (Settings > Networking > Generate
   Domain) and visit it.

Demo admin login (change the password after import):
    URL:      yourdomain.com/admin/login.php
    Username: admin
    Password: Admin@123

--------------------------------------------------
EMAIL SETUP (SMTP)
--------------------------------------------------
The site sends welcome emails, application-status updates, and wallet
confirmation emails (Send/Swap/Buy/Withdraw) via SMTP using PHPMailer
(already included in includes/PHPMailer/ — no composer install needed).

Set these as environment variables (Railway: Service > Variables;
cPanel: edit the constants directly in config.php instead):

    SMTP_HOST       e.g. smtp-relay.brevo.com
    SMTP_PORT       587 (TLS) or 465 (SSL)
    SMTP_USER       your SMTP username
    SMTP_PASS       your SMTP password / API key
    SMTP_SECURE     tls  or  ssl
    SMTP_FROM       no-reply@yourdomain.com
    SMTP_FROM_NAME  Decentralized Trust Attorneys

Any SMTP provider works. Easiest free options if you don't already have
one: Brevo (formerly Sendinblue — 300 free emails/day, SMTP details
under Settings > SMTP & API) or a Gmail account with a generated "App
Password" (smtp.gmail.com, port 587, TLS).

If SMTP_HOST is left blank, the site keeps working normally — it just
silently skips sending emails instead of erroring, so you can deploy
first and wire up email whenever you're ready.

--------------------------------------------------
WHAT'S NEW IN v4
--------------------------------------------------
- Wallet Link improvements: users can now supply a contact email and
  upload a custom wallet icon / image when linking a wallet. The icon
  is stored in uploads/wallets/ and displayed everywhere the wallet
  appears (link-wallet page, admin wallet logs).
- Admin > Wallet Connection Logs page (admin/wallet-logs.php): shows
  every wallet linked by every user with provider badge/logo, public
  key, method, status, and timestamp. Includes live search + status
  filter + CSV export.
- migration_v10.sql: non-destructive ALTER adds the new email and
  image_path columns to the wallet_connections table.
- uploads/wallets/ directory added for custom wallet icon storage.

--------------------------------------------------
WHAT'S NEW IN v3
--------------------------------------------------
- Mobile bottom tab bar on the client wallet dashboard (Dashboard / Send /
  Link Wallet / Receive / More) — the sidebar now hides on phone widths in
  favor of this bar instead of leaving no navigation at all.
- New "Link Wallet" page: save a default wallet address once, and it's
  pre-filled automatically on the Withdraw form from then on.
- New "Profile" page and a "More" page (Swap/Buy/Withdraw/Applications/
  Profile/Logout) for whatever doesn't fit in the 5 mobile tabs.
- Login now sends a "New Login to Your Account" email in addition to the
  existing signup/application/withdrawal/wallet emails — email coverage
  now spans login, signup, applications, and every wallet action.
- All admin tables (Applications, Users, Withdrawals, Transactions) are
  wrapped in a horizontally-scrollable container so nothing gets cut off
  on narrow/mobile screens — swipe sideways to see every column.
- Admin dashboard KPI cards now have icon badges and two more metrics
  (Transactions, Active Wallets), plus a lightweight "Data Explorer" tab
  (admin/explorer.php) with big linkable count-cards for Users/
  Applications/Withdrawals/Wallet Transactions.


- Formation Jurisdiction is no longer limited to 5 US states — the
  application form now offers every country in includes/countries.php
  (~190 countries) with a dependent state/province dropdown for the
  countries that have one (US, Canada, Nigeria, UK, Australia, India,
  South Africa, Ghana, Kenya, UAE, Germany, Mexico, Brazil), and a
  free-text region field for everywhere else. The most common formation
  jurisdictions (US, UK, Canada, UAE, Singapore, Hong Kong) are pinned
  to the top of the list.
- Full wallet screens: Send, Receive (address + QR code), Swap (live
  CoinGecko rates client-side, 0.5% demo fee), and Buy (demo checkout),
  alongside the existing Withdraw flow — all logged to a new
  `transactions` table and visible in a "Recent Activity" ledger on the
  dashboard.
- The client dashboard was redesigned around a sidebar layout (Overview
  / Send / Receive / Swap / Buy / Withdraw / Applications) with quick-
  action buttons on the balance card, matching a premium fintech app
  rather than a bare balance widget.
- Email notifications (see EMAIL SETUP): welcome email on signup,
  application-received + application-status-change emails, and
  confirmation emails for Send / Swap / Buy / Withdraw / admin balance
  adjustments. None of these block login or signup — they're
  notifications only, sent in the background.
- config.php and db.php now support Railway automatically (reading
  MYSQLHOST/MYSQLPORT/MYSQLDATABASE/MYSQLUSER/MYSQLPASSWORD env vars)
  while still working unchanged on cPanel.
- Admin panel: new "Wallet Transactions" page listing every Send/
  Receive/Swap/Buy/admin-adjustment across all users; admin balance
  adjustments now also log a transaction and email the user.

--------------------------------------------------
NOTES
--------------------------------------------------
- SSN is stored as last-4-digits only, never the full number.
- This remains a demo: no real payment processor or blockchain network
  is wired up. Buy "purchases" simply credit the demo balance; Send/Swap
  debit it; Withdraw approval debits it after admin review. Receive
  displays a real-looking generated address for display purposes — an
  admin credits the balance manually (Admin > Users > Edit Balance, or
  a positive "Adjust" amount) once a deposit is confirmed off-platform.

--------------------------------------------------
FOLDER STRUCTURE
--------------------------------------------------
index.php, login.php, application.php, applications.php, logout.php   — public site + app list
dashboard.php, send.php, receive.php, swap.php, buy.php, withdraw.php — client wallet dashboard
link-wallet.php, profile.php, more.php                                 — linked wallet, account, mobile "more" menu
admin/                                                                 — admin panel
  users.php, user_edit.php                                             — user list + balance editor
  withdrawals.php, transactions.php                                    — withdrawals + wallet ledger
  applications.php, application_view.php                               — application review + KYC/ID view
  explorer.php                                                         — Data Explorer hub (quick counts)
assets/css, assets/js                                                  — styling + crypto ticker script
uploads/ids/                                                           — uploaded ID documents (locked down)
uploads/wallets/                                                       — uploaded wallet icon images
includes/countries.php                                                 — country/state data (edit to add more)
includes/mailer.php, includes/PHPMailer/                               — email sending (SMTP)
includes/wallet.php                                                    — wallet helpers (address, tx logging)
includes/dash_header.php, includes/dash_footer.php                     — sidebar layout for wallet pages
sql/schema.sql                                                         — fresh-install schema
sql/migration_v2.sql … sql/migration_v10.sql                          — non-destructive upgrades (run in order)
config.php                                                             — database + SMTP + site settings
