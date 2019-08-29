<?php
/**
 * Assets class
 *
 * @package easy-watermark
 */

namespace EasyWatermark\Core;

use EasyWatermark\Helpers\Image as ImageHelper;
use EasyWatermark\Traits\Hookable;
use EasyWatermark\Watermark\Handler;

/**
 * Assets class
 */
class Assets {

	use Hookable;

	/**
	 * Flag whether assets has been registered already
	 *
	 * @var boolean
	 */
	private $registered = false;

	/**
	 * Watermark handler instance
	 *
	 * @var Handler
	 */
	private $handler;

	/**
	 * Constructor
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( $plugin ) {

		$this->hook();
		$this->handler = $plugin->get_watermark_handler();

	}

	/**
	 * Registers admin scripts/styles
	 *
	 * @action admin_enqueue_scripts 20
	 *
	 * @return void
	 */
	public function register_admin_scripts() {

		if ( true === $this->registered ) {
			return;
		}

		$assets = [
			'attachment-edit' => [ 'jquery' ],
			'settings'        => [ 'jquery' ],
			'uploader'        => [ 'jquery' ],
			'media-library'   => [ 'jquery', 'backbone' ],
			'watermark-edit'  => [ 'jquery', 'wp-color-picker' ],
		];

		foreach ( $assets as $filename => $deps ) {
			$script_version = $this->asset_version( 'scripts', $filename . '.js' );
			$style_version  = $this->asset_version( 'styles', $filename . '.css' );

			if ( false !== $script_version ) {
				wp_register_script( 'ew-' . $filename, $this->asset_url( 'scripts', $filename . '.js' ), $deps, $script_version, true );
			}

			if ( false !== $style_version ) {
				wp_register_style( 'ew-' . $filename, $this->asset_url( 'styles', $filename . '.css' ), [], $style_version );
			}
		}

		$this->registered = true;

	}

	/**
	 * Loads admin scripts/styles
	 *
	 * @action admin_enqueue_scripts 30
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts() {

		$current_screen = get_current_screen();
		$enqueue        = false;
		$localize       = [];

		switch ( $current_screen->id ) {
			case 'attachment':
				$enqueue  = 'attachment-edit';
				$localize = [
					'genericErrorMessage' => __( 'Something went wrong. Please refresh the page and try again.', 'easy-watermark' ),
				];
				break;
			case 'settings_page_easy-watermark':
				$enqueue = 'settings';
				break;
			case 'upload':
				$this->wp_enqueue_media();
				$enqueue  = 'media-library';
				$localize = [
					'watermarks'         => $this->get_watermarks(),
					'mime'               => ImageHelper::get_available_mime_types(),
					'applyAllNonce'      => wp_create_nonce( 'apply_all' ),
					'applySingleNonces'  => $this->get_watermark_nonces(),
					'restoreBackupNonce' => wp_create_nonce( 'restore_backup' ),
					'i18n'               => [
						'watermarkModeToggleButtonLabel' => __( 'Watermark Selected', 'easy-watermark' ),
						'watermarkButtonLabel'           => __( 'Watermark', 'easy-watermark' ),
						'restoreButtonLabel'             => __( 'Restore original images', 'easy-watermark' ),
						'cancelLabel'                    => __( 'Cancel', 'easy-watermark' ),
						'selectWatermarkLabel'           => __( 'Select Watermark', 'easy-watermark' ),
						'allWatermarksLabel'             => __( 'All Watermarks', 'easy-watermark' ),
						'genericErrorMessage'            => __( 'Something went wrong. Please refresh the page and try again.', 'easy-watermark' ),
						/* translators: watermarked images number */
						'watermarkingStatus'             => sprintf( __( 'Watermarked %s images', 'easy-watermark' ), '{counter}' ),
						/* translators: watermarked images number */
						'restoringStatus'                => sprintf( __( 'Restored %s images', 'easy-watermark' ), '{counter}' ),
						/* translators: watermarked images number */
						'watermarkingSuccessMessage'     => sprintf( __( 'Successfully watermarked %s images.', 'easy-watermark' ), '{procesed}' ),
						/* translators: watermarked images number */
						'restoringSuccessMessage'        => sprintf( __( 'Successfully restored %s images.', 'easy-watermark' ), '{procesed}' ),
						/* translators: %1&s - image title, %2&s - error content */
						'bulkActionErrorMessage'         => sprintf( __( 'An error occured while processing %1$s: %2$s', 'easy-watermark' ), '{imageTitle}', '{error}' ),
					],
				];
				break;
			case 'watermark':
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_media();
				$enqueue  = 'watermark-edit';
				$localize = [
					'autosaveNonce'     => wp_create_nonce( 'watermark_autosave' ),
					'previewImageNonce' => wp_create_nonce( 'preview_image' ),
				];
				break;
		}

		if ( $enqueue ) {
			$this->enqueue_asset( $enqueue, $localize );
		}

	}

	/**
	 * Loads scripts/styles altering WordPress media library
	 *
	 * @action wp_enqueue_media
	 *
	 * @return void
	 */
	public function wp_enqueue_media() {

		// In block editor wp_enqueue_media runs before admin_enqueue_scripts, so the scripts are not registered by now.
		$this->register_admin_scripts();
		$this->enqueue_asset( 'uploader', [
			'autoWatermark' => true,
		] );

	}

	/**
	 * Enqueues script/style and localizes if necessary
	 *
	 * @param  string $asset_name Asset name.
	 * @param  array  $localize   Localize data.
	 * @return void
	 */
	private function enqueue_asset( $asset_name, $localize = [] ) {

		$asset_name = 'ew-' . $asset_name;

		wp_enqueue_style( $asset_name );
		wp_enqueue_script( $asset_name );

		if ( $localize ) {
			wp_localize_script( $asset_name, 'ew', $localize );
		}

	}

	/**
	 * Returns asset url
	 *
	 * @param  string $type Asset type.
	 * @param  string $file Filename.
	 * @return string
	 */
	private function asset_url( $type, $file ) {
		return EW_DIR_URL . 'assets/dist/' . $type . '/' . $file;
	}

	/**
	 * Returns asset version
	 *
	 * @param  string $type Asset type.
	 * @param  string $file Filename.
	 * @return string|false
	 */
	private function asset_version( $type, $file ) {

		$path = EW_DIR_PATH . 'assets/dist/' . $type . '/' . $file;

		if ( is_file( $path ) ) {
			return filemtime( $path );
		}

		return false;

	}

	/**
	 * Returns watermarks list
	 *
	 * @return array
	 */
	private function get_watermarks() {

		$watermarks      = $this->handler->get_watermarks();
		$watermarks_list = [];

		foreach ( $watermarks as $watermark ) {
			$watermarks_list[ $watermark->ID ] = $watermark->post_title;
		}

		return $watermarks_list;

	}

	/**
	 * Returns watermarks list
	 *
	 * @return array
	 */
	private function get_watermark_nonces() {

		$watermarks = $this->handler->get_watermarks();
		$nonces     = [];

		foreach ( $watermarks as $watermark ) {
			$nonces[ $watermark->ID ] = wp_create_nonce( 'apply_single-' . $watermark->ID );
		}

		return $nonces;

	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		$this->unhook();
	}

}
