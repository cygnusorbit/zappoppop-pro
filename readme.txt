=== ZapPopPop Pro ===
Contributors: ZapPopPop
Donate link: https://zappoppop.com/
Tags: popup, modal, popup builder, optin, lead generation
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.15
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
* **Twilio** - used only when SMS alerts are enabled. Sends alert text and configured numbers to https://api.twilio.com. Terms: https://www.twilio.com/legal/tos - Privacy: https://www.twilio.com/legal/privacy
* **YouTube / Vimeo** - used only when a video element is placed in a popup. YouTube: https://policies.google.com/privacy - Vimeo: https://vimeo.com/privacy
* **Webhooks** - used only when a webhook URL is configured. Sends the submission payload to that destination URL.

== Third-party libraries ==

* canvas-confetti 1.9.2 - ISC - https://github.com/catdad/canvas-confetti (bundled in `assets/lib/`).
* fireworks-js 2.10.7 - MIT - https://github.com/crashmax-dev/fireworks-js (bundled in `assets/lib/`).
* signature_pad 4.1.7 - MIT - https://github.com/szimek/signature_pad (bundled in `assets/lib/`).
* expr-eval 2.0.2 - MIT - https://github.com/silentmatt/expr-eval (bundled in `assets/lib/`).

== Changelog ==

= 1.0.15 - 2026-11-28 =
* Tweak: One notice on ZapPopPop screens lists every active add-on your licence does not cover, with a Check your licence link.

= 1.0.14 - 2026-11-28 =
* New: All Access Pass licensing. One licence key from zappoppop.com unlocks ZapPopPop Pro and every ZapPopPop Pro add-on.
* New: Pro modules are now separate "ZapPopPop Pro - ..." add-ons (Success Celebration, Targeting, Targeting Triggers, Woo Cart and more), installed and updated from the Extend page.
* New: The licence page shows which add-ons your key covers.
* New: Elementor-style update notice on the Plugins screen with an "Activate licence" link when automatic updates are unavailable, and a "View details" popup with this changelog.
* Tweak: Freemius SDK removed. Licensing and updates now run through zappoppop.com.
* Fix: Module loading fixes.

= 1.0.13 - 2026-11-26 =
* Tweak: More languages added and translations updated.

= 1.0.12 - 2026-11-24 =
* Tweak: Builder and module screens are fully translatable, including built-in interface strings.
* Tweak: Builder fine-tuning.

= 1.0.10 - 2026-11-22 =
* Tweak: Translation ready. Text domain loading and translatable strings added across the modules (Mailchimp, GetResponse, SMS, Double Opt-in, Side Tabs, Targeting Triggers, Woo Cart and more).

= 1.0.09 - 2026-11-22 =
* Tweak: Popup Overlay animations now load their script as a properly enqueued file.
* Tweak: Internal prefix cleanup in A/B Campaigns, Statistics and the licence screen.

= 1.0.08 - 2026-11-21 =
* New: Woo Cart module. Show popups based on cart contents and recover abandoned carts.
* Tweak: Targeting Triggers respect Woo Cart rules, so a blocked popup view is not counted as seen.

= 1.0.07 - 2026-11-20 =
* Tweak: The "Apply Image Select Preset" button was removed. The 200 x 50 preset now applies automatically to new Image Select layers.

= 1.0.06 - 2026-11-20 =
* New: Licence page and automatic updates from zappoppop.com (Easy Digital Downloads).
* New: Wave Overlay library preset.
* Tweak: Module icons added.

= 1.0.15 =
* Initial release of ZapPopPop Pro, the companion add-on for ZapPopPop.
