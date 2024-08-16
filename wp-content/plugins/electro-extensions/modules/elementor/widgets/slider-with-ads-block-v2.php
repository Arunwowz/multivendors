<?php

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Slider With Ads Block Version 2 Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Electro_Elementor_Slider_With_Ads_Block_V2 extends Widget_Base {

    protected $_has_template_content = false;

    /**
     * Get widget name.
     *
     * Retrieve Slider With Ads Version 2 Block widget name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'electro_elementor_slider_with_ads_block_v2';
    }

    /**
     * Get widget title.
     *
     * Retrieve Slider With Ads Version 2 Block widget title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return esc_html__( 'Slider With Ads Block v2', 'electro-extensions' );
    }

    /**
     * Get widget icon.
     *
     * Retrieve Slider With Ads Version 2 Block widget icon.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget icon.
     */
    public function get_icon() {
        return 'eicon-post-slider';
    }

    /**
     * Get widget categories.
     *
     * Retrieve the list of categories the Slider With Ads Block Version 2 widget belongs to.
     *
     * @since 1.0.0
     * @access public
     *
     * @return array Widget categories.
     */
    public function get_categories() {
        return [ 'electro-elements' ];
    }

    /**
     * Register Slider With Ads Block Version 2 widget controls.
     *
     * Adds different input fields to allow the user to change and customize the widget settings.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function register_controls() {

        $revsliders = array(
            esc_html__( 'No sliders found', 'electro-extensions' )      => '',
        );
        
        if ( class_exists( 'RevSlider' ) ) {
            $slider = new \RevSlider();
            $arrSliders = $slider->getArrSliders();

            if ( $arrSliders ) {
                foreach ( $arrSliders as $slider ) {
                    $revsliders[ $slider->getAlias() ] = $slider->getTitle();

                }
            }
            
        }

        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__( 'Content', 'electro-extensions' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'bg_image',
            [
                'label' => esc_html__( 'Background Image', 'electro-extensions' ),
                'type'  => Controls_Manager::MEDIA,
            ]
        );

        $this->add_control(
            'rev_slider_alias',
            [
                'label'         => esc_html__( 'Title', 'electro-extensions' ),
                'type'          => Controls_Manager::SELECT,
                'options'       => $revsliders,
                'default'       => 'home-v1-slider',
            ]
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'ad_image',
            [
                'label' => esc_html__( 'Ad image', 'electro-extensions' ),
                'type'  => Controls_Manager::MEDIA,
            ]
        );

        $repeater->add_control(
            'ad_text',
            [
                'label' => esc_html__( 'Ad text', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
                'placeholder' => esc_html__( 'Enter your ad text here', 'electro-extensions' ),
            ]
        );

        $repeater->add_control(
            'action_text',
            [
                'label' => esc_html__( 'Action Text', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
                'placeholder' => esc_html__( 'Enter your action text here', 'electro-extensions' ),
            ]
        );

        $repeater->add_control(
            'action_link',
            [
                'label' => esc_html__( 'Action Link', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
                'placeholder' => esc_html__( 'Enter your link text here', 'electro-extensions' ),
            ]
        );

        $this->add_control(
            'ads_banners',
            [
                'label'  => esc_html__( 'Products Tabs Element', 'electro-extensions' ),
                'type'   => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [],
            ]
        );

        $this->add_control(
            'el_class',
            [
                'label'         => esc_html__( 'Extra class name', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'If you wish to style particular style use this field', 'electro-extensions' ),
                'default'       => 'mb-5',
            ]
        );

        $this->end_controls_section();

    }

    /**
     * Render Slider With Ads Block Version 2 widget output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function render() {

        $settings = $this->get_settings_for_display();

        extract( $settings );

        $args = array();

        $ads_args = array();

        if( is_object( $ads_banners ) || is_array( $ads_banners ) ) {
            $ads_banners = json_decode( json_encode( $ads_banners ), true );
        } else {
            $ads_banners = json_decode( urldecode( $ads_banners ), true );
        }

        if( is_array( $ads_banners ) ) {
            foreach ( $ads_banners as $key => $ads_banner ) {

                extract(shortcode_atts(array(
                    'ad_text'               => '',
                    'action_text'           => '',
                    'action_link'           => '',
                    'ad_image'              => '',
                ), $ads_banner));

                $image_attributes = isset( $ads_banner['ad_image']['id'] ) ? wp_get_attachment_image_src ($ads_banner['ad_image']['id'], 'full' ) : '';

                
                $ads_args[] = array(
                    'title'       => $ad_text,
                    'action_text' => $action_text,
                    'url'         => $action_link,
                    'image'       => isset( $image_attributes[0] ) ? $image_attributes[0] : '',
                );
            }
        }

        $image_url = !empty( $bg_image['url'] ) ? $bg_image['url'] : '';

        $attr = array(
			'class' => 'stretch-full-width slider-with-das'
		);

        if( isset( $el_class ) && !empty( $el_class ) ){
            $attr['class'] .=  ' ' . $el_class ;
        }
        
        if( $image_url ){
            $attr['style'] = 'background-image:url('. $image_url . ')';
        }
        
        $slider_shortcode = '';
        if( ! empty( $rev_slider_alias ) ) {
            $slider_shortcode = '[rev_slider alias="' . $rev_slider_alias . '"]';
        }
        
        if( function_exists( 'electro_home_v10_slider_block' ) || function_exists( 'electro_home_v10_ads_block' ) ) {

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
        }

    }

}

Plugin::instance()->widgets_manager->register( new Electro_Elementor_Slider_With_Ads_Block_V2 );