<?php
if ( ! function_exists( 'electro_vc_banner_with_products_carousel' ) ) :

    function electro_vc_banner_with_products_carousel( $atts, $content = null ) {

        extract(shortcode_atts(array(
            'bg_image'              => '',
            'title'                 => wp_kses_post( 'OUTLET DEALS <span class="d-block">CLEARANCE</span>' ),
            'subtitle'              => esc_html__( 'SAVE UP TO', 'electro' ),
            'offer_text'            => wp_kses_post( '70<sup class="font-size-36">%</sup><sub class="font-size-16">OFF!</sub>' ),
            'button_text'           => esc_html__( 'Start Buying', 'electro' ),
            'button_url'            => '#',
            'shortcode_tag'         => 'recent_products',
            'products_choice'       => 'ids',
            'product_id'            => '',
            'category'              => '',
            'cat_operator'          => 'IN',
            'attribute'             => '',
            'terms'                 => '',
            'terms_operator'        => 'IN',
            'per_page'              => 10,
            'orderby'               => 'date',
            'order'                 => 'ASC',
            'items'                 => 5,
            'autoplay'              => false,
            'el_class'              => ''
        ), $atts));

        $image_url = isset( $bg_image ) && intval( $bg_image ) ? wp_get_attachment_url( $bg_image ) : '';
        
        $args = apply_filters( 'electro_vc_banner_with_products_carousel_args', array(
            'is_enabled' => true,
            'animation'  => false,
            'el_class'   => $el_class,
            'bg_image'     => $image_url,
            'banner'       => array(
                'title'       => $title,
                'subtitle'    => $subtitle,
                'offer_text'  => $offer_text,
                'button_text' => $button_text,
                'button_url'  => $button_url
            ),
            'products' => array(
                'shortcode'             => $shortcode_tag,
                'products_choice'       => $products_choice,
                'products_ids_skus'     => $product_id,
                'product_category_slug' => $category,
                'cat_operator'          => $cat_operator,
                'attribute'             => $attribute,
                'terms'                 => $terms,
                'terms_operator'        => $terms_operator,
                'shortcode_atts'    => array(
                    'per_page' => $per_page,
                    'orderby'  => $orderby,
                    'order'    => $order
                ),
                'carousel_args' => array(
                    'slideToShow' => $items,
                    'autoplay'    => $autoplay
                )
            )
        ));
        
        $html = '';
        if( function_exists( 'electro_home_v11_banner_with_products_carousel' ) ) {
            ob_start();
            electro_home_v11_banner_with_products_carousel( $args );
            $html = ob_get_clean();
        }
        return $html;
    }
    add_shortcode( 'electro_banner_with_products_carousel' , 'electro_vc_banner_with_products_carousel' );
endif;