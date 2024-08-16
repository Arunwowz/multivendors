<?php

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Banner With Products Carousel Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Electro_Elementor_Banner_With_Products_Carousel extends Widget_Base {

    /**
     * Get widget name.
     *
     * Retrieve Banner With Products Carousel widget name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'electro_elementor_banner_with_products_carousel';
    }

    /**
     * Get widget title.
     *
     * Retrieve Banner With Products Carousel widget title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return esc_html__( 'Banner With Products Carousel', 'electro-extensions' );
    }

    /**
     * Get widget icon.
     *
     * Retrieve Banner With Products Carousel widget icon.
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
     * Retrieve the list of categories in the Banner With Products Carousel widget belongs to.
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
     * Register Banner With Products Carousel widget controls.
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
            'image',
            [
                'label' => esc_html__( 'Image', 'electro-extensions' ),
                'type'  => Controls_Manager::MEDIA,
            ]
        );

        $this->add_control(
            'title',
            [
                'label'         => esc_html__( 'Title', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter title', 'electro-extensions' ),
                'default'		=> wp_kses_post( 'OUTLET DEALS <span class="d-block">CLEARANCE</span>' )
            ]
        );

        $this->add_control(
            'subtitle',
            [
                'label'         => esc_html__( 'Subtitle', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter subtitle', 'electro-extensions' ),
                'default'		=> esc_html__( 'SAVE UP TO', 'electro' )
            ]
        );

        $this->add_control(
            'offer_text',
            [
                'label'         => esc_html__( 'Offer Text', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter offer text', 'electro-extensions' ),
                'default'		=> wp_kses_post( '70<sup class="font-size-36">%</sup><sub class="font-size-16">OFF!</sub>' )
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label'         => esc_html__( 'Button Text', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter button text', 'electro-extensions' ),
                'default'		=> esc_html__( 'Start Buying', 'electro' )
            ]
        );

        $this->add_control(
            'button_url',
            [
                'label'         => esc_html__( 'Button URL', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter URL', 'electro-extensions' ),
            ]
        );

        $this->add_control(
            'shortcode_tag',
            [
                'label'     => esc_html__( 'Shortcode Tags', 'electro-extensions' ),
                'type'      => Controls_Manager::SELECT,
                'options'   => [
                    'featured_products'     => esc_html__( 'Featured Products','electro-extensions'),
                    'sale_products'         => esc_html__( 'On Sale Products','electro-extensions'),
                    'top_rated_products'    => esc_html__( 'Top Rated Products','electro-extensions'),
                    'recent_products'       => esc_html__( 'Recent Products','electro-extensions'),
                    'best_selling_products' => esc_html__( 'Best Selling Products','electro-extensions'),
                    'product_category'      => esc_html__( 'Product Category','electro-extensions'),
                    'product_attribute'     => esc_html__( 'Product Attribute','electro-extensions'),
                    'products'              => esc_html__( 'Products','electro-extensions')
                ],
                'default' => 'recent_products',
            ]
        );

        $this->add_control(
            'products_choice',
            [
                'label'         => esc_html__('Product Choice', 'electro-extensions'),
                'type'          => Controls_Manager::SELECT,
                'options'       => [
                    'ids'    => esc_html__( 'IDs','electro-extensions'),
                    'skus'   => esc_html__( 'SKUs','electro-extensions'),
                ],
                'condition'     => [
                    'shortcode_tag' => 'products',
                ],
            ]
        );

        $this->add_control(
            'product_id',
            [
                'label'         => esc_html__( 'Product id or SKUs', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'description'   => esc_html__( 'Separate multiple Product ids or SKUs by Comma ', 'electro-extensions' ),
                'placeholder'   => esc_html__( 'Enter IDs/SKUs separate by comma(,).', 'electro-extensions' ),
                'condition'     => [
                    'shortcode_tag' => 'products',
                ],
            ]
        );

        $this->add_control(
            'per_page',
            [
                'label'         => esc_html__('Limit', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter limit of the products.', 'electro-extensions'),
                'default'       => 10,
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label'         => esc_html__( 'Orderby', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'description' => esc_html__( ' Sort retrieved posts by parameter. Defaults to \'date\'. One or more options can be passed', 'electro-extensions' ),
                'default'       => 'date',
            ]
        );

        $this->add_control(
            'order',
            [
                'label'         => esc_html__( 'Order', 'electro-extensions' ),
                'type'          => Controls_Manager::SELECT,
                'description'   => esc_html__( 'Designates the ascending or descending order of the \'orderby\' parameter. Defaults to \'ASC\'.', 'electro-extensions' ),
                'options'       => [
                        'DESC'     => esc_html__( 'DESC','electro-extensions'),
                        'ASC'      => esc_html__( 'ASC','electro-extensions')
                ],
                'default'       => 'ASC',
            ]
        );

        $this->add_control( 
            'category',
            [
                'label'         => esc_html__('Category', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter slug separate by comma(,).', 'electro-extensions'),
                'condition'     => [
                    'shortcode_tag' => 'product_category',
                ],
            ]
        );

        $this->add_control(
            'cat_operator',
            [
                'label'         => esc_html__('Category Operator', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'description'   => esc_html__('Operator to compare categories. Possible values are \'IN\', \'NOT IN\', \'AND\'.', 'electro-extensions'),
                'default'       => 'IN',
                'condition'     => [
                    'shortcode_tag' => 'product_category',
                ],
            ]
        );

        $this->add_control(
            'attribute',
            [
                'label'         => esc_html__('Attribute', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter single attribute slug.', 'electro-extensions'),
                'condition'     => [
                    'shortcode_tag' => 'product_attribute',
                ],
            ]
        );

        $this->add_control(
            'terms',
            [
                'label'         => esc_html__('Terms', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter single attribute slug.', 'electro-extensions'),
                'condition'     => [
                    'shortcode_tag' => 'product_attribute',
                ],
            ]
        );

        $this->add_control(
            'terms_operator',
            [
                'label'         => esc_html__('Terms Operator', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Operator to compare categories. Possible values are \'IN\', \'NOT IN\', \'AND\'.', 'electro-extensions'),
                'default'       => 'IN',
                'condition'     => [
                    'shortcode_tag' => 'product_attribute',
                ],
            ]
        );

        $this->add_control(
            'autoplay',
            [
                'label'         => esc_html__('Enable Autoplay', 'electro-extensions'),
                'type'          => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => false,
            ]
        );

        $this->add_control(
            'items',
            [
                'label'         => esc_html__( 'Carousel Items', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter carousel items limit', 'electro-extensions' ),
                'default'		=> '5'
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
     * Render Banner With Products Carousel output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function render() {

        $settings = $this->get_settings_for_display();

        extract( $settings );

        $image_url = !empty( $image['url'] ) ? $image['url'] : '';
        
        $args = [
            'is_enabled' => true,
            'animation'  => false,
            'el_class'   => $el_class,
            'bg_image'     => $image_url,
            'banner'       => [
                'title'       => $title,
                'subtitle'    => $subtitle,
                'offer_text'  => $offer_text,
                'button_text' => $button_text,
                'button_url'  => $button_url
            ],
            'products' => [
                'shortcode'             => $shortcode_tag,
                'products_choice'       => $products_choice,
                'products_ids_skus'     => $product_id,
                'product_category_slug' => $category,
                'cat_operator'          => $cat_operator,
                'attribute'             => $attribute,
                'terms'                 => $terms,
                'terms_operator'        => $terms_operator,
                'shortcode_atts'    => [
                    'per_page' => $per_page,
                    'orderby'  => $orderby,
                    'order'    => $order
                ],
                'carousel_args' => [
                    'slideToShow' => $items,
                    'autoplay'    => $autoplay
                ]
            ]
        ];
        
        if( function_exists( 'electro_home_v11_banner_with_products_carousel' ) ) {

            electro_home_v11_banner_with_products_carousel( $args );
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

Plugin::instance()->widgets_manager->register( new Electro_Elementor_Banner_With_Products_Carousel );