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

    function __construct() {
        add_action( 'init', array( $this, 'init' ) );
    }


    function init() {
        add_filter( 'facetwp_facet_sources', array( $this, 'facet_sources' ) );
        add_filter( 'facetwp_indexer_query_args', array( $this, 'lookup_pods_fields' ) );
        add_filter( 'facetwp_indexer_post_facet', array( $this, 'index_pods_values' ), 1, 2 );
        add_filter( 'facetwp_acf_display_value', array( $this, 'index_source_other' ), 1, 2 );
    }


    function facet_sources() {

    }


    function lookup_pods_fields() {

    }


    function index_pods_values() {

    }


    function index_source_other() {
        
    }
}

new FacetWP_Pods_Addon();
