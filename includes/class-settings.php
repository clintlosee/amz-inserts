<?php
/**
 * Plugin settings: associate tag and optional disclosure.
 *
 * @package Amz_Inserts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Amz_Inserts_Settings {

	public const OPTION = 'amz_inserts_settings';

	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
	}

	public static function defaults(): array {
		return array(
			'associate_tag'   => '',
			'cta_label'       => __( 'View on Amazon', 'amz-inserts' ),
			'disclosure'      => '',
			'show_disclosure' => 0,
		);
	}

	public static function get( string $key, mixed $default = '' ): mixed {
		$settings = get_option( self::OPTION, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings = wp_parse_args( $settings, self::defaults() );

		return $settings[ $key ] ?? $default;
	}

	public static function register(): void {
		register_setting(
			'amz_inserts',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'amz_inserts_main',
			__( 'Affiliate settings', 'amz-inserts' ),
			array( self::class, 'section' ),
			'amz_inserts'
		);

		add_settings_field(
			'associate_tag',
			__( 'Associate tag', 'amz-inserts' ),
			array( self::class, 'field_tag' ),
			'amz_inserts',
			'amz_inserts_main'
		);

		add_settings_field(
			'cta_label',
			__( 'Default CTA label', 'amz-inserts' ),
			array( self::class, 'field_cta_label' ),
			'amz_inserts',
			'amz_inserts_main'
		);

		add_settings_field(
			'show_disclosure',
			__( 'Show disclosure under inserts', 'amz-inserts' ),
			array( self::class, 'field_show_disclosure' ),
			'amz_inserts',
			'amz_inserts_main'
		);

		add_settings_field(
			'disclosure',
			__( 'Disclosure text', 'amz-inserts' ),
			array( self::class, 'field_disclosure' ),
			'amz_inserts',
			'amz_inserts_main'
		);
	}

	public static function sanitize( mixed $input ): array {
		$input     = is_array( $input ) ? $input : array();
		$cta_label = sanitize_text_field( $input['cta_label'] ?? '' );
		if ( '' === $cta_label ) {
			$cta_label = (string) self::defaults()['cta_label'];
		}

		return array(
			'associate_tag'   => sanitize_text_field( $input['associate_tag'] ?? '' ),
			'cta_label'       => $cta_label,
			'disclosure'      => sanitize_textarea_field( $input['disclosure'] ?? '' ),
			'show_disclosure' => empty( $input['show_disclosure'] ) ? 0 : 1,
		);
	}

	public static function menu(): void {
		add_submenu_page(
			'edit.php?post_type=amz_unit',
			__( 'Amazon Inserts Settings', 'amz-inserts' ),
			__( 'Settings', 'amz-inserts' ),
			'manage_options',
			'amz-inserts-settings',
			array( self::class, 'render_page' )
		);
	}

	public static function section(): void {
		echo '<p>' . esc_html__( 'Links that do not already include a tag= parameter will use this Associate tag. Amazon and FTC rules still require a clear affiliate disclosure on the site (a footer notice is enough). The optional text below is only if you want a line under each insert as well.', 'amz-inserts' ) . '</p>';
	}

	public static function field_tag(): void {
		printf(
			'<input type="text" class="regular-text" name="%1$s[associate_tag]" value="%2$s" placeholder="yourtag-20" />',
			esc_attr( self::OPTION ),
			esc_attr( (string) self::get( 'associate_tag' ) )
		);
		echo '<p class="description">' . esc_html__( 'Your Amazon Associates tracking ID, for example yourname-20.', 'amz-inserts' ) . '</p>';
	}

	public static function field_cta_label(): void {
		printf(
			'<input type="text" class="regular-text" name="%1$s[cta_label]" value="%2$s" />',
			esc_attr( self::OPTION ),
			esc_attr( (string) self::get( 'cta_label' ) )
		);
		echo '<p class="description">' . esc_html__( 'Button text shown on card and grid displays.', 'amz-inserts' ) . '</p>';
	}

	public static function field_show_disclosure(): void {
		printf(
			'<label><input type="checkbox" name="%1$s[show_disclosure]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION ),
			checked( 1, (int) self::get( 'show_disclosure', 0 ), false ),
			esc_html__( 'Print disclosure text under each insert', 'amz-inserts' )
		);
	}

	public static function field_disclosure(): void {
		printf(
			'<textarea class="large-text" rows="3" name="%1$s[disclosure]">%2$s</textarea>',
			esc_attr( self::OPTION ),
			esc_textarea( (string) self::get( 'disclosure' ) )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error( 'amz_inserts_messages', 'amz_inserts_saved', __( 'Settings saved.', 'amz-inserts' ), 'updated' );
		}

		settings_errors( 'amz_inserts_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'amz_inserts' );
				do_settings_sections( 'amz_inserts' );
				submit_button();
				?>
			</form>
			<?php self::render_shortcode_help(); ?>
		</div>
		<?php
	}

	/**
	 * In-admin reference for [amz_unit] and [amz_link].
	 */
	public static function render_shortcode_help(): void {
		$examples = '[amz_link asin="B0EXAMPLE1" title="Product name"]' . "\n"
			. '[amz_link asin="B0EXAMPLE1" title="Product name" display="button"]' . "\n"
			. '[amz_link asin="B0EXAMPLE1" title="Product name" display="card"]' . "\n"
			. '[amz_link url="https://www.amazon.com/dp/B0EXAMPLE1" title="Product name"]';
		?>
		<div class="card" style="max-width: 52em; padding: 12px 16px 16px;">
			<h2><?php esc_html_e( 'Shortcode reference', 'amz-inserts' ); ?></h2>
			<p><?php esc_html_e( 'Paste these into a classic post. In the block editor, add the Amazon Insert block to pick a saved unit or build a custom insert.', 'amz-inserts' ); ?></p>

			<p><strong><?php esc_html_e( 'Saved unit', 'amz-inserts' ); ?></strong></p>
			<p><code>[amz_unit id="123"]</code></p>
			<p class="description"><?php esc_html_e( 'Copy the real shortcode from All Units or the Insert box on a unit.', 'amz-inserts' ); ?></p>

			<p><strong><?php esc_html_e( 'One-off link (no saved unit)', 'amz-inserts' ); ?></strong></p>
			<p><?php esc_html_e( 'Use the 10-character ASIN from the Amazon product page. The Associate tag on this screen is added automatically. amazon.com is the default marketplace.', 'amz-inserts' ); ?></p>
			<p class="description"><?php esc_html_e( 'Do not paste amzn.to / a.co short links into [amz_link] — those are not expanded, so tracking may not apply. Short links are fine on a saved unit if you Fetch or save so they can be expanded first.', 'amz-inserts' ); ?></p>
			<pre style="background:#f6f7f7;padding:12px;overflow:auto;"><?php echo esc_html( $examples ); ?></pre>
			<p class="description"><?php esc_html_e( 'display can be text (default), button, image, or card. Optional: image_url, image_id, cta.', 'amz-inserts' ); ?></p>
		</div>
		<?php
	}
}
