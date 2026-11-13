# WordPress.org plugin assets

The files in this folder are synced by `scripts/publish.sh` into the `assets/`
directory of the plugin's WordPress.org SVN repository
(`https://plugins.svn.wordpress.org/masjidfeed-app/assets`). WordPress.org
serves them at the top of the plugin page and in the readme's Screenshots
section. Only images belong here — this README and any other non-image files
are excluded from the SVN sync.

## Required files

| File | Size | Purpose |
|---|---|---|
| `banner-1544x500.png` | 1544x500 | Hero banner (retina) shown at the top of the plugin page |
| `banner-772x250.png` | 772x250 | Hero banner (standard resolution) |
| `icon-256x256.png` | 256x256 | Plugin icon (can also be `icon.png` or `icon.svg`) |
| `screenshot-N.png` | any | Shown for entry `N.` in the `== Screenshots ==` section of `readme.txt` |

## Placeholder images

The current images are generated placeholders from
`php scripts/generate-placeholders.php`. Replace them with real artwork before
publishing:

* **Banner / hero** — phones running MasjidFeed (left) linked to the WordPress
  "Settings → MasjidFeed App" card (right), on a light background with the
  plugin's brand colors. The screenshot descriptions that go with
  `screenshot-N.png` are listed in the `== Screenshots ==` section of
  `wp-content/plugins/masjidfeed-app/readme.txt`.

The 772x250 banner must be the same scene as the 1544x500 banner (it is the
non-retina fallback), so regenerate or crop both together.

## Screenshots

The screenshots are real captures, not artwork: take them from
**Settings → MasjidFeed App** in a configured site, plus phone captures of the
MasjidFeed app. Match each file to the numbered description in `readme.txt`.
