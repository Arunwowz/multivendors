<?php

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Brands With Category Block.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Electro_Elementor_Brands_With_Category_Block extends Widget_Base {

    protected $_has_template_content = false;

    /**
     * Get widget name.
     *
     * Retrieve Brands With Category Block widget name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'electro_elementor_brands_with_category_block';
    }

    /**
     * Get widget title.
     *
     * Retrieve Brands With Category Block widget title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return esc_html__( 'Brands With Category Block', 'electro-extensions' );
    }

    /**
     * Get widget icon.
     *
     * Retrieve Brands With Category Block widget icon.
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
     * Retrieve the list of categories the Brands With Category Block widget belongs to.
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
     * Register Brands With Category Block widget controls.
     *
     * Adds different input fields to allow the user to change and customize the widget settings.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function register_controls() {

        $this->start_controls_section(
            'brands_section',
            [
                'label'     => esc_html__( 'Brands', 'electro-extensions' ),
                'tab'       => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'brands_title',
            [
                'label'         => esc_html__( 'Title', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter title', 'electro-extensions' ),
                'default'       => 'Brands:',
            ]
        );

        $this->add_control(
            'brands_action_text',
            [
                'label'         => esc_html__( 'Action Text', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter Action Text', 'electro-extensions' ),
                'default'       => '+ More Brands',
            ]
        );

        $this->add_control(
            'brands_action_link',
            [
                'label'         => esc_html__( 'Action Link', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter Action Link', 'electro-extensions' ),
            ]
        );

        $this->add_control(
            'brands_limit',
            [
                'label'         => esc_html__('Limit', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter limit of the products.', 'electro-extensions'),
                'default'       => 6,
            ]
        );

        $this->add_control(
            'brands_orderby',
            [
                'label'         => esc_html__( 'Orderby', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'description' => esc_html__( ' Sort retrieved posts by parameter. Defaults to \'name\'. One or more options can be passed', 'electro-extensions' ),
                'default'       => 'name',
            ]
        );

        $this->add_control(
            'brands_order',
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
            'brands_hide_empty',
            [
                'label'         => esc_html__('Hide Empty Brands ', 'electro-extensions'),
                'type'          => Controls_Manager::SWITCHER,
                'description' => esc_html__( 'Hide Brands does not have products ', 'electro-extensions' ),
                'label_on'      => esc_html__( 'Hidden', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Hide', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => false,
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'category_section',
            [
                'label'     => esc_html__( 'Categories', 'electro-extensions' ),
                'tab'       => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'category_child_limit',
            [
                'label'         => esc_html__( 'Category Child Limit', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'description' => esc_html__( 'Enter limit in numbers of child category', 'electro-extensions' ),
                'placeholder'   => esc_html__( 'Enter category child limit', 'electro-extensions' ),
                'default'       => '5',
            ]
        );

        $this->add_control(
            'category_orderby',
            [
                'label'         => esc_html__( 'Orderby', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'description' => esc_html__( ' Sort retrieved posts by parameter. Defaults to \'name\'. One or more options can be passed', 'electro-extensions' ),
                'default'       => 'name',
            ]
        );

        $this->add_control(
            'category_order',
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
            'category_hide_empty',
            [
                'label'         => esc_html__('Hide Empty Category ', 'electro-extensions'),
                'type'          => Controls_Manager::SWITCHER,
                'description' => esc_html__( 'Hide categories does not have products ', 'electro-extensions' ),
                'label_on'      => esc_html__( 'Hidden', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Hide', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => false,
            ]
        );

        $this->add_control(
            'category_include',
            [
                'label'         => esc_html__('Include ID\'s', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter id separate by comma(,).', 'electro-extensions'),
            ]
        );

        $this->add_control(
            'category_limit',
            [
                'label'         => esc_html__( 'Number of Categories to display', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'default'       => '8'
            ]
        );

        $this->add_control(
            'category_action_text',
            [
                'label'         => esc_html__( 'Action Text', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter Action Text', 'electro-extensions' ),
                'default'       => '...'
            ]
        );

        $this->add_control(
            'category_arrow_icon',
            [
                'label'         => esc_html__( 'Icon class', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter icon class', 'electro-extensions' ),
                'default'       => 'fas fa-chevron-right'
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
     * Render Brands With Category Block output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function render() {

        $settings = $this->get_settings_for_display();

        extract( $settings );

        if( ! empty( $category_include ) ) {
            $category_include = explode( ",", $category_include );
            $category_include = array_map( 'trim', $category_include );
        }

        $args =[
            'is_enabled' => true,
            'animation'  => false,
            'el_class'   => $el_class,
            'brands' => [
                'title'            => $brands_title,
                'more_brands_text' => $brands_action_text,
                'more_brands_link' => $brands_action_link,
                'taxonomy_args'    => [
                    'orderby'    => $brands_orderby,
                    'order'      => $brands_order,
                    'number'     => $brands_limit,
                    'hide_empty' => $brands_hide_empty
                ]
            ],
            'categories' => [
                'arrow_icon'         => $category_arrow_icon,
                'cats_child_limit'   => $category_child_limit,
                'more_child_text'    => $category_action_text,
                'product_cats_args'  => [
                    'orderby'    => $category_orderby,
                    'order'      => $category_order,
                    'number'     => $category_limit,
                    'hide_empty' => $category_hide_empty,
                    'parent'     => 0,
                    'include' => $category_include,
                ]
            ]

        ];

        if( function_exists( 'electro_home_v11_brands_with_category_block' ) ) {

            electro_home_v11_brands_with_category_block( $args );
        }
        
    }

}

Plugin::instance()->widgets_manager->register( new Electro_Elementor_Brands_With_Category_Block );