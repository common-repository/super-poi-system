<?php

class SpsPoi {
    public $postType = 'sps_poi';
    public $postTax = 'sps_poi_category';
    public $metaKey = 'sps_location';

    private $metaFields = array('address', 'lat', 'lng');
    private $_options;

    public function __construct() {
        add_action('after_setup_theme', array($this, 'init'), 4);
    }

    public function init() {
        $postArgs = array(
            'labels' => array(
                'name'                  => esc_html__( 'Super POI','sps'),
                'menu_name'             => esc_html__( 'Super POI System','sps'),
                'singular_name'         => esc_html__( 'Super POI','sps'),
                'add_new'               => esc_html__( 'Add New POI','sps'),
                'add_new_item'          => esc_html__( 'Add POI','sps'),
                'edit'                  => esc_html__( 'Edit','sps'),
                'edit_item'             => esc_html__( 'Edit POI','sps'),
                'new_item'              => esc_html__( 'New POI','sps'),
                'view'                  => esc_html__( 'View','sps'),
                'view_item'             => esc_html__( 'View POI','sps'),
                'search_items'          => esc_html__( 'Search POI','sps'),
                'not_found'             => esc_html__( 'No POI found','sps'),
                'not_found_in_trash'    => esc_html__( 'No POI found in Trash','sps'),
                'parent'                => esc_html__( 'Parent POI','sps')
            ),
            'has_archive' => true,
            'supports' => array('title', 'thumbnail', 'excerpt'),
            'can_export' => true,
            'menu_icon'=> 'dashicons-location',
            'public' => false,  // it's not public, it shouldn't have it's own permalink, and so on
            'publicly_queryable' => false,  // you shouldn't be able to access it from url
            'show_ui' => true,  // you should be able to edit it in wp-admin
            'exclude_from_search' => true,  // you should exclude it from search results
            'show_in_nav_menus' => false,  // you shouldn't be able to add it to menus
            'rewrite' => false,  // it shouldn't have rewrite rules
        );

        $this->_options = SpsSettings::getOptions();

        if($this->_options['has_own_page']) {
            $postArgs['public'] = true;
            $postArgs['publicly_queryable'] = true;
            $postArgs['exclude_from_search'] = false;
            $postArgs['show_in_nav_menus'] = true;
            $postArgs['rewrite'] = array('slug' => 'poi');
            $postArgs['supports'][] = 'editor';
        }

        register_post_type($this->postType, $postArgs);

        register_taxonomy($this->postTax, $this->postType, array(
                'labels' => array(
                    'name'              => esc_html__( 'Categories','sps'),
                    'add_new_item'      => esc_html__( 'Add New POI Category','sps'),
                    'new_item_name'     => esc_html__( 'New POI Category','sps')
                ),
                'hierarchical'  => true,
                'query_var'     => true,
                'rewrite'       => array( 'slug' => 'poi-category' )
            )
        );

        //allows to search using "Location" value
        add_filter('parse_query', array($this, 'parseSearchQuery'), 10, 2);
        add_filter('posts_search', array($this, 'parseSearch'), 10, 2);

        add_action('add_meta_boxes', array($this, 'setupMetaBoxes'), 4);
        add_action('save_post_' . $this->postType, array($this, 'saveMetaBoxes'));
        add_filter('manage_' . $this->postType . '_posts_columns', array($this, 'setupListColumns'));
        add_action('manage_' . $this->postType . '_posts_custom_column', array($this, 'displayListColumns'), 10, 2);

        foreach ($this->_options['post_types'] as $postType) {
            if($postType == $this->postType) {
                continue;
            }
            add_action('save_post_' . $postType, array($this, 'saveMetaBoxes'));
            add_filter('manage_' . $postType . '_posts_columns', array($this, 'setupListColumnsForEnabledTypes'));
            add_action('manage_' . $postType . '_posts_custom_column', array($this, 'displayListColumns'), 10, 2);
        }

        //action to display map on front
        add_shortcode('sps_map', array($this, 'shortcodeDisplay'));
        add_action('sps_map', array($this, 'themeDisplay'), 4, 2);
        add_action('the_post', array($this, 'appendToContentCheck'), 4, 2);
    }

    public function parseSearchQuery(WP_Query $query) {
        global $pagenow;
        $currentPage = isset($_GET['post_type']) ? $_GET['post_type'] : '';

        if ( is_admin() &&
            $this->postType == $currentPage &&
            'edit.php' == $pagenow &&
            !empty($_GET['s'])
        ) {
            $query->query_vars['meta_key'] = $this->metaKey;
            $query->query_vars['sps_poi_search'] = true;
        }

        return $query;
    }

    public function parseSearch($search, WP_Query $query) {
        if($query->get('sps_poi_search')) {
            $metaSearch = $this->_getSearchQuery($query);
            //need extra parenthesis because search is actually packed inside AND ()
            $search = ' AND ( 1=1' . $search . $metaSearch['where'] . ')';
        }
        return $search;
    }

    public function setupListColumns($columns) {
        $columns['location'] = __('Location', 'sps');
        $columns['category'] = __('Categories', 'sps');
        return $columns;
    }

    public function setupListColumnsForEnabledTypes($columns) {
        $columns['location'] = __('SPS - Location', 'sps');
        return $columns;
    }

    public function displayListColumns($column_name, $post_ID) {
        switch ($column_name) {
            case 'location':
                $meta = get_post_meta($post_ID, $this->metaKey, true);
                if (!empty($meta) && is_array($meta)) {
                    echo $meta['address'];
                }
            break;
            case 'category':
                $meta = wp_get_post_terms($post_ID, $this->postTax, array('fields' => 'names'));
                if (!empty($meta) && is_array($meta)) {
                    echo join(', ', $meta);
                }
            break;
        }
    }

    public function setupMetaBoxes() {
        add_meta_box('sps-map' , __( 'POI Location', 'sps' ), array($this, 'setupMap'), $this->postType, 'normal', 'high');

        foreach ($this->_options['post_types'] as $postType) {
            if($postType == $this->postType) {
                continue;
            }
            add_meta_box('sps-map', __('Super Poi System', 'sps'), array($this, 'setupMap'), $postType, 'normal', 'high');
        }
    }

    public function saveMetaBoxes($post_id) {
        if (
            !isset($_POST['sps_nonce'])
            || !wp_verify_nonce($_POST['sps_nonce'], 'sps_metabox_nonce')
            || !current_user_can('edit_post', $post_id)
        ) {
            return;
        }

        $new_meta_values = array(
            $this->metaKey => array()
        );

        foreach($_POST as $key => $value) {
            if(strpos($key, $this->metaKey) === 0 && is_array($value)) {
                foreach($value as $subKey => $subValue) {
                    if(is_array($subValue)) {
                        $new_meta_values[$this->metaKey][$subKey] = array_map('sanitize_text_field', $subValue);
                    } else {
                        $new_meta_values[$this->metaKey][$subKey] = sanitize_text_field($subValue);
                    }
                }
            }
        }

        foreach($new_meta_values as $meta_key => $new_meta_value) {
            $meta_value = get_post_meta( $post_id, $meta_key, true );
            if ( $new_meta_value && $meta_value == '' ) {
                add_post_meta( $post_id, $meta_key, $new_meta_value, true );
            } elseif ( $new_meta_value && $new_meta_value != $meta_value ) {
                update_post_meta( $post_id, $meta_key, $new_meta_value );
            }
            elseif ( $new_meta_value = '' && $meta_value ) {
                delete_post_meta( $post_id, $meta_key, $meta_value );
            }
        }
    }

    public function setupMap($object) {
        $value = get_post_meta($object->ID, $this->metaKey, true);

        if(empty($value)) {
            $empties = array_fill(0, count($this->metaFields), '');
            $value = array_combine($this->metaFields, $empties);
            $value['categories'] = array();
            $value['range'] = '';
        }

        wp_nonce_field( 'sps_metabox_nonce', 'sps_nonce' );

        //on "enabled" post type, add some configuration
        if(in_array($object->post_type, $this->_options['post_types'])):
            $categories = get_terms(array(
                'taxonomy' => $this->postTax,
                'hide_empty' => false
            ));

            ?>
            <div class="inside">
                <h3><?php _e('Custom settings', 'sps') ?></h3>

                <?php if(!empty($categories)): ?>
                    <div>
                        <p class="post-attributes-label-wrapper">
                            <label class="post-attributes-label" for="sps-map-categories"><?php _e('Restrict POI to selected categories', 'sps'); ?></label>
                        </p>
                        <select id="sps-map-categories"
                                class="sps-map-categories"
                                name="<?= $this->metaKey ?>[categories][]"
                                multiple="multiple"
                                data-placeholder="<?php _e('Leave empty to display all POI in range', 'sps'); ?>"
                        >
                            <?php foreach($categories as $category): ?>
                                <option value="<?= $category->term_id ?>" <?php selected(true, in_array($category->term_id, $value['categories'])) ?>><?= $category->name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div>
                    <p class="post-attributes-label-wrapper">
                        <label class="post-attributes-label" for="sps-map-range"><?php _e('Scan range', 'sps'); ?></label>
                    </p>
                    <input id="sps-map-range" type="number" min="0"
                           placeholder="<?= sprintf(_x('Default: %s', 'placeholder in range field', 'sps'), $this->_options['range']) ?>"
                           name="<?= $this->metaKey ?>[range]"
                           value="<?= $value['range'] ?>"
                    /> <?php _e('kilometers', 'sps') ?>
                </div>

                <h3><?php _e('Location', 'sps') ?></h3>
            </div>
        <?php endif; ?>

        <div class="sps-gmap">
            <?php foreach($this->metaFields as $field): ?>
                <input type="hidden" class="sps-meta-field-<?= $field ?>" name="<?= $this->metaKey ?>[<?= $field ?>]" value="<?= $value[$field] ?>"/>
            <?php endforeach; ?>
            <input class="sps-map-search" type="text" placeholder="<?php _e('Search for address...', 'sps'); ?>" value="<?= $value['address'] ?>" />
            <div class="sps-map-container" style="height: 500px"></div>
        </div>
    <?php }

    public function appendToContentCheck() {
        $postType = get_post_type();
        if(in_array($postType, $this->_options['post_types_autoload'])) {
            add_filter('the_content', array($this, 'appendToContent'));
        } else {
            //in case it was added for previous post inside some loop
            remove_filter('the_content', array($this, 'appendToContent'));
        }
    }

    public function appendToContent($content, $lat = null, $lng = null) {
        global $more;
        if(!$more) {
            //only add map when viewing full content
            return $content;
        }

        ob_start();
        do_action('sps_map', $lat, $lng);
        $content .= ob_get_clean();
        return $content;
    }

    /**
     * Helper for using plugin as shortcode in content
     *
     * @param $atts
     * @param $content
     * @return string
     */
    public function shortcodeDisplay($atts, $content = '') {
        $atts = shortcode_atts( array(
            'lat' => $this->_options['map_coordinates']['lat'],
            'lng' => $this->_options['map_coordinates']['lng']
        ), $atts, 'sps_map' );

        return $this->appendToContent($content, $atts['lat'], $atts['lng']);
    }

    /**
     * Display map with POI in theme
     * @param $gmap_lat
     * @param $gmap_lng
     */
    public function themeDisplay($gmap_lat, $gmap_lng) {
        global $post;

        $closestAttractions = wp_cache_get('sps_poi_list', get_the_ID());
        list($gmap_lat, $gmap_lng) = $this->_getPostCoordinates($gmap_lat, $gmap_lng);

        if ($gmap_lat && $gmap_lng && false === $closestAttractions) {
            $value = get_post_meta(get_the_ID(), $this->metaKey, true);

            $range = !empty($value['range']) ? $value['range'] : $this->_options['range'];
            $categories = !empty($value['categories']) ? $value['categories'] : null;
            $closestAttractions = $this->getClosestPOI($gmap_lat, $gmap_lng, $range, $categories);
            if(!empty($closestAttractions)) {
                wp_cache_set('sps_poi_list', $closestAttractions, get_the_ID());
            }
        }

        if(empty($closestAttractions)) {
            return;
        }

        ?>

        <div class="sps-gmap">
            <h3><?php _e('Closest Attractions', 'sps'); ?></h3>
            <div class="sps-map-container" data-center='<?= json_encode(array('lat' => $gmap_lat, 'lng' => $gmap_lng)) ?>'></div>
            <?php if($this->_options['author_info']): ?>
                <div class="sps-author-info"><?php _e('WP Plugin', 'sps') ?> &copy; <?= date('Y'); ?> <a target="_blank" href="https://www.sopranovillas.com" title="SopranoVillas">Italy villas</a></div>
            <?php endif; ?>
            <div class="sps-travel-mode">
                <div class="sps-map-button active" role="button" tabindex="0" title="<?php _e('Drive to destination', 'sps'); ?>" aria-label="<?php _e('Drive to destination', 'sps'); ?>"
                     draggable="false"
                     data-travel-mode="DRIVING">
                    <?php _e('Drive', 'sps'); ?>
                </div>
                <div class="sps-map-button" role="button" tabindex="0" title="<?php _e('Walk to destination', 'sps'); ?>"
                     aria-label="<?php _e('Walk to destination', 'sps'); ?>" draggable="false"
                     data-travel-mode="WALKING">
                    <?php _e('Walk', 'sps'); ?>
                </div>
                <input type="hidden" class="sps-travel-mode-value" value="DRIVING"/>
            </div>
            <div class="sps-markers-list">
                <?php
                add_filter('excerpt_more', '__return_empty_string');

                foreach ($closestAttractions as $post) {
                    setup_postdata($post);
                    $location = get_post_meta(get_the_ID(), $this->metaKey, true);
                    echo '<a id="sps-poi-'. get_the_ID() .'"
                    data-image="'. get_the_post_thumbnail_url() .'"
                    data-description="'. get_the_excerpt() .'"
                    data-location=\''. json_encode($location) .'\'';

                    if($this->_options['has_own_page']) {
                        echo 'href="' . get_the_permalink() . '"';
                    } else {
                        echo 'href="#"';
                    }

                    echo 'class="sps-poi-marker">' . get_the_title() . '</a>';
                }

                remove_filter('excerpt_more', '__return_empty_string');
                wp_reset_postdata();
                ?>
            </div>
        </div>
    <?php }

    /**
     * If you want to create own layout in theme, use this function to retrieve POI
     *
     * @param $lat - main point latitude
     * @param $lng - main point longitude
     * @param $range - radius range in kilometers to search for
     * @param $category - POI category (int/string/array)
     * @return mixed|void
     */
    public function getClosestPOI($lat, $lng, $range, $category = null) {
        $queryArgs = array(
            'post_type' => $this->postType,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => array(),
        );

        if(!empty($category)) {
            $queryArgs['tax_query'][] = array(
                'taxonomy' => $this->postTax,
                'field'    => 'term_id',
                'terms'    => $category,
            );
        }

        $attractions = new WP_Query($queryArgs);

        $closestAttractions = array();

        while($attractions->have_posts()) {
            $attractions->the_post();

            list($attraction_lat, $attraction_lng) = $this->_getPostCoordinates();
            if(empty($attraction_lat)) {
                continue;
            }

            $dist = $this->_distance($lat, $lng, $attraction_lat, $attraction_lng);

            if($dist <= $range) {
                $closestAttractions[] = get_post();
            }
        }
        wp_reset_postdata();

        //if needed filter some results
        return apply_filters('sps_closest_attractions', $closestAttractions);
    }

    private function _getPostCoordinates($gmap_lat = null, $gmap_lng = null) {
        if(!is_numeric($gmap_lat) || !is_numeric($gmap_lng)) {
            $location = get_post_meta(get_the_ID(), $this->metaKey, true);

            if(empty($location)) {
                return null;
            }

            if(!is_numeric($gmap_lat)) {
                $gmap_lat = floatval($location['lat']);
            }

            if(!is_numeric($gmap_lng)) {
                $gmap_lng = floatval($location['lng']);
            }
        }

        return array($gmap_lat, $gmap_lng);
    }

    private function _distance($lat1, $lng1, $lat2, $lng2, $unit = "K") {
        $theta = $lng1 - $lng2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        if ($unit == "K") { //Kilometers
            return ($miles * 1.609344);
        } else if ($unit == "N") { //Nautical Miles
            return ($miles * 0.8684);
        }

        return $miles;
    }

    private function _getSearchQuery(WP_Query $query) {
        global $wpdb;

        $sql_meta = get_meta_sql(array(
            array(
                'key' => $this->metaKey,
                'value' => $query->get('s'),
                'compare' => 'LIKE'
            )
        ), 'post', $wpdb->posts, 'ID');
        $sql_meta['where'] = preg_replace('/^ AND/i', ' OR', $sql_meta['where']);
        return $sql_meta;
    }
}
