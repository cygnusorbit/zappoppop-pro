=== ZapPopPop Pro ===
Contributors: ZapPopPop
Donate link: https://zappoppop.com/
Tags: popup, modal, popup builder, optin, lead generation
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pro add-on for ZapPopPop: extra builder elements, modules, templates, A/B Campaigns and Statistics.

== Description ==

ZapPopPop Pro is the companion add-on for the ZapPopPop modular popup builder, unlocking premium builder elements, campaign modules, multi-variant A/B testing, and performance statistics.

= Key Features =
* Premium Builder Elements: Includes Progress Bar, Image Select, Title Select, and Star Rating elements.
* A/B Campaign Split Testing: Run multi-variant tests to identify winning overlays and optimize conversions.
* Comprehensive Analytics: Monitor impressions, conversion rates, and interaction trends.
* Advanced Campaign Modules: Extend popups with sound effects, particles, and custom CSS styling.
* Premium Template Library: Ready-to-use, responsive layouts optimized across mobile and desktop viewports.
* Pure Decomposed Architecture: Hooks cleanly into core filters without modifying base coordinate or drag/push mechanics.

== Installation ==

1. Ensure the core ZapPopPop plugin is installed and activated.
2. Upload the `zappoppop-pro` folder to the `/wp-content/plugins/` directory, or install the .zip directly via WordPress Plugins > Add New.
3. Activate ZapPopPop Pro through the 'Plugins' screen in WordPress.
4. Access the expanded elements, campaign management, and statistics directly inside the **ZapPopPop** menu.

== Frequently Asked Questions ==

= Does ZapPopPop Pro require the core plugin? =
Yes. ZapPopPop Pro is a modular add-on that hooks into the core ZapPopPop engine.

= Does ZapPopPop Pro track personal visitor data? =
No. Aggregated metrics and submission logs are stored locally in your WordPress database without sending personal data to external tracking servers.

= Can I export submissions and analytics? =
Yes. Form submissions and statistical logs can be exported directly to CSV from the administrative screens.

= What happens if ZapPopPop Pro is deactivated? =
Standard popups created with core elements continue to function normally. Pro elements and modules remain dormant until the add-on is re-activated.

== External services ==

ZapPopPop Pro communicates with external services only when the site administrator enables matching features in the core plugin:

* **Geolocation (ipapi.co / ip-api.com)** - used only when country targeting is enabled with a remote provider. Sends the visitor IP address to resolve a country code. ipapi.co: https://ipapi.co/privacy/ - ip-api.com: https://ip-api.com/docs/legal
* **Google reCAPTCHA** - used only when reCAPTCHA is enabled on a form. Sends the reCAPTCHA token and visitor IP to https://www.google.com/recaptcha/api/siteverify. Terms: https://policies.google.com/terms - Privacy: https://policies.google.com/privacy
* **Mailchimp** - used only when the Mailchimp module is enabled. Sends submitted form fields to the Mailchimp API. Terms: https://mailchimp.com/legal/terms/ - Privacy: https://mailchimp.com/legal/privacy
* **GetResponse** - used only when the GetResponse module is enabled. Sends submitted form fields to https://api.getresponse.com. Terms: https://www.getresponse.com/legal/terms-of-service - Privacy: https://www.getresponse.com/legal/privacy
* **Twilio** - used only when SMS alerts are enabled. Sends alert text and configured numbers to https://api.twilio.com. Terms: https://www.twilio.com/legal/tos - Privacy: https://www.twilio.com/legal/privacy
* **YouTube / Vimeo** - used only when a video element is placed in a popup. YouTube: https://policies.google.com/privacy - Vimeo: https://vimeo.com/privacy
* **Webhooks** - used only when a webhook URL is configured. Sends the submission payload to that destination URL.

== Third-party libraries ==

* canvas-confetti 1.9.2 - ISC - https://github.com/catdad/canvas-confetti (bundled in `assets/lib/`).
* fireworks-js 2.10.7 - MIT - https://github.com/crashmax-dev/fireworks-js (bundled in `assets/lib/`).
* signature_pad 4.1.7 - MIT - https://github.com/szimek/signature_pad (bundled in `assets/lib/`).
* expr-eval 2.0.2 - MIT - https://github.com/silentmatt/expr-eval (bundled in `assets/lib/`).

== Changelog ==

= Complete =
* Initial release of ZapPopPop Pro, the companion add-on for ZapPopPop.
