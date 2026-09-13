<?php

namespace MeuMouse\WC_Custom_Installments\Core;

defined('ABSPATH') || exit;

/**
 * Resolve built frontend assets from the Vite manifest.
 *
 * Vite emits fixed file names for the entry script and its stylesheet, so the
 * URLs alone would never change after a rebuild and browsers would serve a
 * stale bundle. Every asset is therefore versioned by its own modification
 * time.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Core
 * @author MeuMouse.com
 */
class Scripts {

	/**
	 * Path of the Vite manifest, relative to the plugin root.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const MANIFEST_PATH = 'app/dist/.vite/manifest.json';

	/**
	 * Path of the Vite output directory, relative to the plugin root.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const DIST_PATH = 'app/dist/';


	/**
	 * Get the script URL, stylesheets and cache-busting version of an entry.
	 *
	 * @since 6.0.0
	 * @param string $entry_source | Entry source path, e.g. src/entries/settings.js
	 * @return array<string,mixed> Empty when the entry is not in the manifest.
	 */
	public static function get_entry_assets( $entry_source ) {
		$manifest = self::get_manifest();
		$entry = self::get_manifest_entry( $entry_source, $manifest );

		if ( empty( $entry ) ) {
			return array();
		}

		return array(
			'script'  => ! empty( $entry['file'] ) ? self::get_asset_url( $entry['file'] ) : '',
			'styles'  => self::collect_css_assets( $entry, $manifest ),
			'version' => ! empty( $entry['file'] ) ? self::get_asset_version( $entry['file'] ) : null,
		);
	}


	/**
	 * Whether the frontend has been built.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_built() {
		return array() !== self::get_manifest();
	}


	/**
	 * Resolve a cache-busting version from a built file's modification time.
	 *
	 * @since 6.0.0
	 * @param string $relative_path | Asset path relative to the dist root
	 * @return string|null
	 */
	public static function get_asset_version( $relative_path ) {
		if ( empty( $relative_path ) || ! is_string( $relative_path ) ) {
			return null;
		}

		$absolute_path = trailingslashit( WC_CUSTOM_INSTALLMENTS_DIR ) . self::DIST_PATH . ltrim( $relative_path, '/' );

		if ( ! file_exists( $absolute_path ) ) {
			return null;
		}

		$modified_time = filemtime( $absolute_path );

		return $modified_time ? (string) $modified_time : null;
	}


	/**
	 * Find a manifest entry by its source path.
	 *
	 * @since 6.0.0
	 * @param string $entry_source | Entry source path
	 * @param array<string,mixed>|null $manifest | Decoded manifest
	 * @return array<string,mixed>
	 */
	private static function get_manifest_entry( $entry_source, $manifest = null ) {
		if ( empty( $entry_source ) || ! is_string( $entry_source ) ) {
			return array();
		}

		if ( null === $manifest ) {
			$manifest = self::get_manifest();
		}

		if ( empty( $manifest ) ) {
			return array();
		}

		$normalized = ltrim( str_replace( '\\', '/', $entry_source ), '/' );

		if ( isset( $manifest[ $normalized ] ) && is_array( $manifest[ $normalized ] ) ) {
			return $manifest[ $normalized ];
		}

		foreach ( $manifest as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['src'] ) ) {
				continue;
			}

			if ( $normalized === ltrim( str_replace( '\\', '/', $entry['src'] ), '/' ) ) {
				return $entry;
			}
		}

		return array();
	}


	/**
	 * Collect the stylesheets of an entry and of every chunk it imports.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $entry | Manifest entry
	 * @param array<string,mixed> $manifest | Decoded manifest
	 * @param array<string,bool> $visited | Recursion guard
	 * @return array<int,string>
	 */
	private static function collect_css_assets( $entry, $manifest, &$visited = array() ) {
		$styles = array();

		if ( empty( $entry ) || ! is_array( $entry ) ) {
			return $styles;
		}

		if ( ! empty( $entry['file'] ) ) {
			if ( isset( $visited[ $entry['file'] ] ) ) {
				return $styles;
			}

			$visited[ $entry['file'] ] = true;
		}

		if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
			foreach ( $entry['css'] as $css_file ) {
				$styles[] = self::get_asset_url( $css_file );
			}
		}

		if ( empty( $entry['imports'] ) || ! is_array( $entry['imports'] ) ) {
			return array_values( array_unique( $styles ) );
		}

		foreach ( $entry['imports'] as $import_key ) {
			if ( empty( $manifest[ $import_key ] ) || ! is_array( $manifest[ $import_key ] ) ) {
				continue;
			}

			$styles = array_merge( $styles, self::collect_css_assets( $manifest[ $import_key ], $manifest, $visited ) );
		}

		return array_values( array_unique( $styles ) );
	}


	/**
	 * Decode the manifest once per request.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function get_manifest() {
		static $manifest = null;

		if ( null !== $manifest ) {
			return $manifest;
		}

		$manifest = array();
		$path = trailingslashit( WC_CUSTOM_INSTALLMENTS_DIR ) . self::MANIFEST_PATH;

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return $manifest;
		}

		$contents = file_get_contents( $path );

		if ( false === $contents || '' === trim( $contents ) ) {
			return $manifest;
		}

		$decoded = json_decode( $contents, true );

		if ( is_array( $decoded ) ) {
			$manifest = $decoded;
		}

		return $manifest;
	}


	/**
	 * Build the public URL of a built asset.
	 *
	 * @since 6.0.0
	 * @param string $relative_path | Asset path relative to the dist root
	 * @return string
	 */
	private static function get_asset_url( $relative_path ) {
		return trailingslashit( WC_CUSTOM_INSTALLMENTS_URL ) . self::DIST_PATH . ltrim( $relative_path, '/' );
	}
}
