<?php
/**
 * Which GitHub repository a plugin's issues go to.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repos Class
 *
 * A repository is always the string "owner/name". It comes from the settings
 * when one is saved there for the plugin, otherwise from the plugin's own
 * header.
 */
class Repos {

	/**
	 * Read one more plugin header, the one GitHub-hosted plugins commonly carry.
	 *
	 * @param array $headers Extra headers.
	 * @return array
	 */
	public static function headers( $headers ) {
		$headers   = (array) $headers;
		$headers[] = 'GitHub Plugin URI';

		return $headers;
	}

	/**
	 * Reduce "owner/name" or a github.com address to "owner/name".
	 *
	 * @param mixed $raw Raw value.
	 * @return string Empty when it does not name a GitHub repository.
	 */
	public static function normalize( $raw ) {
		$value = trim( is_scalar( $raw ) ? (string) $raw : '' );

		if ( preg_match( '#^(?:https?://)?(?:www\.)?github\.com/(.+)$#i', $value, $match ) ) {
			$value = $match[1];
		} elseif ( false !== strpos( $value, ':' ) ) {
			// Another host, or not an address GitHub issues can be sent to.
			return '';
		}

		$parts = explode( '/', trim( (string) preg_replace( '/[?#].*$/', '', $value ), '/' ) );

		if ( count( $parts ) < 2 ) {
			return '';
		}

		$repo = $parts[0] . '/' . (string) preg_replace( '/\.git$/', '', $parts[1] );

		// A name made of dots only would walk up the API's path.
		return preg_match( '#^[A-Za-z0-9][A-Za-z0-9-]{0,38}/(?!\.+$)[A-Za-z0-9._-]{1,100}$#', $repo ) ? $repo : '';
	}

	/**
	 * The repository a plugin's own header names.
	 *
	 * @param array $data Plugin data, as get_plugins() returns it.
	 * @return string
	 */
	public static function detect( array $data ) {
		foreach ( [ 'GitHub Plugin URI', 'PluginURI', 'UpdateURI' ] as $header ) {
			$value = isset( $data[ $header ] ) ? (string) $data[ $header ] : '';

			// "owner/name" without a host is only trusted in the GitHub header.
			if ( 'GitHub Plugin URI' !== $header && false === stripos( $value, 'github.com/' ) ) {
				continue;
			}

			$repo = self::normalize( $value );

			if ( '' !== $repo ) {
				return $repo;
			}
		}

		return '';
	}

	/**
	 * Every installed plugin with its repository.
	 *
	 * @return array Keyed by plugin file: `name`, `version`, `repo`, `detected`.
	 */
	public static function all() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$settings = Settings::get();
		$plugins  = [];

		foreach ( get_plugins() as $file => $data ) {
			$detected = self::detect( $data );

			$plugins[ $file ] = [
				'name'     => (string) $data['Name'],
				'version'  => (string) $data['Version'],
				'repo'     => isset( $settings['repos'][ $file ] ) ? $settings['repos'][ $file ] : $detected,
				'detected' => $detected,
			];
		}

		return $plugins;
	}
}
