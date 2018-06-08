<?php

/**
 * Add addtional weighting to search results for global search
 *
 * @param $formatted_args Array of ElasticPress formatted arguements
 * @return none
 */
function cancerview_formatted_args( $formatted_args ) {
    global $wp_query;

    // only modify for site search not repository searches
    if ( $wp_query->query_vars['post_type'] == '' && empty( $wp_query->query_vars['post_type'] ) || $wp_query->query_vars['post_type'] == 'any' ) {
        $existing_query = $formatted_args['query'];
        unset( $formatted_args['query']) ;

        $formatted_args['query']['function_score']['query'] = $existing_query;

        // lets weight pages higher than custom post types
        $formatted_args['query']['function_score']['functions'] = array(
            array(
                "filter" => array(
                    "term" => array(
                        "post_type" => 'page',
                    ),
                ),
                "weight" => 3
            ),
            array(
                "filter" => array(
                    "terms" => array(
                        "post_type" => array('db-prevention-policy', 'db-fnim-resource', 'db-download-slide', 'db-sage')
                    ),
                ),
                "weight" => 1
            ),
        );

        $formatted_args['query']['function_score']["score_mode"] = "sum";
        $formatted_args['query']['function_score']["boost_mode"] = "multiply";
    }

    return $formatted_args;
}
add_filter( 'ep_formatted_args', 'cancerview_formatted_args' );

/**
 * CPAC Custom Editor buttons and shortcodes.
 *
 * Adds additional buttons to the WordPress editor to simplify the addition of
 * content formatting to the site. Also used for the various shortcodes added.
 *
 * @package Corporate_Site
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // exit if accessed directly.
}

class CPAC_TinyMCE
{
    protected static $instance;
    private $size_text;
    private $page_total;
    private $pages;
    private $file;

    /**
     * Return an instance of the current class
     *
     * @since 1.0.0
     * @return object of this class
     */
    public static function init()
    {
        if ( ( empty( $instance ) || ! $instance ) ) {
            $instance = new self();

            $instance->cpac_tinymce_setup();
        }
    }

    /**
     * Constructor should not be used for WP hooks/filters
     *
     * @since 1.0.0
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Set up class functions with hooks/filters
     *
     * @since 1.0.0
     * @return void
     */
    function cpac_tinymce_setup()
    {
        add_action( 'admin_head', array ($this, 'cpac_tinymce_add_buttons' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'cpac_tinymce_scripts_styles' ) );

        // shortcodes
        
        /*....*/
        
        add_shortcode( 'bios-wrapper', array( $this, 'cpac_tinymce_our_people_wrapper' ) );
        add_shortcode( 'bios', array( $this, 'cpac_tinymce_our_people' ) );
        
        /*....*/
        
        add_shortcode( 'document', array( $this, 'cpac_tinymce_download_documents' ) );
        
        /*....*/

        add_shortcode( 'custom-button', array( $this, 'cpac_tinymce_custom_button' ) );
    }

    /**
     * Enqueue styles
     *
     * @since 1.0.0
     * @return void
     */
    function cpac_tinymce_scripts_styles()
    {
        global $wp_version;

        wp_enqueue_style( 'custom-buttons-css', get_template_directory_uri() . '/inc/assets/css/custom-buttons.css' );
    }

    /*....*/

    /**
     * Shortcode to display Our People wrapper
     *
     * @since 1.0.0
     * @return void
     */
    function cpac_tinymce_our_people_wrapper( $atts, $content = null ) {
        $attr = shortcode_atts( array(
            'accordion' => 'no'
        ), $atts, 'our-people-wrapper');

        $bios_wrapper = '';

        if ( 'no' === $attr['accordion'] ) {
            $bios_wrapper = '<div class="bios-container">' . do_shortcode( $content ) . '</div>';
        } else {
            $bios_wrapper = '<div id="accordion" role="tablist" aria-multiselectable="true">' . do_shortcode( $content ) . '</div>';
        }

        return $bios_wrapper;
    }

    /**
     * Shortcode to display the Our People
     *
     * @since 1.0.0
     * @return void
     */
    function cpac_tinymce_our_people( $attr ) {
        $bios = $img_src = $is_chair = '';

        $attr = shortcode_atts( array(
            'group-ids'      => '',
            'include-images' => 'no',
            'accordion'      => 'no'
        ), $attr, 'our-people');

        $group_ids = $attr['group-ids'];
        $use_images = $attr['include-images'];
        $accordion = $attr['accordion'];

        $args = array(
            'post_type'      => 'our-people',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'order'          => 'asc',
            'orderby'        => 'menu_order',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'our_people_categories',
                    'field'    => 'term_id',
                    'terms'    => array( $group_ids ),
                )
            )
        );

        $our_people = new WP_Query( $args );

        $term = get_term_by( 'id', $group_ids, 'our_people_categories' );

        $css_id_name = strtolower( str_replace( " ", "-", $term->name ) );

        if ( 'yes' === $accordion ) {
            $bios .= '<button class="accordion" role="button">
                <h4 class="panel-title">
                            <a role="button" href="#collapse-' . $css_id_name . '">' . $term->name . '</a>
                </h4>
            </button>
            <div class="panel">
            <div class="bio-containerflex">';

            foreach ( $our_people->posts as $value ) {
                $is_chair    = get_field( 'board_chair', $value->ID );
                $bio_content = get_field( 'bio', $value->ID );

                $bios .= '<div class="bio-container col-xs-12 col-sm-6 col-md-4"><div class="bio">';

                $bios .= '<div class="bio-title">' . $value->post_title . '</div>';

                $bios .= '<div class="bio-content">' . $bio_content . '</div>
                        </div></div>';
            }
            $bios .= '</div></div>';
        } else {
            $bios .= '<ul class="gridder">';
            foreach ( $our_people->posts as $value ) {

                // get all the custom field data
                $is_chair         = get_field( 'board_chair', $value->ID );
                $title            = get_field( 'title', $value->ID );
                $member_since     = get_field( 'member_since', $value->ID );
                $committes        = get_field( 'committees', $value->ID );
                $social_channels  = get_field( 'social_channels', $value->ID );
                $expert_file      = get_field( 'expertfile', $value->ID );
                $expert_file_link = get_field( 'expertfile_link', $value->ID );
                $bio_content      = get_field( 'bio', $value->ID );


              // even though we know an image is available we still need to check
                if ( 'yes' === $use_images && has_post_thumbnail( $value->ID ) ) {
                    $img_src = get_the_post_thumbnail( $value->ID, 'medium', array( 'class' => 'bios-image' ) );


                    $bios .= '<li class="gridder-list" data-griddercontent="#' . $value->ID . '">' . $img_src . '<br /><div class="bio-title">' . $value->post_title . '</div><div class="bio-profile-arrow"></div></li>';
                }



                $bios .= '
                <div id="' . $value->ID . '" class="gridder-content">
                <div class="col-sm-4 col-xs-12">
                          <h5>' . $value->post_title . '</h5>';

                // job title
                if ( ! empty( $title ) ) {
                    $bios .= '  <h6>' . $title . '</h6>';
                }

                // member since
                if ( ! empty( $member_since ) ) {
                    $bios .= '  <h4>' . esc_html__( 'Member since', 'corporate-site' ) . '</h4>
                            <p>' . $member_since . '</p>';
                }

                // committees
                if ( ! empty( $committes ) ) {
                    $bios .= '  <h4>' . esc_html__( 'Committees', 'corporate-site' ) . '</h4>
                            <p>' . $committes . '</p>';
                }

                // social channels
                if ( ( 0 !== count( $social_channels ) ) && ( is_array( $social_channels ) || is_object( $social_channels ) ) ) {
                    $bios .= '  <h4>' . esc_html__( 'Social', 'corporate-site' ) . '</h4>';
                }

                 // expert file link
                if ( 'Yes' === $expert_file ) {
                    $bios .= '<a href="' . $expert_file_link . '" class="generic-button-blue col-xs-12 col-sm-6 col-md-12" target="_blank">' . esc_html__( 'View speaker profile', 'corporate-site' ) . '</a>';
                }

                $bios .= '</div>';

                $bios .= '<div class="col-sm-8 col-xs-12">' . $bio_content . '</div>
                        </div>';
            }

            $bios .= '</ul>';
        }

        return $bios;
    }
    
    /*....*/

    function cpac_tinymce_download_documents( $attr ) {
        $documents = '';

        $attr = shortcode_atts( array(
            'additional-info' => 'false',
            'icon'            => 'download',
            'id'              => ''
        ), $attr, 'download-documents');

        $args = array(
            'p'           => $attr['id'],
            'post_status' => 'inherit',
            'post_type'   => 'attachment'
        );

        $documents_query = new WP_Query( $args );

        $documents .= '<div class="documents col-md-12">';

        foreach ( $documents_query->posts as $document ) {
            $documents .= '<div>
                        <div class="' . $attr['icon'] . '-icon"></div>
                        <div class="document-details">
                          <a href="' . wp_get_attachment_url( $document->ID ) . '" target="_blank">' . ( 'download' === $attr['icon'] ? esc_html__( 'Download document', 'corporate-site' ) : $document->post_title ) . '</a>';
            if ( 'true' === $attr['additional-info'] ) {
                $documents .= '    <p class="file-information">';
                $documents .= $this->cpac_tinymce_file_size( $document->ID ) . ' | ' . $this->cpac_tinymce_page_total( $document->guid );
                $documents .= '    </p>';
            }
            $documents .= '  </div>
                      </div>';
        }
        $documents .= '</div>';

        return $documents;
    }

    /**
     * Shortcode to display the custom buttons
     *
     * @since 1.0.0
     * @return void
     */
    function cpac_tinymce_custom_button( $attr )  {
        $buttons = $button_size = $url = $target = '';

        $attr = shortcode_atts(array(
            'text'      => '',
            'link-type' => 'internal',
            'link'      => '',
            'colour'    => '',
            'size'      => 'small'
        ), $attr, 'custom-buttons');

        // set the button sizing
        if ( 'small' === $attr['size'] ) {
            $button_size = 'col-xs-12 col-sm-3 col-md-3';
        } elseif ( 'medium' === $attr['size'] ) {
            $button_size = 'col-xs-12 col-sm-3 col-md-5';
        } else {
            $button_size = 'col-xs-12 col-sm-12 col-md-12 col-lg-12';
        }

        // getting the link URL check if internal/external
        if ( 'internal' === $attr['link-type'] && is_numeric( $attr['link'] ) ) {
            $url = get_permalink( $attr['link'] );
            $target = 'target="_parent"';
        } elseif ( 'external' === $attr['link-type'] && is_numeric( $attr['link'] ) ) {
            $url = $attr['link'];
            $target = 'target="_parent"';
        } else {
            $url = $attr['link'];
            $target = 'target="_blank"';
        }

        $buttons .= '<div style="clear: both;">
                        <a class="generic-button-' . $attr['colour'] . ' ' . $button_size . '" href="' . $url . '"' . $target . '>' . $attr['text'] . '</a>
                    </div>';

        return $buttons;
    }

    /**
     * Add button to TinyMCE Editor
     *
     * @since 1.0.0
     * @return void
     */
    function cpac_tinymce_add_buttons() {
        global $typenow;

        // check user permissions
        if ( ! current_user_can ('edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
            return;
        }

        // list all the post types where you want the elements to show
        if ( ! in_array( $typenow, array( 'post', 'page', 'news-events', 'corporate-documents', 'highlight_stories', 'job-posting', 'our-people', 'procurement-details' ) ) )
            return;

        // check if WYSIWYG is enabled
        if ( 'true' === get_user_option( 'rich_editing' ) ) {
            add_filter( 'mce_external_plugins', array( $this, 'cpac_tinymce_add_plugins' ) );
            add_filter( 'mce_buttons', array( $this, 'cpac_tinymce_register_buttons' ) );
        }
    }

    /**
     * Add plugin to TinyMCE Editor
     *
     * @since 1.0.0
     * @return void
     */
    function cpac_tinymce_add_plugins( $plugin_array ) {
        $plugin_array['add_buttons'] = get_template_directory_uri() . '/inc/assets/js/tiny-mce-custom-buttons.js';

        return $plugin_array;
    }

    /**
     * Register our buttons
     *
     * @since 1.0.0
     * @return void
     */
    function cpac_tinymce_register_buttons( $buttons ) {
        array_push( $buttons, 'related_content_button' );
        array_push( $buttons, 'related_publications_button' );
        array_push( $buttons, 'related_stats_button' );
        array_push( $buttons, 'our_people_button' );
        array_push( $buttons, 'our_values_button' );
        array_push( $buttons, 'document_downloads_button' );
        array_push( $buttons, 'highlight_stories_button' );
        array_push( $buttons, 'search_type_button' );
        array_push( $buttons, 'custom_button' );

        return $buttons;
    }

    private function cpac_tinymce_page_total( $document ) {

    	// get the total number of pages of the document
        $this->file = new Imagick();
        $this->file->pingImage( $document );

        // get the page total
        $this->page_total = $this->file->getNumberImages();

		// set the page(s) text
        $this->pages = ( $this->page_total > 1 ) ? esc_html__( ' pages' ) : esc_html__( ' page' );

        return $this->page_total . $this->pages;
    }

    private function cpac_tinymce_file_size( $document ) {
        $this->size_text = esc_html__( 'File size', 'corporate-site' ) . ' ' . size_format( filesize( get_attached_file( $document ) ), 0 );
    	
        // return file size text
        return $this->size_text;
    }
}

CPAC_TinyMCE::init();
?>
