<?php
/*
Plugin Name: Super POI System
Description: The best way to integrate geographical ‘Points Of Interest’ to blogs, online travel guides etc: Set main location in a GMap and add surrounding POIs.
Version: 1.1.0
Author: Soprano Villas
Author URI: https://sopranovillas.com/
License: GPLv2 or later
Text Domain: sps
*/

defined('ABSPATH') or die('No script kiddies please!');

class SuperPoiSystem
{
    private $_poi;
    private $_settings;

    public function __construct() {
        if (!$this->_canActivate()) {
            return;
        }

        // autoloader
        spl_autoload_register(array($this, '_loader'));

        // vars
        $this->_settings = array(
            'path' => $this->getPath(__FILE__),
            'url' => $this->getUrl(__FILE__)
        );

        // set text domain
        load_textdomain('sps', $this->_settings['path'] . 'lang/sps-' . get_locale() . '.mo');

        // actions
        add_action('wp_enqueue_scripts', array($this, 'publicAssets'));
        add_action('admin_enqueue_scripts', array($this, 'adminAssets'));

        $plugin = plugin_basename( __FILE__ );
        add_filter( "plugin_action_links_$plugin", array($this, 'addSettingsLink') );

        // tweaks
        add_image_size('sps-poi-img', 0, 300);

        // classes
        $this->_poi = new SpsPoi();
        new SpsSettings($this->_poi->postType);
        new SpsImporter($this->_poi);
    }

    public function addSettingsLink( $links ) {
        $settings_link = '<a href="edit.php?post_type=' . $this->_poi->postType .'&page=sps-settings">' . __( 'Settings' ) . '</a>';
        array_push( $links, $settings_link );
        return $links;
    }

    public function coreAssets() {
        wp_register_script('sps-core-script', $this->_settings['url'] . 'public/core.js', array('jquery'), false, true);
        wp_localize_script('sps-core-script', 'spsSettings', SpsSettings::getOptions());
        wp_enqueue_script('sps-core-script');
    }

    public function publicAssets() {
        $options = SpsSettings::getOptions();
        if(!in_array(get_post_type(), $options['post_types'])) {
            return;
        }

        $this->coreAssets();
        wp_enqueue_script('sps-map-script', $this->_settings['url'] . 'public/map.js', array('sps-core-script'), false, true);
        wp_localize_script('sps-map-script', 'spsMapVars', array(
            'images' => $this->_settings['url'] . 'public/img/',
            'infobox' => $this->_settings['url'] . 'public/infobox.js'
        ));

        wp_enqueue_style('sps-style', $this->_settings['url'] . 'public/style.css');

        if($options['custom_font']) {
            wp_enqueue_style('sps-google-font', 'https://fonts.googleapis.com/css?family=Quicksand');
        }
    }

    public function adminAssets() {
        if(!$this->_isPluginPage()) {
            return;
        }

        wp_enqueue_style('sps-style', $this->_settings['url'] . 'admin/style.css');

        if($this->_isPluginPostEditPage()) {
            $this->coreAssets();
            wp_enqueue_script('sps-admin-script', $this->_settings['url'] . 'admin/map.js', array('sps-core-script', 'sps-select2'), false, true);
            wp_enqueue_script('sps-select2', $this->_settings['url'] . 'admin/select2/js/select2.min.js', array(), false, true);

            wp_enqueue_style('sps-select2', $this->_settings['url'] . 'admin/select2/css/select2.min.css');
        }

        if($this->_isPluginSettingsPage()) {
            wp_enqueue_script('sps-settings-script', $this->_settings['url'] . 'admin/settings.js', array('jquery'), false, true);
        }
    }

    public function getPath($file) {
        return trailingslashit(dirname($file));
    }

    public function getUrl($file) {
        $dir = $this->getPath($file);
        $count = 0;

        // sanitize for Win32 installs
        $dir = str_replace('\\', '/', $dir);

        // if file is in plugins folder
        $wp_plugin_dir = str_replace('\\', '/', WP_PLUGIN_DIR);
        $dir = str_replace($wp_plugin_dir, plugins_url(), $dir, $count);

        if ($count < 1) {
            // if file is in wp-content folder
            $wp_content_dir = str_replace('\\', '/', WP_CONTENT_DIR);
            $dir = str_replace($wp_content_dir, content_url(), $dir, $count);
        }

        if ($count < 1) {
            // if file is in ??? folder
            $wp_dir = str_replace('\\', '/', ABSPATH);
            $dir = str_replace($wp_dir, site_url('/'), $dir);
        }

        return $dir;
    }

    private function _canActivate() {
        global $wp_version;

        return version_compare($wp_version, 4.5, '>=');
    }

    private function _isPluginPostEditPage() {
        return $this->_isPluginPage(array('post.php', 'post-new.php'));
    }

    private function _isPluginSettingsPage() {
        return $this->_isPluginPage(array('edit.php'));
    }

    private function _isPluginPage($scope = array('post.php', 'post-new.php', 'edit.php')) {
        global $pagenow, $typenow;

        // validate page
        if (is_admin() && in_array($pagenow, $scope)) {
            $options = SpsSettings::getOptions();
            return in_array($typenow, $options['post_types']) || $typenow == 'sps_poi';
        }

        return false;
    }

    private function _loader($className) {
        if(strcasecmp(substr($className, 0, 3), 'sps') !== 0) {
            return;
        }

        //translate ClassName into file-name
        $className = strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $className));

        //just for safety, sanitize path so it's not ascending directory tree
        $className = preg_replace('|\.\./|i', '', $className);

        include 'inc' . DIRECTORY_SEPARATOR . $className . '.php';
    }
}

//init
new SuperPoiSystem();