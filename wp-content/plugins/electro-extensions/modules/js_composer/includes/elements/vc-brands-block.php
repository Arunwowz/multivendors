<?php

if ( ! function_exists( 'electro_vc_brands_block_element' ) ) :

	function electro_vc_brands_block_element( $atts, $content = null ){

		extract(shortcode_atts(array(
			'title'				=> esc_html__( 'Known Brands', 'electro' ),
			'columns'			=> '3',
			'limit'				=> '6',
			'include'			=> '',
			'orderby' 			=> 'name',
			'order' 			=> 'ASC',
			'hide_empty'		=> false,
			'el_class'			=> ''
		), $atts));

		$args = apply_filters( 'electro_vc_brands_block_element_args', array(
			'is_enabled'	 => true,
            'section_title'  => $title,
            'number' 		 => $limit,
			'hide_empty'	 => $hide_empty,
            'columns'        => $columns,
            'orderby'        => $orderby,
            'order'          => $order,
			'animation'		 => false,
			'el_class'		 => $el_class
		));

		if( ! empty( $include ) ) {
			$include = explode( ",", $include );
			$include = array_map( 'trim', $include );
			$args['include'] = $include;
		}

		$html = '';
		if( function_exists( 'electro_home_v12_brands_block' ) ) {
			ob_start();
			electro_home_v12_brands_block( $args );
			$html = ob_get_clean();
		}

	    return $html;
	}

	add_shortcode( 'electro_brands_block_element' , 'electro_vc_brands_block_element' );

endif;