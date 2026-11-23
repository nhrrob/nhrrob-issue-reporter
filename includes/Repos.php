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

		/**
		 * The places a report can go. Another plugin can give a target a
		 * `route`: a short label such as "Jira · ACME". A target with a route
		 * goes through `nhrrob_issue_reporter_pre_send`, never to GitHub, and
		 * needs no repository.
		 *
		 * @param array $targets Targets by key.
		 */
		$targets = (array) apply_filters( 'nhrrob_issue_reporter_targets', $targets );

		foreach ( $targets as $key => $target ) {
			$targets[ $key ]['route'] = isset( $target['route'] ) && is_string( $target['route'] ) ? mb_substr( sanitize_text_field( $target['route'] ), 0, 80 ) : '';
		}

		return $targets;
	}

	/**
	 * The target a request names, when its repository is known.
	 *
	 * A plugin, must-use plugin or theme without one can be given a
	 * repository here (the `repo` input of the abilities): it is checked with
	 * GitHub and saved under Settings, as the Repositories tab would.
	 *
	 * @param mixed $key  Key from all(), such as a plugin file.
	 * @param mixed $repo Optional repository for a target without one.
	 * @return array|\WP_Error One target, as all() returns it.
	 */
	public static function resolve( $key, $repo = null ) {
		$targets = self::all();
		$key     = is_string( $key ) ? $key : '';

		if ( ! isset( $targets[ $key ] ) ) {
			if ( 0 === strpos( $key, 'repo:' ) ) {
				/* translators: %s: repository such as owner/name. */
				return self::refused( sprintf( __( '%s is not one of the other repositories. Add it under Tools > Report Issue > Settings > Repositories first.', 'nhrrob-issue-reporter' ), substr( $key, 5 ) ) );
			}

			return self::refused( __( 'Choose an installed plugin, must-use plugin or theme, or a saved repository. get-state lists them.', 'nhrrob-issue-reporter' ) );
		}

		$target = $targets[ $key ];

		if ( '' !== $target['repo'] || '' !== $target['route'] ) {
			return $target;
		}

		$clean = self::normalize( $repo );

		if ( '' === $clean ) {
			/* translators: %s: plugin or theme name. */
			return self::refused( sprintf( __( '%s does not name its GitHub repository. Save it under Tools > Report Issue > Settings > Repositories, or give it as `repo` (owner/name).', 'nhrrob-issue-reporter' ), $target['name'] ) );
		}

		$checked = GitHub::check( $clean );

		if ( is_wp_error( $checked ) ) {
			return self::refused( $checked->get_error_message() );
		}

		$settings                  = Settings::get();
		$settings['repos'][ $key ] = $clean;
		Settings::update( $settings );

		$target['repo'] = $clean;

		return $target;
	}

	/**
	 * A 400 answer with a message.
	 *
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	private static function refused( $message ) {
		return new \WP_Error( 'nhrrob_issue_reporter_no_repo', $message, [ 'status' => 400 ] );
	}
}
