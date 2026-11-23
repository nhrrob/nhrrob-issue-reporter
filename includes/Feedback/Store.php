<?php
/**
 * Feedback from the widget, kept as a private post type.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter\Feedback;

use NHRRob\IssueReporter\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store Class
 *
 * One post per piece of feedback, never public and without a screen of its
 * own: the Inbox in the app shows them. No database table. The screenshot is
 * an attachment with a random file name, hidden from the media library, and
 * deleted with its feedback.
 */
class Store {

	const TYPE     = 'nhrrob_ir_feedback';
	const META     = '_nhrrob_issue_reporter';
	const SHOT     = '_nhrrob_issue_reporter_feedback';
	const KINDS    = [ 'bug', 'change', 'question' ];
	const LEVELS   = [ '', 'minor', 'major', 'critical' ];
	const STATUSES = [ 'new', 'sent', 'done' ];

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register' ] );
		add_filter( 'ajax_query_attachments_args', [ $this, 'hide_in_grid' ] );
		add_action( 'pre_get_posts', [ $this, 'hide_in_list' ] );
		add_action( 'nhrrob_issue_reporter_sent', [ $this, 'sent' ], 10, 2 );
	}

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public function register() {
		register_post_type(
			self::TYPE,
			[
				'label'           => __( 'Feedback', 'nhrrob-issue-reporter' ),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => [ 'title', 'editor', 'author' ],
				'capability_type' => 'post',
			]
		);
	}

	/**
	 * Keep feedback screenshots out of the media library's grid.
	 *
	 * @param array $query Query arguments.
	 * @return array
	 */
	public function hide_in_grid( $query ) {
		$query['meta_query']   = isset( $query['meta_query'] ) && is_array( $query['meta_query'] ) ? $query['meta_query'] : []; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the media library only.
		$query['meta_query'][] = [
			'key'     => self::SHOT,
			'compare' => 'NOT EXISTS',
		];

		return $query;
	}

	/**
	 * Keep feedback screenshots out of the media library's list.
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function hide_in_list( $query ) {
		global $pagenow;

		if ( is_admin() && $query->is_main_query() && 'upload.php' === $pagenow ) {
			$query->set( 'meta_query', $this->hide_in_grid( [ 'meta_query' => $query->get( 'meta_query' ) ] )['meta_query'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- the media library only.
		}
	}

	/**
	 * Save new feedback.
	 *
	 * @param array    $fields `text`, `kind`, `page`, `browser`, `screen`, `errors` (strings).
	 * @param int|null $shot   Attachment id of the screenshot.
	 * @return int|\WP_Error The post id.
	 */
	public static function create( array $fields, $shot ) {
		$text = Rest::text( isset( $fields['text'] ) ? $fields['text'] : '', 5000 );

		// wp_insert_post() unslashes its input and, for people without unfiltered_html,
		// runs kses on it. This is plain text, only ever shown escaped, so neither may change it.
		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );

		if ( $kses ) {
			kses_remove_filters();
		}

		$id = wp_insert_post(
			wp_slash(
				[
					'post_type'    => self::TYPE,
					'post_status'  => 'private',
					'post_title'   => Rest::line( strtok( $text, "\n" ) ),
					'post_content' => $text,
					'post_author'  => get_current_user_id(),
				]
			),
			true
		);

		if ( $kses ) {
			kses_init_filters();
		}

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta(
			$id,
			self::META,
			wp_slash(
				[
					'status'   => 'new',
					'kind'     => in_array( $fields['kind'] ?? '', self::KINDS, true ) ? $fields['kind'] : 'bug',
					'severity' => '',
					'page'     => Rest::line( $fields['page'] ?? '' ),
					'browser'  => Rest::line( $fields['browser'] ?? '' ),
					'screen'   => Rest::line( $fields['screen'] ?? '' ),
					'errors'   => array_slice( array_values( array_filter( array_map( [ self::class, 'error_line' ], (array) ( $fields['errors'] ?? [] ) ) ) ), 0, 20 ),
					'shot'     => (int) $shot,
					'issue'    => null,
				]
			)
		);

		if ( $shot ) {
			update_post_meta( (int) $shot, self::SHOT, $id );
			wp_update_post(
				[
					'ID'          => (int) $shot,
					'post_parent' => $id,
				]
			);
		}

		/**
		 * After feedback was saved from the widget.
		 *
		 * @param int $id Post id of the feedback.
		 */
		do_action( 'nhrrob_issue_reporter_feedback_created', $id );

		return $id;
	}

	/**
	 * One console error as stored: text, at most 500 characters.
	 *
	 * @param mixed $line Raw line.
	 * @return string
	 */
	public static function error_line( $line ) {
		return Rest::text( $line, 500 );
	}

	/**
	 * Every piece of feedback, newest first, as the Inbox shows it.
	 *
	 * @return array
	 */
	public static function all() {
		$posts = get_posts(
			[
				'post_type'      => self::TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- the Inbox, in the dashboard only; feedback is sent or deleted long before this.
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		return array_map( [ self::class, 'row' ], $posts );
	}

	/**
	 * How many are waiting in the Inbox.
	 *
	 * @return int
	 */
	public static function waiting() {
		$rows = wp_list_filter( self::all(), [ 'status' => 'new' ] );

		return count( $rows );
	}

	/**
	 * One piece of feedback as the Inbox shows it.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	public static function row( $post ) {
		$meta = self::meta( $post->ID );
		$user = get_userdata( (int) $post->post_author );
		$shot = $meta['shot'] ? wp_get_attachment_url( $meta['shot'] ) : false;

		$row = [
			'id'       => (int) $post->ID,
			'text'     => $post->post_content,
			'who'      => $user ? $user->display_name : '',
			'time'     => (int) get_post_time( 'U', true, $post ),
			'status'   => $meta['status'],
			'kind'     => $meta['kind'],
			'severity' => $meta['severity'],
			'page'     => $meta['page'],
			'browser'  => $meta['browser'],
			'screen'   => $meta['screen'],
			'errors'   => $meta['errors'],
			'shot'     => $shot ? [
				'id'  => $meta['shot'],
				'url' => $shot,
			] : null,
			'issue'    => $meta['issue'],
			'hub'      => (int) $meta['hub'],
		];

		/**
		 * One piece of feedback as the Inbox shows it. Another plugin can fill
		 * `who` for feedback sent without a WordPress account.
		 *
		 * @param array    $row  The row.
		 * @param \WP_Post $post The feedback post.
		 */
		return (array) apply_filters( 'nhrrob_issue_reporter_feedback_row', $row, $post );
	}

	/**
	 * The stored details, with defaults.
	 *
	 * @param int $id Post id.
	 * @return array
	 */
	public static function meta( $id ) {
		$meta = get_post_meta( $id, self::META, true );

		return wp_parse_args(
			is_array( $meta ) ? $meta : [],
			[
				'status'   => 'new',
				'kind'     => 'bug',
				'severity' => '',
				'page'     => '',
				'browser'  => '',
				'screen'   => '',
				'errors'   => [],
				'shot'     => 0,
				'issue'    => null,
				'hub'      => 0,
			]
		);
	}

	/**
	 * Change the status, kind or severity.
	 *
	 * @param int   $id     Post id.
	 * @param array $fields `status`, `kind`, `severity`; others are ignored.
	 * @return array|\WP_Error The row.
	 */
	public static function update( $id, array $fields ) {
		$post = self::find( $id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$meta    = self::meta( $post->ID );
		$allowed = [
			'status'   => self::STATUSES,
			'kind'     => self::KINDS,
			'severity' => self::LEVELS,
		];

		foreach ( $allowed as $key => $values ) {
			if ( isset( $fields[ $key ] ) && in_array( $fields[ $key ], $values, true ) ) {
				$meta[ $key ] = $fields[ $key ];
			}
		}

		update_post_meta( $post->ID, self::META, wp_slash( $meta ) );

		return self::row( get_post( $post->ID ) );
	}

	/**
	 * Delete feedback and its screenshot.
	 *
	 * @param int $id Post id.
	 * @return true|\WP_Error
	 */
	public static function delete( $id ) {
		$post = self::find( $id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$meta = self::meta( $post->ID );

		if ( $meta['shot'] && (int) get_post_meta( $meta['shot'], self::SHOT, true ) === $post->ID ) {
			wp_delete_attachment( $meta['shot'], true );
		}

		wp_delete_post( $post->ID, true );

		return true;
	}

	/**
	 * Mark feedback as sent once its report was opened on GitHub.
	 *
	 * @param array            $report  The new row of the Sent list.
	 * @param \WP_REST_Request $request The request that sent it, with `feedback`.
	 * @return void
	 */
	public function sent( $report, $request ) {
		$post = self::find( $request->get_param( 'feedback' ) );

		if ( is_wp_error( $post ) ) {
			return;
		}

		$meta           = self::meta( $post->ID );
		$meta['status'] = 'sent';
		$meta['issue']  = [
			'repo'   => (string) $report['repo'],
			'number' => (int) $report['number'],
			'url'    => (string) $report['url'],
		];
		update_post_meta( $post->ID, self::META, wp_slash( $meta ) );
	}

	/**
	 * A piece of feedback by id.
	 *
	 * @param mixed $id Post id.
	 * @return \WP_Post|\WP_Error
	 */
	public static function find( $id ) {
		$post = is_numeric( $id ) ? get_post( (int) $id ) : null;

		if ( ! $post || self::TYPE !== $post->post_type ) {
			return new \WP_Error( 'nhrrob_issue_reporter_no_feedback', __( 'That feedback was not found. It may have been deleted.', 'nhrrob-issue-reporter' ), [ 'status' => 404 ] );
		}

		return $post;
	}
}
