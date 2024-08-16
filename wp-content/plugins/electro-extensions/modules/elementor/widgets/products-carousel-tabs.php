<?php

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Product Tabs Carousel Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Electro_Elementor_Products_Tabs_Elements extends Widget_Base {

    /**
     * Get widget name.
     *
     * Retrieve Product Tabs Carousel name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'electro_elementor_products_carousel_tabs';
    }

    /**
     * Get widget title.
     *
     * Retrieve Product Tabs Carousel title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return esc_html__( 'Product Tabs Carousel', 'electro-extensions' );
    }

    /**
     * Get widget icon.
     *
     * Retrieve Product Tabs Carousel icon.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget icon.
     */
    public function get_icon() {
        return 'eicon-tabs';
    }

    /**
     * Get widget categories.
     *
     * Retrieve the list of categories the Product Tabs Carousel belongs to.
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
     * Register Product Tabs Carousel Product controls.
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
            'nav_align',
            [
                'label'     => esc_html__( 'Header Align', 'electro-extensions' ),
                'type'      => Controls_Manager::SELECT,
                'options'   => [
                    'center'     => esc_html__( 'Center','electro-extensions'),
                    'right'      => esc_html__( 'Right','electro-extensions'),
                    'left'       => esc_html__( 'Left','electro-extensions')
                ],
                'default' => 'left',
            ]
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'title',
            [
                'label' => esc_html__( 'title', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
                'description'   => esc_html__('Enter title.', 'electro-extensions'),
            ]
        );

        $repeater->add_control(
            'shortcode_tag',
            [
                'label' => esc_html__( 'Shortcode', 'electro-extensions' ),
                'type'  => Controls_Manager::SELECT,
                'options'   => [
                    'featured_products'     => esc_html__( 'Featured Products','electro-extensions'),
                    'sale_products'         => esc_html__( 'On Sale Products','electro-extensions'),
                    'top_rated_products'    => esc_html__( 'Top Rated Products','electro-extensions'),
                    'recent_products'       => esc_html__( 'Recent Products','electro-extensions'),
                    'best_selling_products' => esc_html__( 'Best Selling Products','electro-extensions'),
                    'product_category'      => esc_html__( 'Product Category','electro-extensions'),
                    'products'              => esc_html__( 'Products','electro-extensions')
                ],
                'default' => 'recent_products',
            ]
        );

        $repeater->add_control(
            'per_page',
            [
                'label' => esc_html__( 'Limit', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
                'description'   => esc_html__('Enter the number of products to display.', 'electro-extensions'),
                'default'=>'12',
            ]
        );

        $repeater->add_control(
            'orderby',
            [
                'label' => esc_html__( 'Order By', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
                'description'   => esc_html__('Enter orderby.', 'electro-extensions'),
                'default'         => 'date',
            ]
        );

        $repeater->add_control(
            'order',
            [
                'label' => esc_html__( 'Order', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
                'description'   => esc_html__('Enter order.', 'electro-extensions'),
                'default'       => 'desc',
            ]
        );

        $repeater->add_control(
            'products_choice',
            [
                'label' => esc_html__( 'Product Choice', 'electro-extensions' ),
                'type'  => Controls_Manager::SELECT,
                'options'   => [
                    'ids'       =>  esc_html__( 'IDs', 'electro-extensions' )       ,
                    'skus'      =>  esc_html__( 'SKUs', 'electro-extensions' )       ,
                ],
                'default' => 'ids',
            ]
        );

        $repeater->add_control(
            'product_id',
            [
                'label' => esc_html__( 'Product IDs or SKUs', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
                'description'   => esc_html__('Enter IDs/SKUs separate by comma(,).', 'electro-extensions'),
            ]
        );

        $repeater->add_control(
            'category',
            [
                'label' => esc_html__( 'Category', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
                'description'   => esc_html__('Enter slug separate by comma(,).', 'electro-extensions'),
            ]
        );

        $repeater->add_control(
            'cat_operator',
            [
                'label' => esc_html__( 'Category Operator', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
                'description'   => esc_html__('Operator to compare categories. Possible values are \'IN\', \'NOT IN\', \'AND\'.', 'electro-extensions'),
                'default'   => 'IN',
            ]
        );

        $this->add_control(
            'tabs',
            [
                'label'  => esc_html__( 'Products Tabs Element', 'electro-extensions' ),
                'type'   => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [],
            ]
        );

        $this->add_control(
            'items',
            [
                'label'         => esc_html__( 'Carousel: Items', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'default'       => '4',
            ]
        );

        $this->add_control(
            'items_0',
            [
                'label'         => esc_html__('Carousel: Items(0 - 479)', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter the number of items to display.', 'electro-extensions'),
            ]
        );


        $this->add_control(
            'items_480',
            [
                'label'         => esc_html__('Carousel: Items(480 - 767)', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter the number of items to display.', 'electro-extensions'),
            ]
        );


        $this->add_control(
            'items_768',
            [
                'label'         => esc_html__('Carousel: Items(768 - 991)', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter the number of items to display.', 'electro-extensions'),
            ]
        );


        $this->add_control(
            'items_992',
            [
                'label'         => esc_html__('Carousel: Items(992 - 1199)', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter the number of items to display.', 'electro-extensions'),
            ]
        );

        $this->add_control(
            'items_1200',
            [
                'label'         => esc_html__('Carousel: Items(1200 - 1440)', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter the number of items to display.', 'electro-extensions'),
            ]
        );

        $this->add_control(
            'is_dots',
            [
                'label'         => esc_html__( 'Carousel: Show Dots', 'electro-extensions' ),
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
                'label'         => esc_html__( 'Carousel: Autoplay', 'electro-extensions' ),
                'type'          => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => 'false',
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
     * Render Product Tabs Carousel output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function render() {

        $settings = $this->get_settings();

        extract( $settings );

        if( is_object( $tabs ) || is_array( $tabs ) ) {
            $tabs = json_decode( json_encode( $tabs ), true );
        } else {
            $tabs = json_decode( urldecode( $tabs ), true );
        }

        if( $nav_align === 'left' ) {
			$el_class .= ' tabs-nav-align-left';
		} elseif( $nav_align === 'right' ) {
			$el_class .= ' tabs-nav-align-right';
		}

        $tabs_args = array();

        if( is_array( $tabs ) ) {
            foreach ( $tabs as $key => $tab ) {

                extract(shortcode_atts(array(
                    'title'                 => '',
                    'shortcode_tag'         => 'recent_products',
                    'per_page'              => 6,
                    'orderby'               => 'date',
                    'order'                 => 'desc',
                    'products_choice'       => 'ids',
                    'product_id'            => '',
                    'category'              => '',
                    'cat_operator'          => 'IN',
                ), $tab));

                $shortcode_atts = function_exists( 'electro_get_atts_for_shortcode' ) ? electro_get_atts_for_shortcode( array( 'shortcode' => $shortcode_tag, 'product_category_slug' => $category, 'cat_operator' => $cat_operator, 'products_choice' => $products_choice, 'products_ids_skus' => $product_id ) ) : array();
                $shortcode_atts = wp_parse_args( $shortcode_atts, array( 'order' => $order, 'orderby' => $orderby, 'columns' => $items, 'per_page' => $per_page ) );

                $tabs_args[] = array(
                    'title'             => $title,
                    'shortcode_tag'     => $shortcode_tag,
                    'atts'              => $shortcode_atts,
                );
            }
        }

        $args = array(
            'tabs'              => $tabs_args,
            'section_class'     => $el_class,
            'carousel_args'     => array(
                'dots'          => isset( $is_dots) ? filter_var( $is_dots, FILTER_VALIDATE_BOOLEAN): '',
                'items'         => isset( $items ) ? $items : '',
                'autoplay'      => isset( $is_autoplay) ? filter_var( $is_autoplay, FILTER_VALIDATE_BOOLEAN): '',
                'responsive'        => array(
                    '0'     => array( 'items'   => $items_0 ),
                    '480'   => array( 'items'   => $items_480 ),
                    '768'   => array( 'items'   => $items_768 ),
                    '992'   => array( 'items'   => $items_992 ),
                    '1200'  => array( 'items'   => $items_1200 ),
                    '1441'  => array( 'items'   => $items ),
                )
            )
        );

        if( function_exists( 'electro_products_carousel_tabs' ) ) {
            electro_products_carousel_tabs( $args );
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

Plugin::instance()->widgets_manager->register( new Electro_Elementor_Products_Tabs_Elements );