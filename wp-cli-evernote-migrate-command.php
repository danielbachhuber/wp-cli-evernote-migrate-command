<?php
/**
 * Migrate your Evernote content to and from WordPress
 */

class WP_CLI_Evernote_Migrate_Command extends WP_CLI_Command {

	/**
	 * Import an Evernote XML file into WordPress.
	 * 
	 * @synopsis <file>
	 */
	public function import( $args, $assoc_args ) {

		list( $file ) = $args;

		$contents = simplexml_load_file( $file );

		if ( is_a( $contents, 'SimpleXMLElement' ) ) {
			WP_CLI::line( sprintf( "Found %d notes to import", count( $contents->count() ) ) );
		} else {
			WP_CLI::error( "Couldn't load file to import." );
		}

		foreach( $contents->children() as $note ) {

			$post_title = $note->title->__toString();
			$post_content = $this->evernote_enml_to_html( $note->content->__toString() );
			$post = array(
				'post_title'      => sanitize_text_field( $post_title ),
				'post_content'    => trim( $post_content ),
				'post_status'     => 'publish',
				'post_type'       => 'post',
				);

			$post_id = wp_insert_post( $post );
			if ( ! $post_id ) {
				WP_CLI::warning( sprintf( "Couldn't create post for '%s'", $post_title ) );
				continue;
			}

			// Handle any attachments in the note content

			// Persist tags
			if ( $note->tag->count() ) {
				$tags = array_map( 'sanitize_text_field', (array)$note->tag );
				wp_set_post_tags( $post_id, $tags );
			}

			// Store any note attributes in post meta
			foreach( $note->{'note-attributes'}->children() as $key => $value ) {

				$key = str_replace( '-', '_', $key );
				update_post_meta( $post_id, $key, $value->__toString() );

			}

			WP_CLI::line( sprintf( "Created post for '%s'", $post_title ) );
		}

		WP_CLI::success( "Import complete." );

	}

	/**
	 * Turn Evernote's ENML XML format HTML that we prefer
	 * 
	 * @param string
	 * @return string
	 */
	private function evernote_enml_to_html( $enml ) {

		// Pre-process
		$search_replace = array(
			'<br />'      => "\r\n\r\n",
			'<div>'       => '',
			'</div>'      => '',
			'<en-note>'   => '',
			'</en-note>'  => '',
			'<?xml version="1.0" encoding="UTF-8"?>' => '',
			'<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml.dtd">' => '',
			);
		$search = array_keys( $search_replace );
		$replace = array_values( $search_replace );
		$enml = str_replace( $search, $replace, $enml );

		// Post-process
		$html = strip_tags( $enml, '<a><p><ul><li><ol><strong><em><b><i><en-media>' );
		return $html;
	}

}

WP_CLI::add_command( 'evernote-migrate', 'WP_CLI_Evernote_Migrate_Command' );