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
    }
}

new FacetWP_Pods_Addon();
