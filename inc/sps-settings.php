<?php

class SpsSettings
{
    private $_where;
    private static $_options;

    private static $_defaults = array(
        'map_key' => '',
        'author_info' => false,
        'custom_font' => true,
        'post_types_autoload' => array(),
        'post_types' => array(),
        'has_own_page' => '',
        'main_marker' => '',
        'poi_marker' => '',
        'range' => 30, //kilometers
        'map_coordinates' => array(
            'lat' => 41.890251,
            'lng' => 12.492373
        )
    );

    public static function getOptionValue($key) {
        //allow to modify option value on-the-fly for some cases using filter
        return apply_filters('sps-option-' . $key, self::$_options[$key]);
    }

    public static function getOptions() {
        /**
         * Parse incoming $args into an array and merge it with $defaults
         * additionally allows to override options for some cases using filter
         */
        return apply_filters('sps-options', self::parseSettings( get_option( 'sps-settings' ), self::$_defaults ));
    }

    public function __construct($where)
    {
        $this->_where = $where;
        add_action('admin_menu', array($this, 'adminMenu'));
        add_action('admin_init', array($this, 'init'), 4);
    }

    public function init()
    {
        self::$_options = self::getOptions();

        //in case we checked 'separate pages'
        flush_rewrite_rules(false);

        register_setting('sps-settings', 'sps-settings', array($this, 'sanitizeOptions'));

        add_settings_section(
            'default', // ID
            __('Basic settings', 'sps'), // Title
            '__return_false', // Callback
            'sps-settings' // Page
        );

        add_settings_field(
            'map_key', // ID
            __('Google Maps API Key', 'sps'), // Title
            array($this, 'keyCallback'), // Callback
            'sps-settings', // Page
            'default',
            array(
                'key' => 'map_key',
                'description' => __("This field may not be required, but it's advised to use a key for better stability.", 'sps') . ' <a target="_blank" href="https://developers.google.com/maps/documentation/javascript/get-api-key">' . _x('Get one now', 'google map key', 'sps') . '</a>'
            )
        );

        add_settings_field(
            'post_types', // ID
            __('Enable for', 'sps'), // Title
            array($this, 'postTypesCallback'), // Callback
            'sps-settings', // Page
            'default',
            array(
                'key' => 'post_types',
                'post_types' => get_post_types(array(
                    'public' => true
                ), 'objects'),
                'description' => __('Select post types you would like to enable whole feature', 'sps')
            )
        );

        add_settings_field(
            'post_types_autoload', // ID
            __('Automatic append enabled', 'sps'), // Title
            array($this, 'postTypesCallback'), // Callback
            'sps-settings', // Page
            'default',
            array(
                'key' => 'post_types_autoload',
                'post_types' => self::$_options['post_types'],
                'description' => __('Pick objects from enabled ones for which you would like to automatically append plugin map', 'sps')
            )
        );

        add_settings_field(
            'author_info', // ID
            __('Display author info', 'sps'), // Title
            array($this, 'checkboxCallback'), // Callback
            'sps-settings', // Page
            'default',
            array(
                'key' => 'author_info',
                'description' => '<b>' . __('If you like this plugin, support us! We bet you won\'t see the difference.', 'sps') . '</b>'
            )
        );

        add_settings_section(
            'advanced', // ID
            __('Additional settings', 'sps'), // Title
            '__return_false', // Callback
            'sps-settings' // Page
        );

        add_settings_field(
            'custom_font', // ID
            __('Enable google font', 'sps'), // Title
            array($this, 'checkboxCallback'), // Callback
            'sps-settings', // Page
            'advanced',
            array(
                'key' => 'custom_font',
                'description' => __('Enables usage of custom font "Quicksand" in plugin', 'sps')
            )
        );

        add_settings_field(
            'has_own_page', // ID
            __('Enable separate pages', 'sps'), // Title
            array($this, 'checkboxCallback'), // Callback
            'sps-settings', // Page
            'advanced',
            array(
                'key' => 'has_own_page',
                'description' => __('If enabled, POI will have own pages with links, otherwise POI will show only on embedded maps', 'sps')
            )
        );

        add_settings_field(
            'map_coordinates', // ID
            __('Default map coordinates', 'sps'), // Title
            array($this, 'latLngCallback'), // Callback
            'sps-settings', // Page
            'advanced',
            array(
                'key' => 'map_coordinates',
                'description' => __('Starting point for map in POI edit screen', 'sps')
            )
        );

        add_settings_field(
            'range', // ID
            __('Scan range', 'sps'), // Title
            array($this, 'numberCallback'), // Callback
            'sps-settings', // Page
            'advanced',
            array(
                'key' => 'range',
                'description' => __('Enter range (in kilometers) to search POIs for', 'sps')
            )
        );

        add_settings_field(
            'main_marker', // ID
            __('Main point marker', 'sps'), // Title
            array($this, 'textCallback'), // Callback
            'sps-settings', // Page
            'advanced',
            array(
                'key' => 'main_marker',
                'description' => __("Enter here url of the custom marker image you would like to use for main point", 'sps')
            )
        );

        add_settings_field(
            'poi_marker', // ID
            __('POI marker', 'sps'), // Title
            array($this, 'textCallback'), // Callback
            'sps-settings', // Page
            'advanced',
            array(
                'key' => 'poi_marker',
                'description' => __("Enter here url of the custom marker image you would like to use for POI", 'sps')
            )
        );
    }

    public function adminMenu()
    {
        add_submenu_page('edit.php?post_type=' . $this->_where, __('Super POI Settings', 'sps'), __('Super POI Settings', 'sps'),
            'manage_options', 'sps-settings', array($this, 'display'));
    }

    public function postTypesCallback($arg)
    {
        foreach ($arg['post_types'] as $post_type) {
            if(!($post_type instanceof WP_Post_Type)) {
                $post_type = get_post_type_object($post_type);
            }

            if(!self::$_options['has_own_page'] && $post_type->name == $this->_where) {
                //if "Separate pages" is not enabled this wouldn't take effect anyway
                continue;
            }

            $label = $post_type->label;
            $value = $post_type->name;
            $labelClass = '';

            if($post_type->name == $this->_where) {
                $labelClass = 'sps-self';
            }

            printf(
                '<label class="post-type-checkbox %s"><input type="checkbox" id="' . $arg['key'] . '" name="sps-settings[' . $arg['key'] . '][]" value="%s" %s />&nbsp;%s</label>',
                $labelClass, $value, checked(true, is_array(self::$_options[$arg['key']]) ? in_array($value, self::$_options[$arg['key']]) : false, false), $label
            );
        }
        $this->printInputDescription($arg);
    }

    public function textCallback($arg)
    {
        printf(
            '<input class="sps-text-input" type="text" id="' . $arg['key'] . '" name="sps-settings[' . $arg['key'] . ']" value="%s" />',
            self::$_options[$arg['key']]
        );
        $this->printInputDescription($arg);
    }

    public function keyCallback($arg)
    {
        printf(
            '<input class="sps-key-input" type="text" id="' . $arg['key'] . '" name="sps-settings[' . $arg['key'] . ']" value="%s" />',
            self::$_options[$arg['key']]
        );
        $this->printInputDescription($arg);
    }

    public function numberCallback($arg)
    {
        printf(
            '<input type="number" min="0" id="' . $arg['key'] . '" name="sps-settings[' . $arg['key'] . ']" value="%s" />',
            self::$_options[$arg['key']]
        );
        $this->printInputDescription($arg);
    }

    public function checkboxCallback($arg)
    {
        printf(
            '<input type="checkbox" id="' . $arg['key'] . '" name="sps-settings[' . $arg['key'] . ']" value="1" %s />',
            checked(1, isset(self::$_options[$arg['key']]) ? intval(self::$_options[$arg['key']]) : 0, false)
        );
        $this->printInputDescription($arg);
    }

    public function latLngCallback($arg)
    {
        printf(
            '<input type="text" id="' . $arg['key'] . '_lat" name="sps-settings[' . $arg['key'] . '][lat]" value="%s" maxlength="20" placeholder="%s" />
            <input type="text" id="' . $arg['key'] . '_lng" name="sps-settings[' . $arg['key'] . '][lng]" value="%s" maxlength="20" placeholder="%s" />',
            !empty(self::$_options[$arg['key']]['lat']) ? floatval(self::$_options[$arg['key']]['lat']) : '', __('Latitude', 'sps'),
            !empty(self::$_options[$arg['key']]['lng']) ? floatval(self::$_options[$arg['key']]['lng']) : '', __('Longitude', 'sps')
        );
        $this->printInputDescription($arg);
    }

    public function display()
    { ?>

        <div id="sps-settings">
            <h1>Super POI System
                <small>&copy; 2017 SopranoVillas</small>
            </h1>
            <form method="post" action="options.php">
                <?php settings_errors('sps-settings'); ?>
                <?php settings_fields('sps-settings'); ?>
                <?php do_settings_sections('sps-settings'); ?>
                <?php submit_button(); ?>
            </form>
            <h3>
                <?php _e("Want to place map manually?", 'sps'); ?>
            </h3>
            <ul>
               <li><?php _e('in template', 'sps'); ?>: <code>do_action('sps_map', latitude(optional), longitude(optional))</code></li>
               <li><?php _e('in post content', 'sps'); ?>: <code>[sps_map lat=latitude(optional), lng=longitude(optional)]</code></li>
            </ul>
            <p>
                <hr/>
                <br/>
                <?php _e("Author's website", 'sps'); ?>: <a href="https://www.sopranovillas.com">https://www.sopranovillas.com</a>
            </p>
        </div>

        <?php
    }

    public function sanitizeOptions($options)
    {
        $valid = true;
        foreach($options['map_coordinates'] as $key => &$coord) {
            if(!empty($coord) && !preg_match('/^-?[0-9]{1,3}\.[0-9]+$/', $coord)) {
                $coord = '';
                $valid = false;
                $type = 'error';
                $message = $key . ' ' . __( 'value is not a valid coordinate', 'sps' );

                add_settings_error(
                    'sps-settings',
                    esc_attr( 'map_coordinates_' . $key ),
                    $message,
                    $type
                );
            }
        }

        if(!intval($options['range'])) {
            unset($options['range']);
            $valid = false;
            $type = 'error';
            $message = __( 'Range value is not a valid integer', 'sps' );

            add_settings_error(
                'sps-settings',
                esc_attr( 'map_range' ),
                $message,
                $type
            );
        }

        if($valid) {
            $type = 'updated';
            $message = __( 'Settings successfully saved', 'sps' );

            add_settings_error(
                'sps-settings',
                esc_attr( 'settings_updated' ),
                $message,
                $type
            );
        }

        return $options;
    }

    private function printInputDescription($arg, $echo = true) {
        if (is_array($arg) && !empty($arg['description'])) {
            $descr = $arg['description'];
        } else {
            $descr = $arg;
        }

        if(!$echo) {
            return '<small class="input-description">' . $descr . '</small>';
        }

        echo '<small class="input-description">' . $descr . '</small>';
        return true;
    }

    private static function parseSettings( $args, $defaults = '' ) {
        if ( is_object( $args ) )
            $r = get_object_vars( $args );
        elseif ( is_array( $args ) )
            $r =& $args;
        else
            wp_parse_str( $args, $r );

        if ( is_array( $defaults ) ) {
            $r = array_filter(self::arrayFilter($r));
            return array_merge( $defaults, $r );
        }
        return $r;
    }

    private static function arrayFilter($arrayIn){
        $output = array();
        if (is_array($arrayIn)){
            foreach ($arrayIn as $key => $val){
                if (is_array($val)){
                    $output[$key] = self::arrayFilter($val);
                } elseif(!empty($val)) {
                    $output[$key] = $val;
                }
            }
        }

        return $output;
    }
}
