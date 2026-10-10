#!/usr/bin/env python3
"""
Parking zones: GeoJSON <-> app config.

The app does not read GeoJSON. It reads the shape served by
/wp-json/masjid/v1/parking-config (see services/parking-config.ts), which
differs from what geojson.io exports in point order, ring closure and where the
zone details live. This converts between the two, and refuses to emit a config
the app would reject or misread.

Turn the published payload into GeoJSON for editing in geojson.io:

    python3 scripts/parking-geojson-to-config.py --to-geojson current.json > zones.geojson

Build the payload to publish:

    python3 scripts/parking-geojson-to-config.py zones.geojson current.json > parking-config.json

In the second form, current.json supplies everything that is not geography:

    { "center": { "latitude": .., "longitude": .. }, "nearRadiusMeters": 800, "events": [ .. ] }

The payload that is already published works as it is, because its own "zones"
are ignored. That makes the published payload the master copy: download it,
edit, rebuild. The output must go to a different file from the one being read,
or the shell empties it first.

Standard library only. Needs Python 3.8 or later.
"""

import json
import math
import re
import sys
from datetime import date

ZONE_TYPES = ["no-parking", "event-restricted", "allowed"]
# geojson.io draws these simplestyle properties; they are ignored on the way back.
ZONE_COLOURS = {"no-parking": "#d32f2f", "event-restricted": "#f9a825", "allowed": "#2e7d32"}
# A zone further than this from the mosque is almost certainly a lat/lon mix-up.
MAX_DISTANCE_FROM_CENTER_METERS = 3000
DEFAULT_NEAR_RADIUS_METERS = 800

ID_PATTERN = re.compile(r"^[a-z0-9]+(-[a-z0-9]+)*$")
TIME_PATTERN = re.compile(r"^([01]\d|2[0-3]):[0-5]\d$")
DATE_PATTERN = re.compile(r"^\d{4}-\d{2}-\d{2}$")

errors = []


def fail(message):
    errors.append(message)


def read_json(path):
    try:
        with open(path, encoding="utf-8") as handle:
            return json.load(handle)
    except (OSError, ValueError) as error:
        sys.exit(f"Could not read {path}: {error}")


def write_json(data):
    json.dump(data, sys.stdout, indent=2, ensure_ascii=False)
    sys.stdout.write("\n")


def is_number(value):
    return isinstance(value, (int, float)) and not isinstance(value, bool)


def is_text(value):
    return isinstance(value, str) and value.strip() != ""


def distance_meters(a, b):
    d_lat = math.radians(b["latitude"] - a["latitude"])
    d_lon = math.radians(b["longitude"] - a["longitude"])
    h = (
        math.sin(d_lat / 2) ** 2
        + math.sin(d_lon / 2) ** 2
        * math.cos(math.radians(a["latitude"]))
        * math.cos(math.radians(b["latitude"]))
    )
    return 2 * 6_371_000 * math.asin(math.sqrt(h))


def is_real_date(value):
    """True for a "YYYY-MM-DD" that exists on the calendar, so not 2027-02-30."""
    if not (isinstance(value, str) and DATE_PATTERN.match(value)):
        return False
    try:
        date.fromisoformat(value)
    except ValueError:
        return False
    return True


def check_settings(settings, source):
    if not isinstance(settings, dict):
        fail(f"{source}: must be a JSON object")
        return
    center = settings.get("center")
    valid_center = (
        isinstance(center, dict)
        and is_number(center.get("latitude"))
        and is_number(center.get("longitude"))
        and abs(center["latitude"]) <= 90
        and abs(center["longitude"]) <= 180
    )
    if not valid_center:
        fail(f'{source}: "center" must be {{ "latitude": number, "longitude": number }}')
    radius = settings.get("nearRadiusMeters")
    if radius is not None and not (is_number(radius) and radius > 0):
        fail(f'{source}: "nearRadiusMeters" must be a positive number')
    if not isinstance(settings.get("events"), list):
        fail(f'{source}: "events" must be a list (it may be empty)')


def check_events(events, zone_ids):
    seen = set()
    for index, event in enumerate(events):
        if not isinstance(event, dict):
            fail(f"event #{index + 1}: must be a JSON object")
            continue
        event_id = event.get("id")
        label = f'event "{event_id}"' if is_text(event_id) else f"event #{index + 1}"

        if not is_text(event_id):
            fail(f'{label}: missing "id"')
        elif event_id in seen:
            fail(f"{label}: duplicate id")
        else:
            seen.add(event_id)
        if not is_text(event.get("name")):
            fail(f'{label}: missing "name"')
        if not is_text(event.get("message")):
            fail(f'{label}: missing "message"')

        schedule = event.get("schedule")
        if not isinstance(schedule, dict):
            schedule = {}
        start, end = schedule.get("start"), schedule.get("end")
        if not all(isinstance(t, str) and TIME_PATTERN.match(t) for t in (start, end)):
            fail(f'{label}: schedule "start" and "end" must be "HH:mm" (24-hour)')
        elif start == end:
            # The app treats end <= start as running past midnight, so this is
            # a full day rather than an empty window. Far likelier a typo.
            fail(
                f'{label}: "start" and "end" are both {start}, which the app reads as a full '
                '24 hours. For an all-day event use "00:00" to "23:59".'
            )

        if schedule.get("type") == "weekly":
            days = schedule.get("days")
            valid = (
                isinstance(days, list)
                and len(days) > 0
                and all(isinstance(d, int) and not isinstance(d, bool) and 0 <= d <= 6 for d in days)
            )
            if not valid:
                fail(f'{label}: weekly "days" must be numbers 0 (Sunday) to 6 (Saturday)')
        elif schedule.get("type") == "dates":
            dates = schedule.get("dates")
            if not isinstance(dates, list) or len(dates) == 0:
                fail(f'{label}: "dates" must be a list of "YYYY-MM-DD"')
            else:
                for value in dates:
                    if not is_real_date(value):
                        fail(f'{label}: "{value}" is not a real "YYYY-MM-DD" date')
        else:
            fail(f'{label}: schedule "type" must be "weekly" or "dates"')

        event_zone_ids = event.get("zoneIds")
        if event_zone_ids != "all":
            if not isinstance(event_zone_ids, list) or len(event_zone_ids) == 0:
                fail(f'{label}: "zoneIds" must be "all" or a list of zone ids')
            else:
                for zone_id in event_zone_ids:
                    if zone_id not in zone_ids:
                        fail(f'{label}: zoneIds names "{zone_id}", which is not a zone')


def feature_to_zone(feature, index, center):
    if not isinstance(feature, dict):
        fail(f"zone #{index + 1}: must be a GeoJSON Feature")
        return None
    properties = feature.get("properties") or {}
    zone_id = properties.get("id")
    label = f'zone "{zone_id}"' if is_text(zone_id) else f"zone #{index + 1}"

    if not (isinstance(zone_id, str) and ID_PATTERN.match(zone_id)):
        fail(f'{label}: "id" must be lowercase words joined by hyphens, e.g. "north-lot"')
    if not is_text(properties.get("name")):
        fail(f'{label}: missing "name"')
    if properties.get("type") not in ZONE_TYPES:
        fail(f'{label}: "type" must be one of {", ".join(ZONE_TYPES)}')
    message = properties.get("message")
    if message is not None and not isinstance(message, str):
        fail(f'{label}: "message" must be text')

    geometry = feature.get("geometry") or {}
    rings = geometry.get("coordinates")
    if geometry.get("type") != "Polygon" or not isinstance(rings, list) or not rings:
        fail(f'{label}: must be a Polygon, found {geometry.get("type") or "no geometry"}')
        return None
    if len(rings) > 1:
        fail(f"{label}: has a hole cut out of it, which the app cannot represent")

    ring = rings[0]
    valid_points = isinstance(ring, list) and all(
        isinstance(p, list) and len(p) >= 2 and is_number(p[0]) and is_number(p[1]) for p in ring
    )
    if not valid_points:
        fail(f"{label}: every point must be [longitude, latitude] numbers")
        return None

    # GeoJSON repeats the first point to close the ring; the app closes it implicitly.
    if len(ring) > 1 and ring[0][:2] == ring[-1][:2]:
        ring = ring[:-1]
    if len(ring) < 3:
        fail(f"{label}: needs at least 3 points")
        return None

    # GeoJSON is [longitude, latitude]; the app wants named fields.
    coordinates = [{"latitude": point[1], "longitude": point[0]} for point in ring]
    if center and any(
        distance_meters(center, point) > MAX_DISTANCE_FROM_CENTER_METERS for point in coordinates
    ):
        fail(
            f"{label}: has a point more than {MAX_DISTANCE_FROM_CENTER_METERS} m from the mosque. "
            "Check the latitude and longitude are not swapped."
        )

    zone = {"id": zone_id, "name": properties.get("name"), "type": properties.get("type")}
    if message:
        zone["message"] = message
    zone["coordinates"] = coordinates
    return zone


def to_config(geojson_path, settings_path):
    geojson = read_json(geojson_path)
    settings = read_json(settings_path)

    is_collection = (
        isinstance(geojson, dict)
        and geojson.get("type") == "FeatureCollection"
        and isinstance(geojson.get("features"), list)
    )
    if not is_collection:
        sys.exit(f"{geojson_path} is not a GeoJSON FeatureCollection")

    check_settings(settings, settings_path)
    center = settings["center"] if not errors else None

    features = geojson["features"]
    if not features:
        fail("zones: the file has no shapes in it")
    zones = [feature_to_zone(feature, index, center) for index, feature in enumerate(features)]

    zone_ids = set()
    for zone in zones:
        if zone is None:
            continue
        if zone["id"] in zone_ids:
            fail(f'zone "{zone["id"]}": duplicate id')
        zone_ids.add(zone["id"])
    if isinstance(settings, dict) and isinstance(settings.get("events"), list):
        check_events(settings["events"], zone_ids)

    if errors:
        problems = "\n".join(f"  - {error}" for error in errors)
        sys.exit(f"Not written. {len(errors)} problem(s):\n{problems}")

    config = {
        "center": settings["center"],
        "nearRadiusMeters": settings.get("nearRadiusMeters", DEFAULT_NEAR_RADIUS_METERS),
        "zones": zones,
        "events": settings["events"],
    }
    write_json(config)
    print(f"OK: {len(zones)} zone(s), {len(config['events'])} event(s)", file=sys.stderr)


def to_geojson(config_path):
    config = read_json(config_path)
    if not isinstance(config, dict) or not isinstance(config.get("zones"), list):
        sys.exit(f'{config_path} has no "zones" list')

    features = []
    try:
        for zone in config["zones"]:
            ring = [[point["longitude"], point["latitude"]] for point in zone["coordinates"]]
            ring.append(ring[0])
            properties = {"id": zone["id"], "name": zone["name"], "type": zone["type"]}
            if zone.get("message"):
                properties["message"] = zone["message"]
            colour = ZONE_COLOURS.get(zone["type"])
            if colour:
                properties.update({"fill": colour, "stroke": colour, "fill-opacity": 0.4})
            features.append(
                {
                    "type": "Feature",
                    "properties": properties,
                    "geometry": {"type": "Polygon", "coordinates": [ring]},
                }
            )
    except (KeyError, TypeError, IndexError):
        sys.exit(
            f"{config_path} is not in the payload shape: every zone needs id, name, type and "
            'coordinates as a list of { "latitude", "longitude" }'
        )
    write_json({"type": "FeatureCollection", "features": features})
    print(f"OK: {len(features)} zone(s)", file=sys.stderr)


def main():
    args = sys.argv[1:]
    if len(args) == 2 and args[0] == "--to-geojson":
        to_geojson(args[1])
    elif len(args) == 2 and not args[0].startswith("--"):
        to_config(args[0], args[1])
    else:
        sys.exit(
            "Usage:\n"
            "  python3 parking-geojson-to-config.py --to-geojson current.json > zones.geojson\n"
            "  python3 parking-geojson-to-config.py zones.geojson current.json > parking-config.json"
        )


if __name__ == "__main__":
    main()
