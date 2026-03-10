<?php
/**
 * BWS Dynamic Tags for GenerateBlocks
 * 
 * Provides dynamic tags for:
 * - Current/Related post featured images with media fallback
 * - Current/Related post content with linking options
 * - Current/Related post meta images with ACF support
 * - Related post URLs
 *
 * @package BWS_Dynamic_Tags
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ===============================================
// TAG REGISTRATION
// ===============================================

/**
 * Register all dynamic tags on init
 * 
 * @since 1.0.0
 * @return void
 */
function bws_register_dynamic_tags() {
    if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
        return;
    }

    // Current Post Featured Image with Media Selector
    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'       => __( 'Current Post Featured Image', 'generateblocks' ),
            'tag'         => 'current_post_featured_image',
            'type'        => 'media',
            'supports'    => [ 'image-size' ],
            'description' => __( 'Current post featured image with media fallback. Select fallback media below if no featured image exists.', 'generateblocks' ),
            'options'     => bws_get_image_return_type_options(),
            'return'      => 'bws_get_current_post_featured_image_with_ui',
        ]
    );

    // Related Post Featured Image with Media Selector
    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'       => __( 'Related Post Featured Image', 'generateblocks' ),
            'tag'         => 'related_post_featured_image',
            'type'        => 'media',
            'supports'    => [ 'meta', 'image-size' ],
            'description' => __( 'Featured image from related posts with media fallback. Select fallback media below if no related post or featured image exists.', 'generateblocks' ),
            'options'     => bws_get_image_return_type_options(),
            'return'      => 'bws_get_related_post_featured_image_with_ui',
        ]
    );

    // Related Post Content
    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'       => __( 'Related Post Content', 'generateblocks' ),
            'tag'         => 'related_post_content',
            'type'        => 'post',
            'supports'    => [ 'meta' ],
            'description' => __( 'Retrieve content from posts in ACF relationship fields with optional linking.', 'generateblocks' ),
            'options'     => bws_get_related_content_options(),
            'return'      => 'bws_get_related_post_content',
        ]
    );

    // Related Post URL
    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'       => __( 'Related Post URL', 'generateblocks' ),
            'tag'         => 'related_post_url',
            'type'        => 'post',
            'supports'    => [ 'meta' ],
            'description' => __( 'Retrieve URL from the first post in ACF relationship fields.', 'generateblocks' ),
            'options'     => bws_get_related_url_options(),
            'return'      => 'bws_get_related_post_url',
        ]
    );

    // Current Post Meta Image
    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'       => __( 'Current Post Meta Image', 'generateblocks' ),
            'tag'         => 'current_post_meta_image',
            'type'        => 'media',
            'supports'    => [ 'image-size' ],
            'description' => __( 'Image from custom meta field in current post with media fallback. Supports ACF image, icon picker, URL fields, and standard meta.', 'generateblocks' ),
            'options'     => array_merge(
                bws_get_meta_image_options(),
                bws_get_image_return_type_options()
            ),
            'return'      => 'bws_get_current_post_meta_image',
        ]
    );

    // Related Post Meta Image  
    new GenerateBlocks_Register_Dynamic_Tag(
        [
            'title'       => __( 'Related Post Meta Image', 'generateblocks' ),
            'tag'         => 'related_post_meta_image',
            'type'        => 'media',
            'supports'    => [ 'meta', 'image-size' ],
            'description' => __( 'Image from custom meta field in first related post with media fallback. Supports ACF image, icon picker, URL fields, and standard meta.', 'generateblocks' ),
            'options'     => array_merge(
                bws_get_meta_image_options(),
                bws_get_image_return_type_options()
            ),
            'return'      => 'bws_get_related_post_meta_image',
        ]
    );
}
add_action( 'init', 'bws_register_dynamic_tags' );

// ===============================================
// OPTION DEFINITIONS
// ===============================================

/**
 * Get image return type options
 * 
 * @since 1.0.0
 * @return array
 */
function bws_get_image_return_type_options() {
    return [
        'return_type' => [
            'type'    => 'select',
            'label'   => __( 'Return Type', 'generateblocks' ),
            'default' => 'url',
            'options' => [
                [ 'value' => 'url', 'label' => __( 'Image URL', 'generateblocks' ) ],
                [ 'value' => 'alt', 'label' => __( 'Alt Text', 'generateblocks' ) ],
                [ 'value' => 'id', 'label' => __( 'Attachment ID', 'generateblocks' ) ],
                [ 'value' => 'caption', 'label' => __( 'Caption', 'generateblocks' ) ],
            ],
        ],
    ];
}

/**
 * Get meta image field options
 * 
 * @since 1.0.0
 * @return array
 */
function bws_get_meta_image_options() {
    return [
        'meta_key' => [
            'type'        => 'text',
            'label'       => __( 'Meta Key', 'generateblocks' ),
            'help'        => __( 'Enter the meta key for the image field (ACF or standard meta).', 'generateblocks' ),
            'placeholder' => __( 'company_logo', 'generateblocks' ),
        ],
    ];
}

/**
 * Get related content field options
 * 
 * @since 1.0.0
 * @return array
 */
function bws_get_related_content_options() {
    return [
        'target_field' => [
            'type'    => 'select',
            'label'   => __( 'Target Field', 'generateblocks' ),
            'default' => 'post_title',
            'options' => [
                [ 'value' => 'post_title', 'label' => __( 'Post Title', 'generateblocks' ) ],
                [ 'value' => 'post_content', 'label' => __( 'Post Content', 'generateblocks' ) ],
                [ 'value' => 'post_excerpt', 'label' => __( 'Post Excerpt', 'generateblocks' ) ],
                [ 'value' => 'custom', 'label' => __( 'Meta Key', 'generateblocks' ) ],
            ],
        ],
        'custom_field' => [
            'type'  => 'text',
            'label' => __( 'Meta Key', 'generateblocks' ),
            'help'  => __( 'Enter meta key name when "Meta Key" is selected above.', 'generateblocks' ),
        ],
        'link_to' => [
            'type'    => 'select',
            'label'   => __( 'Link To', 'generateblocks' ),
            'default' => 'none',
            'options' => [
                [ 'value' => 'none', 'label' => __( 'No Link', 'generateblocks' ) ],
                [ 'value' => 'post', 'label' => __( 'Post Permalink', 'generateblocks' ) ],
                [ 'value' => 'custom', 'label' => __( 'Meta Key URL Field', 'generateblocks' ) ],
            ],
        ],
        'link_field' => [
            'type'  => 'text',
            'label' => __( 'Meta Key for URL Field', 'generateblocks' ),
            'help'  => __( 'Enter meta key name for URL when "Meta Key URL Field" is selected above.', 'generateblocks' ),
        ],
        'new_window' => [
            'type'  => 'checkbox',
            'label' => __( 'Open in new window', 'generateblocks' ),
            'help'  => __( 'Add target="_blank" and rel="noopener" to links.', 'generateblocks' ),
        ],
        'separator' => [
            'type'  => 'text',
            'label' => __( 'Separator', 'generateblocks' ),
            'help'  => __( 'Text to separate multiple related posts. Default: ", "', 'generateblocks' ),
        ],
        'limit' => [
            'type'  => 'number',
            'label' => __( 'Limit', 'generateblocks' ),
            'help'  => __( 'Maximum number of related posts to display. Default: 1', 'generateblocks' ),
        ],
    ];
}

/**
 * Get related URL field options
 * 
 * @since 1.0.0
 * @return array
 */
function bws_get_related_url_options() {
    return [
        'target_field' => [
            'type'    => 'select',
            'label'   => __( 'Target Field', 'generateblocks' ),
            'default' => 'permalink',
            'options' => [
                [ 'value' => 'permalink', 'label' => __( 'Permalink', 'generateblocks' ) ],
                [ 'value' => 'custom', 'label' => __( 'Meta Key', 'generateblocks' ) ],
            ],
        ],
        'custom_field' => [
            'type'  => 'text',
            'label' => __( 'Meta Key', 'generateblocks' ),
            'help'  => __( 'Enter meta key name when "Meta Key" is selected above.', 'generateblocks' ),
        ],
    ];
}

// ===============================================
// FILTERS
// ===============================================

/**
 * Override media IDs to provide post context for image tags
 * 
 * @since 1.0.0
 * @param int $id Original ID
 * @param array $options Tag options
 * @param object $instance Block instance
 * @return int Modified ID
 */
function bws_override_media_ids_for_post_context( $id, $options, $instance ) {
    $tag_name = $options['tag_name'] ?? '';
    
    $post_context_media_tags = [
        'current_post_featured_image',
        'related_post_featured_image',
        'current_post_meta_image',
        'related_post_meta_image',
    ];
    
    if ( in_array( $tag_name, $post_context_media_tags, true ) ) {
        $current_post_id = get_the_ID();
        
        if ( $current_post_id ) {
            return $current_post_id;
        }
    }
    
    return $id;
}
add_filter( 'generateblocks_dynamic_tag_id', 'bws_override_media_ids_for_post_context', 10, 3 );

// ===============================================
// CALLBACK FUNCTIONS
// ===============================================

/**
 * Get current post featured image with media UI fallback
 * 
 * @since 1.0.0
 * @param array $options Tag options
 * @param array $block Block data
 * @param object $instance Block instance
 * @return string
 */
function bws_get_current_post_featured_image_with_ui( $options, $block, $instance ) {
    $return_type = $options['return_type'] ?? 'url';
    $image_size  = $options['size'] ?? 'full';
    
    $current_post_id = GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
    $fallback_media_id = $options['id'] ?? '';
    
    // Try current post featured image
    if ( $current_post_id ) {
        $featured_attachment_id = get_post_thumbnail_id( $current_post_id );
        
        if ( $featured_attachment_id ) {
            $result = bws_get_attachment_data( $featured_attachment_id, $return_type, $image_size );
            
            if ( ! empty( $result ) ) {
                return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
            }
        }
    }
    
    // Use media selector fallback
    return bws_handle_media_fallback( $fallback_media_id, $return_type, $image_size, $options, $instance );
}

/**
 * Get related post featured image with media UI fallback
 * 
 * @since 1.0.0
 * @param array $options Tag options
 * @param array $block Block data
 * @param object $instance Block instance
 * @return string
 */
function bws_get_related_post_featured_image_with_ui( $options, $block, $instance ) {
    $return_type = $options['return_type'] ?? 'url';
    $image_size  = $options['size'] ?? 'full';
    
    $current_post_id = GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
    $fallback_media_id = $options['id'] ?? '';
    $rel_field_key = $options['key'] ?? '';
    
    // Try related post featured image
    if ( $current_post_id && ! empty( $rel_field_key ) ) {
        $related_posts = bws_get_related_posts_data( $current_post_id, $rel_field_key );
        
        if ( ! empty( $related_posts ) ) {
            $related_post_id = bws_extract_post_id( $related_posts[0] );
            
            if ( $related_post_id ) {
                $featured_attachment_id = get_post_thumbnail_id( $related_post_id );
                
                if ( $featured_attachment_id ) {
                    $result = bws_get_attachment_data( $featured_attachment_id, $return_type, $image_size );
                    
                    if ( ! empty( $result ) ) {
                        return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
                    }
                }
            }
        }
    }
    
    // Use media selector fallback
    return bws_handle_media_fallback( $fallback_media_id, $return_type, $image_size, $options, $instance );
}

/**
 * Get content from related posts
 * 
 * @since 1.0.0
 * @param array $options Tag options
 * @param array $block Block data
 * @param object $instance Block instance
 * @return string
 */
function bws_get_related_post_content( $options, $block, $instance ) {
    $source_post_id = GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
    
    if ( ! $source_post_id ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }

    $rel_field_key  = $options['key'] ?? '';
    $target_field   = $options['target_field'] ?? 'post_title';
    $custom_field   = $options['custom_field'] ?? '';
    $link_to        = $options['link_to'] ?? 'none';
    $link_field     = $options['link_field'] ?? '';
    $new_window     = isset( $options['new_window'] ) && $options['new_window'];
    $separator      = $options['separator'] ?? ', ';
    $limit          = absint( $options['limit'] ?? 1 );

    if ( empty( $rel_field_key ) ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }

    $related_posts = bws_get_related_posts_data( $source_post_id, $rel_field_key );
    
    if ( empty( $related_posts ) ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }

    if ( $limit > 0 ) {
        $related_posts = array_slice( $related_posts, 0, $limit );
    }

    $field_to_extract = ( 'custom' === $target_field && ! empty( $custom_field ) ) 
        ? $custom_field 
        : $target_field;

    $content_items = array();
    
    foreach ( $related_posts as $post_data ) {
        $post_id = bws_extract_post_id( $post_data );
        
        if ( ! $post_id ) {
            continue;
        }

        $text_content = bws_extract_text_field( $post_id, $field_to_extract );
        
        if ( empty( $text_content ) ) {
            continue;
        }

        if ( 'none' !== $link_to ) {
            $url = bws_get_link_url( $post_id, $link_to, $link_field );
            
            if ( $url ) {
                $link_attributes = $new_window ? ' target="_blank" rel="noopener"' : '';
                
                $content_items[] = sprintf(
                    '<a href="%s"%s>%s</a>',
                    esc_url( $url ),
                    $link_attributes,
                    wp_strip_all_tags( $text_content )
                );
            } else {
                $content_items[] = bws_sanitize_rich_content( $text_content );
            }
        } else {
            $content_items[] = bws_sanitize_rich_content( $text_content );
        }
    }

    if ( empty( $content_items ) ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }

    $output = implode( $separator, $content_items );
    
    $filtered_options = $options;
    unset( $filtered_options['link'] );
    
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $output, $filtered_options, $instance );
}

/**
 * Get URL from related post
 * 
 * @since 1.0.0
 * @param array $options Tag options
 * @param array $block Block data
 * @param object $instance Block instance
 * @return string
 */
function bws_get_related_post_url( $options, $block, $instance ) {
    $source_post_id = GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
    
    if ( ! $source_post_id ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }

    $rel_field_key  = $options['key'] ?? '';
    $target_field   = $options['target_field'] ?? 'permalink';
    $custom_field   = $options['custom_field'] ?? '';

    if ( empty( $rel_field_key ) ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }

    $related_posts = bws_get_related_posts_data( $source_post_id, $rel_field_key );
    
    if ( empty( $related_posts ) ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }

    $post_id = bws_extract_post_id( $related_posts[0] );
    
    if ( ! $post_id ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }

    $field_to_extract = ( 'custom' === $target_field && ! empty( $custom_field ) ) 
        ? $custom_field 
        : $target_field;

    $url = bws_extract_url_field( $post_id, $field_to_extract );
    
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $url, $options, $instance );
}

/**
 * Get image from current post meta field
 * 
 * @since 1.0.0
 * @param array $options Tag options
 * @param array $block Block data
 * @param object $instance Block instance
 * @return string
 */
function bws_get_current_post_meta_image( $options, $block, $instance ) {
    $meta_key    = sanitize_text_field( $options['meta_key'] ?? '' );
    $return_type = sanitize_text_field( $options['return_type'] ?? 'url' );
    $image_size  = sanitize_text_field( $options['size'] ?? 'full' );
    
    $current_post_id = GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
    $fallback_media_id = absint( $options['id'] ?? 0 );
    
    if ( empty( $meta_key ) ) {
        return bws_handle_media_fallback( $fallback_media_id, $return_type, $image_size, $options, $instance );
    }
    
    if ( ! bws_is_valid_meta_key( $meta_key ) ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }
    
    // Try current post meta image
    if ( $current_post_id ) {
        $result = bws_get_meta_image_data( $current_post_id, $meta_key, $return_type, $image_size );
        
        if ( ! empty( $result ) ) {
            return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
        }
    }
    
    // Use media selector fallback
    return bws_handle_media_fallback( $fallback_media_id, $return_type, $image_size, $options, $instance );
}

/**
 * Get image from related post meta field
 * 
 * @since 1.0.0
 * @param array $options Tag options
 * @param array $block Block data
 * @param object $instance Block instance
 * @return string
 */
function bws_get_related_post_meta_image( $options, $block, $instance ) {
    $rel_field_key = sanitize_text_field( $options['key'] ?? '' );
    $meta_key      = sanitize_text_field( $options['meta_key'] ?? '' );
    $return_type   = sanitize_text_field( $options['return_type'] ?? 'url' );
    $image_size    = sanitize_text_field( $options['size'] ?? 'full' );
    
    $current_post_id = GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
    $fallback_media_id = absint( $options['id'] ?? 0 );
    
    if ( empty( $rel_field_key ) || empty( $meta_key ) ) {
        return bws_handle_media_fallback( $fallback_media_id, $return_type, $image_size, $options, $instance );
    }
    
    if ( ! bws_is_valid_meta_key( $rel_field_key ) || ! bws_is_valid_meta_key( $meta_key ) ) {
        return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
    }
    
    // Try related post meta image
    if ( $current_post_id ) {
        $related_posts = bws_get_related_posts_data( $current_post_id, $rel_field_key );
        
        if ( ! empty( $related_posts ) ) {
            $related_post_id = bws_extract_post_id( $related_posts[0] );
            
            if ( $related_post_id ) {
                $result = bws_get_meta_image_data( $related_post_id, $meta_key, $return_type, $image_size );
                
                if ( ! empty( $result ) ) {
                    return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
                }
            }
        }
    }
    
    // Use media selector fallback
    return bws_handle_media_fallback( $fallback_media_id, $return_type, $image_size, $options, $instance );
}

// ===============================================
// DATA EXTRACTION HELPERS
// ===============================================

/**
 * Get related posts from ACF relationship or post object field
 * 
 * @since 1.0.0
 * @param int $post_id Source post ID
 * @param string $field_key ACF field key
 * @return array Array of related posts
 */
function bws_get_related_posts_data( $post_id, $field_key ) {
    if ( ! function_exists( 'get_field' ) || ! function_exists( 'get_field_object' ) ) {
        return array();
    }

    // Validate field type for security
    $field_object = get_field_object( $field_key, $post_id );
    
    if ( ! $field_object || ! in_array( $field_object['type'], [ 'relationship', 'post_object' ], true ) ) {
        return array();
    }

    $related_posts = get_field( $field_key, $post_id );
    
    if ( ! $related_posts ) {
        return array();
    }
    
    if ( ! is_array( $related_posts ) ) {
        $related_posts = array( $related_posts );
    }

    return $related_posts;
}

/**
 * Extract post ID from various ACF return formats
 * 
 * @since 1.0.0
 * @param mixed $post_data Post data from ACF
 * @return int|false Post ID or false
 */
function bws_extract_post_id( $post_data ) {
    if ( is_object( $post_data ) && isset( $post_data->ID ) ) {
        return $post_data->ID;
    }
    
    if ( $post_data instanceof WP_Post ) {
        return $post_data->ID;
    }
    
    if ( is_numeric( $post_data ) ) {
        return intval( $post_data );
    }
    
    if ( is_array( $post_data ) && isset( $post_data['ID'] ) ) {
        return $post_data['ID'];
    }
    
    return false;
}

/**
 * Extract text field from post with security constraints
 * 
 * @since 1.0.0
 * @param int $post_id Post ID
 * @param string $field_name Field name to extract
 * @return string Text content
 */
function bws_extract_text_field( $post_id, $field_name ) {
    $standard_fields = array(
        'post_title'   => get_the_title( $post_id ),
        'post_content' => get_post_field( 'post_content', $post_id ),
        'post_excerpt' => get_the_excerpt( $post_id ),
    );

    if ( array_key_exists( $field_name, $standard_fields ) ) {
        return $standard_fields[ $field_name ];
    }

    // ACF field - only return string values
    if ( function_exists( 'get_field' ) ) {
        $value = get_field( $field_name, $post_id );
        
        if ( is_string( $value ) ) {
            return $value;
        }
        
        // For non-string values, return empty to prevent data exposure
        if ( $value !== null && $value !== false ) {
            return '';
        }
    }

    // Standard meta
    $meta_value = get_post_meta( $post_id, $field_name, true );
    
    if ( is_string( $meta_value ) && '' !== $meta_value ) {
        return $meta_value;
    }

    return '';
}

/**
 * Extract URL field from post
 * 
 * @since 1.0.0
 * @param int $post_id Post ID
 * @param string $field_name Field name to extract
 * @return string URL
 */
function bws_extract_url_field( $post_id, $field_name ) {
    if ( 'permalink' === $field_name ) {
        return get_permalink( $post_id );
    }

    if ( function_exists( 'get_field' ) ) {
        $value = get_field( $field_name, $post_id );
        
        if ( $value ) {
            if ( is_array( $value ) && isset( $value['url'] ) ) {
                return $value['url'];
            }
            
            if ( is_string( $value ) ) {
                return $value;
            }
        }
    }

    $meta_value = get_post_meta( $post_id, $field_name, true );
    return is_string( $meta_value ) ? $meta_value : '';
}

/**
 * Get link URL based on link type
 * 
 * @since 1.0.0
 * @param int $post_id Post ID
 * @param string $link_to Link type
 * @param string $link_field Custom field for URL
 * @return string URL
 */
function bws_get_link_url( $post_id, $link_to, $link_field ) {
    switch ( $link_to ) {
        case 'post':
            return get_permalink( $post_id );
            
        case 'custom':
            if ( ! empty( $link_field ) ) {
                return bws_extract_url_field( $post_id, $link_field );
            }
            break;
    }
    
    return '';
}

// ===============================================
// IMAGE PROCESSING HELPERS
// ===============================================

/**
 * Get attachment data by type
 * 
 * @since 1.0.0
 * @param int $attachment_id Attachment ID
 * @param string $return_type Type of data to return
 * @param string $size Image size
 * @return string Requested data
 */
function bws_get_attachment_data( $attachment_id, $return_type = 'url', $size = 'full' ) {
    $attachment = get_post( $attachment_id );
    
    if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
        return '';
    }

    switch ( $return_type ) {
        case 'id':
            return (string) $attachment_id;
            
        case 'alt':
            return get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
            
        case 'caption':
            return wp_get_attachment_caption( $attachment_id );
            
        case 'url':
        default:
            $image_data = wp_get_attachment_image_src( $attachment_id, $size );
            
            if ( ! $image_data || ! isset( $image_data[0] ) ) {
                return '';
            }
            
            return esc_url( $image_data[0] );
    }
}

/**
 * Get image data from meta field
 * 
 * @since 1.0.0
 * @param int $post_id Post ID
 * @param string $meta_key Meta key
 * @param string $return_type Type of data to return
 * @param string $size Image size
 * @return string Requested data
 */
function bws_get_meta_image_data( $post_id, $meta_key, $return_type = 'url', $size = 'full' ) {
    if ( ! $post_id || ! get_post( $post_id ) ) {
        return '';
    }
    
    $meta_value = null;
    
    if ( function_exists( 'get_field' ) ) {
        $meta_value = get_field( $meta_key, $post_id );
    }
    
    if ( ! $meta_value ) {
        $meta_value = get_post_meta( $post_id, $meta_key, true );
    }
    
    if ( ! $meta_value ) {
        return '';
    }
    
    return bws_process_meta_image_value( $meta_value, $return_type, $size );
}

/**
 * Process meta value to extract image data
 * 
 * @since 1.0.0
 * @param mixed $meta_value Meta value
 * @param string $return_type Type of data to return
 * @param string $size Image size
 * @return string Requested data
 */
function bws_process_meta_image_value( $meta_value, $return_type = 'url', $size = 'full' ) {
    $attachment_id = null;
    
    if ( is_array( $meta_value ) ) {
        // ACF Icon Picker format
        if ( isset( $meta_value['type'] ) && isset( $meta_value['value'] ) ) {
            return bws_process_acf_icon_picker( $meta_value, $return_type, $size );
        }
        // ACF Image field
        elseif ( isset( $meta_value['ID'] ) && is_numeric( $meta_value['ID'] ) ) {
            $attachment_id = absint( $meta_value['ID'] );
        } elseif ( isset( $meta_value['id'] ) && is_numeric( $meta_value['id'] ) ) {
            $attachment_id = absint( $meta_value['id'] );
        } elseif ( isset( $meta_value['url'] ) && is_string( $meta_value['url'] ) ) {
            $attachment_id = bws_get_attachment_id_from_url( $meta_value['url'] );
            
            if ( ! $attachment_id && 'url' === $return_type ) {
                return esc_url( $meta_value['url'] );
            }
        }
    } elseif ( is_numeric( $meta_value ) ) {
        $attachment_id = absint( $meta_value );
    } elseif ( is_string( $meta_value ) ) {
        $meta_value = trim( $meta_value );
        
        if ( strpos( $meta_value, 'dashicons-' ) === 0 ) {
            return bws_handle_dashicon_value( $meta_value, $return_type );
        }
        
        $meta_value = esc_url_raw( $meta_value );
        
        if ( filter_var( $meta_value, FILTER_VALIDATE_URL ) ) {
            $attachment_id = bws_get_attachment_id_from_url( $meta_value );
            
            if ( ! $attachment_id && 'url' === $return_type ) {
                return esc_url( $meta_value );
            }
        }
    }
    
    if ( ! $attachment_id ) {
        return '';
    }
    
    return bws_get_attachment_data( $attachment_id, $return_type, $size );
}

/**
 * Process ACF Icon Picker field data
 * 
 * @since 1.0.0
 * @param array $icon_data Icon picker data
 * @param string $return_type Type of data to return
 * @param string $size Image size
 * @return string Requested data
 */
function bws_process_acf_icon_picker( $icon_data, $return_type = 'url', $size = 'full' ) {
    $icon_type = $icon_data['type'] ?? '';
    $icon_value = $icon_data['value'] ?? '';
    
    switch ( $icon_type ) {
        case 'media_library':
        case 'url':
            $url = '';
            
            if ( is_array( $icon_value ) ) {
                if ( isset( $icon_value['url'] ) ) {
                    $url = $icon_value['url'];
                } elseif ( isset( $icon_value['ID'] ) ) {
                    return bws_get_attachment_data( absint( $icon_value['ID'] ), $return_type, $size );
                } elseif ( isset( $icon_value['id'] ) ) {
                    return bws_get_attachment_data( absint( $icon_value['id'] ), $return_type, $size );
                }
            } elseif ( is_string( $icon_value ) ) {
                $url = $icon_value;
            }
            
            if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
                $attachment_id = bws_get_attachment_id_from_url( $url );
                
                if ( $attachment_id ) {
                    return bws_get_attachment_data( $attachment_id, $return_type, $size );
                } elseif ( 'url' === $return_type ) {
                    return esc_url( $url );
                }
            }
            break;
            
        case 'dashicons':
            if ( is_string( $icon_value ) ) {
                return bws_handle_dashicon_value( $icon_value, $return_type );
            }
            break;
    }
    
    return '';
}

/**
 * Handle dashicon values
 * 
 * @since 1.0.0
 * @param string $dashicon_class Dashicon class
 * @param string $return_type Type of data to return
 * @return string Requested data
 */
function bws_handle_dashicon_value( $dashicon_class, $return_type = 'url' ) {
    $dashicon_class = sanitize_html_class( $dashicon_class );
    
    switch ( $return_type ) {
        case 'alt':
            $alt_text = str_replace( [ 'dashicons-', '-' ], [ '', ' ' ], $dashicon_class );
            return ucwords( trim( $alt_text ) );
            
        case 'caption':
            $caption = str_replace( [ 'dashicons-', '-' ], [ 'Dashicon: ', ' ' ], $dashicon_class );
            return ucwords( trim( $caption ) );
            
        case 'url':
        case 'id':
        default:
            return '';
    }
}

/**
 * Get attachment ID from URL
 * 
 * @since 1.0.0
 * @param string $url Image URL
 * @return int|false Attachment ID or false
 */
function bws_get_attachment_id_from_url( $url ) {
    if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        return false;
    }
    
    // Security: same domain only
    $site_url = wp_parse_url( home_url(), PHP_URL_HOST );
    $image_url_host = wp_parse_url( $url, PHP_URL_HOST );
    
    if ( $site_url !== $image_url_host ) {
        return false;
    }
    
    // Try WordPress core function
    $attachment_id = attachment_url_to_postid( $url );
    
    if ( $attachment_id ) {
        return $attachment_id;
    }
    
    // Try without size suffix
    $url_without_size = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif|webp|svg)$)/i', '', $url );
    
    if ( $url_without_size !== $url ) {
        $attachment_id = attachment_url_to_postid( $url_without_size );
        
        if ( $attachment_id ) {
            return $attachment_id;
        }
    }
    
    return false;
}

// ===============================================
// UTILITY HELPERS
// ===============================================

/**
 * Validate meta key format
 * 
 * @since 1.0.0
 * @param string $meta_key Meta key to validate
 * @return bool True if valid
 */
function bws_is_valid_meta_key( $meta_key ) {
    return preg_match( '/^[a-zA-Z0-9_-]+$/', $meta_key );
}

/**
 * Sanitize rich content with proper HTML handling
 * 
 * @since 1.0.0
 * @param string $content Content to sanitize
 * @return string Sanitized content
 */
function bws_sanitize_rich_content( $content ) {
    if ( empty( $content ) ) {
        return '';
    }
    
    add_filter( 'wp_kses_allowed_html', [ 'GenerateBlocks_Dynamic_Tags', 'expand_allowed_html' ], 10, 2 );
    $sanitized = wp_kses_post( $content );
    remove_filter( 'wp_kses_allowed_html', [ 'GenerateBlocks_Dynamic_Tags', 'expand_allowed_html' ], 10, 2 );
    
    return $sanitized;
}

/**
 * Handle media fallback
 * 
 * @since 1.0.0
 * @param int $fallback_media_id Fallback media ID
 * @param string $return_type Type of data to return
 * @param string $image_size Image size
 * @param array $options Tag options
 * @param object $instance Block instance
 * @return string
 */
function bws_handle_media_fallback( $fallback_media_id, $return_type, $image_size, $options, $instance ) {
    if ( $fallback_media_id && is_numeric( $fallback_media_id ) ) {
        $result = bws_get_attachment_data( absint( $fallback_media_id ), $return_type, $image_size );
        
        if ( ! empty( $result ) ) {
            return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
        }
    }
    
    return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
}
