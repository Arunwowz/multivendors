<?php

namespace Elementor;
use Automattic\WooCommerce\Admin\Features\OnboardingTasks\Tasks\Products;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Sidebar With Products.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Electro_Elementor_Sidebar_With_Products extends Widget_Base {

    /**
     * Get widget name.
     *
     * Retrieve Sidebar With Products widget name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'electro_elementor_sidebar_with_products_block';
    }

    /**
     * Get widget title.
     *
     * Retrieve Sidebar With Products widget title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return esc_html__( 'Sidebar With Products', 'electro-extensions' );
    }

    /**
     * Get widget icon.
     *
     * Retrieve Sidebar With Products widget icon.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget icon.
     */
    public function get_icon() {
        return 'eicon-product-categories';
    }

    /**
     * Get widget categories.
     *
     * Retrieve the list of categories the Sidebar With Products widget belongs to.
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
     * Register Sidebar With Products widget controls.
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
                'label'         => esc_html__( 'Content', 'electro-extensions' ),
                'tab'           => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'title',
            [
                'label'         => esc_html__( 'Title', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter title', 'electro-extensions' ),
            ]
        );

        $this->add_control(
            'enable_sidebar',
            [
                'label'         => esc_html__('Enable Sidebar', 'electro-extensions'),
                'type'          => Controls_Manager::SWITCHER,
                'description'   => esc_html__( 'Show sidebar block.', 'electro-extensions' ),
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => 'true',
            ]
        );

        $this->add_control(
            'sidebar_title',
            [
                'label'         => esc_html__( 'Sidebar Title', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter title', 'electro-extensions' ),
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
            'limit',
            [
                'label'         => esc_html__('Limit', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter limit of the products.', 'electro-extensions'),
                'default'       => 15,
            ]
        );

        $this->add_control(
            'columns',
            [
                'label'         => esc_html__('Columns', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter columns of the products.', 'electro-extensions'),
                'default'       => 6,
            ]
        );

        $this->add_control(
            'columns_tablet',
            [
                'label'         => esc_html__('Columns Tablet', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter columns of the products in tablet view.', 'electro-extensions'),
                'default'       => 3,
            ]
        );

        $this->add_control(
            'columns_wide',
            [
                'label'         => esc_html__('Columns Wide', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter columns of the products.', 'electro-extensions'),
                'default'       => 5,
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
            'enable_pagination',
            [
                'label'         => esc_html__('Enable Pagination', 'electro-extensions'),
                'type'          => Controls_Manager::SWITCHER,
                'description'   => esc_html__( 'Show pagination  block.', 'electro-extensions' ),
                'label_on'      => esc_html__( 'Enable', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Disable', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => true,
            ]
        );

        $this->add_control(
            'el_class',
            [
                'label'         => esc_html__( 'Extra class name', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'If you wish to style particular style use this field', 'electro-extensions' ),
            ]
        );

        $this->end_controls_section();
    
    }

    /**
     * Electro product wrap additional classes.
     */
    public function electro_product_wrap_additional_classes() {
        $settings = $this->get_settings_for_display();
        $tablet   = ! empty( $settings['columns_tablet'] ) ? 'row-cols-md-' . $settings['columns_tablet'] : 'row-cols-md-3';
        $classes  = array( 'products', 'list-unstyled', 'row', 'g-0', 'row-cols-2', $tablet );
        return $classes;
    }

    /**
     * Render Sidebar With Products output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function render() {

        $settings = $this->get_settings_for_display();

        extract( $settings );

        $shortcode_atts = function_exists( 'electro_get_atts_for_shortcode' ) ? electro_get_atts_for_shortcode( array( 'shortcode' => $shortcode_tag, 'product_category_slug' => $category, 'cat_operator' => $cat_operator, 'products_choice' => $products_choice, 'products_ids_skus' => $product_id, 'attribute' => $attribute, 'terms' => $terms, 'terms_operator' => $terms_operator ) ) : array();
        $shortcode_atts = wp_parse_args( $shortcode_atts, array( 'order' => $order, 'orderby' => $orderby,'per_page' => $limit, 'columns' => $columns, 'paginate' => $enable_pagination ) );

        if ( function_exists( 'electro_is_wide_enabled' ) && electro_is_wide_enabled() ) {
			$shortcode_atts[ 'columns_wide' ] = $columns_wide;
        }

        $attr = array(
			'class' => 'row categories-with-product'
		);

        if( isset( $el_class ) && !empty( $el_class ) ){
            $attr['class'] .=  ' ' . $el_class ;
        }

        if( function_exists( 'electro_home_v10_sidebar' ) ) { ?>
            <div <?php echo electro_render_attributes( $attr ); ?>><?php
			electro_home_v10_sidebar( $enable_sidebar, $sidebar_title );
            if ( is_woocommerce_activated() ) : ?>
				<div class="col">
					<section class="w-100 mb-0">
						<?php if ( isset( $title ) && ! empty( $title ) ) : ?>
							<header class="mb-0">
								<h2 class="h1"><?php echo esc_html( $title ); ?></h2>
							</header>
						<?php endif; 
                        $has_product = Products::has_products();
                        if ( $has_product ) {
                            add_filter( 'electro_product_loop_additional_classes',  array( $this, 'electro_product_wrap_additional_classes' ) );?>
                            <?php echo electro_do_shortcode( $shortcode_tag , $shortcode_atts ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
                            remove_filter( 'electro_product_loop_additional_classes', array( $this, 'electro_product_wrap_additional_classes' ) );
                        } else {
                            ?><p class="woocommerce-info mb-0"><?php esc_html_e( 'Add Products', 'electro' ) ?></p><?php
                        }?>
					</section>
				</div>
			<?php endif; ?>
            </div><?php
		}

    }
}

Plugin::instance()->widgets_manager->register( new Electro_Elementor_Sidebar_With_Products );