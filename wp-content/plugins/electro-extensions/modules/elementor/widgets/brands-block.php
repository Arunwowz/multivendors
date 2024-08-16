<?php

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Brands Block Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Electro_Elementor_Brands_Block extends Widget_Base {

    protected $_has_template_content = false;

    /**
     * Get widget name.
     *
     * Retrieve Brands Block widget name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'electro_elementor_brands_block';
    }

    /**
     * Get widget title.
     *
     * Retrieve Brands Block widget title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return esc_html__( 'Brands Block', 'electro-extensions' );
    }

    /**
     * Get widget icon.
     *
     * Retrieve Brands Block widget icon.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget icon.
     */
    public function get_icon() {
        return 'eicon-editor-bold';
    }

    /**
     * Get widget categories.
     *
     * Retrieve the list of categories in the Brands Block widget belongs to.
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
     * Register Brands Block widget controls.
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
                'label'         => esc_html__( 'Title', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Enter title', 'electro-extensions' ),
                'default'		=> esc_html__( 'Known Brands', 'electro' )
            ]
        );

        $this->add_control(
            'columns',
            [
                'label'         => esc_html__( 'Enter Columns', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Number of columns', 'electro-extensions' ),
                'default'		=> '3'
            ]
        );

        $this->add_control(
            'limit',
            [
                'label'         => esc_html__( 'Number of Brands to display', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'default'		=> '5'
            ]
        );

        $this->add_control(
            'include',
            [
                'label'         => esc_html__( 'Include ID\'s', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'default'		=> ''
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
            'has_no_products',
            [
                'label' => esc_html__( 'Hide Empty Brands', 'electro-extensions' ),
                'type'  => Controls_Manager::SWITCHER,
                'label_on'      => esc_html__( 'Hidden', 'electro-extensions' ),
                'label_off'     => esc_html__( 'Hide', 'electro-extensions' ),
                'return_value'  => 'true',
                'default'       => false,
                'description'   => esc_html__( 'Hide Brands does not have products', 'electro-extensions' ),
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
     * Render Brands Block output on the frontend.
     *
     * Written in PHP and used to generate the final HTML.
     *
     * @since 1.0.0
     * @access protected
     */
    protected function render() {

        $settings = $this->get_settings_for_display();

        extract( $settings );

        $args = apply_filters( 'electro_elementor_brands_block_element_args', array(
			'is_enabled'	 => true,
            'section_title'  => $title,
            'number' 		 => $limit,
			'hide_empty'	 => $has_no_products,
            'columns'        => $columns,
            'orderby'        => $orderby,
            'order'          => $order,
			'animation'		 => false,
			'el_class'		 => $el_class
		));

		if( ! empty( $include ) ) {
			$include = explode( ",", $include );
			$include = array_map( 'trim', $include );
			$args['include'] = $include;
		}

		if( function_exists( 'electro_home_v12_brands_block' ) ) {
			
			electro_home_v12_brands_block( $args );
		}

    }

}

Plugin::instance()->widgets_manager->register( new Electro_Elementor_Brands_Block );