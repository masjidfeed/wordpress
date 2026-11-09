# Parking API

Data and verification steps for `GET /wp-json/masjid/v1/parking-config`, which
serves the masjid parking map. The endpoint's payload is the master copy:
every change starts by downloading what is live.

## Files

- `sample.geojson` — sample GeoJSON
- `sample.json` — sample parking configuration based on above GeoJSON
- `parking-geojson-to-config.py` — converts between the two formats and checks
  the result. Needs Python 3.8 or later, nothing else installed.

## Publishing a change

1. Download what is live:

   `curl -s -A 'admin/1.0' https://<site.url>/wp-json/masjid/v1/parking-config > current.json`

2. Turn it into GeoJSON: `python3 parking-geojson-to-config.py --to-geojson current.json > zones.geojson`
3. Edit `zones.geojson` in geojson.io (zones) or `events` in `current.json` (schedules).
4. Rebuild the payload: `python3 parking-geojson-to-config.py zones.geojson current.json > parking-config.json`
   — run until it prints `OK`. Keep the output name different from `current.json`.
5. Paste the new `parking-config.json` into the Parking settings tab and save.
   Invalid JSON is refused and the previous document keeps being served.
6. Re-run step 1 and confirm the change is live.

## Done when (deployment checklist)

- `curl -s -A 'admin/1.0' https://<site.url>/wp-json/masjid/v1/parking-config > live.json`
  returns `200` and the saved payload.
- Round-tripping the download prints `OK` twice:
  `python3 parking-geojson-to-config.py --to-geojson live.json > live.geojson`
  `python3 parking-geojson-to-config.py live.geojson live.json > /dev/null`
- A browser User-Agent gets `400` (the endpoint requires `appname/version`).
- A request with a matching `If-None-Match` gets `304`.
- Nothing saved → `404`.
- If a page cache or CDN ever caches this route, purge it on save.

The conversion rules (for reproducing the script elsewhere) and the full
response shape are documented in `api.md` and the ICOB parking-config task
description.