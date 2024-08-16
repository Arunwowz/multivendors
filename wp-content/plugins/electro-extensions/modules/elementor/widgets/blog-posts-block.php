<?php

namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Elementor Blog Posts Block Widget.
 *
 * Elementor widget that inserts an embbedable content into the page, from any given URL.
 *
 * @since 1.0.0
 */
class Electro_Elementor_Blog_Posts_Block extends Widget_Base {

    /**
     * Get widget name.
     *
     * Retrieve Blog Posts Block widget name.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget name.
     */
    public function get_name() {
        return 'electro_elementor_blog_posts_block';
    }

    /**
     * Get widget title.
     *
     * Retrieve Blog Posts Block widget title.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget title.
     */
    public function get_title() {
        return esc_html__( 'Blog Posts Block', 'electro-extensions' );
    }

    /**
     * Get widget icon.
     *
     * Retrieve Blog Posts Block widget icon.
     *
     * @since 1.0.0
     * @access public
     *
     * @return string Widget icon.
     */
    public function get_icon() {
        return 'eicon-posts-grid';
    }

    /**
     * Get widget categories.
     *
     * Retrieve the list of categories in the Blog Posts Block widget belongs to.
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
     * Register Blog Posts Block widget controls.
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
                'default'		=> esc_html__( 'Tips & Inspirations', 'electro-extensions' )
            ]
        );

        $this->add_control(
            'columns',
            [
                'label'         => esc_html__( 'Enter Columns', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'placeholder'   => esc_html__( 'Number of columns', 'electro-extensions' ),
                'default'		=> '2'
            ]
        );

        $this->add_control(
            'limit',
            [
                'label'         => esc_html__( 'Number of Posts to display', 'electro-extensions' ),
                'type'          => Controls_Manager::TEXT,
                'default'		=> '2'
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
                'description'   => esc_html__( ' Sort retrieved posts by parameter. Defaults to \'date\'. One or more options can be passed', 'electro-extensions' ),
                'default'       => 'date',
            ]
        );

        $this->add_control(
            'order',
            [
                'label'         => esc_html__( 'Order', 'electro-extensions' ),
                'type'          => Controls_Manager::SELECT,
                'description'   => esc_html__( 'Designates the ascending or descending order of the \'orderby\' parameter. Defaults to \'DESC\'.', 'electro-extensions' ),
                'options'       => [
                        'DESC'     => esc_html__( 'DESC','electro-extensions'),
                        'ASC'      => esc_html__( 'ASC','electro-extensions')
                ],
                'default'       => 'DESC',
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

        $args = apply_filters( 'electro_elementor_blog_posts_block_element_args', array(
			'is_enabled'	 => true,
			'animation'		 => false,
            'section_title'  => $title,
            'posts_per_page' => $limit,
            'columns'        => $columns,
            'orderby'        => $orderby,
            'order'          => $order,
			'el_class'		 => $el_class
		));

        if( ! empty( $include ) ) {
			$include = explode( ",", $include );
			$include = array_map( 'trim', $include );
			$args['post__in'] = $include;
		}

        if( function_exists( 'electro_home_v12_blog_posts' ) ) {
	
			electro_home_v12_blog_posts( $args );
		}

    }
}

Plugin::instance()->widgets_manager->register( new Electro_Elementor_Blog_Posts_Block );