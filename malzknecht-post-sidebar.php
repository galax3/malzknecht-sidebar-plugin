<?php
/**
 * Plugin Name:       Malzknecht Post-Sidebar
 * Plugin URI:        https://malzknecht.de/
 * Description:       Dynamisches Sidebar-Modul pro Beitrag. Reusable-Block oder freies HTML, mit optionalem Sticky-Wrapper.
 * Version:           0.2.3
 * Author:            Malzknecht
 * Author URI:        https://malzknecht.de/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       malzknecht-post-sidebar
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Update URI:        https://github.com/galax3/malzknecht-sidebar-plugin
 */

defined( 'ABSPATH' ) || exit;

define( 'MPS_VERSION', '0.2.3' );
define( 'MPS_FILE', __FILE__ );
define( 'MPS_DIR', plugin_dir_path( __FILE__ ) );
define( 'MPS_URL', plugin_dir_url( __FILE__ ) );
define( 'MPS_GITHUB_REPO', 'galax3/malzknecht-sidebar-plugin' );

require_once MPS_DIR . 'includes/class-mps-github-updater.php';

class MPS_Post_Sidebar {

	const META_BLOCK_ID = '_mps_sidebar_block_id';
	const META_CUSTOM   = '_mps_sidebar_custom_html';
	const META_STICKY   = '_mps_sidebar_sticky';
	const NONCE_KEY     = 'mps_sidebar_nonce';
	const NONCE_ACTION  = 'mps_save_sidebar_meta';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function boot() {
		$plugin = self::instance();
		add_action( 'add_meta_boxes',     array( $plugin, 'register_meta_box' ) );
		add_action( 'save_post',          array( $plugin, 'save_meta' ), 10, 2 );
		add_action( 'widgets_init',       array( $plugin, 'register_widget' ) );
		add_action( 'wp_enqueue_scripts', array( $plugin, 'enqueue_assets' ) );
		add_shortcode( 'mps_post_sidebar', array( $plugin, 'shortcode' ) );

		if ( is_admin() && class_exists( 'MPS_GitHub_Updater' ) ) {
			new MPS_GitHub_Updater( MPS_FILE, MPS_GITHUB_REPO );

			if ( ! empty( $_GET['mps_force_refresh'] ) || ! empty( $_GET['force-check'] ) ) {
				MPS_GitHub_Updater::clear_cache();
			}

			add_filter(
				'plugin_action_links_' . plugin_basename( MPS_FILE ),
				array( $plugin, 'plugin_action_link' )
			);
		}
	}

	public function plugin_action_link( $links ) {
		$url     = wp_nonce_url(
			admin_url( 'plugins.php?mps_force_refresh=1' ),
			'mps_force_refresh'
		);
		$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'GitHub-Update pruefen', 'malzknecht-post-sidebar' ) );
		return $links;
	}

	public function supported_post_types() {
		return apply_filters( 'mps_supported_post_types', array( 'post' ) );
	}

	public function register_meta_box() {
		foreach ( $this->supported_post_types() as $pt ) {
			add_meta_box(
				'mps_sidebar_module',
				__( 'Sidebar-Modul (dynamisch)', 'malzknecht-post-sidebar' ),
				array( $this, 'render_meta_box' ),
				$pt,
				'side',
				'default'
			);
		}
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_KEY );

		$block_id = (int) get_post_meta( $post->ID, self::META_BLOCK_ID, true );
		$custom   = (string) get_post_meta( $post->ID, self::META_CUSTOM, true );
		$sticky   = get_post_meta( $post->ID, self::META_STICKY, true );
		if ( '' === $sticky ) {
			$sticky = '1';
		}

		$blocks = get_posts( array(
			'post_type'      => 'wp_block',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		) );
		?>
		<p>
			<label for="mps_block_id"><strong><?php esc_html_e( 'Wiederverwendbarer Block', 'malzknecht-post-sidebar' ); ?></strong></label><br>
			<select name="mps_block_id" id="mps_block_id" style="width:100%">
				<option value="0"><?php esc_html_e( 'Keiner', 'malzknecht-post-sidebar' ); ?></option>
				<?php foreach ( $blocks as $b ) : ?>
					<option value="<?php echo (int) $b->ID; ?>" <?php selected( $block_id, $b->ID ); ?>>
						<?php echo esc_html( $b->post_title ? $b->post_title : sprintf( '(ohne Titel) #%d', $b->ID ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="description"><?php esc_html_e( 'Hat Vorrang vor dem freien HTML-Feld.', 'malzknecht-post-sidebar' ); ?></span>
		</p>

		<p>
			<label for="mps_custom_html"><strong><?php esc_html_e( 'Oder: Freies HTML / Shortcode', 'malzknecht-post-sidebar' ); ?></strong></label><br>
			<textarea name="mps_custom_html" id="mps_custom_html" rows="6" style="width:100%; font-family:monospace;"><?php echo esc_textarea( $custom ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Wird nur verwendet, wenn oben kein Block gewaehlt ist.', 'malzknecht-post-sidebar' ); ?></span>
		</p>

		<p>
			<label>
				<input type="checkbox" name="mps_sticky" value="1" <?php checked( $sticky, '1' ); ?>>
				<?php esc_html_e( 'Mit der Seite mitscrollen (sticky)', 'malzknecht-post-sidebar' ); ?>
			</label>
		</p>

		<p style="color:#666;font-size:12px">
			<?php esc_html_e( 'Erscheint nur, wenn das Widget Malzknecht Post-Sidebar in einer Sidebar liegt oder der Shortcode [mps_post_sidebar] genutzt wird. Felder leer lassen = nichts wird angezeigt.', 'malzknecht-post-sidebar' ); ?>
		</p>
		<?php
	}

	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_KEY ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_KEY ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$pt_obj = get_post_type_object( $post->post_type );
		if ( ! $pt_obj || ! current_user_can( $pt_obj->cap->edit_post, $post_id ) ) {
			return;
		}

		$block_id = isset( $_POST['mps_block_id'] ) ? (int) $_POST['mps_block_id'] : 0;
		update_post_meta( $post_id, self::META_BLOCK_ID, $block_id );

		$custom_raw = isset( $_POST['mps_custom_html'] ) ? wp_unslash( $_POST['mps_custom_html'] ) : '';
		$custom     = wp_kses_post( $custom_raw );
		update_post_meta( $post_id, self::META_CUSTOM, $custom );

		$sticky = isset( $_POST['mps_sticky'] ) ? '1' : '0';
		update_post_meta( $post_id, self::META_STICKY, $sticky );
	}

	public function register_widget() {
		register_widget( 'MPS_Sidebar_Widget' );
	}

	public function enqueue_assets() {
		if ( ! is_singular( $this->supported_post_types() ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id || ! $this->has_module_content( $post_id ) ) {
			return;
		}
		wp_enqueue_style(
			'mps-post-sidebar',
			MPS_URL . 'assets/style.css',
			array(),
			MPS_VERSION
		);
	}

	public function has_module_content( $post_id ) {
		$block_id = (int) get_post_meta( $post_id, self::META_BLOCK_ID, true );
		if ( $block_id > 0 && 'publish' === get_post_status( $block_id ) ) {
			return true;
		}
		$custom = trim( (string) get_post_meta( $post_id, self::META_CUSTOM, true ) );
		return '' !== $custom;
	}

	public function render_module( $post_id = null ) {
		if ( null === $post_id ) {
			if ( ! is_singular( $this->supported_post_types() ) ) {
				return '';
			}
			$post_id = get_queried_object_id();
		}
		if ( ! $post_id ) {
			return '';
		}

		$sticky = get_post_meta( $post_id, self::META_STICKY, true );
		if ( '' === $sticky ) {
			$sticky = '1';
		}
		$classes = array( 'mps-post-sidebar' );
		if ( '1' === $sticky ) {
			$classes[] = 'mps-is-sticky';
		}

		$inner    = '';
		$block_id = (int) get_post_meta( $post_id, self::META_BLOCK_ID, true );
		if ( $block_id > 0 ) {
			$block_post = get_post( $block_id );
			if ( $block_post && 'publish' === $block_post->post_status && 'wp_block' === $block_post->post_type ) {
				$inner = do_blocks( $block_post->post_content );
			}
		}
		if ( '' === $inner ) {
			$custom = (string) get_post_meta( $post_id, self::META_CUSTOM, true );
			if ( '' !== trim( $custom ) ) {
				$inner = do_shortcode( $custom );
			}
		}
		if ( '' === $inner ) {
			return '';
		}

		return sprintf(
			'<div class="%s">%s</div>',
			esc_attr( implode( ' ', $classes ) ),
			$inner
		);
	}

	public function shortcode( $atts ) {
		return $this->render_module();
	}
}

class MPS_Sidebar_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'mps_post_sidebar',
			__( 'Malzknecht Post-Sidebar', 'malzknecht-post-sidebar' ),
			array(
				'description' => __( 'Zeigt das pro Beitrag hinterlegte Sidebar-Modul. Bleibt leer, wenn nichts gepflegt ist.', 'malzknecht-post-sidebar' ),
			)
		);
	}

	public function widget( $args, $instance ) {
		$plugin = MPS_Post_Sidebar::instance();
		if ( ! is_singular( $plugin->supported_post_types() ) ) {
			return;
		}
		$content = $plugin->render_module();
		if ( '' === $content ) {
			return;
		}

		$before_widget = $args['before_widget'];
		if ( false !== strpos( $content, 'mps-is-sticky' ) ) {
			$before_widget = preg_replace(
				'/(\bclass\s*=\s*["\'])([^"\']*)/i',
				'$1$2 mps-has-sticky',
				$before_widget,
				1
			);
		}

		echo $before_widget;
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . esc_html( $instance['title'] ) . $args['after_title'];
		}
		echo $content;
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Titel (optional):', 'malzknecht-post-sidebar' ); ?>
			</label>
			<input class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text"
				value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p style="color:#666;font-size:12px">
			<?php esc_html_e( 'Inhalt wird je Beitrag im Beitrags-Editor gepflegt.', 'malzknecht-post-sidebar' ); ?>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title' => isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '',
		);
	}
}

add_action( 'plugins_loaded', array( 'MPS_Post_Sidebar', 'boot' ) );

register_activation_hook( __FILE__, function() {
	if ( class_exists( 'MPS_GitHub_Updater' ) ) {
		MPS_GitHub_Updater::clear_cache();
	}
} );

add_action( 'upgrader_process_complete', function( $upgrader, $hook_extra ) {
	if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
		return;
	}
	if ( class_exists( 'MPS_GitHub_Updater' ) ) {
		MPS_GitHub_Updater::clear_cache();
	}
}, 10, 2 );
