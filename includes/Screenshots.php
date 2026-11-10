<?php
/**
 * Screenshots in a report, hosted in the site's media library.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Screenshots Class
 *
 * GitHub's API cannot attach an image to an issue, so an image is uploaded to
 * the media library and the issue links to it. That link shows the site's
 * address, which a report otherwise never contains, so a report with
 * screenshots is only sent when the person has agreed to it.
 */
class Screenshots {

	const MAX_FILES = 5;
	const MAX_BYTES = 5242880;
	const TYPES     = [ 'image/png', 'image/jpeg', 'image/webp', 'image/gif' ];

	/**
	 * Add an uploaded image to the media library.
	 *
	 * @param mixed $file One entry of the request's files: `name`, `tmp_name`, `size`, `error`.
	 * @return array|\WP_Error `id` and `url`.
	 */
	public static function upload( $file ) {
		if ( ! is_array( $file ) || ! isset( $file['name'], $file['tmp_name'], $file['size'] ) || ! empty( $file['error'] ) ) {
			return self::error( __( 'The image did not arrive. Please try again.', 'nhrrob-issue-reporter' ) );
		}

		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return self::error( __( 'A screenshot can be at most 5 MB.', 'nhrrob-issue-reporter' ) );
		}

		// The type is read from the file itself, not from its name.
		$type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );

		if ( empty( $type['type'] ) || ! in_array( $type['type'], self::TYPES, true ) ) {
			return self::error( __( 'A screenshot has to be a PNG, JPEG, WebP or GIF image.', 'nhrrob-issue-reporter' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$id = media_handle_sideload(
			[
				'name'     => sanitize_file_name( $file['name'] ),
				'tmp_name' => $file['tmp_name'],
			],
			0
		);

		if ( is_wp_error( $id ) ) {
			return self::error( $id->get_error_message() );
		}

		return [
			'id'  => (int) $id,
			'url' => (string) wp_get_attachment_url( $id ),
		];
	}

	/**
	 * The images as Markdown for the body of an issue.
	 *
	 * @param mixed $ids Attachment ids.
	 * @return string|\WP_Error Empty when there are none.
	 */
	public static function markdown( $ids ) {
		$ids = array_values( array_unique( array_map( 'intval', is_array( $ids ) ? $ids : [] ) ) );

		if ( count( $ids ) > self::MAX_FILES ) {
			/* translators: %d: most screenshots in one report. */
			return self::error( sprintf( __( 'A report can have at most %d screenshots.', 'nhrrob-issue-reporter' ), self::MAX_FILES ) );
		}

		$lines = [];

		foreach ( $ids as $index => $id ) {
			$url = wp_attachment_is_image( $id ) ? wp_get_attachment_url( $id ) : false;

			if ( ! $url || ! in_array( get_post_mime_type( $id ), self::TYPES, true ) ) {
				/* translators: %d: attachment id. */
				return self::error( sprintf( __( 'Attachment %d is not an image in the media library.', 'nhrrob-issue-reporter' ), $id ) );
			}

			$lines[] = '![Screenshot ' . ( $index + 1 ) . '](' . esc_url_raw( $url ) . ')';
		}

		return implode( "\n\n", $lines );
	}

	/**
	 * Whether GitHub can load an image from this site. A local address or a
	 * local environment cannot be reached from GitHub.
	 *
	 * @return bool
	 */
	public static function site_is_public() {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		if ( 'local' === wp_get_environment_type() || '' === $host || 'localhost' === $host || preg_match( '/\.(?:test|local|localhost|invalid|example|internal|lan|home)$/', $host ) ) {
			return false;
		}

		// An address given as an IP counts only when it is public.
		return ! filter_var( $host, FILTER_VALIDATE_IP ) || (bool) filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * An answer the app shows in a toast.
	 *
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	private static function error( $message ) {
		return new \WP_Error( 'nhrrob_issue_reporter_screenshot', $message, [ 'status' => 400 ] );
	}
}
