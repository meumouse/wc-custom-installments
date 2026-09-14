/**
 * font-awesome.js - Font Awesome Free, served from the plugin itself.
 *
 * The storefront prints icons as Font Awesome classes by default. 5.x loaded
 * them through a Font Awesome Kit, which fetched the stylesheet and fonts from
 * Font Awesome's CDN on every page view; a WordPress.org plugin may not reach
 * an external server that way, so the free set (solid, regular and brands,
 * with the v4 and v5 font-face aliases) is bundled and built here instead.
 *
 * This entry exists only for its stylesheet. Storefront_Assets enqueues the
 * CSS the manifest lists for it and never the script.
 *
 * Font Awesome Free 6 by Fonticons, Inc. - icons CC BY 4.0, fonts SIL OFL 1.1,
 * code MIT. The licence banner is kept in the built stylesheet.
 *
 * @since 6.0.0
 */
import '@fortawesome/fontawesome-free/css/all.css';
