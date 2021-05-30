<?php
namespace ImgB642File;
defined( 'ABSPATH' ) || exit;

class ImgB642File {
	private $find_replace;

	public function __construct() {
		$this->includes();
		$this->hooks();
	}

	private function hooks() {
		add_action( 'save_post', array( $this, 'run' ), 10, 2 );
	}

	private function includes() {
		require_once IMGB642FILE_ABSPATH . '/class/class-find-replace.php';
	}

	public function run( int $post_id, \WP_Post $post ) {
		// Currently, only supports post and page type
		if ( 'post' !== $post->post_type && 'page' !== $post->post_type ) {
			return;
		}

		$this->find_replace = Find_Replace::instance( $this );

		$content = $this->find_replace->run( $post->post_content, $post )
				->get();

		remove_action( 'save_post', array( $this, 'run' ), 10, 2 );

		wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => $content,
		));

		$this->hooks();
	}
}