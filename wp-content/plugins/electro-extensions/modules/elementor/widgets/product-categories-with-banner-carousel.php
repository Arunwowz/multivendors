<?php

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Product Categories With Banner Carousel Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Electro_Elementor_Product_Categories_With_Banner_Carousel extends Widget_Base {

    /**
     * Get widget name.
     *
     * Retrieve Product Categories With Banner Carousel widget name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'electro_elementor_product_categories_with_banner_carousel';
    }

    /**
     * Get widget title.
     *
     * Retrieve Product Categories With Banner Carousel widget title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return esc_html__( 'Product Categories With Banner Carousel', 'electro-extensions' );
    }

    /**
     * Get widget icon.
     *
     * Retrieve Product Categories With Banner Carousel widget icon.
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
     * Retrieve the list of categories in the Product Categories With Banner Carousel widget belongs to.
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
     * Register Product Categories With Banner Carousel widget controls.
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
            'title',
            [
                'label'         => esc_html__( 'Enter Section Title', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter title', 'electro-extensions' ),
            ]
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'enable_category_1',
            [
                'label'         => esc_html__( 'Enable Categories 1', 'electro-extensions' ),
                'type'          => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => 'true'
            ]
        );

        $repeater->add_control(
            'cat_1_limit',
            [
                'label'         => esc_html__( 'Categories List 1: limit', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter category limit', 'electro-extensions' ),
                'default'       => 3
            ]
        );

        $repeater->add_control(
            'cat_1_child_limit',
            [
                'label'         => esc_html__( 'Categories List 1: Child limit', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter category child limt', 'electro-extensions' ),
                'default'       => 5
            ]
        );

        $repeater->add_control(
            'cat_1_has_no_products',
            [
                'label'         => esc_html__( 'Categories List 1: Hide Empty products', 'electro-extensions' ),
                'type'          => Controls_Manager::SWITCHER,
                'description'   => esc_html__( 'Show Categories does not have products', 'electro-extensions' ),
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => 'true'
            ]
        );

        $repeater->add_control(
            'cat_1_orderby',
            [
                'label'         => esc_html__( 'Categories List 1: Order by', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'default'       => 'name',
                'description'   => esc_html__( ' Sort retrieved posts by parameter. Defaults to \'name\'. One or more options can be passed', 'electro-extensions' )
            ]
        );

        $repeater->add_control(
            'cat_1_order',
            [
                'label'         => esc_html__( 'Order', 'electro-extensions' ),
                'type'          => Controls_Manager::SELECT,
                'description'   => esc_html__( 'Designates the ascending or descending order of the \'orderby\' parameter. Defaults to \'ASC\'.', 'electro-extensions' ),
                'options'       => [
                        'DESC'     => esc_html__( 'DESC','electro-extensions'),
                        'ASC'      => esc_html__( 'ASC','electro-extensions')
                ],
                'default'       => 'ASC'
            ]
        );

        $repeater->add_control(
            'cat_1_include',
            [
                'label'         => esc_html__( 'Categories List 1: Include ID\'s', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT
            ]
        );

        $repeater->add_control(
            'cat_1_slugs',
            [
                'label'         => esc_html__( 'Categories List 1: Include slug\'s', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT
            ]
        );

        $repeater->add_control(
            'enable_category_2',
            [
                'label'         => esc_html__( 'Enable Categories 2', 'electro-extensions' ),
                'type'          => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => 'true'
            ]
        );

        $repeater->add_control(
            'cat_2_limit',
            [
                'label'         => esc_html__( 'Categories List 2: limit', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter category limt', 'electro-extensions' ),
                'default'       => 7
            ]
        );

        $repeater->add_control(
            'cat_2_has_no_products',
            [
                'label'         => esc_html__( 'Categories List 2: Hide Empty products', 'electro-extensions' ),
                'type'          => Controls_Manager::SWITCHER,
                'description'   => esc_html__( 'Show Categories does not have products', 'electro-extensions' ),
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => 'true'
            ]
        );

        $repeater->add_control(
            'cat_2_orderby',
            [
                'label'         => esc_html__( 'Categories List 2: Order by', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'default'       => 'date',
                'description'   => esc_html__( ' Sort retrieved posts by parameter. Defaults to \'date\'. One or more options can be passed', 'electro-extensions' )
            ]
        );

        $repeater->add_control(
            'cat_2_order',
            [
                'label'         => esc_html__( 'Order', 'electro-extensions' ),
                'type'          => Controls_Manager::SELECT,
                'description'   => esc_html__( 'Designates the ascending or descending order of the \'orderby\' parameter. Defaults to \'DESC\'.', 'electro-extensions' ),
                'options'       => [
                        'DESC'     => esc_html__( 'DESC','electro-extensions'),
                        'ASC'      => esc_html__( 'ASC','electro-extensions')
                ],
                'default'       => 'DESC'
            ]
        );

        $repeater->add_control(
            'cat_2_include',
            [
                'label'         => esc_html__( 'Categories List 2: Include ID\'s', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT
            ]
        );

        $repeater->add_control(
            'cat_2_slugs',
            [
                'label'         => esc_html__( 'Categories List 2: Include slug\'s', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
            ]
        );

        $repeater->add_control(
            'enable_banner',
            [
                'label'         => esc_html__( 'Enable Banner ?', 'electro-extensions' ),
                'type'          => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => 'true'
            ]
        );

        $repeater->add_control(
            'image',
            [
                'label' => esc_html__( 'Banner Image', 'electro-extensions' ),
                'type'  => Controls_Manager::MEDIA
            ]
        );

        $repeater->add_control(
            'img_action_link',
            [
                'label' => esc_html__( 'Banner Action Link', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
                'placeholder' => esc_html__( 'Enter your link text here', 'electro-extensions' )
            ]
        );

        $this->add_control(
            'elements',
            [
                'label'  => esc_html__( 'Carousel Elements', 'electro-extensions' ),
                'type'   => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => []
            ]
        );

        $this->add_control(
            'is_nav',
            [
                'label'         => esc_html__('Carousel: Show Navigation', 'electro-extensions'),
                'type'          => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => 'true'
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
                'default'       => 'true'
            ]
        );

        $this->add_control(
            'is_autoplay',
            [
                'label'         => esc_html__('Enable Autoplay', 'electro-extensions'),
                'type'          => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => false
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
     * Render Product Categories With Banner Carousel output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function render() {

        $settings = $this->get_settings_for_display();

        extract( $settings );

        if( is_object( $elements ) || is_array( $elements ) ) {
            $elements = json_decode( json_encode( $elements ), true );
        } else {
            $elements = json_decode( urldecode( $elements ), true );
        }

		$content_args = array();

		if( is_array( $elements ) ) {
			foreach ( $elements as $key => $element ) {
                
				$category_1_args = array(
					'number'			=> isset( $element['cat_1_limit'] ) ? $element['cat_1_limit'] : 5,
					'child_number'		=> isset( $element['cat_1_child_limit'] ) ? $element['cat_1_child_limit'] : 5,
					'hide_empty'		=> isset( $element['cat_1_has_no_products'] ) ? $element['cat_1_has_no_products'] : false,
					'orderby' 			=> isset( $element['cat_1_orderby'] ) ? $element['cat_1_orderby'] : 'name',
					'order' 			=> isset( $element['cat_1_order'] ) ? $element['cat_1_order'] : 'ASC',
					'slugs' 			=> isset( $element['cat_1_slugs'] ) ? $element['cat_1_slugs'] : '',
					'includes' 			=> isset( $element['cat_1_include'] ) ? $element['cat_1_include'] : '',
				);

				$category_2_args = array(
					'number'			=> isset( $element['cat_2_limit'] ) ? $element['cat_2_limit'] : 7,
					'hide_empty'		=> isset( $element['cat_2_has_no_products'] ) ? $element['cat_2_has_no_products'] : true,
					'orderby' 			=> isset( $element['cat_2_orderby'] ) ? $element['cat_2_orderby'] : 'name',
					'order' 			=> isset( $element['cat_2_order'] ) ? $element['cat_2_order'] : 'ASC',
					'slugs' 			=> isset( $element['cat_2_slugs'] ) ? $element['cat_2_slugs'] : '',
					'includes' 			=> isset( $element['cat_2_include'] ) ? $element['cat_2_include'] : '',
				);

				$content_args[] = array(
					'enable_category_1'	=> isset( $element['enable_category_1'] ) ? $element['enable_category_1'] : '',
					'category_1_args'	=> $category_1_args,
					'enable_category_2'	=> isset( $element['enable_category_2'] ) ? $element['enable_category_2'] : '',
					'category_2_args'	=> $category_2_args,
					'enable_banner'		=> isset( $element['enable_banner'] ) ? $element['enable_banner'] : '',
					'image'				=> isset( $element['image']['url'] ) && ! empty( $element['image']['url'] ) ? array( $element['image']['url'] ) : '',
					'img_action_link'	=> isset( $element['img_action_link'] ) ? $element['img_action_link'] : '',
				);
			}
		}
       
		$carousel_args = array(
			'items'				=> 1,
			'dots'				=> false,
			'nav'				=> $is_nav,
			'touchDrag'			=> $is_touchdrag,
			'autoplay'			=> $is_autoplay,
			'rtl'				=> is_rtl() ? true : false,
			'navText'			=> is_rtl() ? array( '<i class="fa fa-angle-right"></i>', '<i class="fa fa-angle-left"></i>' ) : array( '<i class="fa fa-angle-left"></i>', '<i class="fa fa-angle-right"></i>' ),
		);

		$args = array(
			'section_title'		=> $title,
			'content'			=> $content_args,
			'carousel_args'		=> $carousel_args,
			'section_class'		=> $el_class,
		);

		if( function_exists( 'electro_home_product_categories_with_banner_carousel' ) ) {

			electro_home_product_categories_with_banner_carousel( $args );
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

Plugin::instance()->widgets_manager->register( new Electro_Elementor_Product_Categories_With_Banner_Carousel );