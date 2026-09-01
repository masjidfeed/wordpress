<?php
/**
 * Minimal singular template for classic themes.
 */

if (!defined('ABSPATH')) { exit; }

the_post();

ob_start();
the_content();
$masjidfeed_content = ob_get_clean();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main id="primary" class="site-main masjidapp-content-only">
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <?php if (has_post_thumbnail()) : ?>
            <div class="post-thumbnail"><?php the_post_thumbnail(); ?></div>
        <?php endif; ?>
        <div class="entry-content">
            <?php echo $masjidfeed_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </article>
</main>
<?php wp_footer(); ?>
</body>
</html>