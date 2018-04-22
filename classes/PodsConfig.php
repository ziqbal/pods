<?php
/**
 * Class to handle registration of Pods configs and loading/saving of config files.
 *
 * @package Pods
 */
class PodsConfig {

	/**
	 * @var array List of registered config types.
	 */
	protected $registered_config_types = array(
		'json' => 'json',
		'yml'  => 'yml',
	);

	/**
	 * @var array List of registered config item types.
	 */
	protected $registered_config_item_types = array(
		'pods'      => 'pods',
		'templates' => 'templates',
		'pages'     => 'pages',
		'helpers'   => 'helpers',
	);

	/**
	 * @var array List of registered paths.
	 */
	protected $registered_paths = array();

	/**
	 * @var array List of registered Pods configs.
	 */
	protected $pods = array();

	/**
	 * @var array List of registered Pods Template configs.
	 */
	protected $templates = array();

	/**
	 * @var array List of registered Pods Page configs.
	 */
	protected $pages = array();

	/**
	 * @var array List of registered Pods Helper configs.
	 */
	protected $helpers = array();

	/**
	 * @var array Associative array list of other registered configs.
	 */
	protected $custom_configs = array();

	/**
	 * @var array List of config names for each file path.
	 */
	protected $file_path_configs = array();

	/**
	 * PodsConfig constructor.
	 */
	public function __construct() {
		// Nothing to see here.
	}

	/**
	 * Setup initial registered paths and load configs.
	 */
	public function setup() {

		// Register theme.
		$this->register_path( get_template_directory() );

		if ( get_template_directory() !== get_stylesheet_directory() ) {
			// Register child theme.
			$this->register_path( get_stylesheet_directory() );
		}

		$this->load_configs();

	}

	/**
	 * Register a config type.
	 *
	 * @param string $config_type Config type.
	 */
	public function register_config_type( string $config_type ) {

		$config_type = sanitize_title( $config_type );
		$config_type = str_replace( array( '/', DIRECTORY_SEPARATOR ), '-', $config_type );

		$this->registered_config_types[ $config_type ] = $config_type;

	}

	/**
	 * Unregister a config type.
	 *
	 * @param string $config_type Config type.
	 */
	public function unregister_config_type( string $config_type ) {

		$config_type = sanitize_title( $config_type );
		$config_type = str_replace( array( '/', DIRECTORY_SEPARATOR ), '-', $config_type );

		if ( isset( $this->registered_config_types[ $config_type ] ) ) {
			unset( $this->registered_config_types[ $config_type ] );
		}

	}

	/**
	 * Register a config item type.
	 *
	 * @param string $item_type Config item type.
	 */
	public function register_config_item_type( string $item_type ) {

		$item_type = sanitize_title( $item_type );
		$item_type = str_replace( array( '/', DIRECTORY_SEPARATOR ), '-', $item_type );

		$this->registered_config_item_types[ $item_type ] = $item_type;

	}

	/**
	 * Unregister a config item type.
	 *
	 * @param string $item_type Config item type.
	 */
	public function unregister_config_item_type( string $item_type ) {

		$item_type = sanitize_title( $item_type );
		$item_type = str_replace( array( '/', DIRECTORY_SEPARATOR ), '-', $item_type );

		if ( isset( $this->registered_config_item_types[ $item_type ] ) ) {
			unset( $this->registered_config_item_types[ $item_type ] );
		}

	}

	/**
	 * Register a config file path.
	 *
	 * @param string $path File path.
	 */
	public function register_path( string $path ) {

		$path = trailingslashit( $path );

		if ( 0 !== strpos( $path, ABSPATH ) ) {
			$path = ABSPATH . $path;
		}

		$this->registered_paths[ $path ] = $path;

	}

	/**
	 * Unregister a config file path.
	 *
	 * @param string $path File path.
	 */
	public function unregister_path( string $path ) {

		$path = trailingslashit( $path );

		if ( 0 !== strpos( $path, ABSPATH ) ) {
			$path = ABSPATH . $path;
		}

		if ( isset( $this->registered_paths[ $path ] ) ) {
			unset( $this->registered_paths[ $path ] );
		}

	}

	/**
	 * Get file configs based on registered config types and config item types.
	 *
	 * @return array File configs.
	 */
	public function get_file_configs() {

		$file_configs = array();

		// Flesh out the config types.
		foreach ( $this->registered_config_types as $config_type ) {
			$path = sprintf(
				'pods.%s',
				$config_type
			);

			$file_configs[] = array(
				'type' => $config_type,
				'file' => $path,
			);

			foreach ( $this->registered_config_item_types as $config_item_types ) {
				$path = sprintf(
					'pods%s%s.%s',
					DIRECTORY_SEPARATOR,
					$config_item_types,
					$config_type
				);

				$file_configs[] = array(
					'type' => $config_type,
					'file' => $path,
				);
			}//end foreach
		}//end foreach

		return $file_configs;

	}

	/**
	 * Load configs from registered file paths.
	 */
	public function load_configs() {

		/**
		 * @var $wp_filesystem WP_Filesystem_Base
		 */
		global $wp_filesystem;

		/**
		 * Allow plugins/themes to hook into config loading.
		 *
		 * @param PodsConfig $pods_config Pods config object.
		 *
		 * @since 2.7.2
		 */
		do_action( 'pods_config_pre_load_configs', $this );

		$file_configs = $this->get_file_configs();

		foreach ( $this->registered_paths as $config_path ) {
			foreach ( $file_configs as $file_config ) {
				$file_path = $config_path . $file_config['file'];

				if ( ! $wp_filesystem->exists( $file_path ) || ! $wp_filesystem->is_readable( $file_path ) ) {
					continue;
				}

				$raw_config = $wp_filesystem->get_contents( $file_path );

				if ( empty( $raw_config ) ) {
					continue;
				}

				$this->load_config( $file_config['type'], $raw_config, $file_path );
			}//end foreach
		}//end foreach

	}

	/**
	 * Load config from registered file path.
	 *
	 * @param string $config_type Config type.
	 * @param string $raw_config  Raw config content.
	 * @param string $file_path   File path.
	 */
	public function load_config( string $config_type, string $raw_config, string $file_path ) {

		$config = null;

		if ( 'yml' === $config_type ) {
			require_once PODS_DIR . 'vendor/mustangostang/spyc/spyc.php';

			$config = Spyc::YAMLLoadString( $raw_config );
		} elseif ( 'json' === $config_type ) {
			$config = json_decode( $raw_config, true );
		} else {
			/**
			 * Parse Pods config from a custom config type.
			 *
			 * @param array  $config      Config data.
			 * @param string $raw_config  Raw config content.
			 * @param string $config_type Config type.
			 *
			 * @since 2.7.2
			 */
			$config = apply_filters( 'pods_config_parse', [], $raw_config, $config_type );
		}

		if ( $config && is_array( $config ) ) {
			$this->register_config( $config, $file_path );
		}

	}

	/**
	 * Register config for different item types.
	 *
	 * @param array  $config    Config data.
	 * @param string $file_path Config file path.
	 */
	public function register_config( array $config, string $file_path = '' ) {

		if ( ! isset( $this->file_path_configs[ $file_path ] ) ) {
			$this->file_path_configs[ $file_path ] = array();
		}

		foreach ( $config as $item_type => $items ) {
			if ( empty( $items ) || ! is_array( $items ) ) {
				continue;
			}

			if ( ! isset( $this->file_path_configs[ $file_path ][ $item_type ] ) ) {
				$this->file_path_configs[ $file_path ][ $item_type ] = array();
			}

			if ( 'pods' === $item_type ) {
				$this->register_config_pods( $items, $file_path );
			} elseif ( 'templates' === $item_type ) {
				$this->register_config_templates( $items, $file_path );
			} elseif ( 'pages' === $item_type ) {
				$this->register_config_pages( $items, $file_path );
			} elseif ( 'helpers' === $item_type ) {
				$this->register_config_helpers( $items, $file_path );
			} else {
				$this->register_config_custom_item_type( $item_type, $items, $file_path );
			}
		}//end foreach

	}

	/**
	 * Register pod configs.
	 *
	 * @param array  $items     Config items.
	 * @param string $file_path Config file path.
	 */
	public function register_config_pods( array $items, string $file_path = '' ) {

		foreach ( $items as $item ) {
			// Check if the item type and name exists.
			if ( empty( $item['type'] ) || empty( $item['name'] ) ) {
				continue;
			}

			if ( ! isset( $this->pods[ $item['type'] ] ) ) {
				$this->pods[ $item['type'] ] = array();
			}

			if ( isset( $item['id'] ) ) {
				unset( $item['id'] );
			}

			$this->pods[ $item['type'] ][ $item['name'] ] = $item;

			$this->file_path_configs[ $file_path ]['pods'] = $item['type'] . ':' . $item['name'];
		}//end foreach

	}

	/**
	 * Register template configs.
	 *
	 * @param array  $items     Config items.
	 * @param string $file_path Config file path.
	 */
	public function register_config_templates( array $items, string $file_path = '' ) {

		foreach ( $items as $item ) {
			// Check if the item name exists.
			if ( empty( $item['name'] ) ) {
				continue;
			}

			if ( isset( $item['id'] ) ) {
				unset( $item['id'] );
			}

			$this->templates[ $item['name'] ] = $item;

			$this->file_path_configs[ $file_path ]['templates'] = $item['name'];
		}//end foreach

	}

	/**
	 * Register page configs.
	 *
	 * @param array  $items     Config items.
	 * @param string $file_path Config file path.
	 */
	public function register_config_pages( array $items, string $file_path = '' ) {

		foreach ( $items as $item ) {
			// Check if the item name exists.
			if ( empty( $item['name'] ) ) {
				continue;
			}

			if ( isset( $item['id'] ) ) {
				unset( $item['id'] );
			}

			$this->pages[ $item['name'] ] = $item;

			$this->file_path_configs[ $file_path ]['pages'] = $item['name'];
		}//end foreach

	}

	/**
	 * Register helper configs.
	 *
	 * @param array  $items     Config items.
	 * @param string $file_path Config file path.
	 */
	public function register_config_helpers( array $items, string $file_path = '' ) {

		foreach ( $items as $item ) {
			// Check if the item name exists.
			if ( empty( $item['name'] ) ) {
				continue;
			}

			if ( isset( $item['id'] ) ) {
				unset( $item['id'] );
			}

			$this->helpers[ $item['name'] ] = $item;

			$this->file_path_configs[ $file_path ]['helpers'] = $item['name'];
		}//end foreach

	}

	/**
	 * Register config items for custom config item type.
	 *
	 * @param string $item_type Config Item type.
	 * @param array  $items     Config items.
	 * @param string $file_path Config file path.
	 */
	public function register_config_custom_item_type( string $item_type, array $items, string $file_path = '' ) {

		if ( ! isset( $this->custom_configs[ $item_type ] ) ) {
			$this->custom_configs[ $item_type ] = array();
		}

		foreach ( $items as $item ) {
			/**
			 * Pre-process the item to be saved for a custom item type.
			 *
			 * @param array  $item      Item to pre-process.
			 * @param string $item_type Item type.
			 * @param string $file_path File path of config.
			 *
			 * @since 2.7.2
			 */
			$item = apply_filters( 'pods_config_register_custom_item', $item, $item_type, $file_path );

			// Check if the item name exists.
			if ( empty( $item['name'] ) ) {
				continue;
			}

			if ( isset( $item['id'] ) ) {
				unset( $item['id'] );
			}

			$this->custom_configs[ $item_type ][ $item['name'] ] = $item;

			$this->file_path_configs[ $file_path ][ $item_type ] = $item['name'];
		}//end foreach

	}

	/**
	 * @todo Get list of configs that do not match DB.
	 * @todo Handle syncing changed configs to DB.
	 * @todo Handle syncing configs from DB to file.
	 */

}
