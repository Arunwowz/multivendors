<?php

if ( ! function_exists( 'electro_vc_three_banners_block_element' ) ) :

	function electro_vc_three_banners_block_element( $atts, $content = null ){

		extract(shortcode_atts(array(
			'b1_bg_image'			=> '',
			'b1_image'			    => '',
			'b1_action_link'		=> '#',
			'b1_description'		=> wp_kses_post( '<strong>#STAYHOME</strong> AND CATCH BIG <strong>DEALS</strong> ON THE GAMES &amp; CONSOLES' ),
			'b2_bg_image' 			=> '',
			'b2_action_link' 		=> '#',
			'b2_description'		=> esc_html__( 'OFFICE LAPTOPSFOR WORK', 'electro' ),
			'b2_price_pre_text'		=> esc_html__( 'FROM', 'electro' ),
            'b2_price_text'			=> wp_kses_post( '<sup class="h5 fw-bold mb-0">$</sup>749<sup class="h5 fw-bold mb-0">99</sup>' ),
			'b3_bg_image'			=> '',
			'b3_action_link'		=> '#',
			'b3_title'			    => esc_html__( 'LIMITED', 'electro' ),
			'b3_subtitle' 			=> esc_html__( 'WEEK DEAL', 'electro' ),
			'b3_description' 		=> esc_html__( 'HURRY UP BEFORE OFFER WILL END', 'electro' ),
            'el_class'              => ''
		), $atts));

		$args = apply_filters( 'electro_vc_three_banners_block_element_args', array(
            'is_enabled' => true,
            'animation'  => false,
            'el_class'   => $el_class,
            'banners'    => array(
                'banner_1' => array(
                    'bg_image'          => isset( $b1_bg_image ) && intval( $b1_bg_image ) ? wp_get_attachment_url( $b1_bg_image ) : '',
                    'image'             => isset( $b1_image ) && intval( $b1_image ) ? wp_get_attachment_url( $b1_image ) : '',
                    'url'               => $b1_action_link,
                    'desc'              => $b1_description
                ),
                'banner_2' => array(
                    'bg_image'          => isset( $b2_bg_image ) && intval( $b2_bg_image ) ? wp_get_attachment_url( $b2_bg_image ) : '',
                    'url'               => $b2_action_link,
                    'desc'              => $b2_description,
                    'price_pre_text'    => $b2_price_pre_text,
                    'price_text'        => $b2_price_text
                ),
                'banner_3' => array(
                    'bg_image'          => isset( $b3_bg_image ) && intval( $b3_bg_image ) ? wp_get_attachment_url( $b3_bg_image ) : '',
                    'url'               => $b3_action_link,
                    'title'             => $b3_title,
                    'subtitle'          => $b3_subtitle,
                    'desc'              => $b3_description,
                )
            )
        ));

		$html = '';
		if( function_exists( 'electro_home_v12_banners' ) ) {
			ob_start();
			electro_home_v12_banners( $args );
			$html = ob_get_clean();
		}

	    return $html;
	}

	add_shortcode( 'electro_three_banners_block_element' , 'electro_vc_three_banners_block_element' );

endif;