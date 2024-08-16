<?php

if ( ! function_exists( 'electro_vc_blog_posts_block_element' ) ) :

	function electro_vc_blog_posts_block_element( $atts, $content = null ){

		extract(shortcode_atts(array(
			'title'				=> esc_html__( 'Tips & Inspirations', 'electro' ),
			'columns'			=> '2',
			'limit'				=> '2',
			'include'			=> '',
			'orderby' 			=> 'date',
			'order' 			=> 'ASC',
			'el_class'			=> ''
		), $atts));

		$args = apply_filters( 'electro_vc_blog_posts_block_element_args', array(
			'is_enabled'	 => true,
			'animation'		 => false,
            'section_title'  => $title,
            'posts_per_page' => $limit,
            'columns'        => $columns,
            'orderby'        => $orderby,
            'order'          => $order,
			'el_class'		 => $el_class
		));

		if( ! empty( $include ) ) {
			$include = explode( ",", $include );
			$include = array_map( 'trim', $include );
			$args['post__in'] = $include;
		}

		$html = '';
		if( function_exists( 'electro_home_v12_blog_posts' ) ) {
			ob_start();
			electro_home_v12_blog_posts( $args );
			$html = ob_get_clean();
		}

	    return $html;
	}

	add_shortcode( 'electro_blog_posts_block_element' , 'electro_vc_blog_posts_block_element' );

endif;