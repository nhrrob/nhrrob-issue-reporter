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
 * A report goes to a target: a plugin (keyed by its file), a must-use plugin
 * ("mu:" and its file), a theme ("theme:" and its folder) or another
 * repository saved in the settings ("repo:" and the repository). A repository
 * is always the string "owner/name". It comes from the settings when one is
 * saved there, otherwise from the target's own header.
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
	 * Read the same header in a theme's style.css.
	 *
	 * @param array $headers Extra headers.
	 * @return array
	 */
	public static function theme_headers( $headers ) {
		$headers   = (array) $headers;
		$headers[] = 'GitHub Theme URI';

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
	 * Everything a report can go to, with its repository.
	 *
	 * @return array Keyed as described above: `type` (plugin, mu-plugin, theme
	 *               or repo), `name`, `version`, `repo`, `detected`.
	 */
	public static function all() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$settings = Settings::get();
		$found    = [];

		foreach ( get_plugins() as $file => $data ) {
			$found[ $file ] = [ 'plugin', $data['Name'], $data['Version'], self::detect( $data ) ];
		}

		foreach ( get_mu_plugins() as $file => $data ) {
			$found[ 'mu:' . $file ] = [ 'mu-plugin', $data['Name'], $data['Version'], self::detect( $data ) ];
		}

		foreach ( wp_get_themes() as $folder => $theme ) {
			$found[ 'theme:' . $folder ] = [
				'theme',
				$theme->get( 'Name' ),
				$theme->get( 'Version' ),
				self::detect(
					[
						'GitHub Plugin URI' => $theme->get( 'GitHub Theme URI' ),
						'PluginURI'         => $theme->get( 'ThemeURI' ),
						'UpdateURI'         => $theme->get( 'UpdateURI' ),
					]
				),
			];
		}

		$targets = [];

		foreach ( $found as $key => list( $type, $name, $version, $detected ) ) {
			$targets[ $key ] = [
				'type'     => $type,
				'name'     => (string) $name,
				'version'  => (string) $version,
				'repo'     => isset( $settings['repos'][ $key ] ) ? $settings['repos'][ $key ] : $detected,
				'detected' => $detected,
			];
		}

		foreach ( $settings['extra'] as $extra ) {
			$targets[ 'repo:' . $extra['repo'] ] = [
				'type'     => 'repo',
				'name'     => $extra['label'],
				'version'  => '',
				'repo'     => $extra['repo'],
				'detected' => $extra['repo'],
			];
		}

		return $targets;
	}
}
