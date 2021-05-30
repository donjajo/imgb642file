<?php
namespace ImgB642File;
defined( 'ABSPATH' ) || exit;

class Find_Replace {
	private $parent;
	private static $instance;

	private $in;
	private $out = '';

	private $start = 0;
	private $tmpfilename;
	private $job_count = 0;
	private $post;

	const BASE64_LINE_MAX = 76;

	private $data_chunk = array(
		'buffer'      => '',
		'last_pos'    => 0,
		'current_pos' => 0,
		'fp'          => null,
		'upload_url'  => '',
	);

	private function __construct( ImgB642File $parent ) {
		$this->parent = $parent;
		$this->data_chunk = (object) $this->data_chunk;
	}

	public static function instance( ImgB642File $parent ) : Find_Replace {
		if ( empty( self::$instance ) ) {
			self::$instance = new self( $parent );
		}

		return self::$instance;
	}

	public function run( string $content, \WP_Post $post ) {
		// Set where we encounter first data:image/
		// Starting from 0. If there were previous jobs, start from where it stopped. See cleanup() method
		$this->start = strpos( $content, 'data:image/', $this->start );

		$this->post = $post;

		// We could not find `data:image/`. We have no work to do
		if ( $this->start === false ) {
			$this->out = $content;
			$content = '';

			return $this;
		}

		$this->in = $content;
		$content = '';

		// Generate and open temporary file where we store as buffer before upload
		$fp_status = $this->generate_tmpfilename()->create_tmpfile();
		if ( $fp_status ) {
			$this->iterate();
		}

		return $this;
	}

	private function iterate() {
		$begin = false;

		for (
			$this->data_chunk->current_pos = $this->start;
			isset( $this->in[ $this->data_chunk->current_pos ] ) &&
				$this->in[ $this->data_chunk->current_pos ] != '\'' &&
				$this->in[ $this->data_chunk->current_pos ] != '"';
			$this->data_chunk->current_pos++
		) {
			// There might be space after `base64,` lets keep ingoring those spaces till we find a valid character
			if ( $this->in[ $this->data_chunk->current_pos ] == ' ' ) {
				continue;
			}

			// If search has never begun because we have not found a comma yet that depicts, keep searching
			if ( ! $begin ) {

				// Aha, we found a comma, now begin!
				if ( $this->in[ $this->data_chunk->current_pos ] == ',' ) {
					$begin = true;
				}

				continue;
			}

			// First encounter with valid data, lets set a mark so we can start getting data from this point
			if ( $this->data_chunk->last_pos == 0 ) {
				$this->data_chunk->last_pos = $this->data_chunk->current_pos;
			}

			// Add to buffer till 76 characters, which is base64 line max
			$this->data_chunk->buffer .= $this->in[ $this->data_chunk->current_pos ];

			// If we have walked BASE64_LINE_MAX of characters, lets decode those first. Remember, memory safe!
			if ( $this->data_chunk->current_pos - $this->data_chunk->last_pos + 1 == self::BASE64_LINE_MAX ) {
				if ( false == $this->convert_write() ) {
					break;
				}

				// Set another marker, then continue walking
				$this->data_chunk->last_pos = $this->data_chunk->current_pos + 1;
			}
		}

		// Unfortunately, we could not reach another BASE64_LINE_MAX walk before the end of string?
		// Add the remaining of what we have
		if ( $this->data_chunk->current_pos > $this->data_chunk->last_pos ) {
			$this->convert_write();
		}

		// Everything written to file successfully. Now upload to WP
		// Relate it to the current post
		// Get a link and replace the entire base64 encoding
		$url = $this->upload();
		if ( $url ) {
			// Replace from the first encounter with data:image/ till the " or '
			$this->replace();
		}

		// Pheew! Clean up the workspace for another operation!
		$this->cleanup();

		// Recursion, run another job till `data:image/` is no longer found in the content
		$this->run( $this->out, $this->post );
	}

	private function convert_write() {
		if ( $this->data_chunk->fp && $this->data_chunk->buffer ) {
			$decoded = base64_decode( $this->data_chunk->buffer );
			$this->data_chunk->buffer = '';

			if ( ! $decoded ) {
				return false;
			}

			fwrite( $this->data_chunk->fp, $decoded );
			$decoded = '';
		}

		return true;
	}

	private function replace() {
		// We have an upload URL in this job? Replace from start offset till end, which is the current_pos
		if ( $this->data_chunk->upload_url ) {
			$this->out = substr_replace(
				$this->in,
				$this->data_chunk->upload_url,
				$this->start,
				$this->data_chunk->current_pos - $this->start
			);
		}
	}

	private function cleanup() {
		if ( $this->data_chunk->fp ) {
			// Close file and try to unlink a temporary file anyway
			fclose( $this->data_chunk->fp );
			@unlink( $this->tmpfilename );
		}

		if ( $this->data_chunk->upload_url ) {
			// Prepare for next operation, so we don't start from 0 position.
			// Lets start from where the upload URL ends
			$this->start = strlen( $this->data_chunk->upload_url ) + $this->start;
		}

		$this->data_chunk->buffer = '';
		$this->data_chunk->last_pos = 0;
		$this->data_chunk->fp = null;
		$this->data_chunk->current_pos = 0;
		$this->data_chunk->upload_url = '';
		$this->job_count++;
	}

	private function get_file_extension() {
		$definition_part = substr( $this->in, $this->start, 20 );
		if ( preg_match( '/image\/(.*?);/', $definition_part, $matches ) && count( $matches ) > 1 ) {
			return $matches[1];
		} else {
			// Have no choice but to aggressively agree its png
			return 'png';
		}
	}

	private function upload() {
		$dest_filename = sprintf(
			'%s_%d_%d.%s',
			preg_replace( '/\W/', '_', $this->post->post_title ),
			$this->post->ID,
			$this->job_count+1,
			$this->get_file_extension()
		);

		$file = array(
			'name'     => $dest_filename,
			'tmp_name' => $this->tmpfilename,
		);

		$att_id = media_handle_sideload( $file, $this->post->ID );

		if ( is_wp_error( $att_id ) ) {
			return false;
		}

		$this->data_chunk->upload_url = wp_get_attachment_url( $att_id );

		return true;
	}

	private function generate_tmpfilename() {
		$this->tmpfilename = apply_filters( 'image642file_tmp_filename', wp_tempnam( '', get_temp_dir() ), $this );
		return $this;
	}

	private function create_tmpfile() {
		if ( $this->tmpfilename ) {
			$this->data_chunk->fp = fopen( $this->tmpfilename, 'a' );

			if ( $this->data_chunk->fp ) {
				do_action( 'image642file_opened_tmp_file', $this->data_chunk->fp, $this );
				return true;
			}
		}

		return false;
	}

	public function get() {
		return apply_filters( 'image642file_get_out_content', $this->out, $this );
	}
}