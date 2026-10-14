# Changelog

Earlier releases are described on the project's GitHub Releases page.

## 1.3.0

**Requires Moodle 4.5 or later.** Sites on Moodle 4.3 or 4.4 can keep using 1.2.0.

### Added

- **Checkout window** setting: open the ifthenpay checkout in a new tab. The Moodle page stays
  open, follows the payment, and moves on by itself once it is confirmed. Same tab remains the
  default.

### Fixed

- A payment notification that could not be processed (for example, enrolment failed or the
  server was busy) was acknowledged anyway, so ifthenpay never retried it. It is now answered
  with HTTP 503 and ifthenpay retries it.
- Saving a payment account succeeded even when ifthenpay refused to register the notification
  URL, which left its payments unconfirmed. The save is now refused with an explanation.
- Payment notifications compare amounts in cents (`10` and `10.00` are the same charge) and
  check the anti-phishing key in constant time. An amount mismatch is now recorded in the site
  logs.
- The cancel page redirected a paid order through an intermediate "redirecting" page instead of
  going straight to the course.
- The "Open the checkout again" button could fail to appear.
- The cancel page shows the amount in the site's currency format, like the return page.
- Corrected the descriptions of the Backoffice Key and of two privacy fields.

### Changed

- The return page no longer shows the transaction id from the return URL: it came from the
  browser and proved nothing.
- Fewer requests to ifthenpay when saving a payment account.
