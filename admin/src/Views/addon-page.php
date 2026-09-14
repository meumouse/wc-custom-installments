<?php

/**
 * Shell of a screen an add-on fills.
 *
 * The host's app draws the page header and the panel, and the add-on mounts its
 * own app inside it (Admin\Menu::get_pages()). The skeleton below is what the
 * store owner looks at until the host's bootstrap request resolves.
 *
 * @since 6.0.0
 */

defined('ABSPATH') || exit; ?>

<div class="wrap wc-custom-installments-addon-page">
	<div id="wc-custom-installments-addon-page-app" class="wc-custom-installments-app">
		<div class="wcci-skeleton" style="max-width: 720px; height: 100px; margin-top: 1rem;"></div>
		<div class="wcci-skeleton" style="height: 480px; margin-top: 2.5rem;"></div>
	</div>
</div>
