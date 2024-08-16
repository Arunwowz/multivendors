<?php

if ( ! function_exists( 'electro_vc_sidebar_with_products_block' ) ) :

	function electro_vc_sidebar_with_products_block( $atts, $content = null ){

        extract(shortcode_atts(array(
			'sidebar_title'		=> esc_html__( 'Assortment', 'electro' ),
            'enable_sidebar'    => false,
            'section_title'     => esc_html__( 'Hot Products Today', 'electro' ),
            'shortcode_tag'     => 'recent_products',
            'products_choice'   => 'ids',
            'product_id'        => '',
            'category'          => '',
            'cat_operator'      => 'IN',
            'attribute'         => '',
            'terms'             => '',
            'terms_operator'    => 'IN',
            'enable_pagination' => false,
			'orderby'           => 'date',
            'order'             => 'ASC',
            'per_page'          => 15,
            'columns'           => 5,
            'columns_wide'      => 3,
            'el_class'          => ''
		), $atts));

        $shortcode_atts = function_exists( 'electro_get_atts_for_shortcode' ) ? electro_get_atts_for_shortcode( array( 'shortcode' => $shortcode_tag, 'product_category_slug' => $category, 'cat_operator' => $cat_operator, 'products_choice' => $products_choice, 'products_ids_skus' => $product_id, 'attribute' => $attribute, 'terms' => $terms, 'terms_operator' => $terms_operator ) ) : array();
        $shortcode_atts = wp_parse_args( $shortcode_atts, array( 'order' => $order, 'orderby' => $orderby, 'per_page' => $per_page, 'columns' => $columns, 'paginate' => $enable_pagination ) );

        if ( electro_is_wide_enabled() ) {
			$shortcode_atts[ 'columns_wide' ] = $columns_wide;
        }
		
        $attr = array(
			'class' => 'row categories-with-product'
		);

        if( isset( $el_class ) && !empty( $el_class ) ){
            $attr['class'] .=  ' ' . $el_class ;
        }

        $html = '';
        
        if( function_exists( 'electro_home_v10_sidebar' ) ) {
			ob_start();?>
            <div <?php echo electro_render_attributes( $attr ); ?>><?php
			electro_home_v10_sidebar( $enable_sidebar, $sidebar_title );
            if ( is_woocommerce_activated() ) : ?>
				<div class="col">
					<section class="w-100 mb-0">
						<?php if ( isset( $section_title ) && ! empty( $section_title ) ) : ?>
							<header class="mb-0">
								<h2 class="h1"><?php echo esc_html( $section_title ); ?></h2>
							</header>
						<?php endif; ?>
						<?php echo electro_do_shortcode( $shortcode_tag , $shortcode_atts ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</section>
				</div>
			<?php endif; ?>
            </div><?php
			$html = ob_get_clean();
		}
        return $html;

    }
    add_shortcode( 'electro_sidebar_with_products_block' , 'electro_vc_sidebar_with_products_block' );

endif;