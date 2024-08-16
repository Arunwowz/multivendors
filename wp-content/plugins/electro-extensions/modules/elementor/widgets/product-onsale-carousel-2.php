<?php

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Electro Onsale Product Carousel 2 Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Electro_Elementor_Onsale_Product_Carousel_2 extends Widget_Base {

    /**
     * Get widget name.
     *
     * Retrieve Onsale Product Carousel 2 name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'electro_elementor_products_onsale_carousel_2_block';
    }

    /**
     * Get widget title.
     *
     * Retrieve Onsale Product Carousel 2 title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return esc_html__( 'Onsale Product Carousel 2', 'electro-extensions' );
    }

    /**
     * Get widget icon.
     *
     * Retrieve Onsale Product Carousel 2 icon.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget icon.
     */
    public function get_icon() {
        return 'eicon-carousel';
    }

    /**
     * Get widget categories.
     *
     * Retrieve the list of categories the Onsale Product Carousel 2 belongs to.
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
     * Register Onsale Product Carousel 2 controls.
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

        $this->add_control(
            'limit',
            [
                'label'         => esc_html__( 'Number of Products to display', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'default'       => 2,
                'placeholder'   => esc_html__( 'Enter number of products to display', 'electro-extensions' ),
            ]
        );

        $this->add_control(
            'product_choice',
            [
                'label'         => esc_html__( 'Product Choice', 'electro-extensions' ),
                'type'          => Controls_Manager::SELECT,
                'options' => [

                    'recent'    =>esc_html__( 'Recent', 'electro-extensions' ),
                    'random'    =>esc_html__( 'Random', 'electro-extensions' ),
                    'specific'  =>esc_html__( 'Specific', 'electro-extensions' ),
                ],
                'default'=> 'specific',
            ]
        );

        $this->add_control(
            'product_id',
            [
                'label'         => esc_html__('Product ID', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'description'   => esc_html__('Enter the product id seperate by comma(,).', 'electro-extensions'),
            ]
        );

        $this->add_control(
            'show_timer',
            [
                'label'         => esc_html__('Show Timer', 'electro-extensions'),
                'type'          => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => 'true',
            ]
        );

        $this->add_control(
            'is_nav',
            [
                'label'         => esc_html__('Show Navigation', 'electro-extensions'),
                'type'          => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => 'true',
            ]
        );

        $this->add_control(
            'is_touchdrag',
            [
                'label'         => esc_html__('Carousel: Enable Touch Drag', 'electro-extensions'),
                'type'          => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => 'true',
            ]
        );

        $this->add_control(
            'is_autoplay',
            [
                'label'         => esc_html__('Carousel: Autoplay', 'electro-extensions'),
                'type'          => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => false,
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
     * Render Onsale Product Carousel 2 output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function render() {

        $settings = $this->get_settings();

        extract( $settings );

        $section_args = array(
            'limit'				=> $limit,
            'product_choice'	=> $product_choice,
            'product_ids'		=> $product_id,
            'is_nav'			=> $is_nav,
            'show_timer'		=> $show_timer,
            'section_class'     => $el_class
        );
    
        $carousel_args = array(
            'nav'				=> $is_nav,
            'touchDrag'			=> $is_touchdrag,
            'rtl'				=> is_rtl() ? true : false,
            'navText'			=> is_rtl() ? array( '<i class="fa fa-angle-right"></i>', '<i class="fa fa-angle-left"></i>' ) : array( '<i class="fa fa-angle-left"></i>', '<i class="fa fa-angle-right"></i>' ),
            'autoplay'			=> $is_autoplay,
        );
    
        if( function_exists( 'electro_onsale_product_carousel_v9' ) ) {

            electro_onsale_product_carousel_v9( $section_args, $carousel_args );
        }

        $this->render_script();
    
    }

    public function render_script() {
		if ( Plugin::$instance->editor->is_edit_mode() ) :
            
			?><script type="text/javascript">
                (function($) {
                    $(document).ready( function() {
                        $( '[data-ride="owl-carousel"]').each( function() {
                            var $this = $( this ), carouselDiv = $this.data( 'carouselSelector' ), carouselOptions = $this.data( 'carouselOptions' ),
                            shouldReplaceActiveClass = $this.data( 'replaceActiveClass' ), $carousel_elem;

                            if ( 'self' === carouselDiv ) {
                                $carousel_elem = $this.owlCarousel( carouselOptions );
                            } else {
                                $carousel_elem = $this.find( carouselDiv );
                            }

                            if ( true === shouldReplaceActiveClass ) {
                                $carousel_elem.on( 'initialized.owl.carousel translated.owl.carousel', function() {
                                    var $this = $(this);

                                    $this.find( '.owl-item.last-active' ).each( function() {
                                        $(this).removeClass( 'last-active' );
                                    });

                                    $(this).find( '.owl-item.active' ).last().addClass( 'last-active' );
                                });
                            }

                            $carousel_elem.owlCarousel( carouselOptions );
                        });
                    });
                })(jQuery);
            </script><?php

		endif;
	}

}

Plugin::instance()->widgets_manager->register( new Electro_Elementor_Onsale_Product_Carousel_2 );