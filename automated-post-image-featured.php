<?php
/**
 * Plugin Name: Wordpress Automated Post Image Featured
 * Plugin URI: https://github.com/alfreddagenais/automated-post-image-featured
 * Description: Checks if you defined the featured image, and if not it sets the featured image to the uploaded images. So easy like that...
 * Author: Alfred Dagenais
 * Version: 1.0.0
 * Author URI: https://www.alfreddagenais.com
 * Requires at least: 4.7
 *
 */

/*
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License version 3 as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if ( function_exists( 'add_theme_support' ) ) {

	add_theme_support( 'post-thumbnails' ); // This should be in your theme. But we add this here because this way we can have featured images before switching to a theme that supports them.

	class APIFPlugin {

		/**
		 * Install function.
		 */
		public static function install() {
			// do not generate any output here

			if (! wp_next_scheduled ( 'apif_cron_daily_event' )) {
				wp_schedule_event( time(), 'daily', 'apif_cron_daily_event');
			}

			$aIncludePostTypes      = array( 'post' );
			$aIncludePostTypes      = apply_filters( 'apif_post_types_include', $aIncludePostTypes );

			// Select Post and associate with thubmnail
			$aQueryArgs = array(

				'post_type'       	=> $aIncludePostTypes,
				'posts_per_page' 	=> -1,
				'meta_query' 		=> array(

					array(
						'key' 		=> '_thumbnail_id',
						'compare' 	=> 'EXISTS'
					)

				)

			);

			// The Query
			$oQuery = new WP_Query( $aQueryArgs );

			// The Loop
			if ( $oQuery->have_posts() ) {
			
				while ( $oQuery->have_posts() ) {
					$oQuery->the_post();

					$nPostID = get_the_ID();
					$bAlreadyHasThumb = has_post_thumbnail();
					if ( $bAlreadyHasThumb ) {
						
						$nPostThumbnailID = get_post_thumbnail_id();

						$nPostThumbnailRelPostID = get_post_meta( $nPostThumbnailID, '_rel_post_id', TRUE );
						if( is_null($nPostThumbnailRelPostID) || empty($nPostThumbnailRelPostID) || !is_numeric($nPostThumbnailRelPostID) ){

							update_post_meta( $nPostThumbnailID, '_rel_post_id', $nPostID );
							$nPostThumbnailMeta = $aPostThumbnailMeta = APIFPlugin::addAttachmentMeta( get_post_meta( $nPostThumbnailID, '_wp_attachment_metadata', TRUE ), $nPostThumbnailID ) ;

						}

					}

				}

			}

			// Restore original Post Data
			wp_reset_postdata();


			// Select thubmnail with no associate with post
			$aQueryArgs = array(

				'post_type'       	=> 'attachment',
				'posts_per_page' 	=> -1,
				'meta_query' 		=> array(

					'relation' => 'OR',

					array(

						'key' 		=> '_rel_post_id',
						'compare' 	=> 'NOT EXISTS'
						
					),
					array(

						'key' 		=> '_rel_post_id',
						'compare' 	=> 'NOT EXISTS',
						'value' 	=> ''
						
					)

				)

			);

			// The Query
			$oQuery = new WP_Query( $aQueryArgs );

			// The Loop
			if ( $oQuery->have_posts() ) {
			
				while ( $oQuery->have_posts() ) {
					$oQuery->the_post();

					$nThumbnailID = get_the_ID();
					$aPostThumbnailMeta = APIFPlugin::addAttachmentMeta( get_post_meta( $nPostThumbnailID, '_wp_attachment_metadata', TRUE ), $nPostThumbnailID );

				}

			}

			// Restore original Post Data
			wp_reset_postdata();

		}

		/**
		 * Uninstall function.
		 */
		public static function uninstall() {
			wp_clear_scheduled_hook('apif_cron_daily_event');
		}

		/**
		 * Main function.
		 *
		 * @param object $post Post Object.
		 */
		public static function addFeaturedImage( $oPost ) {
			
			$bAlreadyHasThumb 		= has_post_thumbnail( $oPost );
			$sPostType         		= get_post_type( $oPost->ID );
			$aExcludePostTypes      = array( '' );
			$aExcludePostTypes      = apply_filters( 'apif_post_types_exclude', $aExcludePostTypes );

			// Do nothing if the post has already a featured image set.
			if ( $bAlreadyHasThumb ) {
				return;
			}

			// Do the job if the post is not from an excluded type.
			if ( ! in_array( $sPostType, $aExcludePostTypes, TRUE ) ) {

				// Select thubmnail with no associate with post
				$aQueryArgs = array(

					'post_type'       	=> 'attachment',
					'posts_per_page' 	=> 1,
					'orderby'        	=> 'rand',
					'meta_query' 		=> array(

						'relation' => 'AND',

						array(

							'relation' => 'OR',
							array(

								'key' 		=> '_rel_post_id',
								'compare' 	=> 'NOT EXISTS'
								
							),
							array(

								'key' 		=> '_rel_post_id',
								'compare' 	=> 'NOT EXISTS',
								'value' 	=> ''
								
							)
							
						),

						array(

							'relation' => 'OR',
							array(

								'key' 		=> 'height',
								'value'   	=> 1200,
								'type'    	=> 'numeric',
								'compare' 	=> '>='

							),
							array(

								'key' 		=> 'width',
								'value'   	=> 1200,
								'type'    	=> 'numeric',
								'compare' 	=> '>='
								
							)
							
						)

					)

				);
				$aQueryArgs = apply_filters( 'apif_addfeaturedimage_queryargs', $aQueryArgs );

				$aAttachments = get_posts( $aQueryArgs );
				if( is_array($aAttachments) && count($aAttachments) > 0 ){

					foreach ( $aAttachments as $nP => $aAttachment ) {
						
						// Add attachment ID.
						update_post_meta( $oPost->ID, '_thumbnail_id', $aAttachment->ID, TRUE );
						update_post_meta( $aAttachment->ID, '_rel_post_id', $oPost->ID, TRUE );

					}

				}

			}

		}

		/**
		 * Add Attachment Meta 
		 *
		 * @param array $aMeta
		 * @param array $aMeta
		 * @return array $aMeta Meta
		 */
		public static function addAttachmentMeta( $aMeta, $nAttachmentID ) {
			
			update_post_meta( $nAttachmentID, 'height', (int) ( isset($aMeta['height']) ? $aMeta['height'] : 0 ));
			update_post_meta( $nAttachmentID, 'width', (int) ( isset($aMeta['width']) ? $aMeta['width'] : 0 ));
			return $aMeta;

		}

		/**
		 * Insert Custom Columns
		 *
		 * @param array $aColumns
		 */
		public static function customColumns( $aColumns ) {

			$aColumns['featured_image'] = 'Image';
			return $aColumns;

		}

		/**
		 * Set Custom Columns Data
		 *
		 * @param string $sColumnName
		 * @param integer $PostID
		 */
		public static function customColumnsData( $sColumnName, $PostID ) {
			
			switch ( $sColumnName ) {
				case 'featured_image':
					the_post_thumbnail( 'thumbnail' );

				break;
			}

		}

		/**
		 * Cron runned daily
		 */
		public static function cronDaily() {

			$aIncludePostTypes      = array( 'post' );
			$aIncludePostTypes      = apply_filters( 'apif_post_types_include', $aIncludePostTypes );

			// Select Post and associate with thubmnail
			$aQueryArgs = array(

				'post_type'       	=> $aIncludePostTypes,
				'posts_per_page' 	=> -1,
				'meta_query' 		=> array(

					'relation' => 'OR',
					array(

						'key' 		=> '_thumbnail_id',
						'compare' 	=> 'NOT EXISTS'
						
					),
					array(

						'key' 		=> '_thumbnail_id',
						'compare' 	=> 'NOT EXISTS',
						'value' 	=> ''
						
					)

				)

			);

			$aPosts = get_posts( $aQueryArgs );
			if( is_array($aPosts) && count($aPosts) > 0 ){

				foreach ( $aPosts as $nP => $oPost ) {
					
					APIFPlugin::addFeaturedImage( $oPost );

				}

			}

		}


	}

	// Add function when install plugin
	register_activation_hook( __FILE__ , array( 'APIFPlugin', 'install' ) );
	register_deactivation_hook( __FILE__ , array( 'APIFPlugin', 'uninstall' ) );
	
	// Set featured image before post is displayed on the site front-end (for old posts published before enabling this plugin).
	//add_action( 'the_post', array( 'APIFPlugin', 'addFeaturedImage' ) );

	// Hooks added to set the thumbnail when publishing too.
	add_action( 'new_to_publish', array( 'APIFPlugin', 'addFeaturedImage' ) );
	add_action( 'draft_to_publish', array( 'APIFPlugin', 'addFeaturedImage' ) );
	add_action( 'pending_to_publish', array( 'APIFPlugin', 'addFeaturedImage' ) );
	add_action( 'future_to_publish', array( 'APIFPlugin', 'addFeaturedImage' ) );

	// Add custom Metadata to attachment
	add_filter( 'wp_generate_attachment_metadata', array( 'APIFPlugin', 'addAttachmentMeta' ), 10, 2);

	// Custom columns on admin post list page
	add_filter( 'manage_posts_columns' , array( 'APIFPlugin', 'customColumns' ));
	add_action( 'manage_posts_custom_column' , array( 'APIFPlugin', 'customColumnsData' ), 10, 2 );

	// Action for Crons
	add_action( 'apif_cron_daily_event', array( 'APIFPlugin', 'cronDaily' ) );

	//if( isset($_REQUEST['apif_cron_daily_event']) ){
	//	APIFPlugin::cronDaily();
	//	die();
	//}

}
