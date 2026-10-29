## Hosting on Vercel (current)

The site is static HTML served by Vercel. The contact form and mailing list run as Vercel functions in `api/`:

- `api/contact.js` emails enquiries to info@reecemusic.com through the info@ mailbox.
- `api/subscribe.js` saves signups to Supabase (`reece_subscribers`, see `database/supabase-reece.sql`) and sends a welcome email.

Set these in Vercel → Project → Settings → Environment Variables, then redeploy:

| Name | Value |
| --- | --- |
| `SMTP_PASS` | Password of the info@reecemusic.com mailbox |
| `SUPABASE_SERVICE_ROLE_KEY` | Supabase → Project Settings → API Keys → service_role / secret key |
| `SMTP_HOST` | Optional, default `mail.reecemusic.com` |
| `SMTP_PORT` | Optional, default `465` |
| `SMTP_USER` | Optional, default `info@reecemusic.com` |

Until those are set, the forms open a pre-filled email to info@reecemusic.com instead, so nothing is lost.

Hero playlist and performance films are set in `js/config.js`.

## MIDI store ("Play it yourself")

Pages: `learn.html` (all songs), `song.html?id=…` (one song), `purchase-complete.html` (after Stripe).
Functions: `api/midi/songs.js`, `checkout.js`, `verify.js`, `download.js`. Songs and prices: `api/_data/midi-songs.json`.

MIDI files live in the **private** Supabase bucket `reece-midi` (never in this public repo). Upload them in
Supabase → Storage → reece-midi, named exactly as in `midi-songs.json` (e.g. `clocks-free.mid`, `clocks-chorus.mid`, `clocks-full.mid`).
Buyers get a 2-minute signed link after a free click or a verified payment.

Extra Vercel environment variables for the store:

| Name | Value |
| --- | --- |
| `STRIPE_SECRET_KEY` | Stripe secret key (`sk_test_…` for testing, `sk_live_…` to take real payments) |
| `DOWNLOAD_TOKEN_SECRET` | Any long random string (32+ characters) |
| `SUPABASE_SERVICE_ROLE_KEY` | Same key the mailing list uses |

To add a song: add an entry to `api/_data/midi-songs.json` (prices in cents) and upload its three files to the bucket.
