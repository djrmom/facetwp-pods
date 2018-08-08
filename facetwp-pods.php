<?php
/*
Plugin Name: FacetWP - Pods integration
Description: Pods integration with FacetWP
Version: 1.0
Author: FacetWP, LLC
Author URI: https://facetwp.com/
GitHub URI: facetwp/facetwp-pods
*/

defined( 'ABSPATH' ) or exit;

/**
 * Class FacetWP_Pods_Addon
 *
 * @since 0.1
 */
class FacetWP_Pods_Addon {

	/**
	 * @var array Fields data.
	 *
	 * @since 0.1
	 */
	public $fields = array();

	/**
	 * FacetWP_Pods_Addon constructor.
	 *
	 * @since 0.1
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Handle hooks.
	 *
	 * @since 0.1
	 */
	public function init() {
		add_filter( 'facetwp_facet_sources', array( $this, 'facet_sources' ) );
		add_filter( 'facetwp_indexer_query_args', array( $this, 'lookup_fields' ) );
		add_filter( 'facetwp_indexer_row_data', array( $this, 'get_index_rows' ), 1, 2 );
		add_filter( 'facetwp_excluded_custom_fields', array( $this, 'exclude_internal_fields' ) );
	}

	/**
	 * Add Pods fields to sources list.
	 *
	 * @param $sources Sources list.
	 *
	 * @return array Sources list with Pods fields added.
	 *
	 * @since 0.1
	 */
	public function facet_sources( $sources ) {
		$this->setup_fields();

		$sources['pods'] = array(
			'label'   => 'Pods',
			'choices' => array(),
			'weight'  => 5
		);

		foreach ( $this->fields as $choice_id => $field ) {
			$choice_label = sprintf( '[%1$s] %2$s', $field['pod_label'], $field['label'] );

			$sources['pods']['choices'][ $choice_id ] = $choice_label;
		}

		return $sources;
	}

	/**
	 * Hijack the "facetwp_indexer_query_args" hook to lookup the fields once.
	 *
	 * @param array $args Arguments.
	 *
	 * @return array Arguments.
	 *
	 * @since 0.1
	 */
	public function lookup_fields( $args ) {
		$this->setup_fields();

		return $args;
	}

	/**
	 * Setup all post type Pods fields.
	 *
	 * @return array Pods fields.
	 *
	 * @since 0.1
	 */
	public function setup_fields() {
		if ( ! empty( $this->fields ) ) {
			return $this->fields;
		}

		$fields = array();

		$pods_api = pods_api();

		$params = array(
			'type'       => 'post_type',
			'table_info' => false,
		);

		$post_type_pods = $pods_api->load_pods( $params );

		if ( function_exists( 'UPT' ) ) {
			// Check for user pod.
			$params = array(
				'name'       => 'user',
				'table_info' => false,
			);

			$user_pod = $pods_api->load_pod( $params );

			// If we have a user pod, add it to the list.
			if ( $user_pod ) {
				$post_type_pods[] = $user_pod;
			}
		}

		foreach ( $post_type_pods as $pod ) {
			foreach ( $pod['fields'] as $field ) {
				$field['pod_label'] = $pod['label'];

				// If this is the user pod, set the compatible post type.
				if ( 'user' === $field['pod'] ){
					$field['pod'] = 'upt_user';
				}

				$choice_id = sprintf( 'pods/%1$s/%2$s', $field['pod'], $field['name'] );

				$fields[ $choice_id ] = $field;
			}
		}

		$this->fields = $fields;

		return $fields;
	}

	/**
	 * Handle indexing of pods/{pod}/{field} sources.
	 *
	 * @param array $rows   Rows to index.
	 * @param array $params Index parameters.
	 *
	 * @param array Rows to index.
	 *
	 * @since 0.1
	 */
	public function get_index_rows( $rows, $params ) {
		$defaults = $params['defaults'];
		$facet    = $params['facet'];

		if ( ! isset( $facet['source'] ) || 0 !== strpos( $facet['source'], 'pods/' ) ) {
			return $rows;
		}

		// pods/{pod_name}/{field_name}
		$props = explode( '/', $facet['source'] );

		$pod_name   = $props[1];
		$field_name = $props[2];

		$item_id = $defaults['post_id'];

		$post = get_post( $item_id );

		// Check if this matches the source post type.
		if ( ! $post || $pod_name !== $post->post_type ) {
			return $rows;
		}

		// Check if this is a compatible user pod.
		if ( 'upt_user' === $pod_name ) {
			// Integration is not available.
			if ( ! function_exists( 'UPT' ) ) {
				return $rows;
			}

			// Set the real pod name.
			$pod_name = 'user';

			// Get the real user ID.
			$item_id = UPT()->get_user_id( $item_id );

			if ( ! $item_id ) {
				return $rows;
			}
		}

		$pod = $this->setup_pod( $pod_name, $item_id );

		// Pod not found or item does not exist.
		if ( ! $pod || ! $pod->valid() || ! $pod->exists() ) {
			return $rows;
		}

		$field_data = $pod->fields( $field_name );

		// Field not found.
		if ( ! $field_data ) {
			return $rows;
		}

		$field_params = array(
			'name'    => $field_name,
			'keyed'   => true,
			'output'  => 'names', // In the future, add support for slugs=>names.
		);

		$values = $pod->field( $field_params );

		if ( ! is_array( $values ) ) {
			$values = array( $values );
		}

		$field_type = $field_data['type'];

		$tableless_field_types = PodsForm::tableless_field_types();

		foreach ( $values as $value => $display_value ) {
			$row_value = array(
				'value'         => $value,
				'display_value' => $display_value,
			);

			if ( ! in_array( $field_type, $tableless_field_types, true ) ) {
				// Regular fields don't have a $value, it's just a normal array key (0+).
				$row_value['value'] = $row_value['display_value'];
			} elseif ( 0 === $row_value['value'] ) {
				// Set zero values for tableless fields as no value (null).
				$row_value['value'] = null;
			}

			$row = $this->setup_index_row( $row_value, $field, $pod, $params );

			if ( $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * Setup index row for value.
	 *
	 * @param array  $row_value Row value data.
	 * @param string $field     Field name.
	 * @param Pods   $pod       Pod object.
	 * @param array  $params    Index parameters.
	 *
	 * @return array|false Row data or false if bad value.
	 *
	 * @since 0.1
	 */
	public function setup_index_row( $row_value, $field, $pod, $params ) {
		$row = false;

		if ( ! is_scalar( $row_value['value'] ) || ! is_scalar( $row_value['display_value'] ) ) {
			return $row;
		}

		$row = $params['defaults'];

		// Set some Pods-related stuff so people can filter our rows and do more with them later.
		$row['pods_name']    = $pod->pod;
		$row['pods_type']    = $pod->pod_data['type'];
		$row['pods_item_id'] = $pod->id();

		// Set values.
		$row['facet_value']         = $row_value['value'];
		$row['facet_display_value'] = $row_value['display_value'];

		return $row;
	}

	/**
	 * @param array $excluded_fields Excluded fields.
	 *
	 * @return array Excluded fields.
	 *
	 * @since 0.1
	 */
	public function exclude_internal_fields( $excluded_fields ) {
		/** @var wpdb $wpdb */
		global $wpdb;

		// Exclude post meta keys from _pods_pod, _pods_field, etc.
		$sql = "
			SELECT DISTINCT `pm`.`meta_key`
			FROM `{$wpdb->postmeta}` AS `pm`
			LEFT JOIN `{$wpdb->posts}` AS `p`
				ON `p`.`ID` = `pm`.`post_id`
			WHERE `p`.`post_type` LIKE '_pods_%'
		";

		$internal_fields = $wpdb->get_col( $sql );

		$excluded_fields = array_merge( $excluded_fields, $internal_fields );

		// Exclude post meta keys used for relationship order storage.
		$sql = "
			SELECT DISTINCT `meta_key`
			FROM `{$wpdb->postmeta}`
			WHERE `meta_key` LIKE '_pods_%'
		";

		$internal_fields = $wpdb->get_col( $sql );

		$excluded_fields = array_merge( $excluded_fields, $internal_fields );

		// Exclude post meta keys for our Pods.
		$this->setup_fields();

		$internal_fields = array();

		foreach ( $this->fields as $choice_id => $field ) {
			$internal_fields[] = basename( $choice_id );
		}

		$excluded_fields = array_merge( $excluded_fields, $internal_fields );

		$excluded_fields = array_unique( array_filter( $excluded_fields ) );

		return $excluded_fields;
	}

	/**
	 * Setup Pods object.
	 *
	 * @param string $pod_name Pod name.
	 * @param mixed  $item_id  Item ID.
	 *
	 * @return Pods The pod object.
	 *
	 * @since 0.1
	 */
	protected function setup_pod( $pod_name, $item_id ) {
		/** @var Pods $pod */
		static $pod;

		if ( ! $pod || $pod->pod !== $pod_name ) {
			// Setup Pods object if we need to.
			$pod = pods( $pod_name, $item_id );
		} elseif ( (int) $pod->id !== (int) $item_id && $pod->id !== $item_id ) {
			// Fetch the row if it isn't already the current one
			$pod->fetch( $item_id );
		}

		return $pod;
	}

}

new FacetWP_Pods_Addon();
