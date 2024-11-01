<?php

class SpsImporter
{
    private $_where;
    private $_spsPoi;
    private $_formAction = 'sps_importer_submit';
    private $_fileKey = 'sps_csv';
    private $_pageSlug = 'sps-importer';
    private $_allowedFiles = '.csv';

    public function __construct(SpsPoi $poi)
    {
        $this->_spsPoi = $poi;
        $this->_where = $poi->postType;
        add_action('admin_menu', array($this, 'adminMenu'));
        add_action('admin_post_' . $this->_formAction, array($this, 'handleForm'));

        if(!empty($_GET['count'])) {
            $count = (int)$_GET['count'];
            add_action('all_admin_notices', function() use ($count) {
                $this->importNotice($count);
            });
        } elseif(!empty($_GET['error'])) {
            $count = 0;
            add_action('all_admin_notices', function() use ($count) {
                $this->importNotice($count, false);
            });
        }
    }

    public function adminMenu()
    {
        add_submenu_page('edit.php?post_type=' . $this->_where, __('Super POI Importer', 'sps'), __('Super POI Importer', 'sps'),
            'manage_options', $this->_pageSlug, array($this, 'display'));
    }

    public function importNotice($count, $success = true) {
        if ( $success ) {
            if($count > 0) {
                $msg = sprintf(__('Import succeeded! Added POIs: %d', 'sps'), $count);
                echo '<div class="notice notice-success"><h4>'. $msg .'</h4></div>';
            } else {
                $msg = __('No records were imported (invalid entries)', 'sps');
                echo '<div class="notice notice-info"><h4>'. $msg .'</h4></div>';
            }
        } else {
            echo '<div class="notice notice-error"><h4>'. __('Import failed!', 'sps') .'</h4></div>';
        }
    }

    public function handleForm()
    {
        $returnUrl = admin_url('edit.php?post_type='. $this->_spsPoi->postType .'&page='. $this->_pageSlug);

        if (
            !isset($_POST['sps_nonce'])
            || !wp_verify_nonce($_POST['sps_nonce'], 'sps_importer_nonce')
            || !current_user_can('manage_options')
        ) {
            wp_safe_redirect($returnUrl . '&error=1');
            die();
        }

        $file = $_FILES[$this->_fileKey];

        if ($file['error'] > 0) {
            wp_safe_redirect($returnUrl . '&error=1');
            die();
        }

        $csvEntries = array_map('str_getcsv', file($file['tmp_name']));

        $addedEntries = 0;

        if(!empty($csvEntries)) {
            foreach($csvEntries as $entry) {
                if(!is_array($entry) || count($entry) < 3) {
                    continue;
                }

                //first three are required
                list($name, $lat, $lng, $address, $category, $shortDescription, $longDescription) = array_pad($entry, 7, null);

                if(!floatval($lat) || !floatval($lng)) {
                    continue;
                }

                $categories = explode(',', $category);
                $taxKeys = array_fill(0, count($categories), $this->_spsPoi->postTax);
                $mappedCategories = array_map('wp_insert_term', $categories, $taxKeys);

                $categoryIds = array();
                foreach($mappedCategories as $category) {
                    if($category instanceof WP_Error) {
                        if(array_key_exists('term_exists', $category->error_data)) {
                            $categoryIds[] = $category->error_data['term_exists'];
                        }
                    } else {
                        $categoryIds[] = $category['term_id'];
                    }
                }

                $postId = wp_insert_post(array(
                    'post_title' => sanitize_text_field($name),
                    'post_content' => sanitize_textarea_field($longDescription),
                    'post_excerpt' => sanitize_textarea_field($shortDescription),
                    'post_type' => $this->_where,
                    'post_status' => 'publish',
                    'meta_input' => array(
                        $this->_spsPoi->metaKey => array(
                            'address' => $address,
                            'lat' => $lat,
                            'lng' => $lng
                        )
                    ),
                    'tax_input' => array(
                        $this->_spsPoi->postTax => $categoryIds
                    )
                ));

                if($postId instanceof WP_Error) {
                    continue;
                }

                $addedEntries++;
            }
        }

        wp_safe_redirect($returnUrl . '&count=' . $addedEntries);
        die();
    }

    public function display()
    {
        $csvFile = plugin_dir_url(dirname(__FILE__)) . 'samples/sps_import.csv';
        ?>
        <div id="sps-settings">
            <h1>Super POI System - POI importer</h1>
            <br/>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <?php wp_nonce_field( 'sps_importer_nonce', 'sps_nonce' ); ?>
                <input name="action" type="hidden" value="<?= $this->_formAction ?>">
                <label for="sps-csv"><?php _e('Select CSV file', 'sps') ?></label>
                <input id="sps-csv" name="<?= $this->_fileKey ?>" type="file" accept="<?= $this->_allowedFiles ?>" />
                <?php submit_button(__('Start import', 'sps')); ?>
            </form>
            <br/>
            <hr/>
            <h4>Important!</h4>
            <ul>
                <li>
                    Importer doesn't check for duplicates. It will accept any valid records
                </li>
                <li>
                    Currently only CSV format with standard delimiters [ , ] and enclosure [ " ] will be parsed. <b>XLS import comming soon</b>
                </li>
                <li>
                    Elements order: <b>"Title",lat,lng</b>,"address","category(-ies)","short_description","long_description"
                    <br/>
                    <b>Part in bold is required</b>
                </li>
                <li>
                    Sample CSV: <a href="<?= $csvFile ?>">download</a>
                </li>
            </ul>
        </div>

        <?php
    }
}
