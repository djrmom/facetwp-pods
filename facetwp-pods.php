<?php
/*
Plugin Name: FacetWP - Pods integration
Description: Pods integration with FacetWP
Version: 0.1
Author: FacetWP, LLC
Author URI: https://facetwp.com/
GitHub URI: facetwp/facetwp-pods
*/

defined( 'ABSPATH' ) or exit;

class FacetWP_Pods_Addon
{
    /**
     * @var array Fields data.
     */
    public $fields = array();


    public function __construct() {
        add_action( 'init', array( $this, 'init' ) );
    }


    public function init() {
        add_filter( 'facetwp_facet_sources', array( $this, 'facet_sources' ) );
        add_filter( 'facetwp_indexer_query_args', array( $this, 'lookup_pods_fields' ) );
        add_filter( 'facetwp_indexer_post_facet', array( $this, 'index_pods_values' ), 1, 2 );
        add_filter( 'facetwp_acf_display_value', array( $this, 'index_source_other' ), 1, 2 );
    }


    /**
     * Add Pods fields to sources list.
     *
     * @param $sources Sources list.
     *
     * @return array Sources list with Pods fields added.
     */
    public function facet_sources( $sources ) {
        if ( empty( $this->fields ) ) {
            $this->fields = $this->get_fields();
        }

        $sources['pods'] = array(
            'label' => 'Pods',
            'choices' => array(),
            'weight' => 5
        );

        foreach ( $this->fields as $choice_id => $field ) {
            $choice_label = sprintf( '[%1$s] %2$s', $field['pod_label'], $field['label'] );

            $sources['pods']['choices'][ $choice_id ] = $choice_label;
        }

        return $sources;
    }


    /**
     * Hijack the "facetwp_indexer_query_args" hook to lookup the fields once
     */
    function lookup_pods_fields( $args ) {
        $this->fields = $this->get_fields();

        return $args;
    }


    /**
     * Get all post type Pods fields.
     *
     * @return array Pods fields.
     */
    function get_fields() {
        $fields = array();

        $params = array(
            'type'       => 'post_type',
            'table_info' => false,
        );

        $post_type_pods = pods_api()->load_pods( $params );

        foreach ( $post_type_pods as $pod ) {
            foreach ( $pod['fields'] as $field ) {
                $field['pod_label'] = $pod['label'];
            
                $choice_id = sprintf( 'pods/%1$s/%2$s', $field['pod'], $field['name'] );
                
                $fields[ $choice_id ] = $field;
            }
        }

        return $fields;
    }


    /**
     * @todo Index Pods data
     */
    function index_pods_values( $return, $params ) {
        return $return;
    }


    /**
     * @todo account for Pods fields as the facet "source_other"
     */
    function index_source_other( $value, $params ) {
        return $value;
    }
}

new FacetWP_Pods_Addon();
