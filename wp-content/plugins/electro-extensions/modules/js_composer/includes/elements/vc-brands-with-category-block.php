<?php
if ( ! function_exists( 'electro_vc_brands_with_category_block' ) ) :

    function electro_vc_brands_with_category_block( $atts, $content = null ) {

        extract(shortcode_atts(array(
            'title'                 => esc_html__( 'Brands:', 'electro' ),
            'more_brands_text'      => esc_html__( '+ More Brands', 'electro' ),
            'more_brands_link'      => '#',
            'brand_orderby'         => 'name',
            'brand_order'           => 'ASC',
            'brand_number'          => '6',
            'brand_hide_empty'      => false,
            'arrow_icon'            => 'fas fa-chevron-right',
            'cats_child_limit'      => '5',
            'more_child_text'       => '...',
            'category_orderby'      => 'menu_order',
            'category_order'        => 'ASC',
            'category_number'       => '8',
            'category_hide_empty'   => false,
            'object_ids'            => '',
            'el_class'              => ''
        ), $atts));

        if( ! empty( $object_ids ) ) {
            $object_ids = explode( ",", $object_ids );
            $object_ids = array_map( 'trim', $object_ids );
        }

        $args = apply_filters( 'electro_vc_brands_with_category_block_args', array(
            'is_enabled' => true,
            'animation'  => false,
            'el_class'   => $el_class,
            'brands' => array(
                'title'            => $title,
                'more_brands_text' => $more_brands_text,
                'more_brands_link' => $more_brands_link,
                'taxonomy_args'    => array(
                    'orderby'    => $brand_orderby,
                    'order'      => $brand_order,
                    'number'     => $brand_number,
                    'hide_empty' => $brand_hide_empty
                )
            ),
            'categories' => array(
                'arrow_icon'         => $arrow_icon,
                'cats_child_limit'   => $cats_child_limit,
                'more_child_text'    => $more_child_text,
                'product_cats_args'  => array(
                    'orderby'    => $category_orderby,
                    'order'      => $category_order,
                    'number'     => $category_number,
                    'hide_empty' => $category_hide_empty,
                    'parent'     => 0,
                    'include' => $object_ids,
                )
            )

        ));

        $html = '';
        if( function_exists( 'electro_home_v11_brands_with_category_block' ) ) {
            ob_start();
            electro_home_v11_brands_with_category_block( $args );
            $html = ob_get_clean();
        }
        
        return $html;
    }

    add_shortcode( 'electro_brands_with_category_block' , 'electro_vc_brands_with_category_block' );

endif;