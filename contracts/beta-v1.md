# Deskovi Private Beta Checklist v1

WP connector exit criteria before SaaS backend work.

## Must pass

- [ ] WooCommerce active; HPOS declared compatible
- [ ] Connection mock: start → complete → test → activity OK
- [ ] Tickets: create / reply / status; outbound mock sync; sync cursor advances
- [ ] Order context: link order on ticket; privacy toggles hide billing/phone when off
- [ ] Widget **off** → zero storefront scripts
- [ ] Widget **on** → launcher on normal pages; **absent** on cart/checkout
- [ ] Logged-in widget: list / create / reply / order select
- [ ] Guest: order # + billing email → OTP mail → session → ticket
- [ ] Unread badge after agent reply; clears when customer opens thread
- [ ] Privacy policy suggestion appears under Settings → Privacy
- [ ] Tools → Export / Erase personal data includes Deskovi bridge tickets
- [ ] Diagnostics shows Action Scheduler status + checkout guard pass
- [ ] No refund / remote destructive order action routes

## Notes

- Mock mode is default (`ITSDESK_CONNECTION_MODE` / filter). Live HTTP returns 501 until SaaS.
- Guest OTP uses `wp_mail` — configure SMTP/Mailpit on Local.
- Production queues: Action Scheduler (bundled with WooCommerce). Without AS, mock sync may run inline.
