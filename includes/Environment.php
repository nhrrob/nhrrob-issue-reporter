<?php
/**
 * The details about the site that go into a report.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Environment Class
 *
 * Versions and names only. Never the site's address, a path, an option or
 * anything about its users: a report can end up in a public repository.
 */
class Environment {

	/**
	 * The site's details as a Markdown list, shown in the form before sending.
	 *
	 * @param array $plugins Every plugin, as Repos::all() returns it.
	 * @return string
	 */
	public static function text( array $plugins ) {
		$theme  = wp_get_theme();
		$parent = $theme->parent();
		$active = [];

		foreach ( $plugins as $file => $plugin ) {
			if ( is_plugin_active( $file ) ) {
				$active[] = $plugin['name'] . ' ' . $plugin['version'];
			}
		}

		$lines = [
			'WordPress'      => get_bloginfo( 'version' ) . ( is_multisite() ? ' (multisite)' : '' ),
			'PHP'            => PHP_VERSION,
			'Theme'          => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ( $parent ? ' (child of ' . $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) . ')' : '' ),
			'Active plugins' => implode( ', ', $active ),
		];

		return self::format( $lines );
	}

	/**
	 * The line that names the reported plugin, added when the report is sent.
	 *
	 * @param array $plugin One plugin, as Repos::all() returns it.
	 * @return string
	 */
	public static function plugin_line( array $plugin ) {
		return self::format( [ 'Plugin' => $plugin['name'] . ' ' . $plugin['version'] ] );
	}

	/**
	 * Labels and values as a Markdown list.
	 *
	 * @param array $lines Label => value.
	 * @return string
	 */
	public static function format( array $lines ) {
		$text = [];

		foreach ( $lines as $label => $value ) {
			$text[] = '- ' . $label . ': ' . trim( (string) $value );
		}

		return implode( "\n", $text );
	}
}
