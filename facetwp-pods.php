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

    public $fields;


    function __construct() {
        add_action( 'init', array( $this, 'init' ) );
    }


    function init() {
        add_filter( 'facetwp_facet_sources', array( $this, 'facet_sources' ) );
        add_filter( 'facetwp_indexer_query_args', array( $this, 'lookup_pods_fields' ) );
        add_filter( 'facetwp_indexer_post_facet', array( $this, 'index_pods_values' ), 1, 2 );
        add_filter( 'facetwp_acf_display_value', array( $this, 'index_source_other' ), 1, 2 );
    }


    /**
     * @todo Populate the facet "Data source" dropdown with Pods fields
     */
    function facet_sources( $sources ) {
        $fields = $this->get_fields();

        $sources['pods'] = array(
            'label' => 'Pods',
            'choices' => array(),
            'weight' => 5
        );

        // This needs to be re-written for Pods
        foreach ( $fields as $field ) {
            $field_id = $field['hierarchy'];
            $field_label = '[' . $field['group_title'] . '] ' . $field['parents'] . $field['label'];
            $sources['pods']['choices'][ "pods/$field_id" ] = $field_label;
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
     * @todo Get all Pods fields
     */
    function get_fields() {

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
