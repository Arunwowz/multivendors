<?php

if ( ! function_exists('electro_vc_slider_with_ads_block_v2')):

    function electro_vc_slider_with_ads_block_v2( $atts, $content = null ) {

        extract(shortcode_atts(array(
            'rev_slider_alias'      => '',
            'ads_banners'           => '',
            'el_class'              => '',
            'bg_image'              => '',
        ), $atts));

        $image_url = isset( $bg_image ) && intval( $bg_image ) ? wp_get_attachment_url( $bg_image ) : '';

        $attr = array(
			'class' => 'stretch-full-width slider-with-das mb-5'
		);

        if( isset( $el_class ) && !empty( $el_class ) ){
            $attr['class'] .=  ' ' . $el_class ;
        }
        
        if( $image_url ){
            $attr['style'] = 'background-image:url('. $image_url . ')';
        }

        $ads_args = array();

        if( is_object( $ads_banners ) || is_array( $ads_banners ) ) {
            $ads_banners = json_decode( json_encode( $ads_banners ), false );
        } else {
            $ads_banners = json_decode( urldecode( $ads_banners ), true );
        }

        if( is_array( $ads_banners ) ) {
            foreach ( $ads_banners as $key => $ads_banner ) {
                
                extract(shortcode_atts(array(
                    'title'         => wp_kses_post( __( 'Catch Big <strong>Deals</strong> on<br>The Consoles', 'electro' ) ),
                    'action_text'   => esc_html__( 'Shop now', 'electro' ),
                    'url'           => '#',
                    'image'         => '',
                ), $ads_banner));
                
                $ads_args[] = array(
                    'title'         => $title,
                    'action_text'   => $action_text,
                    'url'           => $url,
                    'image'         => isset( $image ) && intval( $image ) ? wp_get_attachment_url( $image ) : '',
                );
            }
        }

        $slider_shortcode = '';

        if( ! empty( $rev_slider_alias ) ) {
            $slider_shortcode = '[rev_slider alias="' . $rev_slider_alias . '"]';
        }

        $html = '';

        if( function_exists( 'electro_home_v10_slider_block' ) || function_exists( 'electro_home_v10_ads_block' ) ) {

            ob_start();
            ?><div <?php echo electro_render_attributes( $attr ); ?>>
                <div class="container">
                    <div class="row">
                        <?php if( function_exists( 'electro_home_v10_slider_block' ) ): ?>
                            <div class="col-lg">
                                <?php electro_home_v10_slider_block( $slider_shortcode ); ?>
                            </div>
                        <?php endif; ?>
                        <?php if( function_exists( 'electro_home_v10_ads_block' ) ): ?>
                            <div class="col-lg-auto">
                                <?php electro_home_v10_ads_block( $ads_args ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div><?php
            $html = ob_get_clean();
        }
        
        return $html;
    }

    add_shortcode( 'electro_slider_with_ads_block_v2' , 'electro_vc_slider_with_ads_block_v2' );
endif;