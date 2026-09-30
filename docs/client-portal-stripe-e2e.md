# Client Portal: Stripe payment end-to-end test (manual)

A manual procedure for the Client Portal "Pay now" flow (Steps 6A to 6C): Stripe Checkout on the seller's connected account, settled only by the signed central Connect webhook.

> **Automated vs manual.** The automated suites (`php artisan test`, `node --test tests/Frontend/*.test.mjs`) run **offline** against an in-memory Stripe fake (`tests/Support/FakeStripe.php`). They never call Stripe and never open a browser. Real Stripe, Checkout and phone/browser behaviour is only verified by working through this document. Record each manual run in the results table at the end.

Use **Stripe test mode only**. Never put real keys in this file, in tickets or in chat.

---

## Prerequisites

- A deployed environment reachable over **HTTPS** (Stripe must reach the webhook). For local testing, use the Stripe CLI (see C.2).
- A Stripe **platform** account in test mode with Connect enabled.
- A phone to repeat the flow at the end (the portal is usually opened from WhatsApp or email).
- Start with a **Spain / EUR** workspace. It's the simplest path. Test Morocco / MAD separately (see "Notes").

---

## A. Deploy migrations

```bash
php artisan migrate            # central: stripe_connect_accounts
php artisan tenants:migrate    # every tenant: invoice_payment_attempts (+ purpose)
php artisan stripe:backfill-connect-accounts --dry-run   # preview the account -> tenant map
php artisan stripe:backfill-connect-accounts             # write it (safe to re-run)
```

The backfill must end with `conflict 0`. If it reports a conflict, one Stripe account is linked in two workspaces. Disconnect it from the wrong one and run the backfill again.

## B. Configure Stripe test keys

In the environment's `.env` (values come from Stripe Dashboard → Developers, **test mode**):

```
STRIPE_KEY=pk_test_…
STRIPE_SECRET=sk_test_…
STRIPE_WEBHOOK_SECRET=whsec_…            # platform webhook (subscriptions), see C
STRIPE_CONNECT_WEBHOOK_SECRET=whsec_…    # Connect webhook, see D
STRIPE_CONNECT_CLIENT_ID=ca_…            # Dashboard → Connect → Settings → OAuth
APP_URL=https://<your-central-domain>
```

In Stripe → Connect → Settings → OAuth, add the redirect URI for each test workspace:
`https://<tenant-domain>/settings/payments/stripe/callback`

Then:

```bash
php artisan config:clear
php artisan stripe:check-readiness
```

Every line must be ✓ (it exits with code 1 otherwise). The command never prints secret values.

## C. Register the central Connect webhook: `/stripe/connect/webhook`

### C.1 Deployed environment

Stripe Dashboard (test mode) → Developers → Webhooks → **Add endpoint**:

- URL: `https://<your-central-domain>/stripe/connect/webhook` (the central domain, **not** a tenant subdomain)
- **Listen to: Events on Connected accounts** (this is what makes it a *Connect* webhook)
- Events:
  - `checkout.session.completed`
  - `checkout.session.async_payment_succeeded`
  - `checkout.session.async_payment_failed`
  - `checkout.session.expired`
  - `account.updated`

Keep (or create) the separate **platform** endpoint `https://<your-central-domain>/stripe/webhook` for subscription billing (Events on *your account*).

### C.2 Local alternative (Stripe CLI)

```bash
stripe listen \
  --forward-to https://<local-central-host>/stripe/webhook \
  --forward-connect-to https://<local-central-host>/stripe/connect/webhook
```

The CLI prints one `whsec_…` for the session. Use it for **both** webhook secrets while testing locally.

## D. Configure `STRIPE_CONNECT_WEBHOOK_SECRET`

Copy the endpoint's **Signing secret** from C.1 (or the CLI secret from C.2) into `STRIPE_CONNECT_WEBHOOK_SECRET`, then `php artisan config:clear` and re-run `php artisan stripe:check-readiness`.

Without it the endpoint refuses every event (HTTP 503). This is intentional: it fails closed.

## E. Connect a test seller

1. Log in to the test workspace as its admin → **Settings → Payments → Connect with Stripe**.
2. Complete Stripe's test onboarding (use Stripe's test data; skip real identity).
3. Back in Settings the status must show connected, with charges enabled. If not, click refresh.
4. Check that the map row exists:
   ```bash
   php artisan stripe:backfill-connect-accounts --dry-run --tenant=<tenant-id>   # expect: unchanged 1
   ```

## F. Create and issue a test invoice

In the workspace: create a customer, create an invoice for them (for example 12.34 EUR), and **issue** it. Drafts are never shown in the portal and can't be paid.

## G. Open its customer portal

There is **no admin button yet** that generates a customer portal link. Create one from the server:

```bash
php artisan tinker
>>> $t = App\Models\Tenant::find('<tenant-id>');
>>> $t->run(fn () => app(App\Services\ClientPortal\ClientPortalService::class)->createAccess(App\Models\Customer::where('email', '<customer-email>')->firstOrFail()));
=> "<64-character token>"      # shown once, only stored hashed
```

Open `https://<tenant-domain>/portal/<token>` in a private window (the customer's view).

## H. Confirm Pay now appears

- The invoice shows the status "À payer" / "En retard" and a **Payer maintenant** button. On a phone it's a full-width button above PDF / UBL.
- PDF and UBL download buttons are present.
- With a very light or very dark brand colour (Settings → brand colour), the button text stays readable.

## I. Start Checkout

Tap **Payer maintenant**. The confirmation shows the invoice number and **amount with currency**. Tap **Payer 12,34 €**.

- The button shows a spinner ("Redirection vers le paiement sécurisé…") and can't be tapped again.
- The browser lands on `checkout.stripe.com` showing the **seller's** business name and the same amount and currency.

## J. Pay with a Stripe test card

`4242 4242 4242 4242`, any future expiry, any CVC, any postcode.

## K. Return to the portal

Stripe redirects to `/portal/<token>?payment=success`:

- The banner shows **"Paiement reçu – Nous vérifions la confirmation du paiement."**
- Within a few seconds it should change to **"Paiement confirmé – Merci, cette facture a bien été réglée."**
- If the webhook is slow, after about 20 s it shows "Confirmation en cours" and stops polling. Refreshing later must show the invoice as paid.
- The `?payment=` parameter is removed from the address bar.

## L. Verify the webhook changed the invoice to paid

- Stripe Dashboard → Developers → Webhooks → the Connect endpoint: the `checkout.session.completed` delivery shows **200**.
- `storage/logs/laravel.log` contains `Client Portal: invoice paid via Stripe Connect`.
- No `critical` log lines (`NOT applied`, `mismatch`).

## M. Verify `paid_at` and `paid_via = stripe`

```bash
php artisan tinker
>>> App\Models\Tenant::find('<tenant-id>')->run(fn () => App\Models\Invoice::where('uuid', '<invoice-uuid>')->first(['status','paid_at','paid_via','stripe_session_id'])->toArray());
# expect status=paid, paid_at set, paid_via=stripe, stripe_session_id=cs_test_…
>>> App\Models\Tenant::find('<tenant-id>')->run(fn () => App\Models\InvoicePaymentAttempt::latest('id')->first(['status','amount_minor','currency'])->toArray());
# expect status=paid, amount_minor=1234, currency=EUR
```

Also check in the **connected** (seller's) Stripe test account, not the platform account, that the payment of 12.34 EUR appears.

## N. Verify Pay now disappears

Reload the portal: the invoice shows **Payée** plus "Payée le <date>", and no Pay now button. The "À payer" summary no longer counts it.

## O. Verify PDF and UBL remain available

Download both for the paid invoice. Both must succeed.

## P. Verify the admin sees the invoice as paid

In the workspace admin: the invoice list and the Payments page show the invoice as paid via Stripe. If "Invoice paid" notifications are enabled in Settings → Notifications, the owner receives the existing "Invoice paid" email.

---

## Cancellation check

1. New issued invoice → portal → Pay now → confirm → on the Stripe page click **back / "←"** (cancel).
2. The portal shows **"Paiement annulé – Aucun paiement n'a été enregistré…"**.
3. The invoice is still unpaid and Pay now is still offered. Tapping it again reuses the same open session: no second Checkout is created (`invoice_payment_attempts` still has one `open` row).
4. Browser Back from Stripe also leaves the portal usable (no modal stuck on "Redirection…").

## Failed-payment checks

1. **Declined card** `4000 0000 0000 0002`: Stripe shows the decline on its own page, the customer can retry there, and the portal invoice stays unpaid.
2. **3-D Secure** `4000 0025 0000 3155`: complete the challenge → paid as in K–N. Fail the challenge → invoice stays unpaid.
3. **Expired session** (optional): expire an open session from the Stripe Dashboard or `stripe checkout sessions expire cs_test_…` with `--stripe-account acct_…`. The attempt becomes `expired` (the `checkout.session.expired` delivery returns 200), the invoice stays unpaid, and Pay now creates a fresh session.

## Safety checks (quick)

- **Success URL alone:** open `/portal/<token>?payment=success` manually for an **unpaid** invoice. The banner verifies, then shows "Confirmation en cours". The invoice is **not** marked paid.
- **Resend a webhook:** Stripe Dashboard → Webhooks → the paid event → *Resend*. It returns 200, and there is no second "paid" history entry and no change to `paid_at`.
- **Bad signature:** `curl -X POST https://<central>/stripe/connect/webhook -d '{}'` returns 400.
- **Connect webhook secret removed** (temporarily, staging only): the endpoint returns 503.

---

## Notes

- **Morocco / MAD:** payments are only offered when the seller's Stripe account can charge (`charges_enabled`). Stripe does not currently offer accounts to businesses established in Morocco (check the current list at stripe.com/global). A MAD invoice therefore needs a seller whose Stripe account is in a supported country. Test MAD separately and don't assume it works.
- **Connect OAuth:** this app links sellers through Stripe's OAuth flow (`/settings/payments/stripe/connect`). Confirm your platform account still has OAuth enabled for Standard accounts in Connect settings.
- **Account conflicts:** one Stripe account can only be linked to one workspace. Linking it in a second workspace is refused with a clear message, and the first workspace keeps it.

## Manual run results

| Date | Environment | Tester | Device / browser | Steps passed | Issues |
|---|---|---|---|---|---|
| _not yet run_ | | | | | |
