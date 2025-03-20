<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );

// END ENQUEUE PARENT ACTION


function storefront_child_enqueue_styles() {
    // Get the parent theme version (to prevent cache issues)
    $parent_style = 'storefront-style'; 

    // Enqueue Parent Theme CSS
    wp_enqueue_style($parent_style, get_template_directory_uri() . '/style.css');

    // Enqueue Child Theme SCSS Compiled CSS
    wp_enqueue_style(
        'storefront-child-style', // Handle name
        get_stylesheet_directory_uri() . '/assets/css/style.css', // Path to your compiled CSS
        array($parent_style), // Dependencies (loads after parent theme)
        filemtime(get_stylesheet_directory() . '/assets/css/style.css'), // Prevent caching
        'all' // Media type
    );
}
add_action('wp_enqueue_scripts', 'storefront_child_enqueue_styles');

function remove_storefront_footer_widgets() {
    remove_action( 'storefront_footer', 'storefront_footer_widgets', 10 );
}
add_action( 'wp_head', 'remove_storefront_footer_widgets' );

function custom_storefront_widgets_init() {
        // Register the upper footer widget area
        register_sidebar( array(
            'name'          => __( 'Upper Footer', 'storefront' ),
            'id'            => 'upper-footer',
            'description'   => __( 'Widgets added here will appear in the upper footer.', 'storefront' ),
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ) );
    // First, unregister the existing footer widgets
    for ( $i = 1; $i <= 4; $i++ ) {
        unregister_sidebar( 'footer-' . $i );
    }

    // Register the new 7 footer widget areas
    for ( $i = 1; $i <= 7; $i++ ) {
        register_sidebar( array(
            'name'          => sprintf( __( 'Footer %d', 'storefront' ), $i ),
            'id'            => 'footer-' . $i,
            'description'   => __( 'Add widgets here.', 'storefront' ),
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ) );
    }
}
add_action( 'widgets_init', 'custom_storefront_widgets_init', 11 );





