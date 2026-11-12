<h1 align="center">ifthenpay | Payment Gateway</h1>
<h3 align="center">📦 A Payment Gateway Plugin for Moodle 📦</h3>

Accept diverse payments—including credit and store cards (Cartão Crédito), mobile and digital wallets (such as Apple Pay, Google Pay, MBWAY, and Pix), and local bank transfers (Multibanco and Payshop)—all through our secure gateway.

To unlock the full potential of this integration, users must have an active ifthenpay account. Once subscribed, you'll need to activate a Gateway Key and associate/activate your selected Payment Methods by [contacting our helpdesk](https://helpdesk.ifthenpay.com/en/support/home). Please note that our services operate under a tariff structure; users should check tariffs per payment method in our website.

## Requirements 📋

- Moodle 4.5 or later.
- An active ifthenpay account, with a Gateway Key for Moodle.

## Setup 📥

1. Install the plugin in your Moodle instance.
2. Ensure you have an **ifthenpay** account. If not, you can [create one here](https://ifthenpay.com/aderir/).
3. Retrieve your **Backoffice Key** and generate one or more **Gateway Keys** as needed.
4. Configure each Gateway Key with the desired payment methods and associated accounts (with support from our helpdesk team).
5. In Moodle, configure the ifthenpay payment account by choosing the desired Gateway Key and link it to the associated payment methods.
6. Enable **Enrolment on payment** for the Moodle courses you wish to offer with paid access.
7. Configure the enrolment method for each course, specifying the currency you want to accept.

## Checkout window 🪟

By default the ifthenpay checkout replaces the Moodle page. In **Site administration → Plugins →
Payment gateways → ifthenpay**, *Checkout window* can open it in a new tab instead: the Moodle page
stays open and moves on by itself once the payment is confirmed.

## Staff notifications 🔔

Each payment account can notify staff by Moodle notification when:

- **Payment completed** — a payment is confirmed and the course enrolment (or other purchased item)
  has been delivered. The message names the payer, the item, the amount, the transaction reference
  and the date.
- **Payment problem** — a known payment runs into an amount mismatch, an anti-phishing key
  mismatch, a refused delivery (for example, the enrolment was refused) or an unexpected error.
  Each problem type is notified once per payment, and the subject names the problem.

Configure it in **Site administration → Payments → Payment accounts → (account) → ifthenpay**,
section **Staff notifications**: choose *Notify when* and the *Recipients* — **Site
administrators**, **Course teachers** (users who can edit the purchased course; course enrolments
only) and/or **Other users** you pick.

- Recipients need the capability **Receive ifthenpay payment notifications**
  (`paygw/ifthenpay:receivenotifications`). Managers and editing teachers have it by default, and
  site administrators always do. To notify someone else, such as a finance person, give them a
  role with that capability, then add them under *Other users*.
- Notifications go through Moodle's messaging (web and email by default), so each recipient can
  change how they receive them in their own notification preferences. Texts are in each
  recipient's language.
- Mobile (Moodle app) delivery is available but off by default. A recipient can switch it on in
  their own notification preferences, or an administrator can make it the default under **Site
  administration → General → Messaging → Notification settings** (the *Mobile* column of the two
  ifthenpay rows). It needs the site's Moodle app notifications set up (*Site administration →
  General → Mobile app*).
- They are sent by a background task, so **Moodle cron must be running**.
- Notifications are **off by default**: after upgrading nothing is sent until you turn them on
  for an account. No notifications are sent while the ifthenpay gateway is disabled, or for a
  notification type disabled site-wide in *Notification settings*.

## Currencies 💱

| Currency | Payment methods that settle in it |
| --- | --- |
| 🇪🇺 **EUR** — Euro | Multibanco, MB WAY, Payshop, Visa/Mastercard, Apple Pay, Google Pay |
| 🇧🇷 **BRL** — Brazilian Real | Pix |

The currency is set per enrolment method, on the course. ifthenpay derives the currency it charges
in from the accounts attached to your Gateway Key, so make sure the methods you enable can settle
in the currency you priced the course in — a course priced in BRL should offer Pix.

## Language Support 🌍

This plugin currently supports the following languages:

- 🇬🇧 **English** — `en`
- 🇵🇹 **Portuguese (Portugal)** — `pt`
- 🇪🇸 **Spanish** — `es`
- 🇫🇷 **French** — `fr`

The plugin will automatically adapt its text and interface to match the language configured in your Moodle settings, ensuring a seamless experience for both admins and customers. The ifthenpay checkout page itself follows the same language.

## Support & Maintenance 🛠️

If you encounter issues or need assistance, please refer to the following resources:

- 📖 [Official ifthenpay FAQ](https://helpdesk.ifthenpay.com/en/support/home)
- 📬 Support Email: `suporte@ifthenpay.com`

#### Keeping the Plugin Updated:

- Always use the latest versions of Moodle, and this plugin.
- Review the [changelog](CHANGES.md) before updating.
- After updates, test payment flows to ensure functionality.

Regular updates and active monitoring will ensure continued compatibility and optimal performance.
