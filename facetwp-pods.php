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
    public function lookup_pods_fields( $args ) {
        $this->fields = $this->get_fields();

        return $args;
    }


    /**
     * Get all post type Pods fields.
     *
     * @return array Pods fields.
     */
    public function get_fields() {
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
    public function index_pods_values( $return, $params ) {
        $defaults = $params['defaults'];
        $facet = $params['facet'];

        if ( isset( $facet['source'] ) && 0 === strpos( $facet['source'], 'pods/' ) ) {
            $props = explode( '/', $facet['source'] );
            $pod = $props[1];
            $field_name = $props[2];
            $object_id = $defaults['post_id'];

            // get field value
            $value = pods( $pod, $object_id )->field( array(
                'name' => $field_name
            ) );

            // get field properties
            $field = pods_api()->load_field( array(
                'pod' => $pod,
                'name' => $field_name
            ) );

            // index values
            $this->index_field_value( $value, $field, $defaults );

            return true;
        }

        return $return;
    }


    /**
     * @todo index values depending on the field type
     */
    public function index_field_value( $value, $field, $params ) {
        $value = maybe_unserialize( $value );

        // checkboxes
        if ( 'checkbox' == $field['type'] || 'select' == $field['type'] || 'radio' == $field['type'] ) {
            if ( false !== $value ) {
                foreach ( (array) $value as $val ) {
                    $display_value = isset( $field['choices'][ $val ] ) ?
                        $field['choices'][ $val ] :
                        $val;

                    $params['facet_value'] = $val;
                    $params['facet_display_value'] = $display_value;
                    FWP()->indexer->index_row( $params );
                }
            }
        }

        // relationship
        elseif ( 'relationship' == $field['type'] || 'post_object' == $field['type'] ) {
            if ( false !== $value ) {
                foreach ( (array) $value as $val ) {
                    $params['facet_value'] = $val;
                    $params['facet_display_value'] = get_the_title( $val );
                    FWP()->indexer->index_row( $params );
                }
            }
        }

        // user
        elseif ( 'user' == $field['type'] ) {
            if ( false !== $value )  {
                foreach ( (array) $value as $val ) {
                    $user = get_user_by( 'id', $val );
                    if ( false !== $user ) {
                        $params['facet_value'] = $val;
                        $params['facet_display_value'] = $user->display_name;
                        FWP()->indexer->index_row( $params );
                    }
                }
            }
        }

        // taxonomy
        elseif ( 'taxonomy' == $field['type'] ) {
            if ( ! empty( $value ) ) {
                foreach ( (array) $value as $val ) {
                    global $wpdb;

                    $term_id = (int) $val;
                    $term = $wpdb->get_row( "SELECT name, slug FROM {$wpdb->terms} WHERE term_id = '$term_id' LIMIT 1" );
                    if ( null !== $term ) {
                        $params['facet_value'] = $term->slug;
                        $params['facet_display_value'] = $term->name;
                        $params['term_id'] = $term_id;
                        FWP()->indexer->index_row( $params );
                    }
                }
            }
        }

        // date_picker
        elseif ( 'date_picker' == $field['type'] ) {
            $formatted = $this->format_date( $value );
            $params['facet_value'] = $formatted;
            $params['facet_display_value'] = apply_filters( 'facetwp_acf_display_value', $formatted, $params );
            FWP()->indexer->index_row( $params );
        }

        // true_false
        elseif ( 'true_false' == $field['type'] ) {
            $display_value = ( 0 < (int) $value ) ? __( 'Yes', 'fwp' ) : __( 'No', 'fwp' );
            $params['facet_value'] = $value;
            $params['facet_display_value'] = $display_value;
            FWP()->indexer->index_row( $params );
        }

        // google_map
        elseif ( 'google_map' == $field['type'] ) {
            if ( isset( $value['lat'] ) && isset( $value['lng'] ) ) {
                $params['facet_value'] = $value['lat'];
                $params['facet_display_value'] = $value['lng'];
                FWP()->indexer->index_row( $params );
            }
        }

        // text
        else {
            $params['facet_value'] = $value;
            $params['facet_display_value'] = apply_filters( 'facetwp_acf_display_value', $value, $params );
            FWP()->indexer->index_row( $params );
        }
    }


    /**
     * @todo account for Pods fields as the facet "source_other"
     */
    public function index_source_other( $value, $params ) {
        return $value;
    }
}

new FacetWP_Pods_Addon();
