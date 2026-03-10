<?php
/**
 * BWS Taxonomy Term Dynamic Tags for GenerateBlocks
 * Extension to v1.0.0 BWS Dynamic Tags
 * 
 * Provides dynamic tags for taxonomy terms:
 * - Term name, permalink, and description
 * - Term field image fields with URL fallback
 *
 * @package BWS_Dynamic_Tags
 * @version 1.1.0
 * @requires BWS Dynamic Tags v1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prevent duplicate loading
if ( defined( 'BWS_TERM_TAGS_V11_LOADED' ) ) {
    return;
}
define( 'BWS_TERM_TAGS_V11_LOADED', true );

// ===============================================
// TAXONOMY TERM TAG REGISTRATION
// ===============================================

/**
 * Register taxonomy term dynamic tags on init
 * 
 * @since 1.1.0
 * @return void
 */
function bws_register_taxonomy_term_tags() {
    if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
        return;
    }
    
    // Prevent duplicate registration
    static $registered = false;
    if ( $registered ) {
        return;
    }
    $registered = true;

    // Term Name
    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'       => __( 'Term Name', 'generateblocks' ),
            'tag'         => 'term_name',
            'type'        => 'term',
            'supports'    => [ 'source' ],
            'description' => __( 'Get the name of the current or specific taxonomy term.', 'generateblocks' ),
            'return'      => 'bws_get_term_name_callback',
        ]
    );

    // Term Permalink
    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'       => __( 'Term Permalink', 'generateblocks' ),
            'tag'         => 'term_permalink',
            'type'        => 'term',
            'supports'    => [ 'source' ],
            'description' => __( 'Get the permalink of the current or specific taxonomy term.', 'generateblocks' ),
            'return'      => 'bws_get_term_permalink_callback',
        ]
    );

    // Term Description
    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'       => __( 'Term Description', 'generateblocks' ),
            'tag'         => 'term_description',
            'type'        => 'term',
            'supports'    => [ 'source' ],
            'description' => __( 'Get the description of the current or specific taxonomy term.', 'generateblocks' ),
            'return'      => 'bws_get_term_description_callback',
        ]
    );

    // Term Field Image
    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'       => __( 'Term Field Image', 'generateblocks' ),
            'tag'         => 'term_field_image',
            'type'        => 'term',
            'supports'    => [ 'source', 'image-size' ],
            'description' => __( 'Get custom image from taxonomy term fields with URL fallback. Supports ACF image fields and standard term meta.', 'generateblocks' ),
            'options'     => array_merge(
                bws_get_term_image_field_options(),
                bws_get_image_return_type_options()
            ),
            'return'      => 'bws_get_term_field_image_callback',
        ]
    );
}
add_action( 'init', 'bws_register_taxonomy_term_tags' );

// ===============================================
// MEDIA ID OVERRIDE FOR TERM CONTEXT
// ===============================================

/**
 * Override media IDs to provide term context for term image tags
 * 
 * @since 1.1.0
 * @param int $id Original ID
 * @param array $options Tag options
 * @param object $instance Block instance
 * @return int Modified ID
 */
if ( ! function_exists( 'bws_override_media_ids_for_term_context' ) ) {
function bws_override_media_ids_for_term_context( $id, $options, $instance ) {
    $tag_name = $options['tag_name'] ?? '';
    
    // Only handle term field image tags
    if ( 'term_field_image' === $tag_name ) {
        $term_id = bws_reliable_term_context_detection( $options );
        
        if ( $term_id ) {
            return $term_id;
        }
    }
    
    return $id;
}
}

// Add the filter hook only once
if ( ! has_filter( 'generateblocks_dynamic_tag_id', 'bws_override_media_ids_for_term_context' ) ) {
    add_filter( 'generateblocks_dynamic_tag_id', 'bws_override_media_ids_for_term_context', 15, 3 );
}

// ===============================================
// TERM TAG OPTION DEFINITIONS
// ===============================================

/**
 * Get term image field options
 * 
 * @since 1.1.0
 * @return array
 */
if ( ! function_exists( 'bws_get_term_image_field_options' ) ) {
function bws_get_term_image_field_options() {
    return [
        'field_key' => [
            'type'        => 'text',
            'label'       => __( 'Meta Field Key', 'generateblocks' ),
            'help'        => __( 'Enter the meta field key for the image field (ACF or standard term meta).', 'generateblocks' ),
            'placeholder' => __( 'image', 'generateblocks' ),
        ],
        'fallback_url' => [
            'type'        => 'url',
            'label'       => __( 'Fallback Image URL', 'generateblocks' ),
            'help'        => __( 'Enter a fallback image URL to use when no custom image is found.', 'generateblocks' ),
            'placeholder' => __( 'https://example.com/default-image.jpg', 'generateblocks' ),
        ],
    ];
}
}

// ===============================================
// TERM CONTEXT DETECTION
// ===============================================

/**
 * Reliable term context detection with multiple fallback methods
 * 
 * @since 1.1.0
 * @param array $options Tag options that may contain specific term ID
 * @return int|false Term ID or false if not found
 */
if ( ! function_exists( 'bws_reliable_term_context_detection' ) ) {
function bws_reliable_term_context_detection( $options = [] ) {
    // Primary: Check for specific term ID in options
    if ( isset( $options['term_id'] ) && $options['term_id'] ) {
        $term_id = absint( $options['term_id'] );
        if ( $term_id && term_exists( $term_id ) ) {
            return $term_id;
        }
    }
    
    // Secondary: Check for GenerateBlocks ID override
    if ( isset( $options['id'] ) && $options['id'] ) {
        $term_id = absint( $options['id'] );
        if ( $term_id && term_exists( $term_id ) ) {
            return $term_id;
        }
    }
    
    // Tertiary: Direct taxonomy queries (archive pages)
    if ( is_tax() || is_category() || is_tag() ) {
        $queried_object = get_queried_object();
        if ( $queried_object && isset( $queried_object->term_id ) ) {
            return $queried_object->term_id;
        }
    }
    
    // Quaternary: Archive context
    if ( is_archive() ) {
        $term_id = get_queried_object_id();
        if ( $term_id && is_numeric( $term_id ) ) {
            return $term_id;
        }
    }
    
    // Quinary: Check for first term from current post (if taxonomy specified)
    $taxonomy = $options['taxonomy'] ?? '';
    if ( $taxonomy && ! is_admin() ) {
        $post_id = get_the_ID();
        if ( $post_id ) {
            $terms = get_the_terms( $post_id, $taxonomy );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $first_term = reset( $terms );
                return $first_term->term_id;
            }
        }
    }
    
    return false;
}
}

/**
 * Get term object with validation
 * 
 * @since 1.1.0
 * @param int $term_id Term ID
 * @return WP_Term|false Term object or false
 */
if ( ! function_exists( 'bws_get_validated_term' ) ) {
function bws_get_validated_term( $term_id ) {
    if ( ! $term_id ) {
        return false;
    }
    
    $term = get_term( $term_id );
    
    if ( is_wp_error( $term ) || ! $term ) {
        return false;
    }
    
    return $term;
}
}

// ===============================================
// TERM CALLBACK FUNCTIONS
// ===============================================

/**
 * Get term name callback
 * 
 * @since 1.1.0
 * @param array $options Tag options
 * @param array $block Block data
 * @param object $instance Block instance
 * @return string
 */
if ( ! function_exists( 'bws_get_term_name_callback' ) ) {
function bws_get_term_name_callback( $options, $block, $instance ) {
    $term_id = bws_reliable_term_context_detection( $options );
    $term = bws_get_validated_term( $term_id );
    
    if ( ! $term ) {
        // Only show placeholder in editor if no term found
        if ( is_admin() || wp_is_json_request() ) {
            return GenerateBlocks_Dynamic_Tag_Callbacks::output( 'Term Name Preview', $options, $instance );
        }
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }
    
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $term->name, $options, $instance );
}
}

/**
 * Get term permalink callback
 * 
 * @since 1.1.0
 * @param array $options Tag options
 * @param array $block Block data
 * @param object $instance Block instance
 * @return string
 */
if ( ! function_exists( 'bws_get_term_permalink_callback' ) ) {
function bws_get_term_permalink_callback( $options, $block, $instance ) {
    $term_id = bws_reliable_term_context_detection( $options );
    $term = bws_get_validated_term( $term_id );
    
    if ( ! $term ) {
        // Only show placeholder in editor if no term found
        if ( is_admin() || wp_is_json_request() ) {
            return GenerateBlocks_Dynamic_Tag_Callbacks::output( '#term-permalink-preview', $options, $instance );
        }
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }
    
    $permalink = get_term_link( $term );
    
    if ( is_wp_error( $permalink ) ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }
    
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( esc_url( $permalink ), $options, $instance );
}
}

/**
 * Get term description callback
 * 
 * @since 1.1.0
 * @param array $options Tag options
 * @param array $block Block data
 * @param object $instance Block instance
 * @return string
 */
if ( ! function_exists( 'bws_get_term_description_callback' ) ) {
function bws_get_term_description_callback( $options, $block, $instance ) {
    $term_id = bws_reliable_term_context_detection( $options );
    $term = bws_get_validated_term( $term_id );
    
    if ( ! $term ) {
        // Only show placeholder in editor if no term found
        if ( is_admin() || wp_is_json_request() ) {
            return GenerateBlocks_Dynamic_Tag_Callbacks::output( 'Term description preview content will appear here.', $options, $instance );
        }
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }
    
    $description = $term->description;
    
    if ( empty( $description ) ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }
    
    // Sanitize description content
    add_filter( 'wp_kses_allowed_html', [ 'GenerateBlocks_Dynamic_Tags', 'expand_allowed_html' ], 10, 2 );
    $sanitized_description = wp_kses_post( $description );
    remove_filter( 'wp_kses_allowed_html', [ 'GenerateBlocks_Dynamic_Tags', 'expand_allowed_html' ], 10, 2 );
    
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $sanitized_description, $options, $instance );
}
}

/**
 * Get term field image callback
 * 
 * @since 1.1.0
 * @param array $options Tag options
 * @param array $block Block data
 * @param object $instance Block instance
 * @return string
 */
if ( ! function_exists( 'bws_get_term_field_image_callback' ) ) {
function bws_get_term_field_image_callback( $options, $block, $instance ) {
    $field_key = sanitize_text_field( $options['field_key'] ?? '' );
    $return_type = sanitize_text_field( $options['return_type'] ?? 'url' );
    $image_size = sanitize_text_field( $options['size'] ?? 'full' );
    $fallback_url = esc_url_raw( $options['fallback_url'] ?? '' );
    
    if ( empty( $field_key ) || ! preg_match( '/^[a-zA-Z0-9_-]+$/', $field_key ) ) {
        return bws_handle_term_image_fallback( $fallback_url, $return_type, $image_size, $options, $instance );
    }
    
    $term_id = bws_reliable_term_context_detection( $options );
    $term = bws_get_validated_term( $term_id );
    
    if ( ! $term ) {
        return bws_handle_term_image_fallback( $fallback_url, $return_type, $image_size, $options, $instance );
    }
    
    $image_data = bws_get_term_field_image_data( $term->term_id, $term->taxonomy, $field_key, $return_type, $image_size );
    
    if ( ! empty( $image_data ) ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( $image_data, $options, $instance );
    }
    
    // Use fallback
    return bws_handle_term_image_fallback( $fallback_url, $return_type, $image_size, $options, $instance );
}
}


// ===============================================
// TERM DATA RETRIEVAL HELPERS
// ===============================================

/**
 * Get field image data from term custom field
 * 
 * @since 1.1.0
 * @param int $term_id Term ID
 * @param string $taxonomy Taxonomy slug
 * @param string $field_key Field key
 * @param string $return_type Type of data to return
 * @param string $image_size Image size
 * @return string Image data or empty string
 */
if ( ! function_exists( 'bws_get_term_field_image_data' ) ) {
function bws_get_term_field_image_data( $term_id, $taxonomy, $field_key, $return_type = 'url', $image_size = 'full' ) {
    if ( ! $term_id || ! $taxonomy || ! $field_key ) {
        return '';
    }
    
    $image_value = '';
    
    // Try ACF field first
    if ( function_exists( 'get_field' ) ) {
        $image_value = get_field( $field_key, $taxonomy . '_' . $term_id );
    }
    
    // Fallback to standard term meta
    if ( empty( $image_value ) ) {
        $image_value = get_term_meta( $term_id, $field_key, true );
    }
    
    if ( empty( $image_value ) ) {
        return '';
    }
    
    // Reuse existing image processing function from v1.0.0
    return bws_process_meta_image_value( $image_value, $return_type, $image_size );
}
}

/**
 * Handle image fallback for term field images using URLs and placeholders
 * 
 * @since 1.1.0
 * @param string $fallback_url Fallback image URL
 * @param string $return_type Type of data to return
 * @param string $image_size Image size (not used for URLs)
 * @param array $options Tag options
 * @param object $instance Block instance
 * @return string
 */
if ( ! function_exists( 'bws_handle_term_image_fallback' ) ) {
function bws_handle_term_image_fallback( $fallback_url, $return_type, $image_size, $options, $instance ) {
    // First try the URL fallback
    if ( ! empty( $fallback_url ) && filter_var( $fallback_url, FILTER_VALIDATE_URL ) ) {
        // Security: Validate URL is from allowed domains or use attachment_url_to_postid for local URLs
        $site_url_host = wp_parse_url( home_url(), PHP_URL_HOST );
        $fallback_url_host = wp_parse_url( $fallback_url, PHP_URL_HOST );
        
        // If it's a local URL, try to get attachment data
        if ( $site_url_host === $fallback_url_host ) {
            $attachment_id = bws_get_attachment_id_from_url( $fallback_url );
            
            if ( $attachment_id ) {
                $result = bws_get_attachment_data( $attachment_id, $return_type, $image_size );
                
                if ( ! empty( $result ) ) {
                    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
                }
            }
        }
        
        // For external URLs or when we can't find an attachment, return based on return type
        switch ( $return_type ) {
            case 'url':
                return GenerateBlocks_Dynamic_Tag_Callbacks::output( esc_url( $fallback_url ), $options, $instance );
                
            case 'id':
                // External URLs don't have IDs in WordPress
                if ( $site_url_host === $fallback_url_host ) {
                    $attachment_id = bws_get_attachment_id_from_url( $fallback_url );
                    if ( $attachment_id ) {
                        return GenerateBlocks_Dynamic_Tag_Callbacks::output( (string) $attachment_id, $options, $instance );
                    }
                }
                return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
                
            case 'alt':
            case 'caption':
                // External URLs don't have alt text or captions in WordPress
                if ( $site_url_host === $fallback_url_host ) {
                    $attachment_id = bws_get_attachment_id_from_url( $fallback_url );
                    if ( $attachment_id ) {
                        $result = bws_get_attachment_data( $attachment_id, $return_type, $image_size );
                        if ( ! empty( $result ) ) {
                            return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
                        }
                    }
                }
                return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
        }
    }
    
    // Editor context: Use placeholders when no fallback URL is available
    if ( is_admin() || wp_is_json_request() ) {
        switch ( $return_type ) {
            case 'id':
                $placeholder = '0';
                break;
            case 'alt':
                $placeholder = 'Term image alt text placeholder';
                break;
            case 'caption':
                $placeholder = 'Term image caption placeholder';
                break;
            case 'url':
            default:
                // Use GenerateBlocks built-in placeholder
                $placeholder = bws_get_generateblocks_image_placeholder();
                break;
        }
        
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( $placeholder, $options, $instance );
    }
    
    // Frontend: Return empty if no fallback available
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
}
}

/**
 * Get GenerateBlocks image placeholder URL
 * 
 * @since 1.1.0
 * @return string Placeholder URL
 */
if ( ! function_exists( 'bws_get_generateblocks_image_placeholder' ) ) {
function bws_get_generateblocks_image_placeholder() {
    // Use GenerateBlocks standard placeholder if available
    if ( defined( 'GENERATEBLOCKS_DIR_URL' ) ) {
        $placeholder_path = GENERATEBLOCKS_DIR_URL . 'assets/images/image-placeholder.png';
        
        // Check if the placeholder file exists
        $placeholder_file = str_replace( GENERATEBLOCKS_DIR_URL, GENERATEBLOCKS_DIR, $placeholder_path );
        if ( file_exists( $placeholder_file ) ) {
            return $placeholder_path;
        }
    }
    
    // Fallback to a simple data URL placeholder
    return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjFmMWYxIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNHB4IiBmaWxsPSIjNjY2IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iMC4zZW0iPkltYWdlIFBsYWNlaG9sZGVyPC90ZXh0Pjwvc3ZnPg==';
}
}
