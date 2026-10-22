/**
 * update-check.js - "Verificar atualizações" on this plugin's row in the plugins list.
 *
 * Asks the REST route (Rest\Update_Check) instead of following the link to the
 * admin-post handler, and answers below the row, the way core shows an update:
 * a `plugin-update-tr` row holding an inline notice. The link keeps working
 * without this script; it only reloads the page.
 *
 * When there is an update, the row carries core's "update now" link with the
 * `update-link` class and the plugin's data attributes, so core's updates.js
 * updates in place from there, as it does for any other plugin.
 *
 * The click is delegated: after an in-place update, updates.js rewrites the
 * row meta to show the new version, and the link with it.
 *
 * Every word shown comes from the server: the answer from the route, and the
 * busy and failure labels from the link's data attributes
 * (Distribution\Update_Check).
 *
 * The add-on ships its own copy of this for its own row, keyed on its own
 * attribute, so the two never pick up each other's link.
 *
 * @since 6.1.0
 */
( function () {
	const LINK_SELECTOR = '[data-wcci-update-check]';
	const RESULT_ATTRIBUTE = 'data-wcci-update-check-result';

	if ( ! window.wp || ! window.wp.apiFetch ) {
		return;
	}

	const initialLink = document.querySelector( LINK_SELECTOR );
	const initialRow = initialLink ? initialLink.closest( 'tr[data-plugin]' ) : null;

	// Whether core already marked the row as having an update, so clearing a
	// previous answer restores the row as it was.
	const hadUpdateRow = initialRow ? initialRow.classList.contains( 'update' ) : false;
	let busy = false;

	/**
	 * Announce a message to screen readers.
	 *
	 * @param {string} message - Text to announce.
	 * @return {void}
	 */
	function speak( message ) {
		if ( window.wp.a11y && typeof window.wp.a11y.speak === 'function' ) {
			window.wp.a11y.speak( message );
		}
	}

	/**
	 * Show the outcome below the plugin row.
	 *
	 * @param {HTMLTableRowElement} row - This plugin's row.
	 * @param {object} response - { result, type, message, action } from the route.
	 * @param {string} fallback - Message for an answer that brought none.
	 * @return {void}
	 */
	function render( row, response, fallback ) {
		const slug = row.getAttribute( 'data-slug' ) || '';
		const previous = row.parentNode.querySelector( '[' + RESULT_ATTRIBUTE + ']' );

		if ( previous ) {
			previous.remove();
		}

		row.classList.toggle( 'update', hadUpdateRow );

		// Core already lists the update below the row: point at its link rather
		// than repeat it.
		const coreRow = slug ? document.getElementById( slug + '-update' ) : null;

		if ( 'available' === response.result && coreRow ) {
			const coreLink = coreRow.querySelector( '.update-link' );

			speak( response.message || fallback );

			if ( coreLink ) {
				coreLink.focus();
			}

			return;
		}

		const updateRow = document.createElement( 'tr' );
		const cell = document.createElement( 'td' );
		const notice = document.createElement( 'div' );
		const paragraph = document.createElement( 'p' );

		updateRow.className = 'plugin-update-tr' + ( row.classList.contains( 'active' ) ? ' active' : '' );
		updateRow.setAttribute( RESULT_ATTRIBUTE, response.result || 'error' );
		updateRow.setAttribute( 'data-slug', slug );
		updateRow.setAttribute( 'data-plugin', row.getAttribute( 'data-plugin' ) );

		if ( slug && ! coreRow ) {
			updateRow.id = slug + '-update';
		}

		cell.colSpan = row.cells.length;
		cell.className = 'plugin-update colspanchange';
		notice.className = 'update-message notice inline notice-alt notice-' + ( response.type || 'error' );
		paragraph.textContent = response.message || fallback;

		if ( response.action && response.action.url ) {
			const action = document.createElement( 'a' );

			action.href = response.action.url;
			action.textContent = response.action.label;

			if ( response.action.update ) {
				action.className = 'update-link';
			}

			paragraph.append( ' ', action );
		}

		notice.appendChild( paragraph );
		cell.appendChild( notice );
		updateRow.appendChild( cell );
		row.classList.add( 'update' );
		row.after( updateRow );

		speak( paragraph.textContent );
	}

	document.addEventListener( 'click', function ( event ) {
		const link = event.target instanceof Element ? event.target.closest( LINK_SELECTOR ) : null;
		const row = link ? link.closest( 'tr[data-plugin]' ) : null;

		if ( ! row ) {
			return;
		}

		event.preventDefault();

		if ( busy ) {
			return;
		}

		const label = link.textContent;
		const fallback = link.getAttribute( 'data-failed' );

		busy = true;
		link.textContent = link.getAttribute( 'data-checking' );
		link.setAttribute( 'aria-disabled', 'true' );

		window.wp
			.apiFetch( {
				path: link.getAttribute( 'data-path' ),
				method: 'POST',
			} )
			.then( function ( response ) {
				render( row, response, fallback );
			} )
			.catch( function () {
				render( row, { result: 'error', type: 'error' }, fallback );
			} )
			.finally( function () {
				busy = false;
				link.textContent = label;
				link.removeAttribute( 'aria-disabled' );
			} );
	} );
} )();
