<?php
/**
 * Metabox class
 *
 * @package easy-watermark
 */

namespace EasyWatermark\Metaboxes;

use EasyWatermark\Traits\Hookable;

/**
 * Metabox class
 */
abstract class Metabox {

	use Hookable;

	/**
	 * @param  string  metabox id
	 */
	protected $id;

	/**
	 * @param  string  metabox title
	 */
	protected $title;

	/**
	 * @param  string  metabox position (normal|side|advanced)
	 */
	protected $position = 'normal';

	/**
	 * @param  string  metabox priority
	 */
	protected $priority = 'high';

	/**
	 * @param  bool  whether to initially hide metabox
	 */
	protected $hide = true;

	/**
	 * Constructor
	 *
	 * @return void
	 */
	public function __construct() {
		$this->hook();
		$this->init();
	}

	/**
	 * Metabox setup
	 *
	 * @action do_meta_boxes
	 *
	 * @return void
	 */
	public function setup() {
		global $post;

		if ( 2 > $this->get_watermarks_count() || 'publish' == $post->post_status ) {
			add_meta_box( $this->id, $this->title, [ $this, 'content' ], 'watermark', $this->position, $this->priority );
		}
	}

	/**
	 * Hides metabox
	 *
	 * @filter hidden_meta_boxes
	 *
	 * @param  array   $hidden
	 * @param  object  $screen
	 * @param  bool    $use_defaults
	 * @return bool
	 */
	public function hide( $hidden, $screen, $use_defaults ) {
		if ( true == $this->hide && 'watermark' == $screen->id ) {
			$hidden += [
				$this->id
			];
		}

		return $hidden;
	}

	/**
	 * Inits metabox
	 *
	 * @return void
	 */
	abstract public function init();

	/**
	 * Renders metabox content
	 *
	 * @param  object  $post  current pot
	 * @return void
	 */
	abstract public function content( $post );

	/**
	 * Returns watermarks count
	 *
	 * @return object
	 */
	public function get_watermarks_count() {
		return wp_count_posts( 'watermark' )->publish;
	}
}