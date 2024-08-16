<?php

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Product Category Tags Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Electro_Elementor_Product_Category_Tags extends Widget_Base {

    /**
     * Get widget name.
     *
     * Retrieve Product Category Tags widget name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'electro_elementor_product_category_tags';
    }

    /**
     * Get widget title.
     *
     * Retrieve Product Category Tags widget title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return esc_html__( 'Product Category Tags', 'electro-extensions' );
    }

    /**
     * Get widget icon.
     *
     * Retrieve Product Category Tags widget icon.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget icon.
     */
    public function get_icon() {
        return 'eicon-tags';
    }

    /**
     * Get widget categories.
     *
     * Retrieve the list of categories in the Product Category Tags widget belongs to.
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
     * Register Product Category Tags widget controls.
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
                'tab'       => Controls_Manager::TAB_CONTENT
            ]
        );

        $this->add_control(
            'title',
            [
                'label'         => esc_html__( 'Title', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter title', 'electro-extensions' ),
                'default'		=> esc_html__( 'Popular Search', 'electro-extensions' )
            ]
        );

        $this->add_control(
            'per_page',
            [
                'label'         => esc_html__('Limit', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter limit of the products.', 'electro-extensions'),
                'default'       => 8
            ]
        );

        $this->add_control(
            'has_no_products',
            [
                'label'         => esc_html__('Hide Empty', 'electro-extensions'),
                'type'          => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Hidden', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Hide', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => true
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label'         => esc_html__( 'Orderby', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'description' => esc_html__( ' Sort retrieved posts by parameter. Defaults to \'name\'. One or more options can be passed', 'electro-extensions' ),
                'default'       => 'name'
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
                'default'       => 'ASC'
            ]
        );

        $this->add_control(
            'slugs',
            [
                'label'         => esc_html__( 'Include slug\'s', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter slug spearate by comma(,). Maximum of 7.', 'electro-extensions' )
            ]
        );

        $this->add_control(
            'include',
            [
                'label'         => esc_html__('Include ID\'s', 'electro-extensions'),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__('Enter ids spearate by comma(,). Maximum of 7.', 'electro-extensions')
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
     * Render Product Category Tags output on the frontend.
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
			'number'			=> $per_page,
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

		$args = array(
			'section_title'			=> $title,
			'category_args'			=> $cat_args,
			'section_class'			=> $el_class,
		);

		if( function_exists( 'electro_home_product_category_tags' ) ) {

			electro_home_product_category_tags( $args );
		}

    }
    
}

Plugin::instance()->widgets_manager->register( new Electro_Elementor_Product_Category_Tags );