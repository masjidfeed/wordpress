# Push Gateway — High-Level Design

Central push-delivery service that lets organizations self-serve install the
MasjidFeed App WordPress plugin while MasjidApp is centrally distributed as a
single multi-tenant binary. The gateway is the **sole holder of the Firebase
project service account**, and isolation between tenants is enforced at the
gateway instead of by Firebase project boundaries.

## Goals

1. No malicious actor can broadcast to all app instances.
2. Organization A cannot send notifications to organization B's users.
3. Self-serve onboarding: "install plugin → verify site → done."

## Why the gateway (current design problem)

Today every WordPress site uploads a service account for the **shared**
Firebase project (`masjidapp-bcfc7`). FCM does not scope sends: any service
account on a project can deliver to **any** token or topic of that project, so
under the current model org A's site admin can already broadcast to every
MasjidApp user of every tenant.

Additionally, the current topic name (`masjid_<masjid_id>`,
`Masjid_Feed_Firebase::topic()`) is derived from an admin-editable setting, so
two sites can collide on the same topic. The gateway assigns canonical topics
instead.

## Components

```
┌─────────-───┐  1. fetch /config (site info, gateway URL, site_id)
│  MasjidApp  │──────────────────────────────┐
│ (one binary)│                              ▼
└─────┬───────┘                    ┌──────────────┐
      │ 2. topic key               │   Gateway    │  owns the ONLY
      │   (App Check + site_id)    │  (operator-  │  Firebase project
      └───────────────────────────▶│   hosted)    │  service account
                                   └──────▲───────┘
┌────────────────-┐  3. POST send    │
│ WordPress site  │───────────-──────┘
│ (MasjidFeed App │     (site_id + secret)
│    plugin)      │   keeps: settings, events, announcements,
└─────────────-───┘   queue table, retry/cron logic
```

## 1. Onboarding

1. Org installs the plugin; the plugin exposes a verification resource at
   `masjid/v1/gateway-verification` containing a challenge value from the
   signup form.
2. Admin selects whether to use MasjidFeed push notifications or custom Firebase settings.
3. When MasjidFeed push notifications are enabled, the plugin calls the gateway to register; the gateway calls back the site URL to verify domain ownership and issues a `gateway_secret`; the secret is stored encrypted in the plugin.
4. The gateway stores the `gateway_secret_hash` along with a generated a per-site **topic secret**: `site_id → url, gateway_secret_hash, topic_secret` — per-*org* state, never per-device.

## 2. Topic key issuance (app → gateway)

`POST {gateway}/v1/topic-key` — called by the **app**, not the plugin.

- Body: `{site_id}`; header: `X-Firebase-AppCheck`.
- Gateway verifies the App Check token against the central project's JWKS and
  the two known app IDs (both are properties of the single app binary, so they
  are static gateway config — no per-site lookup).
- Gateway verifies the site exists and is active (local registry; site
  `/config` ping cached for minutes, so this is ~one outbound request per user
  session, not per device).
- Gateway returns `topic = "tenant_<site_id>_<version>_<topic_secret>"`.
- The app subscribes **client-side** via `FirebaseMessaging.subscribeToTopic`
  — free, no server call, no storage, no token-hygiene jobs.

The topic secret must not ship in the plugin or the public `/config` — it is
only obtainable through this App-Check-gated endpoint. The client SDK is the
only client-side subscribe path.

## 3. Sending (plugin → gateway)

`POST {gateway}/v1/sites/{site_id}/messages` — called by the plugin's
`process_job` in place of `send_topic()`.

- Auth: `Authorization: Bearer <gateway_secret>`; `site_id` comes from the
  URL, **not** the body.
- Gateway actions: authenticate → rate limit (per-site token bucket + daily
  quota) → validate payload schema (title/body lengths, `deepLink` must match
  the site's registered template scheme) → check `notification_id` idempotency
  (recent-IDs cache per site) → publish to `/topics/tenant_<site_id>_<version>_<topic_secret>` →
  return the FCM message name.
- The plugin keeps its existing queue, dedupe, retry/backoff, and admin UI
  untouched; only the send call and the "Validate connection" debug tool change
  to hit the gateway.

### Topic rotation

Version the topic name. When a topic secret is rotated, the app re-subscribes
when `/config` or the topic key changes, and the gateway accepts sends to the
previous version for a grace window.

## 4. Data model (gateway)

Per-tenant state only — **no per-device state**:

```
sites        (site_id, url, status, gateway_secret_hash, topic_secret, quotas, created_at)
audit_log    (actor, action, site_id, at)
```

`sites` (site_id, url, status, gateway_secret_hash, topic_secret, quotas, created_at) are stored in Firestore or Cosmos DB (or blob storage) and loaded into memory on service startup. In the future, cache can be moved to Redis. Change feed is used to keep the in-memory cache in sync with the persistent storage. Change feed is polled every 5-10 minutes to reduce cost.

`audit_log` is stored in Firestore or Cosmos DB and contains only site-registration-related actions.

## 5. Threat-model check

| Threat | Mitigation |
|---|---|
| Org A's WP admin wants to hit org B's users | Their secret only authorizes `/v1/sites/<their site_id>/messages`; there is no endpoint accepting arbitrary topics or tokens. Topic names are gateway-assigned and contain a per-site secret. |
| Compromised site secret, mass spam | Per-site rate limits/quotas, payload caps, one secret per site → revoke centrally without touching others. |
| Malicious app user broadcasts | They never hold Firebase credentials; sends only happen through the gateway. Topic membership via the client SDK means a user controls only their own subscription. |
| Cross-tenant eavesdropping (accepted trade-off) | App Check proves "a genuine app instance," not "which tenant the user selected," so a determined user could fetch topic keys for multiple sites and subscribe to each. They can only *receive*. Acceptable because masjid announcement content is public; state it in the privacy notes. |
| Scripted fake topic-key requests | App Check verified at the gateway before any key is issued; debug tokens only in a staging environment. |
| Topic secret leakage | Secret never appears in the plugin, `/config`, or any public endpoint; rotation invalidates leaked versions after the grace window. |

## 6. Cost profile

- No `registrations` table, no FCM IID storage, no stale-token revalidation,
  no unregister endpoint.
- Undeliverable/stale device tokens cost nothing — FCM topic fan-out absorbs
  them silently.
- Two stateless endpoints plus a tiny per-tenant registry: deployable on a
  free-tier serverless runtime (e.g., Cloud Run or Cloud Functions + Firestore
  free tier) at roughly $0.

## 7. Trade-offs / notes

- **No subscriber counts / device analytics** per tenant.
- **Cross-tenant eavesdropping** is possible by determined users (see threat
  model) — accepted because notification content is public.
- Loss of per-site APNs tuning is irrelevant: one central project already owns
  APNs.
- The gateway is intentionally thin: topic-key issuance, send, quotas — no
  content storage, no device registry. Stateless behind a load balancer.
- Plugin-side removals: service-account upload/validation
  (`process_firebase_uploads`), `Masjid_Feed_Credential_Store` usage becomes
  gateway-secret storage, `Masjid_Feed_Firebase`/`Masjid_Feed_Firebase_Client`
  FCM send calls replaced by an HTTP call, and the debug tools re-target the
  gateway's health/validate endpoints.

---

## Appendix: token-registry variant (superseded)

The original design managed topic membership server-side. It provides
stronger isolation (no cross-tenant eavesdropping at all) and per-tenant
subscriber analytics, at the cost of per-device storage and hygiene work. It
remains the fallback if the eavesdropping trade-off above ever becomes
unacceptable.

### Device registration (gateway endpoint)

`POST {gateway}/v1/registrations` — called by the **app**, not the plugin.

- Body: `{site_id, fcm_token, platform}`; header: `X-Firebase-AppCheck`.
- Gateway verifies the App Check token against the central project's JWKS and
  the two known app IDs.
- Gateway verifies the site exists and is active (local registry, plus a
  periodic ping of the site's `/config`).
- **Topic mapping:** gateway assigns the canonical topic `tenant_<site_id>`.
  The plugin-supplied `masjid_id` is never used for addressing.
- Gateway subscribes the token via the FCM IID API (server-side only — the
  client SDK's `subscribeToTopic` must never be used in this variant, because
  it is unauthenticated and would defeat the model) and stores
  `token → site_id, platform, last_seen`.
- `DELETE` mirrors this (App Check required) and unsubscribes.

The plugin's `/push/registrations` endpoint is deprecated (kept one release for
old app versions, proxied to the gateway).

### Sending

Identical to the recommended design, except the gateway resolves the topic
from its own registry instead of a per-site topic secret, and can therefore
report exact subscriber counts per tenant.

### Data model

```
sites        (site_id, url, status, secret_hash, quotas, created_at)
registrations(fcm_token PK, site_id, platform, app_check_ok, last_seen)
message_log  (notification_id, site_id, fcm_message_name, status, at)  -- short retention
audit_log    (actor, action, site_id, at)
```

### Additional mitigations (beyond the recommended design)

| Threat | Mitigation |
|---|---|
| Malicious app user subscribes to another tenant's topic | Client-side topic subscribe is never used; membership is managed exclusively via gateway IID calls. A user only ever controls their own token. |
| Stale/dead tokens | Periodic FCM token re-validation; unsubscribes on `NOT_FOUND` errors. |
