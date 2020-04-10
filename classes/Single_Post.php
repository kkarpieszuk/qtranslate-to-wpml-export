<?php

namespace QT_Importer;

require_once 'Utils.php';

class Single_Post {

	private $wpdb;
	private $qt_default_language;
	private $qt_active_languages;
	private $qt_url_mode;
	private $utils;

	public function __construct( $qt_default_language, $qt_active_languages, $qt_url_mode ) {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->utils = new Utils();
		$this->qt_default_language = $qt_default_language;
		$this->qt_active_languages = $qt_active_languages;
		$this->qt_url_mode = $qt_url_mode;
	}

	public function process_post( $post_id ) {
		global $sitepress, $sitepress_settings;

		if ( get_post_meta( $post_id, '_qt_imported', true ) ) return; // already imported

		$post = get_post( $post_id, ARRAY_A );

		$translatable_tax = $sitepress->get_translatable_taxonomies( true, $post['post_type'] );

		if ( $post ) {

			$posts_to_create = [];

			$post['post_title'] = $this->replace_legacies_in_post_element( $post['post_title'] );
			$posts_to_create = $this->add_elements_to_posts_to_create( $posts_to_create, $post, 'post_title' );

			$post['post_content'] = $this->replace_legacies_in_post_element( $post['post_content'] );
			$posts_to_create = $this->add_elements_to_posts_to_create( $posts_to_create, $post, 'post_content' );

			$post['post_excerpt'] = $this->replace_legacies_in_post_element( $post['post_excerpt'] );
			$posts_to_create = $this->add_elements_to_posts_to_create( $posts_to_create, $post, 'post_excerpt' );

			$custom_fields = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT meta_key, meta_value FROM {$this->wpdb->postmeta} WHERE post_id=%d", $post_id ) );
			foreach ( $custom_fields as $cf ) {
				// only handle scalar values
				if ( ! is_serialized( $cf->meta_value ) ) {

					$cf->meta_value = $this->replace_legacies_in_post_element( $cf->meta_value );
					$elements_by_language = preg_split( '#\[:([a-z]{2})\]#', $cf->meta_value );
					array_shift( $elements_by_language );
					preg_match_all( '#\[:([a-z]{2})\]#', $cf->meta_value, $matches );
					$languages = $matches['1'];
					foreach ( $languages as $key => $language_code ) {
						$language_code                 = strtolower( $language_code );
						$languages[ $key ] = $language_code;
					}
					foreach ( $elements_by_language as $key => $element ) {
						if ( isset( $matches[2] ) ) {
							$posts_to_create[ $lang ]['custom_fields'][ $cf->meta_key ] = $matches[2]; // @todo $lang does not exist so check what is going on here
						}
					}
				} else {
					// copying all the other custom fields
					foreach ( $this->qt_active_languages as $lang ) {
						if ( $this->qt_default_language != $lang ) {
							$posts_to_create[ $lang ]['custom_fields'][ $cf->meta_key ] = $cf->meta_value;
						}
					}
				}

			}

			//echo $post_id . "------------------------";

			// put the default language in front
			$active_languages = array_merge( array( $this->qt_default_language ), array_diff( $this->qt_active_languages, array( $this->qt_default_language ) ) );


			// handle empty titles
			foreach ( $active_languages as $language ) {
				if ( empty( $posts_to_create[ $language ]['post_title'] ) && ! empty( $posts_to_create[ $language ]['post_content'] ) ) {
					$posts_to_create[ $language ]['post_title'] = $post['post_title'];
				}
			}

			// if the post in the default language does not exist pick a different post as a 'source'
			if ( empty( $posts_to_create[ $this->qt_default_language ] ) ) {
				foreach ( $active_languages as $language ) {
					if ( $language != $this->qt_default_language && ! empty( $posts_to_create[ $language ]['post_title'] ) ) {
						$posts_to_create[ $language ]['__icl_source'] = true;
						break;
					}
				}
			}

			foreach ( $active_languages as $language ) {

				//echo $language . "------------------------";

				if ( empty( $posts_to_create[ $language ]['post_title'] ) ) {
					continue;
				} // obslt

				$post['post_title']   = $posts_to_create[ $language ]['post_title'];
				$post['post_content'] = isset( $posts_to_create[ $language ]['post_content'] ) ? $posts_to_create[ $language ]['post_content'] : '';
				if ( isset( $posts_to_create[ $language ]['post_excerpt'] ) ) {
					$post['post_excerpt'] = $posts_to_create[ $language ]['post_excerpt'];
				}
				$_POST['icl_post_language'] = $this->utils->_lang_map( $language );
				$_POST['post_title']        = $post['post_title'];

				global $iclTranslationManagement;
				if ( ! empty( $iclTranslationManagement ) ) {
					remove_action( 'save_post', array(
						$iclTranslationManagement,
						'save_post_actions'
					), 11, 2 );
				}

				if ( $language == $this->qt_default_language || ! empty( $posts_to_create[ $language ]['__icl_source'] ) ) {

					$trid = $sitepress->get_element_trid( $post['ID'], 'post' . $post['post_type'] );
					if ( is_null( $trid ) ) {
						$sitepress->set_element_language_details( $post['ID'], 'post_' . $post['post_type'], null, $this->utils->_lang_map( $this->qt_default_language ) );
					}

					$id = wp_update_post( $post );
					update_post_meta( $post['ID'], '_qt_imported', 'original' );
					$post_imported = true;
				} else {
					$_POST['icl_translation_of'] = $post['ID'];
					$post_copy                   = $post;

					unset( $post_copy['ID'], $post_copy['post_name'], $post_copy['post_parent'],
						$post_copy['guid'], $post_copy['comment_count'], $post_copy['ancestors'] );

					if ( isset( $sitepress_settings['sync_page_parent'] ) ) {
						$icl_sync_page_parent = $sitepress_settings['sync_page_parent'];
					}
					$iclsettings['sync_page_parent'] = 0;

					if ( ! in_array( $post['post_type'], array(
						'post',
						'page'
					) ) ) {
						$iclsettings['custom_posts_sync_option'][ $post['post_type'] ] = 1;
					}

					$sitepress->save_settings( $iclsettings );

					$current_language = apply_filters( 'wpml_current_language', null );
					do_action( 'wpml_switch_language', $this->utils->_lang_map( $language ) );

					$id = wp_insert_post( $post_copy );

					do_action( 'wpml_switch_language', $current_language );

					if ( isset( $sitepress_settings['sync_page_parent'] ) ) {
						$iclsettings['sync_page_parent'] = $icl_sync_page_parent;
					}
					$sitepress->save_settings( $iclsettings );

					update_post_meta( $id, '_qt_imported', 'from-' . $post['ID'] );
					$post_imported = true;

					unset( $_POST['icl_translation_of'], $_POST['post_title'], $_POST['icl_post_language'] );

					// fix terms
					foreach ( $translatable_tax as $tax ) {
						$terms = wp_get_object_terms( $post['ID'], $tax );

						if ( $terms ) {
							$translated_terms = array();
							foreach ( $terms as $term ) {
								$translated_term = icl_object_id( $term->term_id, $tax, false, $this->utils->_lang_map( $language ) );
								if ( $translated_term ) {
									$translated_terms[] = intval( $translated_term );
								}
							}

							wp_set_object_terms( $id, $translated_terms, $tax, false );
						}
					}

					if ( $post['post_status'] == 'publish' ) {
						$_qt_redirects_map = get_option( '_qt_redirects_map' );

						$original_url = get_permalink( $post['ID'] );
						if ( $this->qt_url_mode == 1 ) {
							$glue         = false === strpos( $original_url, '?' ) ? '?' : '&';
							$original_url .= $glue . 'lang=' . $language;
						} elseif ( $this->qt_url_mode == 2 ) {
							$original_url = str_replace( home_url(), rtrim( home_url(), '/' ) . '/' . $language, $original_url );
						} elseif ( $this->qt_url_mode == 2 ) {
							$parts        = parse_url( home_url() );
							$original_url = str_replace( $parts['host'], $language . '.' . $parts['host'], $original_url );
						}

						$_qt_redirects_map[ $original_url ] = get_permalink( $id );
						update_option( '_qt_redirects_map', $_qt_redirects_map );
					}


				}

				if ( ! empty( $posts_to_create[ $language ]['custom_fields'] ) ) {
					foreach ( $posts_to_create[ $language ]['custom_fields'] as $k => $v ) {
						update_post_meta( $id, $k, $v );
					}
				}

			}

			if ( ! isset( $post_imported ) || ! $post_imported ) {
				update_post_meta( $post['ID'], '_qt_imported', 'import_failed' );
			}

			// handle comments
			$comments = $this->wpdb->get_col( $this->wpdb->prepare( "SELECT comment_ID FROM {$this->wpdb->comments} WHERE comment_post_ID = %d", $post['ID'] ) );
			if ( $comments ) {
				foreach ( $comments as $comment_id ) {
					$sitepress->set_element_language_details( $comment_id, 'comment', null, $this->utils->_lang_map( $this->qt_default_language ) );
				}
			}
		}
	}

	private function replace_legacies_in_post_element( $post_element ) {
		$post_element = preg_replace( '#<!--:--><!--:([a-z]{2})-->#', '[:$1]', $post_element ); // replace middle legacy syntax <!--:--><!--:en--> into [:en]
		$post_element = str_replace( '<!--:-->', '[:]', $post_element ); // replace end legacy syntax <!--:--> into [:]
		$post_element = preg_replace( '#<!--:([a-z]{2})-->#', '[:$1]', $post_element ); // replace start legacy syntax <!--:en--> into [:en]
		if ( 3 == strlen( $post_element ) - strrpos( $post_element, "[:]" ) ) {
			// remove last [:] but remember it exists only if string is translated
			$post_element = substr( $post_element, 0, strlen( $post_element ) - 3 );
		}
		return $post_element;
	}

	private function add_elements_to_posts_to_create( $posts_to_create, $post, $element_type ) {
		$elements_by_language = preg_split( '#\[:([a-z]{2})\]#', $post[ $element_type ] );
		array_shift( $elements_by_language );
		preg_match_all( '#\[:([a-z]{2})\]#', $post['post_title'], $matches );
		$languages = $matches['1'];
		foreach ( $languages as $key => $language_code ) {
			$language_code                 = strtolower( $language_code );
			$languages[ $key ] = $language_code;
		}
		foreach ( $elements_by_language as $key => $element ) {
			$posts_to_create[ $languages[ $key ] ][ $element_type ] = $element; // @todo check "PHP Notice:  Undefined offset: 0 ...", I guess $key is 0
			if ( 'post_content' === $element_type && $key === 0 && count( $elements_by_language ) > 2 ) { // if post has <!--more--> tag, add this tag to first language as well
				$posts_to_create[ $languages[ $key ] ][ $element_type ] .= "<!--more-->"; // @todo adds this incorrectly now, to every post which has more than 2 languages
			}
		};

		return $posts_to_create;
	}
}