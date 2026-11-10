<?php
/**
 * Plugin settings: the repository of each plugin and theme, other
 * repositories to report to, and the GitHub token.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Class
 */
class Settings {

	const OPTION       = 'nhrrob_issue_reporter_settings';
	const TOKEN_OPTION = 'nhrrob_issue_reporter_token';

	/**
	 * All settings, sanitized, with defaults filled in.
	 *
	 * @return array
	 */
	public static function get() {
		return self::sanitize( (array) get_option( self::OPTION, [] ) );
	}

	/**
	 * Sanitize a settings array.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public static function sanitize( array $input ) {
		$repos = [];
		$extra = [];

		foreach ( isset( $input['repos'] ) && is_array( $input['repos'] ) ? $input['repos'] : [] as $key => $repo ) {
			$repo = Repos::normalize( $repo );

			// A plugin by its file, such as "akismet/akismet.php", a must-use plugin as "mu:" and its file, a theme as "theme:" and its folder.
			if ( '' !== $repo && is_string( $key ) && preg_match( '#^(?:(?:mu:)?[A-Za-z0-9._/-]+\.php|theme:[A-Za-z0-9._-]+)$#', $key ) ) {
				$repos[ $key ] = $repo;
			}
		}

		foreach ( isset( $input['extra'] ) && is_array( $input['extra'] ) ? $input['extra'] : [] as $row ) {
			$repo = Repos::normalize( is_array( $row ) && isset( $row['repo'] ) ? $row['repo'] : '' );

			if ( '' !== $repo && ! isset( $extra[ $repo ] ) ) {
				$label          = is_array( $row ) && isset( $row['label'] ) ? trim( sanitize_text_field( (string) $row['label'] ) ) : '';
				$extra[ $repo ] = [
					'label' => '' !== $label ? mb_substr( $label, 0, 100 ) : $repo,
					'repo'  => $repo,
				];
			}
		}

		return [
			'repos' => $repos,
			'extra' => array_values( $extra ),
		];
	}

	/**
	 * Save the settings.
	 *
	 * @param array $input Raw settings.
	 * @return void
	 */
	public static function update( array $input ) {
		update_option( self::OPTION, self::sanitize( $input ), false );
	}

	/**
	 * The GitHub token. The constant NHRROB_ISSUE_REPORTER_TOKEN in wp-config.php
	 * wins over the stored one, and keeps the token out of the database.
	 *
	 * @return string
	 */
	public static function token() {
		if ( defined( 'NHRROB_ISSUE_REPORTER_TOKEN' ) && is_string( NHRROB_ISSUE_REPORTER_TOKEN ) && '' !== NHRROB_ISSUE_REPORTER_TOKEN ) {
			return NHRROB_ISSUE_REPORTER_TOKEN;
		}

		return (string) get_option( self::TOKEN_OPTION, '' );
	}

	/**
	 * Whether the token comes from wp-config.php.
	 *
	 * @return bool
	 */
	public static function token_is_constant() {
		return defined( 'NHRROB_ISSUE_REPORTER_TOKEN' ) && is_string( NHRROB_ISSUE_REPORTER_TOKEN ) && '' !== NHRROB_ISSUE_REPORTER_TOKEN;
	}

	/**
	 * Store a token, or remove the stored one with an empty string.
	 *
	 * @param string $token Token.
	 * @return void
	 */
	public static function update_token( $token ) {
		// GitHub tokens are letters, digits and underscores.
		$token = (string) preg_replace( '/[^A-Za-z0-9_]/', '', (string) $token );

		if ( '' === $token ) {
			delete_option( self::TOKEN_OPTION );
			return;
		}

		update_option( self::TOKEN_OPTION, $token, false );
	}

	/**
	 * Whether the current user may report issues and change the settings.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		/**
		 * The capability needed to report an issue and to change the settings.
		 *
		 * @param string $capability Capability.
		 */
		return current_user_can( (string) apply_filters( 'nhrrob_issue_reporter_capability', 'activate_plugins' ) );
	}
}
