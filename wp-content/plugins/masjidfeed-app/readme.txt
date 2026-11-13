=== MasjidFeed App ===
Contributors: stankovski
Tags: masjid, mosque, mobile app, prayer times, events, announcements, push notifications, donation
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Requires Plugins: muslim-prayer-times
Stable tag: 1.0.2
License: MIT
License URI: https://github.com/masjidfeed/wordpress/blob/main/LICENSE

Your masjid's mobile app, powered by the WordPress site you already run. Prayer times, announcements, events, donations and push notifications - no new tools to learn.

== Description ==

<!--
MAINTAINER NOTE - WordPress.org design assets (place in the plugin's `assets/` folder):

* banner-1544x500.png and banner-772x250.png - hero banner at the top of the plugin page.
  WHAT IT SHOULD BE: a wide, calm scene. Left third: an iPhone mockup of the MasjidFeed
  home screen showing prayer times, iqamah times and a real masjid's logo and accent
  color. Right two-thirds: a simplified WordPress card showing "Settings -> MasjidFeed
  App", with a thin arrow flowing from WordPress to the phone - "publish on your site,
  the app follows". Light background, brand colors, no small text. The 772x250 crop must
  still read well.
* icon-256x256.png - plugin icon (source: assets/masjidfeed-app-icon.png). App-icon style
  mark that still reads at 64px.

Lines marked [LOGO: …], [HERO: …] and [IMAGE: …] below are placeholders. Replace them
with real <img> tags (or drop the corresponding files into assets/) before publishing.
-->

[LOGO: MasjidFeed mark and wordmark, centered, roughly 600px wide, on transparent or white background]

[HERO - WHAT IT SHOULD BE: a side-by-side composite on a light background. Left: an
iPhone and an Android phone, both running MasjidFeed with the same masjid's logo,
accent color, prayer times and iqamah times on the home screen. Right: a floating
card showing the WordPress "Settings -> MasjidFeed App" screen. A subtle arrow flows
from WordPress to the phones - one glance says "your website powers the app".
Also crop a 1544x500 banner version of this scene for the WordPress.org assets.]

MasjidFeed App turns your existing WordPress site into the content system for the
MasjidFeed mobile app - a calm, native app for iOS and Android that your congregation
opens every day.

You keep doing what you already do: publishing posts, adding events, updating pages.
MasjidFeed App does the rest. It serves the app your prayer times, announcements,
events, donation link and branding over a fast REST API. No new dashboard, no double
entry, no developer required.

Get the app for your community:

* <a href="https://apps.apple.com/us/app/masjidfeed/id6794994746">Download on the App Store</a>
* <a href="https://play.google.com/store/apps/details?id=com.goodsoftware.masjidapp">Get it on Google Play</a>
* <a href="https://goodsoftware.foundation/p/masjidfeed/">Project home</a>

= Your masjid, not a template =

Everything lives in one place: **Settings -> MasjidFeed App**. Set your masjid's name,
logo, splash screen, primary color (light and dark mode), contact details and social
links once, and every copy of the app shows your masjid. Changes appear on phones the
next time the app is started.

[IMAGE - WHAT IT SHOULD BE: three phones side by side, each running MasjidFeed for a
different masjid - different logo, different accent color, same familiar layout.
Caption idea: "One app, your masjid's identity."]

= Prayer times, without double entry =

MasjidFeed App builds on the free Muslim Prayer Times plugin (required). The
calculation settings your site already uses are served straight to the app - including
iqamah times - so the notice board, the website and the app always agree. The app also
offers a home-screen widget for prayer times.

= Announcements your congregation actually reads =

Choose which categories and tags feed the app's Announcements screen. Publish a post,
and it is there - with an optional push notification. No filters selected? Every post
is shared, so nothing changes about how you already publish.

= Events from the calendar you already use =

MasjidFeed App speaks to the events plugin you may already run:

* <a href="https://wordpress.org/plugins/the-events-calendar/">The Events Calendar</a>
* <a href="https://wordpress.org/plugins/awesome-calendar-events/">Awesome Calendar Events</a>

Pick one in settings, narrow the feed with category filters, and upcoming events
appear in the app. No events plugin selected? The Events screen simply stays off.

= Push notifications, in one click =

Give a post your trigger tag and press publish: your congregation gets a notification
with the title, a short body and the featured image. Behind the scenes delivery runs
through Firebase Cloud Messaging - via the hosted push service, your own Firebase
project, or both. Failed sends retry with backoff, logs are kept for 30 days, and the
Debug tools let you validate the connection and send a test to a real device before
you go live.

Setup walkthrough: <a href="https://goodsoftware.foundation/p/masjidfeed/push-notifications/">Push notifications guide</a>

= Friday, handled =

Optionally pick a Friday category. On Fridays (in your site's timezone) the app shows
only posts from that category - Khutbah topics, Jumu'ah logistics, Ramadan schedules -
then returns to your regular filters afterwards. Set it once and forget it.

[IMAGE - WHAT IT SHOULD BE: a phone on a Friday showing a Jumu'ah announcement card
(e.g. "Jumu'ah 1:00 PM - Khutbah by …") with a small "Fri" calendar badge, next to a
tiny WordPress editor showing the same post being published.]

= Even your parking lot is in the app =

Paste your parking map zones and restriction schedules once and the app serves them to
drivers. Until you save the map, the app quietly keeps using its built-in data.

= Safe, quiet and fast =

* Responses are cached with ETag and Cache-Control headers, so phones load quickly and
  your server stays cool.
* Push device registration is protected by Firebase App Check.
* Your Firebase service account is encrypted with Sodium; the encryption key lives on
  your server and is never stored in the WordPress database.
* Optional API and push tracing records the latest 100 calls and sends with redacted
  payloads - and wipes clean when your debugging is done.

= Preview before your community does =

The Preview tab lists your latest announcements exactly as the app will show them, so
you can sanity-check your filters before Friday.

= Donate, Ramadan, and more =

Feature switches let you turn parts of the app on or off (Qibla compass, prayer
reminders), and link fields connect the app to pages you already have:

* Donation page - opens in a clean reader view, without your theme's header and footer
* Optional Ramadan page, shown in the app during Ramadan
* Phone, email, website, WhatsApp, Facebook, Instagram and your address for directions

= New to MasjidFeed? =

Start here:

1. <a href="https://goodsoftware.foundation/p/masjidfeed/getting-started/">Getting started</a> - from plugin install to phones in hands
2. <a href="https://goodsoftware.foundation/p/masjidfeed/push-notifications/">Push notifications setup</a> - Firebase, App Check and test sends
3. <a href="https://goodsoftware.foundation/p/masjidfeed/faq/">Frequently asked questions</a>
4. <a href="https://goodsoftware.foundation/p/masjidfeed/support/">Support</a>

== Installation ==

1. Install and activate the free "Muslim Prayer Times" plugin (required), plus your
   events plugin (The Events Calendar or Awesome Calendar Events) if you use one.
2. Install and activate MasjidFeed App.
3. Open Settings -> MasjidFeed App and fill in your masjid's name, branding, contact
   details, and category filters.
4. Install MasjidFeed from the App Store or Google Play, add your masjid, and confirm
   that prayer times and announcements match your site.
5. Optional: configure push notifications under the Push Notifications tab.

Full guide: https://goodsoftware.foundation/p/masjidfeed/getting-started/

== Upgrading from Masjid App ==

Install and activate MasjidFeed App before deleting the legacy Masjid App
plugin. Activation imports the legacy settings, Firebase credentials, API trace
setting, and retained trace entries. Values already configured in MasjidFeed
App take precedence.

The legacy plugin's uninstall handler deletes its stored options, so deleting
it before activating MasjidFeed App leaves no settings to import. It is safe to
deactivate the legacy plugin first, activate MasjidFeed App, verify the
settings, and then delete the legacy plugin.

Firebase credentials are decrypted with the previous
`MASJIDAPP_CREDENTIAL_KEY` and re-encrypted for MasjidFeed App. Sites may keep
using that legacy key name, although migrating the same value to
`MASJIDFEED_CREDENTIAL_KEY` is recommended.

== Push Notifications ==

The **Settings -> MasjidFeed App -> Push Notifications** section accepts a Firebase
service-account JSON file plus the iOS plist and Android google-services JSON
for this tenant. The service account is encrypted with Sodium using a
`MASJIDFEED_CREDENTIAL_KEY` constant or environment variable of at least 32
characters. The key must not be stored in the WordPress database.

Publishing a post with the configured trigger tag queues one Firebase topic
notification. Editors may send a tagged, published post again from its editor.
Post notifications include the post's large featured image when available. The
image must be publicly accessible over HTTPS. Android displays the FCM image
directly; iOS requires a Notification Service Extension in the mobile app to
download and attach the image to the notification.

Transient Firebase failures retry with backoff, logs are retained for 30 days,
and a real system cron must invoke WordPress cron reliably.

Administrators can temporarily enable API and push tracing from the settings
page. The latest 100 MasjidFeed App REST calls and Firebase sends are retained with
timing and status. REST traces include App Check presence and redacted
parameters; Firebase traces include the redacted push payload. Disable tracing
and clear the entries after debugging is complete.

The optional deep-link override supports `{masjidId}`, `{postId}`, `{type}` and
`{slug}`. Blank uses the built-in event or announcement URL. The Firebase Admin
SDK is installed from the committed Composer lockfile and vendored for release.

Full walkthrough: https://goodsoftware.foundation/p/masjidfeed/push-notifications/

== Screenshots ==

1. Settings -> MasjidFeed App, General tab: masjid name, logo and splash logo, primary
   colors for light and dark mode, and contact & social links.
2. General tab, continued: donation and Ramadan page URLs, feature flags, and the
   optional Friday announcement category.
3. Events and Announcements filters: choosing the events plugin and the categories
   and tags that feed the app.
4. Push Notifications tab: delivery channels, Firebase credentials and the hosted
   push service registration.
5. Debug tools: Validate connection, a test notification to a real device, and the
   API and push traces with redacted payloads.
6. Preview tab: the latest announcements as the mobile app will list them.
7. Parking Map tab: zones and restriction schedules before saving.
8. The mobile app: home screen with prayer times and the home-screen widget.
9. The mobile app: announcements and events screens.

== FAQ ==

= Do I need a developer to set this up? =

No. The settings screen covers everything an administrator needs: branding, contact
details, category filters, donation and Ramadan links, and parking. Push notification
setup does involve Firebase, but the built-in Debug tools validate each step and the
guide walks you through it.

= Does this plugin create an app-store listing for my masjid? =

No. MasjidFeed is one shared app published by the Good Software Foundation; this
plugin configures what it displays for your masjid - your logo, colors, content and
links. Your community installs MasjidFeed from the App Store or Google Play.

= Which events plugins are supported? =

The Events Calendar and Awesome Calendar Events. If neither is selected, the Events
feature in the app is disabled.

= Is push notification setup required? =

No. Without it, the app still shows prayer times, announcements and events - it just
does not send notifications. You can add push later from the Push Notifications tab.

= Will it slow my site down? =

The endpoints are cached with ETag and Cache-Control headers and store rendered
payloads via the WordPress Transients API, so app traffic stays light.

= I used the older "Masjid App" plugin. What do I do? =

Install and activate MasjidFeed App first; activation imports your legacy settings,
Firebase credentials and trace entries. Then verify, and only then delete the old
plugin. Details are in the "Upgrading from Masjid App" section above.

= Where do my Firebase credentials live? =

Encrypted in your WordPress database with Sodium. The encryption key
(`MASJIDFEED_CREDENTIAL_KEY`) lives on your server as an environment variable or
`wp-config.php` constant - never in the database.

= Where can I get help? =

Visit <a href="https://goodsoftware.foundation/p/masjidfeed/support/">the support page</a> or open an
issue at https://github.com/masjidfeed/wordpress/issues

== External services ==

This plugin connects to Google Firebase, a push messaging platform provided by
Google. It is needed to deliver the push notifications that the MasjidFeed
mobile app receives (new events and announcements posted on this site). The
connection is optional: it is only used when Push Notifications are configured
on the **Settings -> MasjidFeed App** page with a Firebase service account.

What is sent and when:

* **Google OAuth 2.0 token endpoint** (`oauth2.googleapis.com/token`): a signed
  JWT assertion built from the service account's email and project ID is sent
  to obtain a short-lived Firebase access token. This happens whenever a
  notification is queued or a topic registration is processed and no
  unexpired token is cached.
* **Firebase Cloud Messaging** (`fcm.googleapis.com`): when an admin publishes
  a post that carries the configured trigger tag (or re-sends it from the post
  editor), the notification payload is sent: the post title, an excerpt-style
  body, the publicly accessible featured-image URL (if set), the post URL or
  deep link, and the Firebase topic name. No visitor or subscriber data is
  sent by the plugin itself.
* **Firebase Instance ID** (`iid.googleapis.com`): when a device registers for
  push notifications through the app, the app-supplied device registration
  token and topic name are forwarded so the device can be added to or removed
  from the topic.
* **Firebase App Check** (`firebaseappcheck.googleapis.com`): the plugin
  downloads Firebase's public signing keys to verify App Check tokens that
  the mobile app attaches to the registration endpoint. No site data is sent
  in this request; it is a read-only fetch of public keys, cached for one
  hour.

The Firebase service account credentials entered by the admin are stored
encrypted in the WordPress database and are used to sign these requests; they
are never sent to any service other than Google's Firebase endpoints listed
above.

This service is provided by Google. Terms of Service:
https://firebase.google.com/terms - Privacy Policy:
https://policies.google.com/privacy

== For developers ==

The app fetches everything over a REST API under the `masjid/v1` namespace:

* `/wp-json/masjid/v1/config` - app configuration (branding, contact, feature flags)
* `/wp-json/masjid/v1/salahapi` - SalahAPI prayer time configuration (proxied from Muslim Prayer Times)
* `/wp-json/masjid/v1/events` - up to 100 upcoming post objects with event fields (limit parameter, default 20)
* `/wp-json/masjid/v1/announcements` - up to 20 announcement post objects
* `/wp-json/masjid/v1/posts/{id}` - one published post with optional event fields
* `/wp-json/masjid/v1/parking-config` - parking map zones and restriction schedules (404 until saved on the Parking tab)
* `/wp-json/masjid/v1/push/registrations` - App Check-protected FCM topic registration

Configuration, list, and post responses include ETag and Cache-Control
headers and cache their rendered payloads using the WordPress Transients API.

Admins can enable content-only rendering for mobile app web views. When
enabled, adding `?render=contentOnly` to a published post or page URL removes
the visual site header and footer while retaining theme styles and scripts.
Block themes keep their selected template; classic themes use a minimal plugin
template containing the featured image and content. The `/config` response
automatically adds this parameter to non-empty donation and Ramadan URLs.

The response field names consumed by the mobile app are documented in `api.md`
at https://github.com/masjidfeed/wordpress

== Release Build ==

The development repository contains Composer manifests and the unscoped vendor
directory. Run `composer install --no-dev --optimize-autoloader`, then
`composer install --optimize-autoloader` and `composer run build-release` before
deploying to WordPress.org. The build prefixes dependencies under
`Masjid_Feed\Dependencies` and produces the self-contained `vendor-prefixed`
directory used at runtime.

== Changelog ==

= 1.0.0 =
* Initial release.
