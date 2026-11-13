<?php

declare(strict_types=1);

// Generates placeholder images for the WordPress.org assets directory.
//
// WordPress.org serves banner-*, icon-* and screenshot-* files from the
// plugin's SVN "assets" directory; scripts/publish.sh syncs the repository's
// assets/ folder into it. Each placeholder image carries a caption describing
// what the real artwork should show (also documented in assets/README.md and
// the readme.txt placeholders). Replace the placeholders before publishing.
//
// Usage: php scripts/generate-placeholders.php

$repo_root = dirname(__DIR__);
$assets_dir = $repo_root . '/assets';
$plugin_icon = $repo_root . '/wp-content/plugins/masjidfeed-app/assets/masjidfeed-app-icon.png';

if (!is_dir($assets_dir) && !mkdir($assets_dir, 0775, true)) {
    fwrite(STDERR, "Unable to create directory: $assets_dir\n");
    exit(1);
}

$palette = [
    'bg' => [242, 250, 247],
    'bg_alt' => [222, 238, 232],
    'ink' => [19, 78, 74],
    'accent' => [15, 118, 110],
    'accent_hi' => [45, 212, 191],
    'white' => [255, 255, 255],
];

function allocate(GdImage $image, array $rgb): int
{
    return imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
}

function wrap_text(string $text, int $max_chars): array
{
    $lines = [];
    $line = '';
    foreach (preg_split('/\s+/', trim($text)) as $word) {
        if ('' !== $line && strlen($line . ' ' . $word) > $max_chars) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = '' === $line ? $word : $line . ' ' . $word;
        }
    }
    if ('' !== $line) {
        $lines[] = $line;
    }
    return $lines;
}

function draw_lines(GdImage $image, array $lines, int $font, int $x_center, int $y_start, int $color): void
{
    $step = imagefontheight($font) + 6;
    foreach ($lines as $index => $line) {
        $x = (int) ($x_center - imagefontwidth($font) * strlen($line) / 2);
        imagestring($image, $font, $x, $y_start + $index * $step, $line, $color);
    }
}

function draw_phone(GdImage $image, int $x, int $y, int $w, int $h, array $palette): void
{
    $ink = allocate($image, $palette['ink']);
    $accent = allocate($image, $palette['accent']);
    $white = allocate($image, $palette['white']);

    imagefilledrectangle($image, $x, $y, $x + $w, $y + $h, $ink);
    $screen_inset = (int) max(3, $w * 0.06);
    $screen = [
        'x' => $x + $screen_inset,
        'y' => $y + $screen_inset,
        'w' => $w - 2 * $screen_inset,
        'h' => $h - 2 * $screen_inset,
    ];
    imagefilledrectangle($image, $screen['x'], $screen['y'], $screen['x'] + $screen['w'], $screen['y'] + $screen['h'], $accent);

    $rows = 4;
    $row_h = (int) ($screen['h'] * 0.09);
    $row_gap = (int) ($screen['h'] * 0.05);
    $row_y = $screen['y'] + (int) ($screen['h'] * 0.22);
    for ($i = 0; $i < $rows; $i++) {
        $bar_w = (int) ($screen['w'] * (0.85 - 0.08 * ($i % 2)));
        imagefilledrectangle(
            $image,
            $screen['x'] + (int) ($screen['w'] * 0.08),
            $row_y + $i * ($row_h + $row_gap),
            $screen['x'] + (int) ($screen['w'] * 0.08) + $bar_w,
            $row_y + $i * ($row_h + $row_gap) + $row_h,
            $white
        );
    }
}

function draw_wp_card(GdImage $image, int $x, int $y, int $w, int $h, array $palette): void
{
    $ink = allocate($image, $palette['ink']);
    $accent = allocate($image, $palette['accent']);
    $white = allocate($image, $palette['white']);
    $bg_alt = allocate($image, $palette['bg_alt']);

    imagefilledrectangle($image, $x, $y, $x + $w, $y + $h, $white);
    imagerectangle($image, $x, $y, $x + $w, $y + $h, $accent);

    $dot = (int) (min($w, $h) * 0.22);
    $dot_x = $x + (int) ($w * 0.1);
    $dot_y = $y + (int) ($h * 0.12);
    imagefilledellipse($image, $dot_x + (int) ($dot / 2), $dot_y + (int) ($dot / 2), $dot, $dot, $accent);
    imagestring($image, 5, $dot_x + (int) ($dot * 0.24), $dot_y + (int) ($dot * 0.28), 'W', $white);

    $bar_x = $x + (int) ($w * 0.1);
    $bar_w = (int) ($w * 0.8);
    $bar_h = (int) ($h * 0.07);
    $bar_y = $dot_y + $dot + (int) ($h * 0.08);
    for ($i = 0; $i < 4; $i++) {
        imagefilledrectangle(
            $image,
            $bar_x,
            $bar_y + $i * ($bar_h + (int) ($h * 0.04)),
            $bar_x + $bar_w - $i * (int) ($w * 0.12),
            $bar_y + $i * ($bar_h + (int) ($h * 0.04)) + $bar_h,
            $bg_alt
        );
    }
    imagerectangle($image, $x, $y, $x + $w, $y + $h, $ink);
}

function draw_arrow(GdImage $image, int $x1, int $x2, int $y, int $head, array $palette): void
{
    $accent_hi = allocate($image, $palette['accent_hi']);
    $ink = allocate($image, $palette['ink']);
    imageline($image, $x1, $y, $x2 - $head, $y, $accent_hi);
    imageline($image, $x1, $y - 1, $x2 - $head, $y - 1, $accent_hi);
    imageline($image, $x1, $y + 1, $x2 - $head, $y + 1, $accent_hi);
    imagefilledpolygon($image, [$x2, $y, $x2 - $head, $y - (int) ($head * 0.6), $x2 - $head, $y + (int) ($head * 0.6)], $ink);
}

function make_banner(int $width, int $height, array $palette, string $out_path): void
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, allocate($image, $palette['bg']));
    imagefilledrectangle($image, 0, (int) ($height * 0.8), $width, $height, allocate($image, $palette['bg_alt']));

    $ink = allocate($image, $palette['ink']);
    $scale = $height / 500;

    $phone_h = (int) ($height * 0.58);
    $phone_w = (int) ($phone_h * 0.52);
    $phone_x = (int) ($width * 0.14);
    $phone_y = (int) ($height * 0.42) - (int) ($phone_h / 2);
    draw_phone($image, $phone_x, $phone_y, $phone_w, $phone_h, $palette);

    $card_h = (int) ($height * 0.42);
    $card_w = (int) ($card_h * 1.9);
    $card_x = (int) ($width * 0.68);
    $card_y = (int) ($height * 0.42) - (int) ($card_h / 2);
    draw_wp_card($image, $card_x, $card_y, $card_w, $card_h, $palette);

    draw_arrow($image, $phone_x + $phone_w + (int) ($width * 0.02), $card_x - (int) ($width * 0.01), (int) ($height * 0.42), (int) (18 * $scale), $palette);

    $title = 'MasjidFeed App - PLACEHOLDER banner ' . $width . 'x' . $height;
    $title_font = $width > 1000 ? 5 : 4;
    $title_y = (int) ($height * 0.06);
    draw_lines($image, [$title], $title_font, (int) ($width / 2), $title_y, $ink);

    $caption_lines = wrap_text(
        'Hero placeholder: phones running MasjidFeed (left) linked to the WordPress '
        . 'Settings -> MasjidFeed App card (right). Replace with real artwork - see assets/README.md.',
        (int) ($width / (imagefontwidth(3) * 1.05))
    );
    draw_lines($image, $caption_lines, 3, (int) ($width / 2), $height - (int) ($height * 0.16), $ink);

    imagepng($image, $out_path);
    imagedestroy($image);
}

$screenshots = [
    1 => ['size' => [1200, 750], 'text' => 'Settings - MasjidFeed App, General tab: masjid name, logo and splash logo, primary colors (light and dark mode), contact and social links.'],
    2 => ['size' => [1200, 750], 'text' => 'General tab continued: donation and Ramadan page URLs, feature flags, and the optional Friday announcement category.'],
    3 => ['size' => [1200, 750], 'text' => 'Events and Announcements filters: choosing the events plugin and the categories and tags that feed the app.'],
    4 => ['size' => [1200, 750], 'text' => 'Push Notifications tab: delivery channels, Firebase credentials, and the hosted push service registration.'],
    5 => ['size' => [1200, 750], 'text' => 'Debug tools: Validate connection, a test notification to a real device, and API and push traces with redacted payloads.'],
    6 => ['size' => [1200, 750], 'text' => 'Preview tab: the latest announcements exactly as the mobile app will list them.'],
    7 => ['size' => [1200, 750], 'text' => 'Parking Map tab: zones and restriction schedules before saving.'],
    8 => ['size' => [600, 1200], 'text' => 'Mobile app: home screen with prayer times and the home-screen widget.'],
    9 => ['size' => [600, 1200], 'text' => 'Mobile app: announcements and events screens.'],
];

foreach ($screenshots as $number => $spec) {
    [$width, $height] = $spec['size'];
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, allocate($image, $palette['bg']));
    imagefilledrectangle($image, 0, 0, $width, (int) ($height * 0.1), allocate($image, $palette['bg_alt']));

    $ink = allocate($image, $palette['ink']);
    $accent = allocate($image, $palette['accent']);

    $title = 'PLACEHOLDER screenshot-' . $number . ' (' . $width . 'x' . $height . ')';
    $body_lines = wrap_text($spec['text'], (int) ($width / (imagefontwidth(3) * 1.05)));

    $block_height = (count($body_lines) + 3) * (imagefontheight(3) + 6) + imagefontheight(5);
    $y = (int) (($height - $block_height) / 2);
    draw_lines($image, [$title], 5, (int) ($width / 2), $y, $accent);
    draw_lines($image, $body_lines, 3, (int) ($width / 2), $y + imagefontheight(5) + 18, $ink);

    imagepng($image, sprintf('%s/screenshot-%d.png', $assets_dir, $number));
    imagedestroy($image);
}

make_banner(1544, 500, $palette, $assets_dir . '/banner-1544x500.png');
make_banner(772, 250, $palette, $assets_dir . '/banner-772x250.png');

if (is_file($plugin_icon)) {
    copy($plugin_icon, $assets_dir . '/icon-256x256.png');
} else {
    $image = imagecreatetruecolor(256, 256);
    imagefill($image, 0, 0, allocate($image, $palette['accent']));
    imagestring($image, 5, 74, 120, 'PLACEHOLDER', allocate($image, $palette['white']));
    imagepng($image, $assets_dir . '/icon-256x256.png');
    imagedestroy($image);
}

echo "Placeholder images written to $assets_dir\n";
