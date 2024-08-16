<?php

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Three Banners Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Electro_Elementor_Three_Banners extends Widget_Base {

    /**
     * Get widget name.
     *
     * Retrieve Three Banners widget name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'electro_elementor_three_banners';
    }

    /**
     * Get widget title.
     *
     * Retrieve Three Banners widget title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return esc_html__( 'Three Banners', 'electro-extensions' );
    }

    /**
     * Get widget icon.
     *
     * Retrieve Three Banners widget icon.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget icon.
     */
    public function get_icon() {
        return 'eicon-banner';
    }

    /**
     * Get widget categories.
     *
     * Retrieve the list of categories in the Three Banners widget belongs to.
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
     * Register Three Banners widget controls.
     *
     * Adds different input fields to allow the user to change and customize the widget settings.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function register_controls() {

        $this->start_controls_section(
            'banner_1_section',
            [
                'label'     => esc_html__( 'Banner 1 Options', 'electro-extensions' ),
                'tab'       => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'b1_bg_image',
            [
                'label'         => esc_html__( 'Banner Background Image', 'electro-extensions' ),
                'type'          => Controls_Manager::MEDIA,
            ]
        );

        $this->add_control(
            'b1_image',
            [
                'label'         => esc_html__( 'Banner Image', 'electro-extensions' ),
                'type'          => Controls_Manager::MEDIA,
            ]
        );

        $this->add_control(
            'b1_action_link',
            [
                'label'         => esc_html__( 'Banner Link', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT
            ]
        );

        $this->add_control(
            'b1_description',
            [
                'label'         => esc_html__( 'Banner Description', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXTAREA,
                'default'		=> wp_kses_post( '<strong>#STAYHOME</strong> AND CATCH BIG <strong>DEALS</strong> ON THE GAMES &amp; CONSOLES' )
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'banner_2_section',
            [
                'label'     => esc_html__( 'Banner 2 Options', 'electro-extensions' ),
                'tab'       => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'b2_bg_image',
            [
                'label'         => esc_html__( 'Banner Background Image', 'electro-extensions' ),
                'type'          => Controls_Manager::MEDIA,
            ]
        );

        $this->add_control(
            'b2_action_link',
            [
                'label'         => esc_html__( 'Banner Link', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT
            ]
        );

        $this->add_control(
            'b2_description',
            [
                'label'         => esc_html__( 'Banner Description', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'default'		=> esc_html__( 'OFFICE LAPTOPSFOR WORK', 'electro' )
            ]
        );

        $this->add_control(
            'b2_price_pre_text',
            [
                'label'         => esc_html__('Banner Before Price Text', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter pre text', 'electro-extensions' ),
                'default'		=> esc_html__( 'FROM', 'electro' ),
            ]
        );

        $this->add_control(
            'b2_price_text',
            [
                'label'         => esc_html__( 'Banner Price Text', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter price', 'electro-extensions' ),
                'default'		=> wp_kses_post( '<sup class="h5 fw-bold mb-0">$</sup>749<sup class="h5 fw-bold mb-0">99</sup>' )
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'banner_3_section',
            [
                'label'     => esc_html__( 'Banner 3 Options', 'electro-extensions' ),
                'tab'       => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'b3_bg_image',
            [
                'label'         => esc_html__( 'Banner Background Image', 'electro-extensions' ),
                'type'          => Controls_Manager::MEDIA,
            ]
        );

        $this->add_control(
            'b3_action_link',
            [
                'label'         => esc_html__( 'Banner Link', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT
            ]
        );

        $this->add_control(
            'b3_title',
            [
                'label'         => esc_html__( 'Banner Title', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter banner title', 'electro-extensions' ),
                'default'		=> esc_html__( 'LIMITED', 'electro' )
            ]
        );

        $this->add_control(
            'b3_subtitle',
            [
                'label'         => esc_html__( 'Banner Subtitle', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter banner subtitle', 'electro-extensions' ),
                'default'		=> esc_html__( 'WEEK DEAL', 'electro' )
            ]
        );

        $this->add_control(
            'b3_description',
            [
                'label'         => esc_html__( 'Banner Description', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'default'		=> esc_html__( 'HURRY UP BEFORE OFFER WILL END', 'electro' )
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'additional_class',
            [
                'label'     => esc_html__( 'Additional Class', 'electro-extensions' ),
                'tab'       => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'el_class',
            [
                'label'         => esc_html__( 'Element Class', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'default'		=> ''
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render Three Banners output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function render() {

        $settings = $this->get_settings_for_display();

        extract( $settings );

        $args = apply_filters( 'electro_elementor_three_banners_block_element_args', array(
            'is_enabled' => true,
            'animation'  => false,
            'el_class'   => $el_class,
            'banners'    => array(
                'banner_1' => array(
                    'bg_image'          => isset( $b1_bg_image['url'] ) && !empty( $b1_bg_image['url'] ) ? $b1_bg_image['url'] : '',
                    'image'             => isset( $b1_image['url'] ) && !empty( $b1_image['url'] ) ? $b1_image['url'] : '',
                    'url'               => $b1_action_link,
                    'desc'              => $b1_description
                ),
                'banner_2' => array(
                    'bg_image'          => isset( $b2_bg_image['url'] ) && !empty( $b2_bg_image['url'] ) ? $b2_bg_image['url'] : '',
                    'url'               => $b2_action_link,
                    'desc'              => $b2_description,
                    'price_pre_text'    => $b2_price_pre_text,
                    'price_text'        => $b2_price_text
                ),
                'banner_3' => array(
                    'bg_image'          => isset( $b3_bg_image['url'] ) && !empty( $b3_bg_image['url'] ) ? $b3_bg_image['url'] : '',
                    'url'               => $b3_action_link,
                    'title'             => $b3_title,
                    'subtitle'          => $b3_subtitle,
                    'desc'              => $b3_description,
                )
            )
        ));

		if( function_exists( 'electro_home_v12_banners' ) ) {

			electro_home_v12_banners( $args );
		}
    }

}

Plugin::instance()->widgets_manager->register( new Electro_Elementor_Three_Banners );