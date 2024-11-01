<?php
/**
 * Rapid_Addon
 *
 * @package     WP All Import Rapid_Addon
 * @copyright   Copyright (c) 2014, Soflyy
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @version     1.1.1
 */

if ( ! class_exists( 'Rapid_Addon' ) ) {
	/**
	 * This is WP All Import Rapid addon class.
	 */
	class Rapid_Addon {

		public $name;
		public $slug;
		public $fields;
		public $options        = array();
		public $accordions     = array();
		public $image_sections = array();
		public $import_function;
		public $post_saved_function;
		public $notice_text;
		public $logger        = null;
		public $when_to_run   = false;
		public $image_options = array(
			'download_images'                    => 'yes',
			'download_featured_delim'            => ',',
			'download_featured_image'            => '',
			'gallery_featured_image'             => '',
			'gallery_featured_delim'             => ',',
			'featured_image'                     => '',
			'featured_delim'                     => ',',
			'search_existing_images'             => 1,
			'is_featured'                        => 0,
			'create_draft'                       => 'no',
			'set_image_meta_title'               => 0,
			'image_meta_title_delim'             => ',',
			'image_meta_title'                   => '',
			'set_image_meta_caption'             => 0,
			'image_meta_caption_delim'           => ',',
			'image_meta_caption'                 => '',
			'set_image_meta_alt'                 => 0,
			'image_meta_alt_delim'               => ',',
			'image_meta_alt'                     => '',
			'set_image_meta_description'         => 0,
			'image_meta_description_delim'       => ',',
			'image_meta_description_delim_logic' => 'separate',
			'image_meta_description'             => '',
			'auto_rename_images'                 => 0,
			'auto_rename_images_suffix'          => '',
			'auto_set_extension'                 => 0,
			'new_extension'                      => '',
			'do_not_remove_images'               => 1,
			'search_existing_images_logic'       => 'by_url',
		);

		protected $isWizard = true;

		public function __construct( $name, $slug ) {
			$this->name = $name;
			$this->slug = $slug;
			if ( ! empty( $_GET['id'] ) ) {
				$this->isWizard = false;
			}
		}

		public function set_import_function( $name ) {
			$this->import_function = $name;
		}

		public function set_post_saved_function( $name ) {
			$this->post_saved_function = $name;
		}

		public function is_active_addon( $post_type = null ) {
			if ( ! is_plugin_active( 'wp-all-import-pro/wp-all-import-pro.php' ) and ! is_plugin_active( 'wp-all-import/plugin.php' ) ) {
				return false;
			}

			$addon_active = false;

			if ( $post_type !== null ) {
				if ( @in_array( $post_type, $this->active_post_types ) or empty( $this->active_post_types ) ) {
					$addon_active = true;
				}
			}

			if ( $addon_active ) {
				$current_theme = wp_get_theme();

				$parent_theme = $current_theme->parent();

				$theme_name = $current_theme->get( 'Name' );

				$addon_active = ( @in_array( $theme_name, $this->active_themes ) or empty( $this->active_themes ) ) ? true : false;

				if ( ! $addon_active and $parent_theme ) {
					$parent_theme_name = $parent_theme->get( 'Name' );
					$addon_active      = ( @in_array( $parent_theme_name, $this->active_themes ) or empty( $this->active_themes ) ) ? true : false;
				}

				if ( $addon_active and ! empty( $this->active_plugins ) ) {
					include_once ABSPATH . 'wp-admin/includes/plugin.php';

					foreach ( $this->active_plugins as $plugin ) {
						if ( ! is_plugin_active( $plugin ) ) {
							$addon_active = false;
							break;
						}
					}
				}
			}

			if ( $this->when_to_run == 'always' ) {
				$addon_active = true;
			}

			return apply_filters( 'rapid_is_active_add_on', $addon_active, $post_type, $this->slug );
		}

		/**
		 *
		 * Add-On Initialization
		 *
		 * @param array $conditions - list of supported themes and post types
		 */
		public function run( $conditions = array() ) {
			if ( empty( $conditions ) ) {
				$this->when_to_run = 'always';
			}

			@$this->active_post_types = ( ! empty( $conditions['post_types'] ) ) ? $conditions['post_types'] : array();
			@$this->active_themes     = ( ! empty( $conditions['themes'] ) ) ? $conditions['themes'] : array();
			@$this->active_plugins    = ( ! empty( $conditions['plugins'] ) ) ? $conditions['plugins'] : array();

			add_filter( 'pmxi_addons', array( $this, 'wpai_api_register' ) );
			add_filter( 'wp_all_import_addon_parse', array( $this, 'wpai_api_parse' ) );
			add_filter( 'wp_all_import_addon_import', array( $this, 'wpai_api_import' ) );
			add_filter( 'wp_all_import_addon_saved_post', array( $this, 'wpai_api_post_saved' ) );
			add_filter( 'pmxi_options_options', array( $this, 'wpai_api_options' ) );
			add_filter( 'wp_all_import_image_sections', array( $this, 'additional_sections' ), 10, 1 );
			add_filter( 'pmxi_custom_types', array( $this, 'filter_post_types' ), 10, 2 );
			add_filter( 'pmxi_post_list_order', array( $this, 'sort_post_types' ), 10, 1 );
			add_filter( 'wp_all_import_post_type_image', array( $this, 'post_type_image' ), 10, 1 );
			add_action( 'pmxi_extend_options_featured', array( $this, 'wpai_api_metabox' ), 10, 2 );
			add_action( 'admin_init', array( $this, 'admin_notice_ignore' ) );
		}

		public function parse( $data ) {
			if ( ! $this->is_active_addon( $data['import']->options['custom_type'] ) ) {
				return false;
			}

			$parsedData = $this->helper_parse( $data, $this->options_array() );
			return $parsedData;
		}


		public function add_field( $field_slug, $field_name, $field_type, $enum_values = null, $tooltip = '', $is_html = true, $default_text = '' ) {
			$field = array(
				'name'          => $field_name,
				'type'          => $field_type,
				'enum_values'   => $enum_values,
				'tooltip'       => $tooltip,
				'is_sub_field'  => false,
				'is_main_field' => false,
				'slug'          => $field_slug,
				'is_html'       => $is_html,
				'default_text'  => $default_text,
			);

			$this->fields[ $field_slug ] = $field;

			if ( ! empty( $enum_values ) ) {
				foreach ( $enum_values as $key => $value ) {
					if ( is_array( $value ) ) {
						if ( $field['type'] == 'accordion' ) {
							$this->fields[ $value['slug'] ]['is_sub_field'] = true;
						} else {
							foreach ( $value as $n => $param ) {
								if ( is_array( $param ) and ! empty( $this->fields[ $param['slug'] ] ) ) {
									$this->fields[ $param['slug'] ]['is_sub_field'] = true;
								}
							}
						}
					}
				}
			}

			return $field;
		}

		public function add_acf_field( $field ) {
			$this->fields[ $field->post_name ] = array(
				'type'      => 'acf',
				'field_obj' => $field,
			);
		}

		private $acfGroups = array();

		public function use_acf_group( $acf_group ) {
			$this->add_text(
				'<div class="postbox acf_postbox default acf_signle_group rad4">
    <h3 class="hndle" style="margin-top:0;"><span>' . $acf_group['title'] . '</span></h3>
	    <div class="inside">'
			);
			$acf_fields = get_posts(
				array(
					'posts_per_page' => -1,
					'post_type'      => 'acf-field',
					'post_parent'    => $acf_group['ID'],
					'post_status'    => 'publish',
					'orderby'        => 'menu_order',
					'order'          => 'ASC',
				)
			);
			if ( ! empty( $acf_fields ) ) {
				foreach ( $acf_fields as $field ) {
					$this->add_acf_field( $field );
				}
			}
			$this->add_text( '</div></div>' );
			$this->acfGroups[] = $acf_group['ID'];
			add_filter( 'wp_all_import_acf_is_show_group', array( $this, 'acf_is_show_group' ), 10, 2 );
		}

		public function acf_is_show_group( $is_show, $acf_group ) {
			return ( in_array( $acf_group['ID'], $this->acfGroups ) ) ? false : true;
		}

		/**
		 *
		 * Add an option to WP All Import options list
		 *
		 * @param string $slug - option name
		 * @param string $default_value - default option value
		 */
		public function add_option( $slug, $default_value = '' ) {
			$this->options[ $slug ] = $default_value;
		}

		public function options_array() {
			$options_list = array();

			if ( ! empty( $this->fields ) ) {
				foreach ( $this->fields as $field_slug => $field_params ) {
					if ( in_array( $field_params['type'], array( 'title', 'plain_text', 'acf' ) ) ) {
						continue;
					}
					$default_value = '';
					if ( ! empty( $field_params['enum_values'] ) ) {
						foreach ( $field_params['enum_values'] as $key => $value ) {
							$default_value = $key;
							break;
						}
					}
					$options_list[ $field_slug ] = $default_value;
				}
			}

			if ( ! empty( $this->options ) ) {
				foreach ( $this->options as $slug => $value ) {
					$options_arr[ $slug ] = $value;
				}
			}

			$options_arr[ $this->slug ] = $options_list;
			$options_arr['rapid_addon'] = plugin_basename( __FILE__ );

			return $options_arr;
		}

		public function wpai_api_options( $all_options ) {
			$all_options = $all_options + $this->options_array();

			return $all_options;
		}


		public function wpai_api_register( $addons ) {
			if ( empty( $addons[ $this->slug ] ) ) {
				$addons[ $this->slug ] = 1;
			}

			return $addons;
		}


		public function wpai_api_parse( $functions ) {
			$functions[ $this->slug ] = array( $this, 'parse' );
			return $functions;
		}

		public function wpai_api_post_saved( $functions ) {
			$functions[ $this->slug ] = array( $this, 'post_saved' );
			return $functions;
		}


		public function wpai_api_import( $functions ) {
			$functions[ $this->slug ] = array( $this, 'import' );
			return $functions;
		}

		public function post_saved( $importData ) {
			if ( is_callable( $this->post_saved_function ) ) {
				call_user_func( $this->post_saved_function, $importData['pid'], $importData['import'], $importData['logger'] );
			}
		}

		public function import( $importData, $parsedData ) {
			if ( ! $this->is_active_addon( $importData['post_type'] ) ) {
				return;
			}

			$import_options = $importData['import']['options'][ $this->slug ];

			if ( ! empty( $parsedData ) ) {
				$this->logger = $importData['logger'];

				$post_id = $importData['pid'];
				$index   = $importData['i'];
				$data    = array();
				if ( ! empty( $this->fields ) ) {
					foreach ( $this->fields as $field_slug => $field_params ) {
						if ( in_array( $field_params['type'], array( 'title', 'plain_text' ) ) ) {
							continue;
						}
						switch ( $field_params['type'] ) {
							case 'image':
								// import the specified image, then set the value of the field to the image ID in the media library

								$image_url_or_path = $parsedData[ $field_slug ][ $index ];

								$download = $import_options['download_image'][ $field_slug ];

								$uploaded_image = PMXI_API::upload_image( $post_id, $image_url_or_path, $download, $importData['logger'], true, '', 'images', true, $importData['articleData'] );

								$data[ $field_slug ] = array(
									'attachment_id'     => $uploaded_image,
									'image_url_or_path' => $image_url_or_path,
									'download'          => $download,
								);

								break;

							case 'file':
								$image_url_or_path = $parsedData[ $field_slug ][ $index ];

								$download = $import_options['download_image'][ $field_slug ];

								$uploaded_file = PMXI_API::upload_image( $post_id, $image_url_or_path, $download, $importData['logger'], true, '', 'files', true, $importData['articleData'] );

								$data[ $field_slug ] = array(
									'attachment_id'     => $uploaded_file,
									'image_url_or_path' => $image_url_or_path,
									'download'          => $download,
								);

								break;

							default:
								// set the field data to the value of the field after it's been parsed
								$data[ $field_slug ] = $parsedData[ $field_slug ][ $index ];
								break;
						}

						// apply mapping rules if they exist
						if ( ! empty( $import_options['mapping'][ $field_slug ] ) ) {
							$mapping_rules = json_decode( $import_options['mapping'][ $field_slug ], true );

							if ( ! empty( $mapping_rules ) and is_array( $mapping_rules ) ) {
								foreach ( $mapping_rules as $rule_number => $map_to ) {
									if ( isset( $map_to[ trim( $data[ $field_slug ] ) ] ) ) {
										$data[ $field_slug ] = trim( $map_to[ trim( $data[ $field_slug ] ) ] );
										break;
									}
								}
							}
						}
						// --------------------
					}
				}

				call_user_func( $this->import_function, $post_id, $data, $importData['import'], $importData['articleData'], $importData['logger'] );
			}
		}

		/**
		 * Displays add on template. Modified to include template.
		 *
		 * @param  String $post_type     Post type for import.
		 * @param  Array  $current_values Current values set.
		 * @return void
		 */
		public function wpai_api_metabox( $post_type, $current_values ) {
			if ( ! $this->is_active_addon( $post_type ) ) {
				return;
			}

			echo $this->helper_metabox_top( $this->name );

			echo $this->display_duration_fields( $current_values );

			echo $this->display_recurrance_fields( $current_values );

			echo $this->display_location_fields( $current_values );

			echo $this->helper_metabox_bottom();

			$this->include_script();
		}

		private function include_script() {
			$url = SC_PLUGIN_URL . 'includes/admin/assets/';

			// Meta-box
			wp_register_style( 'sugar_calendar_admin_meta_box', SC_EVENT_IMPORT_PLUGIN_URL . 'assets/css/sc-addon.css' );
			wp_enqueue_style( 'sugar_calendar_admin_meta_box' );

			wp_register_script( 'sc_import_addon_js', SC_EVENT_IMPORT_PLUGIN_URL . 'assets/js/sc-addon.js', array( 'jquery' ) );

			wp_enqueue_script( 'sc_import_addon_js' );
		}

		private function display_duration_fields( $current_values ) {
			ob_start(); ?>
			<div id="duration" class="section-content" style="display: block;">
				<?php

				$duration_fields = array(
					'start_date' => $this->fields['start_date'],
					'end_date'   => $this->fields['end_date'],
					'all_day'    => $this->fields['all_day'],
				);

				foreach ( $duration_fields as $field_slug => $field_params ) {
					$this->render_field( $field_params, $field_slug, $current_values, false );
				}
				?>
			 </div>
			<?php

			$duration_content = ob_get_contents();
			ob_end_clean();
			return $duration_content;
		}

		private function display_recurrance_fields( $current_values ) {
			ob_start();
			?>
			<div id="recurrence" class="section-content" style="display: none;">
				<?php

				$recurrance_fields = array(
					'recurrence'     => $this->fields['recurrence'],
					'recurrence_end' => $this->fields['recurrence_end'],
				);

				foreach ( $recurrance_fields as $field_slug => $field_params ) {
					$this->render_field( $field_params, $field_slug, $current_values, false );
				}
				?>
			 </div>
			<?php

			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}

		private function display_location_fields( $current_values ) {
			ob_start();
			?>
			<div id="location" class="section-content" style="display: none;">
				<?php

				$location_fields = array( 'event_location' => $this->fields['event_location'] );

				foreach ( $location_fields as $field_slug => $field_params ) {
					$this->render_field( $field_params, $field_slug, $current_values, false );
				}
				?>
			 </div>
			<?php

			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}

		public function render_field( $field_params, $field_slug, $current_values, $in_the_bottom = false ) {
			if ( ! isset( $current_values[ $this->slug ][ $field_slug ] ) ) {
				$current_values[ $this->slug ][ $field_slug ] = isset( $field_params['default_text'] ) ? $field_params['default_text'] : '';
			}

			if ( $field_params['type'] == 'text' ) {
				PMXI_API::add_field(
					'simple',
					$field_params['name'],
					array(
						'tooltip'     => $field_params['tooltip'],
						'field_name'  => $this->slug . '[' . $field_slug . ']',
						'field_value' => ( $current_values[ $this->slug ][ $field_slug ] == '' && $this->isWizard ) ? $field_params['default_text'] : $current_values[ $this->slug ][ $field_slug ],
					)
				);
			} elseif ( $field_params['type'] == 'textarea' ) {
				PMXI_API::add_field(
					'textarea',
					$field_params['name'],
					array(
						'tooltip'     => $field_params['tooltip'],
						'field_name'  => $this->slug . '[' . $field_slug . ']',
						'field_value' => ( $current_values[ $this->slug ][ $field_slug ] == '' && $this->isWizard ) ? $field_params['default_text'] : $current_values[ $this->slug ][ $field_slug ],
					)
				);
			} elseif ( $field_params['type'] == 'wp_editor' ) {
				PMXI_API::add_field(
					'wp_editor',
					$field_params['name'],
					array(
						'tooltip'     => $field_params['tooltip'],
						'field_name'  => $this->slug . '[' . $field_slug . ']',
						'field_value' => ( $current_values[ $this->slug ][ $field_slug ] == '' && $this->isWizard ) ? $field_params['default_text'] : $current_values[ $this->slug ][ $field_slug ],
					)
				);
			} elseif ( $field_params['type'] == 'image' or $field_params['type'] == 'file' ) {
				if ( ! isset( $current_values[ $this->slug ]['download_image'][ $field_slug ] ) ) {
					$current_values[ $this->slug ]['download_image'][ $field_slug ] = '';
				}

				PMXI_API::add_field(
					$field_params['type'],
					$field_params['name'],
					array(
						'tooltip'        => $field_params['tooltip'],
						'field_name'     => $this->slug . '[' . $field_slug . ']',
						'field_value'    => $current_values[ $this->slug ][ $field_slug ],
						'download_image' => $current_values[ $this->slug ]['download_image'][ $field_slug ],
						'field_key'      => $field_slug,
						'addon_prefix'   => $this->slug,

					)
				);
			} elseif ( $field_params['type'] == 'radio' ) {
				if ( ! isset( $current_values[ $this->slug ]['mapping'][ $field_slug ] ) ) {
					$current_values[ $this->slug ]['mapping'][ $field_slug ] = array();
				}
				if ( ! isset( $current_values[ $this->slug ]['xpaths'][ $field_slug ] ) ) {
					$current_values[ $this->slug ]['xpaths'][ $field_slug ] = '';
				}

				PMXI_API::add_field(
					'enum',
					$field_params['name'],
					array(
						'tooltip'       => $field_params['tooltip'],
						'field_name'    => $this->slug . '[' . $field_slug . ']',
						'field_value'   => $current_values[ $this->slug ][ $field_slug ],
						'enum_values'   => $field_params['enum_values'],
						'mapping'       => true,
						'field_key'     => $field_slug,
						'mapping_rules' => $current_values[ $this->slug ]['mapping'][ $field_slug ],
						'xpath'         => $current_values[ $this->slug ]['xpaths'][ $field_slug ],
						'addon_prefix'  => $this->slug,
						'sub_fields'    => $this->get_sub_fields( $field_params, $field_slug, $current_values ),
					)
				);
			} elseif ( $field_params['type'] == 'accordion' ) {
				PMXI_API::add_field(
					'accordion',
					$field_params['name'],
					array(
						'tooltip'       => $field_params['tooltip'],
						'field_name'    => $this->slug . '[' . $field_slug . ']',
						'field_key'     => $field_slug,
						'addon_prefix'  => $this->slug,
						'sub_fields'    => $this->get_sub_fields( $field_params, $field_slug, $current_values ),
						'in_the_bottom' => $in_the_bottom,
					)
				);
			} elseif ( $field_params['type'] == 'acf' ) {
				$fieldData          = ( ! empty( $field_params['field_obj']->post_content ) ) ? unserialize( $field_params['field_obj']->post_content ) : array();
				$fieldData['ID']    = $field_params['field_obj']->ID;
				$fieldData['id']    = $field_params['field_obj']->ID;
				$fieldData['label'] = $field_params['field_obj']->post_title;
				$fieldData['key']   = $field_params['field_obj']->post_name;
				if ( empty( $fieldData['name'] ) ) {
					$fieldData['name'] = $field_params['field_obj']->post_excerpt;
				}
				if ( function_exists( 'pmai_render_field' ) ) {
					echo pmai_render_field( $fieldData, ( ! empty( $current_values ) ) ? $current_values : array() );
				}
			} elseif ( $field_params['type'] == 'title' ) {
				?>
				<h4 class="wpallimport-add-on-options-title"><?php _e( $field_params['name'], 'wp_all_import_plugin' ); ?>
																   <?php
																	if ( ! empty( $field_params['tooltip'] ) ) :
																		?>
					<a href="#help" class="wpallimport-help" title="<?php echo $field_params['tooltip']; ?>" style="position:relative; top: -1px;">?</a>
																			<?php
																	endif;
																	?>
				</h4>               
					<?php
			} elseif ( $field_params['type'] == 'plain_text' ) {
				if ( $field_params['is_html'] ) :
					echo $field_params['name']; else :
						?>
					<p style="margin: 0 0 12px 0;"><?php echo $field_params['name']; ?></p>
							<?php
				endif;
			}
		}
		/**
		 *
		 * Helper function for nested radio fields
		 */
		public function get_sub_fields( $field_params, $field_slug, $current_values ) {
			$sub_fields = array();
			if ( ! empty( $field_params['enum_values'] ) ) {
				foreach ( $field_params['enum_values'] as $key => $value ) {
					$sub_fields[ $key ] = array();
					if ( is_array( $value ) ) {
						if ( $field_params['type'] == 'accordion' ) {
							$sub_fields[ $key ][] = $this->convert_field( $value, $current_values );
						} else {
							foreach ( $value as $k => $sub_field ) {
								if ( is_array( $sub_field ) and ! empty( $this->fields[ $sub_field['slug'] ] ) ) {
									$sub_fields[ $key ][] = $this->convert_field( $sub_field, $current_values );
								}
							}
						}
					}
				}
			}
			return $sub_fields;
		}

		public function convert_field( $sub_field, $current_values ) {
			$field = array();
			if ( ! isset( $current_values[ $this->slug ][ $sub_field['slug'] ] ) ) {
				$current_values[ $this->slug ][ $sub_field['slug'] ] = isset( $sub_field['default_text'] ) ? $sub_field['default_text'] : '';
			}
			switch ( $this->fields[ $sub_field['slug'] ]['type'] ) {
				case 'text':
					$field = array(
						'type'   => 'simple',
						'label'  => $this->fields[ $sub_field['slug'] ]['name'],
						'params' => array(
							'tooltip'       => $this->fields[ $sub_field['slug'] ]['tooltip'],
							'field_name'    => $this->slug . '[' . $sub_field['slug'] . ']',
							'field_value'   => ( $current_values[ $this->slug ][ $sub_field['slug'] ] == '' && $this->isWizard ) ? $sub_field['default_text'] : $current_values[ $this->slug ][ $sub_field['slug'] ],
							'is_main_field' => $sub_field['is_main_field'],
						),
					);
					break;
				case 'textarea':
					$field = array(
						'type'   => 'textarea',
						'label'  => $this->fields[ $sub_field['slug'] ]['name'],
						'params' => array(
							'tooltip'       => $this->fields[ $sub_field['slug'] ]['tooltip'],
							'field_name'    => $this->slug . '[' . $sub_field['slug'] . ']',
							'field_value'   => ( $current_values[ $this->slug ][ $sub_field['slug'] ] == '' && $this->isWizard ) ? $sub_field['default_text'] : $current_values[ $this->slug ][ $sub_field['slug'] ],
							'is_main_field' => $sub_field['is_main_field'],
						),
					);
					break;
				case 'wp_editor':
					$field = array(
						'type'   => 'wp_editor',
						'label'  => $this->fields[ $sub_field['slug'] ]['name'],
						'params' => array(
							'tooltip'       => $this->fields[ $sub_field['slug'] ]['tooltip'],
							'field_name'    => $this->slug . '[' . $sub_field['slug'] . ']',
							'field_value'   => ( $current_values[ $this->slug ][ $sub_field['slug'] ] == '' && $this->isWizard ) ? $sub_field['default_text'] : $current_values[ $this->slug ][ $sub_field['slug'] ],
							'is_main_field' => $sub_field['is_main_field'],
						),
					);
					break;
				case 'image':
					$field = array(
						'type'   => 'image',
						'label'  => $this->fields[ $sub_field['slug'] ]['name'],
						'params' => array(
							'tooltip'        => $this->fields[ $sub_field['slug'] ]['tooltip'],
							'field_name'     => $this->slug . '[' . $sub_field['slug'] . ']',
							'field_value'    => $current_values[ $this->slug ][ $sub_field['slug'] ],
							'download_image' => null,
							'field_key'      => $sub_field['slug'],
							'addon_prefix'   => $this->slug,
							'is_main_field'  => $sub_field['is_main_field'],
						),
					);

					if ( array_key_exists( 'download_image', $current_values[ $this->slug ] ) ) {
						$field['params']['download_image'] = $current_values[ $this->slug ]['download_image'][ $sub_field['slug'] ];
					}
					break;
				case 'file':
					$field = array(
						'type'   => 'file',
						'label'  => $this->fields[ $sub_field['slug'] ]['name'],
						'params' => array(
							'tooltip'        => $this->fields[ $sub_field['slug'] ]['tooltip'],
							'field_name'     => $this->slug . '[' . $sub_field['slug'] . ']',
							'field_value'    => $current_values[ $this->slug ][ $sub_field['slug'] ],
							'download_image' => null,
							'field_key'      => $sub_field['slug'],
							'addon_prefix'   => $this->slug,
							'is_main_field'  => $sub_field['is_main_field'],
						),
					);

					if ( array_key_exists( 'download_image', $current_values[ $this->slug ] ) ) {
						$field['params']['download_image'] = $current_values[ $this->slug ]['download_image'][ $sub_field['slug'] ];
					}

					break;
				case 'radio':
					$field = array(
						'type'   => 'enum',
						'label'  => $this->fields[ $sub_field['slug'] ]['name'],
						'params' => array(
							'tooltip'       => $this->fields[ $sub_field['slug'] ]['tooltip'],
							'field_name'    => $this->slug . '[' . $sub_field['slug'] . ']',
							'field_value'   => $current_values[ $this->slug ][ $sub_field['slug'] ],
							'enum_values'   => $this->fields[ $sub_field['slug'] ]['enum_values'],
							'mapping'       => true,
							'field_key'     => $sub_field['slug'],
							'mapping_rules' => isset( $current_values[ $this->slug ]['mapping'][ $sub_field['slug'] ] ) ? $current_values[ $this->slug ]['mapping'][ $sub_field['slug'] ] : array(),
							'xpath'         => isset( $current_values[ $this->slug ]['xpaths'][ $sub_field['slug'] ] ) ? $current_values[ $this->slug ]['xpaths'][ $sub_field['slug'] ] : '',
							'addon_prefix'  => $this->slug,
							'sub_fields'    => $this->get_sub_fields( $this->fields[ $sub_field['slug'] ], $sub_field['slug'], $current_values ),
							'is_main_field' => $sub_field['is_main_field'],
						),
					);
					break;
				case 'accordion':
					$field = array(
						'type'   => 'accordion',
						'label'  => $this->fields[ $sub_field['slug'] ]['name'],
						'params' => array(
							'tooltip'       => $this->fields[ $sub_field['slug'] ]['tooltip'],
							'field_name'    => $this->slug . '[' . $sub_field['slug'] . ']',
							'field_key'     => $sub_field['slug'],
							'addon_prefix'  => $this->slug,
							'sub_fields'    => $this->get_sub_fields( $this->fields[ $sub_field['slug'] ], $sub_field['slug'], $current_values ),
							'in_the_bottom' => false,
						),
					);
					break;
				default:
					// code...
					break;
			}
			return $field;
		}

		/**
		 *
		 * Add accordion options
		 */
		public function add_options( $main_field = false, $title = '', $fields = array() ) {
			if ( ! empty( $fields ) ) {
				if ( $main_field ) {
					$main_field['is_main_field'] = true;
					$fields[]                    = $main_field;
				}

				return $this->add_field( 'accordion_' . $fields[0]['slug'], $title, 'accordion', $fields );
			}
		}

		public function add_title( $title = '', $tooltip = '' ) {
			if ( empty( $title ) ) {
				return;
			}

			return $this->add_field( sanitize_key( $title ) . time(), $title, 'title', null, $tooltip );
		}

		public function add_text( $text = '', $is_html = false ) {
			if ( empty( $text ) ) {
				return;
			}

			$count = is_array( $this->fields ) ? count( $this->fields ) : 0;

			return $this->add_field( sanitize_key( $text ) . time() . uniqid() . $count, $text, 'plain_text', null, '', $is_html );
		}

		public function helper_metabox_top( $name ) {
			ob_start();
			?>
	<div class="wpallimport-collapsed wpallimport-section wpallimport-addon <?php echo $this->slug; ?> closed">
	<div class="wpallimport-content-section">
		<div class="wpallimport-collapsed-header">
			<h3> <?php echo $name; ?></h3>    
		</div>
		<div class="wpallimport-collapsed-content" style="padding: 0;">
			<div class="wpallimport-collapsed-content-inner">
			<div class="inside">
			   <div class="sugar-calendar-wrap">
				  <div class="sc-vertical-sections">
					<ul class="section-nav">
						<li class="section-title" aria-selected="true">
						   <a href="#duration">
						   <i class="dashicons dashicons-clock"></i>
						   <span class="label"><?php esc_attr_e( 'Duration', 'sc-wp-import' ); ?></span>
						   </a>
						</li>
						<li class="section-title" aria-selected="false">
						   <a href="#recurrence">
						   <i class="dashicons dashicons-controls-repeat"></i>
						   <span class="label"><?php esc_attr_e( 'Recurrence', 'sc-wp-import' ); ?></span>
						   </a>
						</li>
						<li class="section-title" aria-selected="false">
						   <a href="#location">
						   <i class="dashicons dashicons-location"></i>
						   <span class="label"><?php esc_attr_e( 'Location', 'sc-wp-import' ); ?></span>
						   </a>
						</li>
					 </ul>

					 <div class="section-wrap">
			<?php

			$header_content = ob_get_contents();
			ob_end_clean();
			return $header_content;
		}

		public function helper_metabox_bottom() {
			ob_start();
			?>
						</div>
					 <br class="clear">
				  </div>
			   </div>
			</div>
			</div>
					</div>
				</div>
			</div>
			<?php
			$bottom_content = ob_get_contents();
			ob_end_clean();
			return $bottom_content;
		}

		/**
		 *
		 * simply add an additional section for attachments
		 */
		public function import_files( $slug, $title ) {
			$this->import_images( $slug, $title, 'files' );
		}

		/**
		 *
		 * simply add an additional section
		 */
		public function import_images( $slug, $title, $type = 'images' ) {
			if ( empty( $title ) or empty( $slug ) ) {
				return;
			}

			$section_slug = 'pmxi_' . $slug;

			$this->image_sections[] = array(
				'title' => $title,
				'slug'  => $section_slug,
				'type'  => $type,
			);

			foreach ( $this->image_options as $option_slug => $value ) {
				$this->add_option( $section_slug . $option_slug, $value );
			}

			if ( count( $this->image_sections ) > 1 ) {
				add_filter( 'wp_all_import_is_show_add_new_images', array( $this, 'filter_is_show_add_new_images' ), 10, 2 );
			}

			add_filter( 'wp_all_import_is_allow_import_images', array( $this, 'is_allow_import_images' ), 10, 2 );

			if ( function_exists( $slug ) ) {
				add_action( $section_slug, $slug, 10, 4 );
			}
		}
		/**
		 *
		 * filter to allow import images for free edition of WP All Import
		 */
		public function is_allow_import_images( $is_allow, $post_type ) {
			return ( $this->is_active_addon( $post_type ) ) ? true : $is_allow;
		}

		/**
		 *
		 * filter to control additional images sections
		 */
		public function additional_sections( $sections ) {
			if ( ! empty( $this->image_sections ) ) {
				foreach ( $this->image_sections as $add_section ) {
					$sections[] = $add_section;
				}
			}

			return $sections;
		}
		/**
		 *
		 * remove the 'Don't touch existing images, append new images' when more than one image section is in use.
		 */
		public function filter_is_show_add_new_images( $is_show, $post_type ) {
			return ( $this->is_active_addon( $post_type ) ) ? false : $is_show;
		}

		/**
		 *
		 * disable the default images section
		 */
		public function disable_default_images( $post_type = false ) {
			add_filter( 'wp_all_import_is_images_section_enabled', array( $this, 'is_enable_default_images_section' ), 10, 2 );
		}
		public function is_enable_default_images_section( $is_enabled, $post_type ) {
			return ( $this->is_active_addon( $post_type ) ) ? false : true;
		}

		public function helper_parse( $parsingData, $options ) {
			extract( $parsingData );

			$data = array(); // parsed data

			if ( ! empty( $import->options[ $this->slug ] ) ) {
				$this->logger = $parsingData['logger'];

				$cxpath = $xpath_prefix . $import->xpath;

				$tmp_files = array();

				foreach ( $options[ $this->slug ] as $option_name => $option_value ) {
					if ( isset( $import->options[ $this->slug ][ $option_name ] ) and $import->options[ $this->slug ][ $option_name ] != '' ) {
						if ( $import->options[ $this->slug ][ $option_name ] == 'xpath' ) {
							if ( $import->options[ $this->slug ]['xpaths'][ $option_name ] == '' ) {
								$count and $data[ $option_name ] = array_fill( 0, $count, '' );
							} else {
								$data[ $option_name ] = XmlImportParser::factory( $xml, $cxpath, (string) $import->options[ $this->slug ]['xpaths'][ $option_name ], $file )->parse();
								$tmp_files[]          = $file;
							}
						} else {
							$data[ $option_name ] = XmlImportParser::factory( $xml, $cxpath, (string) $import->options[ $this->slug ][ $option_name ], $file )->parse();
							$tmp_files[]          = $file;
						}
					} else {
						$data[ $option_name ] = array_fill( 0, $count, '' );
					}
				}

				foreach ( $tmp_files as $file ) { // remove all temporary files created
					unlink( $file );
				}
			}

			return $data;
		}


		public function can_update_meta( $meta_key, $import_options ) {
			$import_options = $import_options['options'];

			if ( $import_options['update_all_data'] == 'yes' ) {
				return true;
			}

			if ( ! $import_options['is_update_custom_fields'] ) {
				return false;
			}

			if ( $import_options['update_custom_fields_logic'] == 'full_update' ) {
				return true;
			}
			if ( $import_options['update_custom_fields_logic'] == 'only' and ! empty( $import_options['custom_fields_list'] ) and is_array( $import_options['custom_fields_list'] ) and in_array( $meta_key, $import_options['custom_fields_list'] ) ) {
				return true;
			}
			if ( $import_options['update_custom_fields_logic'] == 'all_except' and ( empty( $import_options['custom_fields_list'] ) or ! in_array( $meta_key, $import_options['custom_fields_list'] ) ) ) {
				return true;
			}

			return false;
		}

		public function can_update_taxonomy( $tax_name, $import_options ) {
			$import_options = $import_options['options'];

			if ( $import_options['update_all_data'] == 'yes' ) {
				return true;
			}

			if ( ! $import_options['is_update_categories'] ) {
				return false;
			}

			if ( $import_options['update_categories_logic'] == 'full_update' ) {
				return true;
			}
			if ( $import_options['update_categories_logic'] == 'only' and ! empty( $import_options['taxonomies_list'] ) and is_array( $import_options['taxonomies_list'] ) and in_array( $tax_name, $import_options['taxonomies_list'] ) ) {
				return true;
			}
			if ( $import_options['update_categories_logic'] == 'all_except' and ( empty( $import_options['taxonomies_list'] ) or ! in_array( $tax_name, $import_options['taxonomies_list'] ) ) ) {
				return true;
			}

			return false;
		}

		public function can_update_image( $import_options ) {
			$import_options = $import_options['options'];

			if ( $import_options['update_all_data'] == 'yes' ) {
				return true;
			}

			if ( ! $import_options['is_update_images'] ) {
				return false;
			}

			if ( $import_options['is_update_images'] ) {
				return true;
			}

			return false;
		}


		public function admin_notice_ignore() {
			if ( isset( $_GET[ $this->slug . '_ignore' ] ) && '0' == $_GET[ $this->slug . '_ignore' ] ) {
				update_option( $this->slug . '_ignore', 'true' );
			}
		}

		public function display_admin_notice() {
			if ( $this->notice_text ) {
				$notice_text = $this->notice_text;
			} else {
				$notice_text = $this->name . ' requires WP All Import <a href="http://www.wpallimport.com/" target="_blank">Pro</a> or <a href="http://wordpress.org/plugins/wp-all-import" target="_blank">Free</a>.';
			}

			if ( ! get_option( sanitize_key( $this->slug ) . '_notice_ignore' ) ) {
				?>

				<div class="error notice is-dismissible wpallimport-dismissible" style="margin-top: 10px;" rel="<?php echo sanitize_key( $this->slug ); ?>">
					<p>
					<?php
					_e(
						sprintf(
							$notice_text,
							'?' . $this->slug . '_ignore=0'
						),
						'rapid_addon_' . $this->slug
					);
					?>
						</p>
				</div>

				<?php
			}
		}

		/*
		*
		* $conditions - array('themes' => array('Realia'), 'plugins' => array('plugin-directory/plugin-file.php', 'plugin-directory2/plugin-file.php'))
		*
		*/
		public function admin_notice( $notice_text = '', $conditions = array() ) {
			$is_show_notice = false;

			include_once ABSPATH . 'wp-admin/includes/plugin.php';

			if ( ! is_plugin_active( 'wp-all-import-pro/wp-all-import-pro.php' ) and ! is_plugin_active( 'wp-all-import/plugin.php' ) ) {
				$is_show_notice = true;
			}

			// Supported Themes
			if ( ! $is_show_notice and ! empty( $conditions['themes'] ) ) {
				$themeInfo    = wp_get_theme();
				$parentInfo   = $themeInfo->parent();
				$currentTheme = $themeInfo->get( 'Name' );

				$is_show_notice = in_array( $currentTheme, $conditions['themes'] ) ? false : true;

				if ( $is_show_notice and $parentInfo ) {
					$parent_theme   = $parentInfo->get( 'Name' );
					$is_show_notice = in_array( $parent_theme, $conditions['themes'] ) ? false : true;
				}
			}

			// Required Plugins
			if ( ! $is_show_notice and ! empty( $conditions['plugins'] ) ) {
				$requires_counter = 0;
				foreach ( $conditions['plugins'] as $plugin ) {
					if ( is_plugin_active( $plugin ) ) {
						$requires_counter++;
					}
				}

				if ( $requires_counter != count( $conditions['plugins'] ) ) {
					$is_show_notice = true;
				}
			}

			if ( $is_show_notice ) {
				if ( $notice_text != '' ) {
					$this->notice_text = $notice_text;
				}

				add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
			}
		}

		public function log( $m = false ) {
			$m and $this->logger and call_user_func( $this->logger, $m );
		}

		public function remove_post_type( $type = '' ) {
			if ( ! empty( $type ) ) {
				$this->add_option( 'post_types_to_remove', $type );
			}
		}

		public function filter_post_types( $custom_types = array(), $custom_type = '' ) {
			$options    = $this->options_array();
			$option_key = 'post_types_to_remove';

			if ( array_key_exists( $option_key, $options ) ) {
				$type = $options[ $option_key ];

				if ( ! empty( $type ) ) {
					if ( ! is_array( $type ) ) {
						if ( array_key_exists( $type, $custom_types ) ) {
							unset( $custom_types[ $type ] );
						}
					} else {
						foreach ( $type as $key => $post_type ) {
							if ( array_key_exists( $post_type, $custom_types ) ) {
								unset( $custom_types[ $post_type ] );
							}
						}
					}
				}
			}
			return $custom_types;
		}

		public function sort_post_types( array $order ) {
			$options    = $this->options_array();
			$option_key = 'post_type_move';

			if ( array_key_exists( $option_key, $options ) ) {
				$move_rules = maybe_unserialize( $options[ $option_key ] );

				foreach ( $move_rules as $rule ) {
					$move_this = $rule['move_this'];
					$move_to   = $rule['move_to'];
					if ( $move_to > count( $order ) ) {
						if ( ( $rm_key = array_search( $move_this, $order ) ) !== false ) {
							unset( $order[ $rm_key ] );
						}
						array_push( $order, $move_this );
					} else {
						if ( ( $rm_key = array_search( $move_this, $order ) ) !== false ) {
							unset( $order[ $rm_key ] );
						}
						array_splice( $order, $move_to, 0, $move_this );
					}
				}

				return $order;
			}

			return $order;
		}

		public function move_post_type( $move_this = null, $move_to = null ) {
			$move_rules = array();

			if ( ! is_array( $move_this ) && ! is_array( $move_to ) ) {
				$move_rules[] = array(
					'move_this' => $move_this,
					'move_to'   => $move_to,
				);
			} else {
				foreach ( $move_this as $key => $move_post ) {
					$move_rules[] = array(
						'move_this' => $move_post,
						'move_to'   => $move_to[ $key ],
					);
				}
			}

			$this->add_option( 'post_type_move', $move_rules );
		}

		public function set_post_type_image( $post_type = null, $image = null ) {
			$post_type_image_rules = array();

			if ( ! is_array( $post_type ) ) {
				$post_type_image_rules[ $post_type ] = array(
					'post_type' => $post_type,
					'image'     => $image,
				);
			} else {
				if ( count( $post_type ) == count( $image ) ) {
					foreach ( $post_type as $key => $post_name ) {
						$post_type_image_rules[ $post_name ] = array(
							'post_type' => $post_name,
							'image'     => $image[ $key ],
						);
					}
				}
			}

			$this->add_option( 'post_type_image', $post_type_image_rules );
		}

		public function post_type_image( $image ) {
			$options    = $this->options_array();
			$option_key = 'post_type_image';
			if ( array_key_exists( $option_key, $options ) ) {
				$post_type_image_rules = maybe_unserialize( $options[ $option_key ] );
				return $post_type_image_rules;
			}
			return $image;
		}
	}
}
