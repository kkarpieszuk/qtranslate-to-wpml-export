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

		if ( get_post_meta( $post_id, '_qt_imported', true ) ) {
			return;
		}

		$post = get_post( $post_id, ARRAY_A );

		$translatable_tax = $sitepress->get_translatable_taxonomies( true, $post['post_type'] );

		if ( $post ) {

			$post['post_title'] = preg_replace( '#<!--:--><!--:([a-z]{2})-->#', '[:$1]', $post['post_title'] ); // replace middle legacy syntax <!--:--><!--:en--> into [:en]
			$post['post_title'] = str_replace( '<!--:-->', '[:]', $post['post_title'] ); // replace end legacy syntax <!--:--> into [:]
			$post['post_title'] = preg_replace( '#<!--:([a-z]{2})-->#', '[:$1]', $post['post_title'] ); // replace start legacy syntax <!--:en--> into [:en]
			if ( 3 == strlen( $post['post_title'] ) - strrpos( $post['post_title'], "[:]" ) ) {
				// remove last [:] but remember it exists only if string is translated
				$post['post_title'] = substr( $post['post_title'], 0, strlen( $post['post_title'] ) - 3 );
			}
			$exp = preg_split( '#\[:([a-z]{2})\]#', $post['post_title'] );
			array_shift( $exp );
			preg_match_all( '#\[:([a-z]{2})\]#', $post['post_title'], $matches );
			$languages = $matches['1'];
			foreach ( $languages as $key => $l ) {
				$l                 = strtolower( $l );
				$languages[ $key ] = $l;
			}
			foreach ( $exp as $key => $e ) {
				$langs[ $languages[ $key ] ]['title'] = $e;
			};

			$post['post_content'] = preg_replace( '#<!--:--><!--:([a-z]{2})-->#', '[:$1]', $post['post_content'] );
			$post['post_content'] = str_replace( '<!--:-->', '[:]', $post['post_content'] );
			$post['post_content'] = preg_replace( '#<!--:([a-z]{2})-->#', '[:$1]', $post['post_content'] );
			if ( 3 == strlen( $post['post_content'] ) - strrpos( $post['post_content'], "[:]" ) ) {
				// remove last [:] but remember it exists only if string is translated
				$post['post_content'] = substr( $post['post_content'], 0, strlen( $post['post_content'] ) - 3 );
			}
			$exp = preg_split( '#\[:([a-z]{2})\]#', $post['post_content'] );
			array_shift( $exp );
			preg_match_all( '#\[:([a-z]{2})\]#', $post['post_content'], $matches );
			$languages = $matches['1'];
			foreach ( $languages as $key => $l ) {
				$l                 = strtolower( $l );
				$languages[ $key ] = $l;
			}
			foreach ( $exp as $key => $e ) {
				$langs[ $languages[ $key ] ] = ['content' => $e ];
				if ( $key == 0 && count( $exp ) > 2 ) { // if post has <!--more--> tag, add this tag to first language as well
					$langs[ $languages[ $key ] ]['content'] .= "<!--more-->";
				}
			};

			$post['post_excerpt'] = preg_replace( '#<!--:--><!--:([a-z]{2})-->#', '[:$1]', $post['post_excerpt'] );
			$post['post_excerpt'] = str_replace( '<!--:-->', '[:]', $post['post_excerpt'] );
			$post['post_excerpt'] = preg_replace( '#<!--:([a-z]{2})-->#', '[:$1]', $post['post_excerpt'] );
			if ( 3 == strlen( $post['post_excerpt'] ) - strrpos( $post['post_excerpt'], "[:]" ) ) {
				// remove last [:] but remember it exists only if string is translated
				$post['post_excerpt'] = substr( $post['post_excerpt'], 0, strlen( $post['post_excerpt'] ) - 3 );
			}
			$exp = preg_split( '#\[:([a-z]{2})\]#', $post['post_excerpt'] );
			array_shift( $exp );
			preg_match_all( '#\[:([a-z]{2})\]#', $post['post_excerpt'], $matches );
			$languages = $matches['1'];
			foreach ( $languages as $key => $l ) {
				$l                 = strtolower( $l );
				$languages[ $key ] = $l;
			}
			foreach ( $exp as $key => $e ) {
				$langs[ $languages[ $key ] ]['excerpt'] = $e;
			};

			$custom_fields = $this->wpdb->get_results( $this->wpdb->prepare( "SELECT meta_key, meta_value FROM {$this->wpdb->postmeta} WHERE post_id=%d", $post_id ) );
			foreach ( $custom_fields as $cf ) {
				// only handle scalar values
				if ( ! is_serialized( $cf->meta_value ) ) {

					$cf->meta_value = preg_replace( '#<!--:--><!--:([a-z]{2})-->#', '[:$1]', $cf->meta_value );
					$cf->meta_value = str_replace( '<!--:-->', '[:]', $cf->meta_value );
					$cf->meta_value = preg_replace( '#<!--:([a-z]{2})-->#', '[:$1]', $cf->meta_value );
					if ( 3 == strlen( $cf->meta_value ) - strrpos( $cf->meta_value, "[:]" ) ) {
						// remove last [:] but remember it exists only if string is translated
						$cf->meta_value = substr( $cf->meta_value, 0, strlen( $cf->meta_value ) - 3 );
					}
					$exp = preg_split( '#\[:([a-z]{2})\]#', $cf->meta_value );
					array_shift( $exp );
					preg_match_all( '#\[:([a-z]{2})\]#', $cf->meta_value, $matches );
					$languages = $matches['1'];
					foreach ( $languages as $key => $l ) {
						$l                 = strtolower( $l );
						$languages[ $key ] = $l;
					}
					foreach ( $exp as $key => $e ) {
						if ( isset( $matches[2] ) ) {
							$langs[ $lang ]['custom_fields'][ $cf->meta_key ] = $matches[2];
						}
					}
				} else {
					// copying all the other custom fields
					foreach ( $this->qt_active_languages as $lang ) {
						if ( $this->qt_default_language != $lang ) {
							$langs[ $lang ]['custom_fields'][ $cf->meta_key ] = $cf->meta_value;
						}
					}
				}

			}

			//echo $post_id . "------------------------";

			// put the default language in front
			$active_languages = array_merge( array( $this->qt_default_language ), array_diff( $this->qt_active_languages, array( $this->qt_default_language ) ) );


			// handle empty titles
			foreach ( $active_languages as $language ) {
				if ( empty( $langs[ $language ]['title'] ) && ! empty( $langs[ $language ]['content'] ) ) {
					$langs[ $language ]['title'] = $post['post_title'];
				}
			}

			// if the post in the default language does not exist pick a different post as a 'source'
			if ( empty( $langs[ $this->qt_default_language ] ) ) {
				foreach ( $active_languages as $language ) {
					if ( $language != $this->qt_default_language && ! empty( $langs[ $language ]['title'] ) ) {
						$langs[ $language ]['__icl_source'] = true;
						break;
					}
				}
			}

			foreach ( $active_languages as $language ) {

				//echo $language . "------------------------";

				if ( empty( $langs[ $language ]['title'] ) ) {
					continue;
				} // obslt

				$post['post_title']   = $langs[ $language ]['title'];
				$post['post_content'] = isset( $langs[ $language ]['content'] ) ? $langs[ $language ]['content'] : '';
				if ( isset( $langs[ $language ]['excerpt'] ) ) {
					$post['post_excerpt'] = $langs[ $language ]['excerpt'];
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

				if ( $language == $this->qt_default_language || ! empty( $langs[ $language ]['__icl_source'] ) ) {

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

				if ( ! empty( $langs[ $language ]['custom_fields'] ) ) {
					foreach ( $langs[ $language ]['custom_fields'] as $k => $v ) {
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
}