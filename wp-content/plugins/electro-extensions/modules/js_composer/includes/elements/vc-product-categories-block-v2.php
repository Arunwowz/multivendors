<?php

if ( ! function_exists( 'electro_vc_product_categories_block_v2_element' ) ) :

	function electro_vc_product_categories_block_v2_element( $atts, $content = null ){

		extract(shortcode_atts(array(
			'columns'			=> '5',
			'limit'				=> '5',
			'has_no_products'	=> false,
			'orderby' 			=> 'menu_order',
			'order' 			=> 'ASC',
			'include'			=> '',
			'slugs'				=> '',
			'el_class'			=> ''
		), $atts));

		$cat_args = array(
            'columns'           => $columns,
			'number'			=> $limit,
			'hide_empty'		=> $has_no_products,
			'orderby' 			=> $orderby,
			'order' 			=> $order,
		);

		if( ! empty( $slugs ) ) {
			$slugs = explode( ",", $slugs );
			$slugs = array_map( 'trim', $slugs );
			
			$slug_include = array();

			foreach ( $slugs as $slug ) {
				$slug_include[] = "'" . $slug ."'";
			}

			if ( ! empty($slug_include ) ) {
				$cat_args['slug'] 		= $slugs;
				$cat_args['include'] 	= $slug_include;
				$cat_args['orderby']	= 'include';
			}

		} elseif( ! empty( $include ) ) {
			$include = explode( ",", $include );
			$include = array_map( 'trim', $include );
			$cat_args['include'] = $include;
		}

		$args = apply_filters( 'electro_vc_product_categories_block_v2_element_args', array(
			'is_enabled' => true,
			'animation'	 => false,
            'el_class'   => $el_class,
            'categories' => $cat_args
            
		));

		$html = '';
		if( function_exists( 'electro_home_v12_categories_block' ) ) {
			ob_start();
			electro_home_v12_categories_block( $args );
			$html = ob_get_clean();
		}

	    return $html;
	}

	add_shortcode( 'electro_product_categories_block_v2' , 'electro_vc_product_categories_block_v2_element' );

endif;