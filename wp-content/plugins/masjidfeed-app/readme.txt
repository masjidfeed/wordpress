=== MasjidFeed App ===
Contributors: stankovski
Tags: masjid, mobile app, prayer times, events
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.1
License: MIT
License URI: https://github.com/masjidfeed/wordpress/blob/main/LICENSE

The WordPress backend for the MasjidFeed mobile app.

== Description ==

MasjidFeed App is the WordPress-side component of the masjid mobile app: it turns
this site into the app's content management system. Admins manage the app's
branding, contact info, donation link, and feature flags, and choose which
post categories/tags feed the app's Events and Announcements screens. An
optional category can replace the regular announcement filters on Fridays in
the WordPress site timezone. These settings are available from the
**Settings → MasjidFeed App** page, while the app fetches everything over a REST API
under the `masjid/v1` namespace:

* `/wp-json/masjid/v1/config` — app configuration (branding, contact, feature flags)
* `/wp-json/masjid/v1/salahapi` — SalahAPI prayer time configuration (proxied from Muslim Prayer Times)
* `/wp-json/masjid/v1/events` — up to 100 upcoming post objects with event fields (limit parameter, default 20)
* `/wp-json/masjid/v1/announcements` — up to 20 announcement post objects
* `/wp-json/masjid/v1/posts/{id}` — one published post with optional event fields
* `/wp-json/masjid/v1/push/registrations` — App Check-protected FCM topic registration

Configuration, list, and post responses include ETag and Cache-Control
headers and cache their rendered payloads using the WordPress Transients API.

Admins can also enable content-only rendering for mobile app web views. When
enabled, adding `?render=contentOnly` to a published post or page URL removes
the visual site header and footer while retaining theme styles and scripts.
Block themes keep their selected template; classic themes use a minimal plugin
template containing the featured image and content. The `/config`
response automatically adds this parameter to non-empty donation and Ramadan
URLs.

It requires the "Muslim Prayer Times" plugin to be installed and active,
since it builds on its prayer-time data rather than duplicating it. Events
are provided by an events plugin selected in settings: "Awesome Calendar
Events" or "The Events Calendar". When no events plugin is selected, the
Events feature is disabled.

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

The **Settings → MasjidFeed App → Push Notifications** section accepts a Firebase
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

== Release Build ==

The development repository contains Composer manifests and the unscoped vendor
directory. Run `composer install --no-dev --optimize-autoloader`, then
`composer install --optimize-autoloader` and `composer run build-release` before
deploying to WordPress.org. The build prefixes dependencies under
`Masjid_Feed\\Dependencies` and produces the self-contained `vendor-prefixed`
directory used at runtime.

== External services ==

This plugin connects to Google Firebase, a push messaging platform provided by
Google. It is needed to deliver the push notifications that the MasjidFeed
mobile app receives (new events and announcements posted on this site). The
connection is optional: it is only used when Push Notifications are configured
on the **Settings → MasjidFeed App** page with a Firebase service account.

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
https://firebase.google.com/terms — Privacy Policy:
https://policies.google.com/privacy

== Changelog ==

= 1.0.0 =
* Initial release.
