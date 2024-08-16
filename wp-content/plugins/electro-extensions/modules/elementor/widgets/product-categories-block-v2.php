<?php

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Product Categories Block Version 2 Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Electro_Elementor_Product_Categorries_Block_V2 extends Widget_Base {

    /**
     * Get widget name.
     *
     * Retrieve Product Categories Block Version 2 widget name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'electro_home_categories_block_v2';
    }

    /**
     * Get widget title.
     *
     * Retrieve Product Categories Block Version 2 widget title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return esc_html__( 'Product Categories Block v2', 'electro-extensions' );
    }

    /**
     * Get widget icon.
     *
     * Retrieve Product Categories Block Version 2 widget icon.
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
     * Retrieve the list of categories the Product Categories Block Version 2 widget belongs to.
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
     * Register Product Categories Block widget Version 2 controls.
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
                'label' => esc_html__( 'Content', 'electro-extensions' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'columns',
            [
                'label'     => esc_html__( 'Enter Columns', 'electro-extensions' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => '5',
            ]
        );

        $this->add_control(
            'limit',
            [
                'label'     => esc_html__( 'Number of Categories to display', 'electro-extensions' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => '5',
            ]
        );

        $this->add_control(
            'has_no_products',
            [
                'label' => esc_html__( 'Hide Empty Categories', 'electro-extensions' ),
                'type'  => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Hidden', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Hide', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => false,
                'description'   => esc_html__( 'Hide categories does not have products', 'electro-extensions' ),
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label'         => esc_html__( 'Orderby', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'description' => esc_html__( ' Sort retrieved posts by parameter. Defaults to \'name\'. One or more options can be passed', 'electro-extensions' ),
                'default'       => 'name',
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
            'slugs',
            [
                'label' => esc_html__( 'Include slug\'s', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
            ]
        ); 

        $this->add_control(
            'include',
            [
                'label' => esc_html__( 'Include ID\'s', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
            ]
        );

        $this->add_control(
            'el_class',
            [
                'label' => esc_html__( 'Extra class name', 'electro-extensions' ),
                'type'  => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'If you wish to style particular content element differently, please add a class name to this field and refer to it in your custom CSS file.', 'electro-extensions' ),
            ]
        );

        $this->end_controls_section();

    }

    /**
     * Render Product Categories Block Version 2 widget output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function render() {

        $settings = $this->get_settings_for_display();

        extract( $settings );

        $cat_args = array(
            'columns'           => $columns,
			'number'			=> $limit,
			'hide_empty'		=> $has_no_products,
			'orderby' 			=> $orderby,
			'order' 			=> $order,
		);

		if( ! empty( $slugs ) ) {
			$slugs = explode( ",", $slugs );
			$slugs = array_map( 'trim', $slugs );
			
			$slug_include = array();

			foreach ( $slugs as $slug ) {
				$slug_include[] = "'" . $slug ."'";
			}

			if ( ! empty($slug_include ) ) {
				$cat_args['slug'] 		= $slugs;
				$cat_args['include'] 	= $slug_include;
				$cat_args['orderby']	= 'include';
			}

		} elseif( ! empty( $include ) ) {
			$include = explode( ",", $include );
			$include = array_map( 'trim', $include );
			$cat_args['include'] = $include;
		}

		$args = apply_filters( 'electro_elementor_product_categories_block_v2_element_args', array(
			'is_enabled' => true,
			'animation'	 => false,
            'el_class'   => $el_class,
            'categories' => $cat_args
            
		));

        if( function_exists( 'electro_home_v12_categories_block' ) ) {

			electro_home_v12_categories_block( $args );
		}

    }

}

Plugin::instance()->widgets_manager->register( new Electro_Elementor_Product_Categorries_Block_V2 );