<?php

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Banner 1-6 Block Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Electro_Elementor_Banner_1_6_Block extends Widget_Base {

    /**
     * Get widget name.
     *
     * Retrieve Banner 1-6 Block widget name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'electro_elementor_banner_1_6_block';
    }

    /**
     * Get widget title.
     *
     * Retrieve Banner 1-6 Block widget title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return esc_html__( 'Banner 1-6 Block', 'electro-extensions' );
    }

    /**
     * Get widget icon.
     *
     * Retrieve Banner 1-6 Block widget icon.
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
     * Retrieve the list of categories in the Banner 1-6 Block widget belongs to.
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
     * Register Banner 1-6 Block widget controls.
     *
     * Adds different input fields to allow the user to change and customize the widget settings.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function register_controls() {
    
        $this->start_controls_section(
            'content_section',
            [
                'label'     => esc_html__( 'Content', 'electro-extensions' ),
                'tab'       => Controls_Manager::TAB_CONTENT,
            ]
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'image',
            [
                'label' => esc_html__( 'Image', 'electro-extensions' ),
                        'type'  => Controls_Manager::MEDIA
            ]
        );


        $repeater->add_control(
            'action_link',
            [
                'label'         => esc_html__( 'Action Link', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter URL', 'electro-extensions' )
            ]
        );

        $repeater->add_control(
            'el_classes',
            [
                'label'         => esc_html__( 'Element Class', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter class here', 'electro-extensions' )
            ]
        );

        $this->add_control(
            'ads_banners',
            [
                'label'       => esc_html__( 'Banners block', 'electro-extensions' ),
                'type'        => Controls_Manager::REPEATER,
                'description' => esc_html__( 'Maximum 7 Banners', 'electro-extensions' ),
                'fields'      => $repeater->get_controls(),
                'default'     => []
            ]
        );

        $this->add_control(
            'el_class',
            [
                'label'         => esc_html__( 'Section Element Class', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'default'		=> ''
            ]
        );



        $this->end_controls_section();
    }

    /**
     * Render Banner 1-6 Block output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function render() {

        $settings = $this->get_settings_for_display();

        extract( $settings );

        $ads_banners_args[] = array();

        if ( $settings['ads_banners'] ) {
            foreach (  $settings['ads_banners'] as $ad_banners ) {

                $ads_banners_args[] = array(
                    'image'         => !empty( $ad_banners['image']['url'] ) ? $ad_banners['image']['url']  : '',
                    'action_link'   => !empty( $ad_banners['action_link'] ) ? $ad_banners['action_link'] : '',
                    'el_classes'    => !empty( $ad_banners['el_classes'] ) ? $ad_banners['el_classes'] : '',
                );
            }
        }

        $section_class = 'section-home-banner-1-6';

		if( ! empty( $el_class ) ) {
			$section_class .= ' ' . $el_class;
		}

		$args = array();

		for( $i = 0; $i < 7; $i++ ) {
            $index = $i + 1;
			$image_attributes = '';
			if( isset( $ads_banners_args[$index]["image"] ) && ! empty( $ads_banners_args[$index]["image"] ) ) {
				$image_attributes =( $ads_banners_args[$index]["image"] );
			}

			$args[] = array(
				'image'			=> $image_attributes,
				'action_link'	=> isset( $ads_banners_args[$index]["action_link"] ) && ! empty( $ads_banners_args[$index]["action_link"] ) ? $ads_banners_args[$index]["action_link"] : '',
				'el_class'		=> isset( $ads_banners_args[$index]["el_classes"] ) && ! empty( $ads_banners_args[$index]["el_classes"] ) ? $ads_banners_args[$index]["el_classes"] : '',
			);
		}

		if( function_exists( 'electro_home_banner_1_6_block' ) ) {

			?><div class="<?php echo esc_attr( $section_class ); ?>"><?php
				electro_home_banner_1_6_block( $args );
			?></div><?php
		}

    }
}

Plugin::instance()->widgets_manager->register( new Electro_Elementor_Banner_1_6_Block );