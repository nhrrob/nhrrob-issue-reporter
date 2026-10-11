<?php
/**
 * The dashboard screen: Tools → Report Issue.
 *
 * @package NHRRob\IssueReporter
 */

namespace NHRRob\IssueReporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Class
 *
 * One screen with three tabs: the report form, the reports already sent and
 * the settings. Both forms post back to the screen itself and are handled in
 * handle() before anything is printed.
 */
class Admin {

	const PAGE  = 'nhrrob-issue-reporter';
	const NONCE = 'nhrrob_issue_reporter';

	/**
	 * What the report form shows: the values typed so far.
	 *
	 * @var array
	 */
	private $form = [];

	/**
	 * Open issues that resemble the report being written.
	 *
	 * @var array
	 */
	private $similar = [];

	/**
	 * Why the last submission did not go through.
	 *
	 * @var string
	 */
	private $error = '';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_filter( 'plugin_action_links', [ $this, 'action_link' ], 10, 3 );
	}

	/**
	 * Add the screen under Tools.
	 *
	 * @return void
	 */
	public function menu() {
		/** This filter is documented in includes/Settings.php */
		$capability = (string) apply_filters( 'nhrrob_issue_reporter_capability', 'activate_plugins' );
		$hook       = add_management_page( __( 'Report Issue', 'nhrrob-issue-reporter' ), __( 'Report Issue', 'nhrrob-issue-reporter' ), $capability, self::PAGE, [ $this, 'render' ] );

		if ( $hook ) {
			add_action( 'load-' . $hook, [ $this, 'handle' ] );
		}
	}

	/**
	 * The address of one of the screen's tabs.
	 *
	 * @param string $tab  Tab: `report`, `sent` or `settings`.
	 * @param array  $args More query arguments.
	 * @return string
	 */
	public static function url( $tab = 'report', array $args = [] ) {
		return add_query_arg( array_merge( [ 'page' => self::PAGE ], 'report' === $tab ? [] : [ 'tab' => $tab ], $args ), admin_url( 'tools.php' ) );
	}

	/**
	 * Add "Report issue" under a plugin in the Plugins list, when its repository is known.
	 *
	 * @param array  $actions Links under the plugin.
	 * @param string $file    Plugin file.
	 * @param array  $data    Plugin data.
	 * @return array
	 */
	public function action_link( $actions, $file, $data ) {
		if ( ! Settings::can_manage() ) {
			return $actions;
		}

		$settings = Settings::get();
		$repo     = isset( $settings['repos'][ $file ] ) ? $settings['repos'][ $file ] : Repos::detect( (array) $data );

		if ( '' !== $repo ) {
			$actions['nhrrob_issue_reporter'] = sprintf( '<a href="%s">%s</a>', esc_url( self::url( 'report', [ 'plugin' => rawurlencode( $file ) ] ) ), esc_html__( 'Report issue', 'nhrrob-issue-reporter' ) );
		}

		return $actions;
	}

	/**
	 * Handle a submitted form. Runs before the screen prints anything.
	 *
	 * @return void
	 */
	public function handle() {
		if ( ! isset( $_POST['nhrrob_issue_reporter_action'] ) ) {
			return;
		}

		if ( ! Settings::can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'nhrrob-issue-reporter' ), 403 );
		}

		check_admin_referer( self::NONCE );

		if ( 'settings' === $_POST['nhrrob_issue_reporter_action'] ) {
			$repos = isset( $_POST['repos'] ) && is_array( $_POST['repos'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['repos'] ) ) : [];
			$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

			$this->save_settings( $repos, $token, ! empty( $_POST['remove_token'] ) );
			return;
		}

		$this->form = [
			'plugin'      => isset( $_POST['plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin'] ) ) : '',
			'title'       => isset( $_POST['title'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['title'] ) ) ) : '',
			'what'        => isset( $_POST['what'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['what'] ) ) ) : '',
			'steps'       => isset( $_POST['steps'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['steps'] ) ) ) : '',
			'environment' => isset( $_POST['environment'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['environment'] ) ) ) : '',
		];

		$this->send_report( ! empty( $_POST['confirmed'] ) );
	}

	/**
	 * Save the settings form and go back to it.
	 *
	 * @param array  $repos        Plugin file => what was typed for its repository.
	 * @param string $token        A new token, or empty to keep the stored one.
	 * @param bool   $remove_token Whether to forget the stored token.
	 * @return void
	 */
	private function save_settings( array $repos, $token, $remove_token ) {
		$plugins = Repos::all();
		$keep    = [];

		foreach ( $repos as $file => $repo ) {
			// Saved only when it differs from what the plugin's own header says.
			if ( isset( $plugins[ $file ] ) && '' !== Repos::normalize( $repo ) && Repos::normalize( $repo ) !== $plugins[ $file ]['detected'] ) {
				$keep[ $file ] = $repo;
			}
		}

		Settings::update( [ 'repos' => $keep ] );

		if ( $remove_token ) {
			Settings::update_token( '' );
		} elseif ( '' !== $token ) {
			Settings::update_token( $token );
		}

		wp_safe_redirect( self::url( 'settings', [ 'saved' => 1 ] ) );
		exit;
	}

	/**
	 * Send the report in $this->form, unless something about it has to be shown first.
	 *
	 * @param bool $confirmed Whether the similar issues were already shown.
	 * @return void
	 */
	private function send_report( $confirmed ) {
		$plugins = Repos::all();
		$form    = $this->form;
		$plugin  = isset( $plugins[ $form['plugin'] ] ) ? $plugins[ $form['plugin'] ] : null;

		if ( ! $plugin || '' === $plugin['repo'] ) {
			$this->error = __( 'Choose a plugin that has a repository.', 'nhrrob-issue-reporter' );
			return;
		}

		if ( '' === $form['title'] || '' === $form['what'] ) {
			$this->error = __( 'A title and a description of what happened are needed.', 'nhrrob-issue-reporter' );
			return;
		}

		if ( ! $confirmed ) {
			$similar = GitHub::similar( $plugin['repo'], $form['title'] );

			if ( is_wp_error( $similar ) ) {
				$this->error = $similar->get_error_message();
				return;
			}

			if ( $similar ) {
				$this->similar = $similar;
				return;
			}
		}

		$environment = '' !== $form['environment'] ? Environment::plugin_line( $plugin ) . "\n" . $form['environment'] : '';
		$issue       = GitHub::create( $plugin['repo'], mb_substr( $form['title'], 0, 200 ), GitHub::body( $form['what'], $form['steps'], $environment ) );

		if ( is_wp_error( $issue ) ) {
			$this->error = $issue->get_error_message();
			return;
		}

		Reports::add( $plugin['name'], $plugin['repo'], $issue, $form['title'] );

		wp_safe_redirect( self::url( 'sent', [ 'created' => 1 ] ) );
		exit;
	}

	/**
	 * Print the screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Settings::can_manage() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chooses which tab to show; changes nothing.
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'report';
		$tabs = [
			'report'   => __( 'Report', 'nhrrob-issue-reporter' ),
			'sent'     => __( 'Sent', 'nhrrob-issue-reporter' ),
			'settings' => __( 'Settings', 'nhrrob-issue-reporter' ),
		];
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'report';

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Report Issue', 'nhrrob-issue-reporter' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper">';

		foreach ( $tabs as $slug => $label ) {
			printf( '<a class="nav-tab%s" href="%s">%s</a>', $slug === $tab ? ' nav-tab-active' : '', esc_url( self::url( $slug ) ), esc_html( $label ) );
		}

		echo '</nav>';

		if ( 'sent' === $tab ) {
			$this->render_sent();
		} elseif ( 'settings' === $tab ) {
			$this->render_settings();
		} else {
			$this->render_report();
		}

		echo '</div>';
	}

	/**
	 * Print a notice inside the screen.
	 *
	 * @param string $type    `error`, `warning`, `success` or `info`.
	 * @param string $message Message; may contain links.
	 * @return void
	 */
	private function notice( $type, $message ) {
		printf( '<div class="notice notice-%s inline"><p>%s</p></div>', esc_attr( $type ), wp_kses( $message, [ 'a' => [ 'href' => [] ] ] ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- one short pair.
	}

	/**
	 * The report form.
	 *
	 * @return void
	 */
	private function render_report() {
		$plugins = Repos::all();
		$form    = $this->form;

		if ( ! $form ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preselects a plugin in the form; changes nothing.
			$chosen = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : '';
			$form   = [
				'plugin'      => isset( $plugins[ $chosen ] ) ? $chosen : '',
				'title'       => '',
				'what'        => '',
				'steps'       => '',
				'environment' => Environment::text( $plugins ),
			];
		}

		$has_token = '' !== Settings::token();
		$has_repo  = (bool) array_filter( wp_list_pluck( $plugins, 'repo' ) );

		if ( ! $has_token ) {
			/* translators: %s: address of the Settings tab. */
			$this->notice( 'warning', sprintf( __( 'A GitHub token is needed before a report can be sent. <a href="%s">Add one in Settings</a>.', 'nhrrob-issue-reporter' ), esc_url( self::url( 'settings' ) ) ) );
		}

		if ( ! $has_repo ) {
			/* translators: %s: address of the Settings tab. */
			$this->notice( 'warning', sprintf( __( 'No plugin has a repository yet. <a href="%s">Set one in Settings</a>.', 'nhrrob-issue-reporter' ), esc_url( self::url( 'settings' ) ) ) );
		}

		if ( '' !== $this->error ) {
			$this->notice( 'error', esc_html( $this->error ) );
		}

		if ( $this->similar ) {
			echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'These open issues look similar. Nothing was sent yet.', 'nhrrob-issue-reporter' ) . '</strong></p><ul class="ul-disc">';

			foreach ( $this->similar as $issue ) {
				printf( '<li><a href="%s" target="_blank" rel="noopener noreferrer">#%d %s</a></li>', esc_url( $issue['url'] ), (int) $issue['number'], esc_html( $issue['title'] ) );
			}

			echo '</ul><p>' . esc_html__( 'If one of them is the same problem, add a comment there. If not, send your report with the button below.', 'nhrrob-issue-reporter' ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( self::url() ) . '">';
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="nhrrob_issue_reporter_action" value="report">';

		if ( $this->similar ) {
			echo '<input type="hidden" name="confirmed" value="1">';
		}

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="nhrrob-issue-reporter-plugin">' . esc_html__( 'Plugin', 'nhrrob-issue-reporter' ) . '</label></th><td>';
		echo '<select id="nhrrob-issue-reporter-plugin" name="plugin" required>';
		echo '<option value="">' . esc_html__( 'Choose a plugin', 'nhrrob-issue-reporter' ) . '</option>';

		foreach ( $plugins as $file => $plugin ) {
			printf(
				'<option value="%1$s"%2$s%3$s>%4$s</option>',
				esc_attr( $file ),
				selected( $form['plugin'], $file, false ),
				disabled( '', $plugin['repo'], false ),
				/* translators: 1: plugin name, 2: repository such as owner/name. */
				esc_html( '' !== $plugin['repo'] ? sprintf( __( '%1$s (%2$s)', 'nhrrob-issue-reporter' ), $plugin['name'], $plugin['repo'] ) : sprintf( __( '%s (no repository set)', 'nhrrob-issue-reporter' ), $plugin['name'] ) ) // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment -- %s: plugin name.
			);
		}

		echo '</select><p class="description">' . esc_html__( 'The issue is opened in the repository shown next to the plugin. In a public repository anyone can read it.', 'nhrrob-issue-reporter' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="nhrrob-issue-reporter-title">' . esc_html__( 'Title', 'nhrrob-issue-reporter' ) . '</label></th><td>';
		echo '<input type="text" id="nhrrob-issue-reporter-title" name="title" class="large-text" maxlength="200" required value="' . esc_attr( $form['title'] ) . '"></td></tr>';

		echo '<tr><th scope="row"><label for="nhrrob-issue-reporter-what">' . esc_html__( 'What happened', 'nhrrob-issue-reporter' ) . '</label></th><td>';
		echo '<textarea id="nhrrob-issue-reporter-what" name="what" class="large-text" rows="6" required>' . esc_textarea( $form['what'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'What you saw, and what you expected. Markdown works.', 'nhrrob-issue-reporter' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="nhrrob-issue-reporter-steps">' . esc_html__( 'Steps to reproduce', 'nhrrob-issue-reporter' ) . '</label></th><td>';
		echo '<textarea id="nhrrob-issue-reporter-steps" name="steps" class="large-text" rows="5">' . esc_textarea( $form['steps'] ) . '</textarea></td></tr>';

		echo '<tr><th scope="row"><label for="nhrrob-issue-reporter-environment">' . esc_html__( 'Environment', 'nhrrob-issue-reporter' ) . '</label></th><td>';
		echo '<textarea id="nhrrob-issue-reporter-environment" name="environment" class="large-text code" rows="6">' . esc_textarea( $form['environment'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Sent with the report, together with the name and version of the chosen plugin. Edit or empty it to send less. The site address is never added.', 'nhrrob-issue-reporter' ) . '</p></td></tr>';

		echo '</tbody></table>';

		submit_button( $this->similar ? __( 'Send the report anyway', 'nhrrob-issue-reporter' ) : __( 'Send report', 'nhrrob-issue-reporter' ), 'primary', 'submit', true, $has_token && $has_repo ? [] : [ 'disabled' => 'disabled' ] );
		echo '</form>';
	}

	/**
	 * The reports sent so far.
	 *
	 * @return void
	 */
	private function render_sent() {
		Reports::refresh();

		$rows = Reports::all();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- shows a message after a redirect; changes nothing.
		if ( isset( $_GET['created'] ) && $rows ) {
			/* translators: 1: address of the issue, 2: issue number, 3: repository such as owner/name. */
			$this->notice( 'success', sprintf( __( 'The report was sent: <a href="%1$s">issue #%2$d in %3$s</a>.', 'nhrrob-issue-reporter' ), esc_url( $rows[0]['url'] ), (int) $rows[0]['number'], esc_html( $rows[0]['repo'] ) ) );
		}

		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No report has been sent from this site yet.', 'nhrrob-issue-reporter' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped" style="margin-top:16px"><thead><tr>';

		foreach ( [ __( 'Issue', 'nhrrob-issue-reporter' ), __( 'Plugin', 'nhrrob-issue-reporter' ), __( 'Repository', 'nhrrob-issue-reporter' ), __( 'State', 'nhrrob-issue-reporter' ), __( 'Reported', 'nhrrob-issue-reporter' ) ] as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}

		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			printf(
				'<tr><td><a href="%1$s" target="_blank" rel="noopener noreferrer">#%2$d %3$s</a></td><td>%4$s</td><td>%5$s</td><td>%6$s</td><td>%7$s</td></tr>',
				esc_url( $row['url'] ),
				(int) $row['number'],
				esc_html( $row['title'] ),
				esc_html( $row['plugin'] ),
				esc_html( $row['repo'] ),
				esc_html( 'closed' === $row['state'] ? __( 'Closed', 'nhrrob-issue-reporter' ) : __( 'Open', 'nhrrob-issue-reporter' ) ),
				esc_html( wp_date( get_option( 'date_format' ), (int) $row['time'] ) )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * The settings form.
	 *
	 * @return void
	 */
	private function render_settings() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- shows a message after a redirect; changes nothing.
		if ( isset( $_GET['saved'] ) ) {
			$this->notice( 'success', esc_html__( 'Settings saved.', 'nhrrob-issue-reporter' ) );
		}

		echo '<form method="post" action="' . esc_url( self::url( 'settings' ) ) . '" autocomplete="off">';
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="nhrrob_issue_reporter_action" value="settings">';

		echo '<h2>' . esc_html__( 'GitHub token', 'nhrrob-issue-reporter' ) . '</h2>';
		echo '<p>' . esc_html__( 'Reports are sent with a GitHub token. Create a fine-grained personal access token that covers only the repositories below, with one permission: "Issues: Read and write".', 'nhrrob-issue-reporter' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="nhrrob-issue-reporter-token">' . esc_html__( 'Token', 'nhrrob-issue-reporter' ) . '</label></th><td>';

		if ( Settings::token_is_constant() ) {
			echo '<p>' . esc_html__( 'The token is set in wp-config.php with NHRROB_ISSUE_REPORTER_TOKEN.', 'nhrrob-issue-reporter' ) . '</p>';
		} else {
			$stored = '' !== Settings::token();

			// The stored token is never printed, not even masked.
			echo '<input type="password" id="nhrrob-issue-reporter-token" name="token" class="regular-text" value="" autocomplete="new-password" placeholder="' . esc_attr( $stored ? __( 'A token is saved. Type a new one to replace it.', 'nhrrob-issue-reporter' ) : '' ) . '">';

			if ( $stored ) {
				echo '<p><label><input type="checkbox" name="remove_token" value="1"> ' . esc_html__( 'Remove the saved token', 'nhrrob-issue-reporter' ) . '</label></p>';
			}

			echo '<p class="description">' . esc_html__( 'To keep the token out of the database, define NHRROB_ISSUE_REPORTER_TOKEN in wp-config.php and leave this empty.', 'nhrrob-issue-reporter' ) . '</p>';
		}

		echo '</td></tr></tbody></table>';

		echo '<h2>' . esc_html__( 'Repositories', 'nhrrob-issue-reporter' ) . '</h2>';
		echo '<p>' . esc_html__( 'Where each plugin\'s issues go, as owner/name or a github.com address. A repository named in the plugin\'s own header is filled in; leave the others empty for plugins you do not report on.', 'nhrrob-issue-reporter' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Plugin', 'nhrrob-issue-reporter' ) . '</th><th scope="col">' . esc_html__( 'Repository', 'nhrrob-issue-reporter' ) . '</th></tr></thead><tbody>';

		foreach ( Repos::all() as $file => $plugin ) {
			$id = 'nhrrob-issue-reporter-repo-' . md5( $file );

			printf(
				'<tr><td><label for="%1$s">%2$s</label></td><td><input type="text" id="%1$s" name="repos[%3$s]" class="regular-text" value="%4$s" placeholder="owner/name"></td></tr>',
				esc_attr( $id ),
				esc_html( $plugin['name'] ),
				esc_attr( $file ),
				esc_attr( $plugin['repo'] )
			);
		}

		echo '</tbody></table>';
		submit_button( __( 'Save settings', 'nhrrob-issue-reporter' ) );
		echo '</form>';
	}
}
