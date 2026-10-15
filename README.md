 airline refund system

Customer, admin and finance portals for an airline. Customers register with name, email and phone, log in with a
one-time code sent to their email, fill in a refund request form (passenger, trip, amounts in USD, reason, supporting
documents, declaration), and chat with the admin (text, photos, videos, documents) on each refund.

## Set up (XAMPP)
1. Copy this folder to `C:\xampp\htdocs\refund_system`.
2. Start Apache and MySQL. In phpMyAdmin, import `database.sql`.
   If you imported the earlier version, drop the `refund_system` database first, because the refund table changed.
3. Check `config.php`: DB details and `BASE_URL` (must match the folder name).
4. Open http://localhost/refund_system/
5. Restart Apache once so the upload limits in `.htaccess` apply (26 MB per file).

## Staff accounts
`database.sql` creates an admin (`admin@example.com`) and a finance user (`finance@example.com`).
Change them to real inboxes before first login:

    UPDATE users SET email='you@gmail.com' WHERE role='admin';
    UPDATE users SET email='finance@gmail.com' WHERE role='finance';

The admin can add more staff under Users.

## Login codes by email (Gmail)
PHPMailer is already included in `lib/`, so you do not need Composer.
1. Pick the Gmail account that will send the codes. Turn on **2-Step Verification** (myaccount.google.com/security).
2. Open myaccount.google.com/apppasswords and create an App Password named SkyRefund. Google shows 16 letters.
3. In `config.php` set:
   - `MAIL_ENABLED` to `true`
   - `SMTP_USER` and `MAIL_FROM` to that Gmail address
   - `SMTP_PASS` to the App Password (spaces do not matter)
4. Log in as admin and open **Users**, then **Test the email settings** (`mail_test.php`). It checks your setup and
   shows the exact error if a test email fails.
5. When the test email arrives, set `OTP_DEBUG` to `false` so codes stop appearing on screen.
Staff accounts must use real inboxes, because their login code is sent there. Check Spam if a code is slow.

## Notes
- Codes expire after 5 minutes, allow 5 wrong attempts, and can be requested once a minute.
- Chat files live in `uploads/chat` and are only served through `api/file.php` after a permission check.
- Allowed uploads: jpg, png, gif, webp, mp4, webm, mov, pdf, doc, docx (25 MB each, 5 per message).
- The finance portal only sees approved and paid refunds; it has no access to chats.

## Look and currency
- Colors are CSS variables at the top of `assets/css/style.css` (`--accent` is the sky blue). Change them to re-theme.
- All amounts are USD and shown with `$` (see `money()` in `includes/functions.php`).
- The app name is `APP_NAME` in `config.php`.

## Transfer to finance and conversations
- After approving a refund, the admin clicks **Transfer to finance**. Only transferred refunds appear on the finance dashboard.
- Finance can **Return to admin** with a private reason if something is wrong.
- Each refund has up to three one-on-one conversations: customer and admin, customer and finance (after the transfer),
  and an internal admin and finance thread that the customer cannot see.
- Upgrading an existing database: run `upgrade_to_airline.sql` (if not yet run), then `upgrade_transfer.sql`.

## Messages (direct conversations)
- Every role has a **Messages** inbox with an unread count and a **New conversation** button.
- Customers can message admin and finance users, admin can message customers and finance, and finance can message customers and admin.
  Customer-to-customer is not allowed. A direct chat is private to its two people.
- When someone writes and the other side has nothing unread from them yet, an email alert is sent (needs email set up).
  Set `SITE_URL` in `config.php` to your real address so the link in the email works.
- Upgrade order for an existing database: `upgrade_to_airline.sql`, `upgrade_transfer.sql`, then `upgrade_direct_chat.sql`.
