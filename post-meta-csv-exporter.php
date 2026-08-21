<?php
/**
 * Plugin Name: Post Meta CSV Exporter
 * Plugin URI: https://harveyplum.com
 * Description: Export WordPress posts and selected post meta fields to CSV from the admin area.
 * Version:     1.0.7
 * Author:      Harvey Plum
 * Author URI:  https://harveyplum.com
 * GitHub Plugin URI: https://github.com/HarveyPlum/post-meta-csv-exporter
 * Update URI: https://github.com/HarveyPlum/post-meta-csv-exporter
 * Primary Branch: main
 * Release Asset: true
 * License:     GPL-2.0-or-later
 * Text Domain: post-meta-csv-exporter
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Post_Meta_CSV_Exporter {
	private const VERSION      = '1.0.7';
	private const PAGE_SLUG    = 'post-meta-csv-exporter';
	private const NONCE_ACTION = 'pmce_export_csv';

	/**
	 * Boot the plugin.
	 */
	public static function init(): void {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}
	}

	/**
	 * Register hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_post_pmce_export_csv', array( $this, 'handle_export' ) );
	}

	/**
	 * Add the exporter page under Tools.
	 */
	public function register_admin_page(): void {
		add_management_page(
			__( 'Harvey Plum Post Meta CSV Exporter', 'post-meta-csv-exporter' ),
			__( 'Harvey Plum Meta CSV Export', 'post-meta-csv-exporter' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the exporter UI.
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'post-meta-csv-exporter' ) );
		}

		$post_types = $this->get_exportable_post_types();

		if ( empty( $post_types ) ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Harvey Plum Post Meta CSV Exporter', 'post-meta-csv-exporter' ); ?></h1>
				<p><?php esc_html_e( 'No exportable post types were found.', 'post-meta-csv-exporter' ); ?></p>
				<p class="description" style="margin-top: 24px;">
					<?php
					printf(
						/* translators: 1: plugin version, 2: support email */
						esc_html__( 'Version %1$s. Contact %2$s for help with the plugin.', 'post-meta-csv-exporter' ),
						esc_html( self::VERSION ),
						esc_html( 'support@harveyplum.com' )
					);
					?>
				</p>
			</div>
			<?php
			return;
		}

		$selected_post_type = isset( $_GET['pmce_post_type'] ) ? sanitize_key( wp_unslash( $_GET['pmce_post_type'] ) ) : array_key_first( $post_types );
		$include_protected  = ! empty( $_GET['include_protected_meta'] );

		if ( ! isset( $post_types[ $selected_post_type ] ) ) {
			$selected_post_type = array_key_first( $post_types );
		}

		$standard_fields     = $this->get_standard_fields();
		$default_fields      = $this->get_default_standard_fields();
		$available_meta_keys = $this->get_meta_keys_for_post_type( $selected_post_type, $include_protected );
		$available_statuses  = $this->get_statuses_for_post_type( $selected_post_type );
		$available_authors   = $this->get_authors_for_post_type( $selected_post_type );
		$available_categories = $this->get_terms_for_post_type( $selected_post_type, 'category' );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Harvey Plum Post Meta CSV Exporter', 'post-meta-csv-exporter' ); ?></h1>
				<p><?php esc_html_e( 'Choose a post type, select the columns you want, and export the results as a CSV file.', 'post-meta-csv-exporter' ); ?></p>

			<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" style="margin: 20px 0 24px;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="pmce-post-type"><?php esc_html_e( 'Post type', 'post-meta-csv-exporter' ); ?></label>
							</th>
							<td>
								<select id="pmce-post-type" name="pmce_post_type">
									<?php foreach ( $post_types as $post_type => $label ) : ?>
										<option value="<?php echo esc_attr( $post_type ); ?>" <?php selected( $selected_post_type, $post_type ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<label style="margin-left: 12px;">
									<input type="checkbox" name="include_protected_meta" value="1" <?php checked( $include_protected ); ?>>
									<?php esc_html_e( 'Include protected meta keys (starting with an underscore)', 'post-meta-csv-exporter' ); ?>
								</label>
								<?php submit_button( __( 'Load Fields', 'post-meta-csv-exporter' ), 'secondary', 'load_fields', false, array( 'style' => 'margin-left: 12px;' ) ); ?>
							</td>
						</tr>
					</tbody>
				</table>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pmce_export_csv">
				<input type="hidden" name="pmce_post_type" value="<?php echo esc_attr( $selected_post_type ); ?>">
				<input type="hidden" name="include_protected_meta" value="<?php echo $include_protected ? '1' : '0'; ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>

				<h2><?php esc_html_e( 'Filters', 'post-meta-csv-exporter' ); ?></h2>
					<p><?php esc_html_e( 'Optionally narrow the export by status, author, category, and publish date.', 'post-meta-csv-exporter' ); ?></p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="pmce-post-status"><?php esc_html_e( 'Status', 'post-meta-csv-exporter' ); ?></label>
							</th>
							<td>
								<select id="pmce-post-status" name="pmce_post_status[]" multiple size="<?php echo esc_attr( (string) min( 6, max( 3, count( $available_statuses ) ) ) ); ?>">
									<?php foreach ( $available_statuses as $status_value => $status_label ) : ?>
										<option value="<?php echo esc_attr( $status_value ); ?>">
											<?php echo esc_html( $status_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Leave all options unselected to export every status.', 'post-meta-csv-exporter' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="pmce-post-author"><?php esc_html_e( 'Author', 'post-meta-csv-exporter' ); ?></label>
							</th>
							<td>
								<select id="pmce-post-author" name="pmce_post_author[]" multiple size="<?php echo esc_attr( (string) min( 8, max( 3, count( $available_authors ) ) ) ); ?>">
									<?php foreach ( $available_authors as $author_id => $author_name ) : ?>
										<option value="<?php echo esc_attr( (string) $author_id ); ?>">
											<?php echo esc_html( $author_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Leave all options unselected to export every author.', 'post-meta-csv-exporter' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Publish date', 'post-meta-csv-exporter' ); ?></th>
							<td>
								<label for="pmce-date-from" style="margin-right: 12px;">
									<?php esc_html_e( 'From', 'post-meta-csv-exporter' ); ?>
								</label>
								<input type="date" id="pmce-date-from" name="pmce_date_from" value="">
								<label for="pmce-date-to" style="margin: 0 12px 0 20px;">
									<?php esc_html_e( 'To', 'post-meta-csv-exporter' ); ?>
								</label>
								<input type="date" id="pmce-date-to" name="pmce_date_to" value="">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="pmce-post-category"><?php esc_html_e( 'Category', 'post-meta-csv-exporter' ); ?></label>
							</th>
							<td>
								<?php if ( empty( $available_categories ) ) : ?>
									<p class="description"><?php esc_html_e( 'No categories are available for the selected post type.', 'post-meta-csv-exporter' ); ?></p>
								<?php else : ?>
									<select id="pmce-post-category" name="pmce_post_category[]" multiple size="<?php echo esc_attr( (string) min( 8, max( 3, count( $available_categories ) ) ) ); ?>">
										<?php foreach ( $available_categories as $category_id => $category_name ) : ?>
											<option value="<?php echo esc_attr( (string) $category_id ); ?>">
												<?php echo esc_html( $category_name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Leave all options unselected to export every category.', 'post-meta-csv-exporter' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Standard Columns', 'post-meta-csv-exporter' ); ?></h2>
				<p><?php esc_html_e( 'These columns help identify each row in the export.', 'post-meta-csv-exporter' ); ?></p>
				<fieldset style="margin-bottom: 24px;">
					<?php foreach ( $standard_fields as $field_key => $label ) : ?>
						<label style="display: inline-block; min-width: 220px; margin: 0 20px 10px 0;">
							<input
								type="checkbox"
								name="standard_fields[]"
								value="<?php echo esc_attr( $field_key ); ?>"
								<?php checked( in_array( $field_key, $default_fields, true ) ); ?>
							>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>

					<h2><?php esc_html_e( 'Available Meta Fields', 'post-meta-csv-exporter' ); ?></h2>
					<?php if ( empty( $available_meta_keys ) ) : ?>
						<p><?php esc_html_e( 'No post meta keys were found for the selected post type.', 'post-meta-csv-exporter' ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Check the fields you want to include in the CSV export.', 'post-meta-csv-exporter' ); ?></p>
						<p>
							<button type="button" class="button" data-pmce-toggle="all"><?php esc_html_e( 'Select All Meta Fields', 'post-meta-csv-exporter' ); ?></button>
							<button type="button" class="button" data-pmce-toggle="none"><?php esc_html_e( 'Clear All Meta Fields', 'post-meta-csv-exporter' ); ?></button>
						</p>
						<fieldset id="pmce-meta-fields" style="max-height: 420px; overflow: auto; border: 1px solid #ccd0d4; padding: 16px; background: #fff;">
							<?php foreach ( $available_meta_keys as $meta_key ) : ?>
								<label style="display: inline-block; min-width: 260px; margin: 0 20px 10px 0; vertical-align: top;">
									<input type="checkbox" name="meta_fields[]" value="<?php echo esc_attr( $meta_key ); ?>">
									<code><?php echo esc_html( $meta_key ); ?></code>
								</label>
							<?php endforeach; ?>
						</fieldset>
					<?php endif; ?>

			<p style="margin-top: 24px;">
				<?php submit_button( __( 'Export CSV', 'post-meta-csv-exporter' ), 'primary', 'export_csv', false ); ?>
			</p>
			<p class="description" style="margin-top: 16px;">
				<?php
				printf(
					/* translators: 1: plugin version, 2: support email */
					esc_html__( 'Version %1$s. Contact %2$s for help with the plugin.', 'post-meta-csv-exporter' ),
					esc_html( self::VERSION ),
					esc_html( 'support@harveyplum.com' )
				);
				?>
			</p>
		</form>
	</div>

		<script>
			document.addEventListener('click', function (event) {
				const toggle = event.target.getAttribute('data-pmce-toggle');
				if (!toggle) {
					return;
				}

				document.querySelectorAll('#pmce-meta-fields input[type="checkbox"]').forEach(function (checkbox) {
					checkbox.checked = toggle === 'all';
				});
			});
		</script>
		<?php
	}

	/**
	 * Export the selected data to CSV.
	 */
	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export data.', 'post-meta-csv-exporter' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$post_types = $this->get_exportable_post_types();
		$post_type  = isset( $_POST['pmce_post_type'] ) ? sanitize_key( wp_unslash( $_POST['pmce_post_type'] ) ) : '';

		if ( ! isset( $post_types[ $post_type ] ) ) {
			wp_die( esc_html__( 'Invalid post type selected.', 'post-meta-csv-exporter' ) );
		}

		$include_protected = ! empty( $_POST['include_protected_meta'] );
		$available_meta    = $this->get_meta_keys_for_post_type( $post_type, $include_protected );
		$standard_fields   = $this->sanitize_standard_field_selection( $_POST['standard_fields'] ?? array() );
		$meta_fields       = $this->sanitize_meta_field_selection( $_POST['meta_fields'] ?? array(), $available_meta );
		$post_statuses     = $this->sanitize_post_status_filter( $_POST['pmce_post_status'] ?? array(), $post_type );
		$post_authors      = $this->sanitize_post_author_filter( $_POST['pmce_post_author'] ?? array(), $post_type );
		$post_categories   = $this->sanitize_term_filter( $_POST['pmce_post_category'] ?? array(), $post_type, 'category' );
		$date_from         = $this->sanitize_date_filter( $_POST['pmce_date_from'] ?? '' );
		$date_to           = $this->sanitize_date_filter( $_POST['pmce_date_to'] ?? '' );

		if ( empty( $standard_fields ) && empty( $meta_fields ) ) {
			wp_die( esc_html__( 'Please select at least one field to export.', 'post-meta-csv-exporter' ) );
		}

		$query_args = array(
			'post_type'              => $post_type,
			'post_status'            => ! empty( $post_statuses ) ? $post_statuses : 'any',
			'numberposts'            => -1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		);

		if ( ! empty( $post_authors ) ) {
			$query_args['author__in'] = $post_authors;
		}

		if ( ! empty( $post_categories ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'category',
					'field'    => 'term_id',
					'terms'    => $post_categories,
					'operator' => 'IN',
				),
			);
		}

		if ( $date_from || $date_to ) {
			$query_args['date_query'] = array(
				array_filter(
					array(
						'after'     => $date_from ?: null,
						'before'    => $date_to ?: null,
						'inclusive' => true,
					),
					static function ( $value ): bool {
						return null !== $value;
					}
				),
			);
		}

		$post_ids = get_posts( $query_args );

		$filename = sprintf(
			'%s-meta-export-%s.csv',
			sanitize_file_name( $post_type ),
			gmdate( 'Y-m-d-His' )
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output    = fopen( 'php://output', 'w' );
		$delimiter = (string) apply_filters( 'pmce_csv_delimiter', ',' );
		$headers   = array();

		if ( false === $output ) {
			wp_die( esc_html__( 'The CSV export could not be generated.', 'post-meta-csv-exporter' ) );
		}

		// Add a UTF-8 BOM so spreadsheet apps like Excel reliably detect the encoding.
		fwrite( $output, "\xEF\xBB\xBF" );

		$standard_field_labels = $this->get_standard_fields();

		foreach ( $standard_fields as $field_key ) {
			$headers[] = $standard_field_labels[ $field_key ];
		}

		foreach ( $meta_fields as $meta_key ) {
			$headers[] = $meta_key;
		}

		fputcsv( $output, array_map( array( $this, 'protect_csv_cell' ), $headers ), $delimiter );

		foreach ( $post_ids as $post_id ) {
			$row = array();

			foreach ( $standard_fields as $field_key ) {
				$row[] = $this->get_standard_field_value( (int) $post_id, $field_key );
			}

			foreach ( $meta_fields as $meta_key ) {
				$row[] = $this->format_meta_values( get_post_meta( (int) $post_id, $meta_key, false ) );
			}

			fputcsv( $output, array_map( array( $this, 'protect_csv_cell' ), $row ), $delimiter );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Fetch post types that make sense for exporting.
	 *
	 * @return array<string, string>
	 */
	private function get_exportable_post_types(): array {
		$post_type_objects = get_post_types(
			array(
				'show_ui' => true,
			),
			'objects'
		);

		unset( $post_type_objects['attachment'], $post_type_objects['revision'], $post_type_objects['nav_menu_item'] );

		$post_types = array();

		foreach ( $post_type_objects as $post_type => $object ) {
			$post_types[ $post_type ] = $object->labels->singular_name ?: $post_type;
		}

		asort( $post_types );

		return $post_types;
	}

	/**
	 * Return the standard CSV columns.
	 *
	 * @return array<string, string>
	 */
	private function get_standard_fields(): array {
		return array(
			'post_id'     => __( 'Post ID', 'post-meta-csv-exporter' ),
			'post_title'  => __( 'Title', 'post-meta-csv-exporter' ),
			'post_slug'   => __( 'Slug', 'post-meta-csv-exporter' ),
			'post_status' => __( 'Status', 'post-meta-csv-exporter' ),
			'post_date'   => __( 'Publish Date', 'post-meta-csv-exporter' ),
			'post_author' => __( 'Author', 'post-meta-csv-exporter' ),
			'category'    => __( 'Category', 'post-meta-csv-exporter' ),
			'tags'        => __( 'Tags', 'post-meta-csv-exporter' ),
			'permalink'   => __( 'Permalink', 'post-meta-csv-exporter' ),
		);
	}

	/**
	 * Default standard fields to pre-select.
	 *
	 * @return string[]
	 */
	private function get_default_standard_fields(): array {
		return array( 'post_id', 'post_title', 'post_status', 'post_date' );
	}

	/**
	 * Get valid post statuses for the selected post type.
	 *
	 * @param string $post_type Selected post type.
	 * @return array<string, string>
	 */
	private function get_statuses_for_post_type( string $post_type ): array {
		global $wpdb;

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"
				SELECT DISTINCT post_status
				FROM {$wpdb->posts}
				WHERE post_type = %s
					AND post_status <> 'auto-draft'
				ORDER BY post_status ASC
				",
				$post_type
			)
		);

		if ( ! is_array( $results ) ) {
			return array();
		}

		$status_objects = get_post_stati( array(), 'objects' );
		$statuses       = array();

		foreach ( $results as $status ) {
			$status = sanitize_key( (string) $status );

			if ( '' === $status ) {
				continue;
			}

			if ( isset( $status_objects[ $status ] ) && ! empty( $status_objects[ $status ]->label ) ) {
				$statuses[ $status ] = (string) $status_objects[ $status ]->label;
				continue;
			}

			$statuses[ $status ] = ucfirst( str_replace( '-', ' ', $status ) );
		}

		return $statuses;
	}

	/**
	 * Get authors that have content for the selected post type.
	 *
	 * @param string $post_type Selected post type.
	 * @return array<int, string>
	 */
	private function get_authors_for_post_type( string $post_type ): array {
		global $wpdb;

		$author_ids = $wpdb->get_col(
			$wpdb->prepare(
				"
				SELECT DISTINCT post_author
				FROM {$wpdb->posts}
				WHERE post_type = %s
					AND post_status <> 'auto-draft'
				ORDER BY post_author ASC
				",
				$post_type
			)
		);

		if ( ! is_array( $author_ids ) ) {
			return array();
		}

		$authors = array();

		foreach ( $author_ids as $author_id ) {
			$author_id = (int) $author_id;

			if ( $author_id <= 0 ) {
				continue;
			}

			$user = get_userdata( $author_id );

			if ( ! $user ) {
				continue;
			}

			$authors[ $author_id ] = $user->display_name;
		}

		asort( $authors );

		return $authors;
	}

	/**
	 * Get taxonomy terms that are assigned to the selected post type.
	 *
	 * @param string $post_type Selected post type.
	 * @param string $taxonomy Selected taxonomy.
	 * @return array<int, string>
	 */
	private function get_terms_for_post_type( string $post_type, string $taxonomy ): array {
		global $wpdb;

		if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
			return array();
		}

		$term_rows = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT DISTINCT t.term_id, t.name
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				WHERE tt.taxonomy = %s
					AND p.post_type = %s
					AND p.post_status <> 'auto-draft'
				ORDER BY t.name ASC
				",
				$taxonomy,
				$post_type
			)
		);

		if ( ! is_array( $term_rows ) ) {
			return array();
		}

		$terms = array();

		foreach ( $term_rows as $term_row ) {
			$term_id = isset( $term_row->term_id ) ? (int) $term_row->term_id : 0;
			$name    = isset( $term_row->name ) ? (string) $term_row->name : '';

			if ( $term_id <= 0 || '' === $name ) {
				continue;
			}

			$terms[ $term_id ] = $name;
		}

		return $terms;
	}

	/**
	 * Get all distinct meta keys for a post type.
	 *
	 * @param string $post_type Selected post type.
	 * @param bool   $include_protected Whether protected keys should be listed.
	 * @return string[]
	 */
	private function get_meta_keys_for_post_type( string $post_type, bool $include_protected = false ): array {
		global $wpdb;

		$sql = "
			SELECT DISTINCT pm.meta_key
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_type = %s
				AND p.post_status <> 'auto-draft'
		";

		if ( ! $include_protected ) {
			$sql .= " AND pm.meta_key NOT LIKE '\\_%'";
		}

		$sql .= ' ORDER BY pm.meta_key ASC';

		$meta_keys = $wpdb->get_col( $wpdb->prepare( $sql, $post_type ) );

		if ( ! is_array( $meta_keys ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'strval', $meta_keys ),
				static function ( string $meta_key ): bool {
					return '' !== $meta_key;
				}
			)
		);
	}

	/**
	 * Sanitize standard field input.
	 *
	 * @param mixed $submitted Submitted fields.
	 * @return string[]
	 */
	private function sanitize_standard_field_selection( $submitted ): array {
		$allowed  = array_keys( $this->get_standard_fields() );
		$selected = array_map( 'sanitize_key', wp_unslash( (array) $submitted ) );

		return array_values( array_intersect( $selected, $allowed ) );
	}

	/**
	 * Sanitize selected meta keys against available keys.
	 *
	 * @param mixed    $submitted Submitted keys.
	 * @param string[] $allowed Allowed keys.
	 * @return string[]
	 */
	private function sanitize_meta_field_selection( $submitted, array $allowed ): array {
		$selected = array_map( 'sanitize_text_field', wp_unslash( (array) $submitted ) );

		return array_values( array_intersect( $selected, $allowed ) );
	}

	/**
	 * Sanitize the selected post status filter.
	 *
	 * @param mixed  $submitted Submitted status.
	 * @param string $post_type Selected post type.
	 * @return string[]
	 */
	private function sanitize_post_status_filter( $submitted, string $post_type ): array {
		$allowed  = array_keys( $this->get_statuses_for_post_type( $post_type ) );
		$selected = array_map( 'sanitize_key', wp_unslash( (array) $submitted ) );

		return array_values( array_intersect( $selected, $allowed ) );
	}

	/**
	 * Sanitize the selected author filter.
	 *
	 * @param mixed  $submitted Submitted author ID.
	 * @param string $post_type Selected post type.
	 * @return int[]
	 */
	private function sanitize_post_author_filter( $submitted, string $post_type ): array {
		$allowed  = array_keys( $this->get_authors_for_post_type( $post_type ) );
		$selected = array_map( 'absint', wp_unslash( (array) $submitted ) );

		return array_values( array_intersect( $selected, $allowed ) );
	}

	/**
	 * Sanitize the selected term filter.
	 *
	 * @param mixed  $submitted Submitted term IDs.
	 * @param string $post_type Selected post type.
	 * @param string $taxonomy Selected taxonomy.
	 * @return int[]
	 */
	private function sanitize_term_filter( $submitted, string $post_type, string $taxonomy ): array {
		$allowed  = array_keys( $this->get_terms_for_post_type( $post_type, $taxonomy ) );
		$selected = array_map( 'absint', wp_unslash( (array) $submitted ) );

		return array_values( array_intersect( $selected, $allowed ) );
	}

	/**
	 * Sanitize YYYY-MM-DD filter input.
	 *
	 * @param mixed $submitted Submitted date.
	 */
	private function sanitize_date_filter( $submitted ): string {
		$date = sanitize_text_field( wp_unslash( (string) $submitted ) );

		if ( '' === $date ) {
			return '';
		}

		$datetime = DateTime::createFromFormat( 'Y-m-d', $date );

		if ( false === $datetime ) {
			return '';
		}

		return $datetime->format( 'Y-m-d' ) === $date ? $date : '';
	}

	/**
	 * Return a standard field value for a post.
	 */
	private function get_standard_field_value( int $post_id, string $field_key ): string {
		switch ( $field_key ) {
			case 'post_id':
				return (string) $post_id;
			case 'post_title':
				return (string) get_the_title( $post_id );
			case 'post_slug':
				return (string) get_post_field( 'post_name', $post_id );
			case 'post_status':
				return (string) get_post_status( $post_id );
			case 'post_date':
				return (string) get_post_field( 'post_date', $post_id );
			case 'post_author':
				$author_id = (int) get_post_field( 'post_author', $post_id );
				return $author_id ? (string) get_the_author_meta( 'display_name', $author_id ) : '';
			case 'category':
				return $this->get_term_names_for_post( $post_id, 'category' );
			case 'tags':
				return $this->get_term_names_for_post( $post_id, 'post_tag' );
			case 'permalink':
				return (string) get_permalink( $post_id );
			default:
				return '';
		}
	}

	/**
	 * Format a set of meta values for a single CSV cell.
	 *
	 * @param array<int, mixed> $values Meta values for one key.
	 */
	private function format_meta_values( array $values ): string {
		if ( empty( $values ) ) {
			return '';
		}

		$prepared_values = array_map(
			array( $this, 'prepare_meta_value' ),
			$values
		);

		return implode( ' | ', $prepared_values );
	}

	/**
	 * Convert meta values into readable CSV strings.
	 *
	 * @param mixed $value Meta value.
	 */
	private function prepare_meta_value( $value ): string {
		$value = maybe_unserialize( $value );

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$encoded = wp_json_encode( $value );
			return false === $encoded ? '' : $encoded;
		}

		if ( null === $value ) {
			return '';
		}

		return (string) $value;
	}

	/**
	 * Prevent spreadsheet applications from treating exported text as a formula.
	 *
	 * @param mixed $value CSV cell value.
	 */
	private function protect_csv_cell( $value ): string {
		$value = (string) $value;

		if ( '' !== $value && preg_match( '/^[=+\-@\t\r]/u', $value ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Get a comma-separated list of term names for a post and taxonomy.
	 */
	private function get_term_names_for_post( int $post_id, string $taxonomy ): string {
		$post_type = get_post_type( $post_id );

		if ( ! $post_type || ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
			return '';
		}

		$terms = get_the_terms( $post_id, $taxonomy );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		$term_names = array();

		foreach ( $terms as $term ) {
			if ( isset( $term->name ) && '' !== $term->name ) {
				$term_names[] = $term->name;
			}
		}

		return implode( ', ', $term_names );
	}
}

Post_Meta_CSV_Exporter::init();
