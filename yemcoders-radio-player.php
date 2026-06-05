<?php
/**
 * Plugin Name: YemCoders Radio Player
 * Description: A simple radio player plugin for WordPress with multiple stations, manageable from the dashboard, categorized lists, search, and an optional station grid.
 * Version: 1.8.7
 * Author: Mahesh Kumar
 * Author URL: https://maheshkumarm.com
 * License: GPL2
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin path and URL for convenience
define('YCR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('YCR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Add a user notice to the admin dashboard
function custom_radio_player_admin_notice() {
    if (isset($_GET['page']) && $_GET['page'] === 'radio-player-settings') {
        ?>
        <div class="notice notice-info is-dismissible">
            <p><strong>YemCoders Radio Player:</strong> To use the radio player on your site, add the following shortcode to any post or page:</p>
            <pre>[custom_radio_player]</pre>
            <p>To filter by language: <code>[custom_radio_player language="English"]</code></p>
            <p>To filter by country: <code>[custom_radio_player country="USA"]</code></p>
            <p>To filter by both: <code>[custom_radio_player language="Spanish" country="Spain"]</code></p>
            <p>This will display the player (initially paused) and a grid of available stations below it, with a search option. A "Popup Player" button will also be available.</p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'custom_radio_player_admin_notice');

// Register plugin settings
function ycr_radio_player_register_settings() {
    register_setting( 'radio_player_settings_group', 'radio_player_stations', 'ycr_sanitize_stations_callback' );
    register_setting( 'radio_player_settings_group', 'radio_player_default_station', 'absint' );
    register_setting( 'radio_player_settings_group', 'radio_player_recently_added_limit', 'absint' );
    register_setting( 'radio_player_settings_group', 'radio_player_frontend_pagination_per_page', 'absint' );
    register_setting( 'radio_player_settings_group', 'radio_player_stations_per_row', 'absint' );
    register_setting( 'radio_player_settings_group', 'radio_player_stations_per_row_mobile', 'absint' );
    register_setting( 'radio_player_settings_group', 'radio_player_login_link_type', 'sanitize_text_field' );
    register_setting( 'radio_player_settings_group', 'radio_player_login_link_url', 'ycr_sanitize_login_url' );
    register_setting( 'radio_player_settings_group', 'radio_player_login_link_class', 'sanitize_text_field' );
    register_setting( 'radio_player_settings_group', 'radio_player_login_button_text', 'sanitize_text_field' );
    register_setting( 'radio_player_settings_group', 'radio_player_login_icon_class', 'sanitize_text_field' );
    register_setting( 'radio_player_settings_group', 'radio_player_disclaimer_text', 'wp_kses_post' );
    register_setting( 'radio_player_settings_group', 'radio_player_custom_css', 'wp_kses_post' );
}
add_action( 'admin_init', 'ycr_radio_player_register_settings' );


function ycr_sanitize_login_url( $url ) {
    if ( 'javascript:void(0);' === $url || '#' === $url || '' === $url ) {
        return $url;
    }
    return esc_url_raw( $url );
}

function ycr_sanitize_stations_callback( $input ) {
    $new_input = [];
    if ( is_array( $input ) ) {
        foreach ( $input as $index => $station ) {
            if ( is_array( $station ) ) {
                $new_input[ $index ] = [
                    'name'     => sanitize_text_field( $station['name'] ?? '' ),
                    'url'      => esc_url_raw( $station['url'] ?? '' ),
                    'logo'     => esc_url_raw( $station['logo'] ?? '' ),
                    'country'  => sanitize_text_field( $station['country'] ?? '' ),
                    'language' => sanitize_text_field( $station['language'] ?? '' ),
                    'pinned'   => isset( $station['pinned'] ) ? (bool) $station['pinned'] : false,
                ];
                // Ensure added_timestamp is preserved if it exists, especially for single station saves
                if (isset($station['added_timestamp'])) {
                    $new_input[$index]['added_timestamp'] = absint($station['added_timestamp']);
                }
            }
        }
    }
    return $new_input;
}

function custom_radio_player_enqueue_scripts() {
    $plugin_version = '1.8.7';
    wp_enqueue_style('custom-radio-player-style', YCR_PLUGIN_URL . 'css/style_v10.css', array(), $plugin_version);
    wp_enqueue_script('custom-radio-player-script', YCR_PLUGIN_URL . 'js/script_v10.js', array('jquery'), $plugin_version, true);

    $user_pinned_stations_urls = [];
    $is_user_logged_in = is_user_logged_in();
    $pin_nonce = '';
    $user_display_name = '';

    if ($is_user_logged_in) {
        $user_id = get_current_user_id();
        $current_user = wp_get_current_user();
        $user_display_name = $current_user->display_name;

        $raw_pinned_stations = get_user_meta($user_id, '_ycr_user_pinned_stations', true);
        if (is_array($raw_pinned_stations)) {
            $user_pinned_stations_urls = array_values(array_unique(array_filter($raw_pinned_stations, 'esc_url_raw')));
        }
        $pin_nonce = wp_create_nonce('ycr_pin_station_nonce');
    }

    $stations_per_row = (int) get_option('radio_player_stations_per_row', 6);
    if (!in_array($stations_per_row, [3, 4, 5, 6, 8, 10])) $stations_per_row = 6;

    $stations_per_row_mobile = (int) get_option('radio_player_stations_per_row_mobile', 3);
    if (!in_array($stations_per_row_mobile, [2, 3, 4])) $stations_per_row_mobile = 3;

    $favorites_title_js = $is_user_logged_in && !empty($user_display_name) ? esc_html($user_display_name) . "'s Favorite Stations" : "My Favorite Stations";

    $login_link_type = get_option('radio_player_login_link_type', 'direct');
    $login_link_url = get_option('radio_player_login_link_url', wp_login_url(get_permalink()));
    $login_link_class = get_option('radio_player_login_link_class', '');
    $login_button_text = get_option('radio_player_login_button_text', 'Login');
    $login_icon_class = get_option('radio_player_login_icon_class', 'fas fa-sign-in-alt');


    wp_localize_script('custom-radio-player-script', 'ycRadioPlayerGlobal', array(
        'popupUrl' => home_url('/?yc_radio_action=popup_player'),
        'defaultLogoUrl' => YCR_PLUGIN_URL . 'images/default-logo.png',
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'isLoggedIn' => $is_user_logged_in,
        'userPinnedStations' => $user_pinned_stations_urls,
        'pinNonce' => $pin_nonce,
        'stationsPerRow' => $stations_per_row,
        'stationsPerRowMobile' => $stations_per_row_mobile,
        'favoritesTitle' => $favorites_title_js,
        'loginLinkType' => $login_link_type,
        'loginLinkUrl' => esc_url($login_link_url),
        'loginLinkClass' => esc_attr($login_link_class),
        'loginButtonText' => esc_html($login_button_text),
        'loginIconClass' => esc_attr($login_icon_class),
        'loginNoticeTemplate' => '
            <div id="ycr-login-popup" class="ycr-popup-overlay">
                <div class="ycr-popup-content">
                    <button class="ycr-popup-close-btn">&times;</button>
                    <p>Log in or create an account to build your personal list of favorite stations. Your favorites will appear in the "My Favorite Stations" section.</p>
                    <a href="#" id="ycr-dialog-login-link" class="button ycr-dialog-login-btn">
                        <i class="fas fa-sign-in-alt"></i> <span>Login</span>
                    </a>
                </div>
            </div>',
        'accessRestrictedNoticeTemplate' => '
            <div id="ycr-access-restricted-popup" class="ycr-popup-overlay">
                <div class="ycr-popup-content">
                    <button class="ycr-popup-close-btn">&times;</button>
                    <p>Log in or create an account to access more stations.</p>
                     <a href="#" id="ycr-dialog-restricted-login-link" class="button ycr-dialog-login-btn">
                        <i class="fas fa-sign-in-alt"></i> <span>Login</span>
                    </a>
                </div>
            </div>',
        'errorNoticeTemplate' => '<div class="ycr-user-notice ycr-error-notice" style="display:none; padding: 10px; margin:5px 0; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; border-radius: 4px; text-align:center;"></div>',
        'infoNoticeTemplate' => '<div class="ycr-user-notice ycr-info-notice" style="display:none; padding: 10px; margin:5px 0; border: 1px solid #bee5eb; background-color: #d1ecf1; color: #0c5460; border-radius: 4px; text-align:center;"></div>',
        // Default values, will be overridden by shortcode if attributes are used
        'filteredLanguageByShortcode' => '',
        'filteredCountryByShortcode'  => '',
        'baseBrowseHeading' => 'Browse All Stations' // Default heading for the main grid
    ));

    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), null);
}
add_action('wp_enqueue_scripts', 'custom_radio_player_enqueue_scripts');

function custom_radio_player_admin_enqueue_scripts($hook_suffix) {
    // Enqueue for main settings page
    if ($hook_suffix === 'toplevel_page_radio-player-settings') {
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
    }
    // For Manage Taxonomies page
    else if ($hook_suffix === 'radio-player-settings_page_ycr_manage_taxonomies') {
        wp_enqueue_script('jquery');
    }
}
add_action('admin_enqueue_scripts', 'custom_radio_player_admin_enqueue_scripts');

function custom_radio_player_admin_menu() {
    add_menu_page( 'Radio Player Settings', 'Radio Stations', 'manage_options', 'radio-player-settings', 'custom_radio_player_settings_page', 'dashicons-format-audio', 100 );
    add_submenu_page( 'radio-player-settings', 'Manage Taxonomies', 'Manage Taxonomies', 'manage_options', 'ycr_manage_taxonomies', 'ycr_render_manage_taxonomies_page' );
	add_submenu_page( 'radio-player-settings', 'Radio Player - Need Help?', 'Need Help?', 'manage_options', 'ycr_need_help', 'ycr_render_need_help_page' );
}
add_action('admin_menu', 'custom_radio_player_admin_menu');

if (!function_exists('ycr_render_manage_taxonomies_page')) {
    function ycr_render_manage_taxonomies_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        $taxonomies = [
            'country'  => ['label' => 'Countries', 'option_name' => 'radio_player_countries'],
            'language' => ['label' => 'Languages', 'option_name' => 'radio_player_languages'],
        ];
        ?>
        <div class="wrap ycr-taxonomy-manager">
            <h1>Manage Taxonomies</h1>
            <p>Add, edit, or delete terms for Countries, and Languages. Changes here will be reflected in the station management dropdowns and on the frontend.</p><hr>
            <?php foreach ($taxonomies as $tax_key => $tax_data):
                $terms = get_option($tax_data['option_name'], []); if (!is_array($terms)) $terms = []; sort($terms);
                $singular_label = ($tax_key === 'country') ? 'Country' : 'Language';
            ?>
            <div class="ycr-taxonomy-section" id="ycr-taxonomy-section-<?php echo esc_attr($tax_key); ?>">
                <h2><?php echo esc_html($tax_data['label']); ?></h2>
                <div class="ycr-add-term-form">
                    <h3>Add New <?php echo esc_html($singular_label); ?></h3>
                    <form class="ycr-add-form" data-taxonomy="<?php echo esc_attr($tax_key); ?>">
                        <input type="hidden" name="ycr_taxonomy_nonce_field_<?php echo esc_attr($tax_key); ?>_add" value="<?php echo wp_create_nonce('ycr_add_term_' . $tax_key); ?>">
                        <input type="text" name="term_name" class="ycr-new-term-name regular-text" placeholder="New <?php echo esc_attr(strtolower($singular_label)); ?> name" required>
                        <button type="submit" class="button button-primary ycr-add-term-btn">Add <?php echo esc_html($singular_label); ?></button>
                        <span class="spinner"></span><span class="ycr-form-status"></span>
                    </form>
                </div>
                <div class="ycr-terms-list-wrapper">
                    <h3>Existing <?php echo esc_html($tax_data['label']); ?></h3>
                    <?php if (!empty($terms)): ?>
                    <ul class="ycr-terms-list" data-taxonomy="<?php echo esc_attr($tax_key); ?>">
                        <?php foreach ($terms as $term_name):
                            $edit_nonce_action = 'ycr_edit_term_' . $tax_key . '_' . $term_name;
                            $delete_nonce_action = 'ycr_delete_term_' . $tax_key . '_' . $term_name;
                        ?>
                        <li data-term="<?php echo esc_attr($term_name); ?>"
                            data-nonce-edit="<?php echo wp_create_nonce($edit_nonce_action); ?>"
                            data-nonce-delete="<?php echo wp_create_nonce($delete_nonce_action); ?>">
                            <span class="ycr-term-name"><?php echo esc_html($term_name); ?></span>
                            <span class="ycr-term-actions">
                                <button class="button button-small ycr-edit-term-btn">Edit</button>
                                <button class="button button-small button-link-delete ycr-delete-term-btn">Delete</button>
                            </span>
                            <div class="ycr-edit-term-inline" style="display:none;">
                                <input type="text" class="ycr-edit-term-input regular-text" value="<?php echo esc_attr($term_name); ?>">
                                <button class="button button-small button-primary ycr-save-edit-btn">Save</button>
                                <button class="button button-small ycr-cancel-edit-btn">Cancel</button>
                                <span class="spinner"></span><span class="ycr-edit-status"></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?><p>No <?php echo esc_html(strtolower($tax_data['label'])); ?> added yet.</p><?php endif; ?>
                </div>
            </div><hr>
            <?php endforeach; ?>
        </div>
        <style>
            .ycr-taxonomy-manager h1, .ycr-taxonomy-manager h2, .ycr-taxonomy-manager h3 { margin-bottom: 0.5em; }
            .ycr-taxonomy-section { margin-bottom: 30px; padding: 20px; background-color: #fff; border: 1px solid #ccd0d4; border-radius: 4px; }
            .ycr-add-term-form { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px dashed #eee; }
            .ycr-add-term-form form { display: flex; align-items: center; gap: 10px; }
            .ycr-add-term-form .ycr-new-term-name { flex-grow: 1; }
            .ycr-terms-list { list-style: none; margin: 0; padding: 0; }
            .ycr-terms-list li { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f0f0f0; flex-wrap: wrap;}
            .ycr-terms-list li:last-child { border-bottom: none; }
            .ycr-term-name { flex-grow: 1; margin-right: 10px;}
            .ycr-term-actions button { margin-left: 5px; }
            .ycr-edit-term-inline { display: flex; align-items: center; gap: 5px; width: 100%; margin-top: 5px; }
            .ycr-edit-term-inline .ycr-edit-term-input { flex-grow: 1; }
            .ycr-form-status, .ycr-edit-status { font-style: italic; font-size: 0.9em; margin-left: 10px; }
            .ycr-form-status.success, .ycr-edit-status.success { color: green; }
            .ycr-form-status.error, .ycr-edit-status.error { color: red; }
            .ycr-taxonomy-section .spinner { visibility: hidden; margin-left:5px; }
            .ycr-taxonomy-section .spinner.is-active { visibility: visible; }
        </style>
        <script type="text/javascript">
            const ycrAdminAjax = { ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>' }; // Define ycrAdminAjax for this script
            jQuery(document).ready(function($) {
                // --- Taxonomy Manager ---
                $('.ycr-taxonomy-manager').on('submit', '.ycr-add-form', function(e) {
                    e.preventDefault();
                    const $form = $(this);
                    const $button = $form.find('.ycr-add-term-btn');
                    const $spinner = $form.find('.spinner');
                    const $status = $form.find('.ycr-form-status');
                    const taxonomyKey = $form.data('taxonomy');
                    const termName = $form.find('.ycr-new-term-name').val().trim();
                    const addNonceValue = $form.find('input[name="ycr_taxonomy_nonce_field_' + taxonomyKey + '_add"]').val();

                    if (!termName) {
                        $status.text('Term name cannot be empty.').removeClass('success').addClass('error');
                        return;
                    }

                    $button.prop('disabled', true);
                    $spinner.addClass('is-active');
                    $status.text('').removeClass('success error');

                    $.ajax({
                        url: ycrAdminAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ycr_add_taxonomy_term',
                            nonce_field_add: addNonceValue,
                            taxonomy_key: taxonomyKey,
                            term_name: termName
                        },
                        success: function(response) {
                            if (response.success) {
                                $status.text(response.data.message).removeClass('error').addClass('success');
                                $form.find('.ycr-new-term-name').val('');
                                const $listWrapper = $('#ycr-taxonomy-section-' + taxonomyKey).find('.ycr-terms-list-wrapper');
                                let $list = $listWrapper.find('.ycr-terms-list');

                                if (!$list.length) {
                                     $listWrapper.find('p').remove();
                                     $listWrapper.append('<ul class="ycr-terms-list" data-taxonomy="'+taxonomyKey+'"></ul>');
                                     $list = $listWrapper.find('.ycr-terms-list');
                                }

                                const newLiHTML = `
                                    <li data-term="${response.data.term}"
                                        data-nonce-edit="${response.data.new_nonce_edit || ''}"
                                        data-nonce-delete="${response.data.new_nonce_delete || ''}">
                                        <span class="ycr-term-name">${response.data.term}</span>
                                        <span class="ycr-term-actions">
                                            <button class="button button-small ycr-edit-term-btn">Edit</button>
                                            <button class="button button-small button-link-delete ycr-delete-term-btn">Delete</button>
                                        </span>
                                        <div class="ycr-edit-term-inline" style="display:none;">
                                            <input type="text" class="ycr-edit-term-input regular-text" value="${response.data.term}">
                                            <button class="button button-small button-primary ycr-save-edit-btn">Save</button>
                                            <button class="button button-small ycr-cancel-edit-btn">Cancel</button>
                                            <span class="spinner"></span>
                                            <span class="ycr-edit-status"></span>
                                        </div>
                                    </li>`;
                                const $newLi = $(newLiHTML);
                                $list.append($newLi);

                                $list.children('li').sort(function(a, b) {
                                    const termA = $(a).data('term') ? $(a).data('term').toString().toLowerCase() : '';
                                    const termB = $(b).data('term') ? $(b).data('term').toString().toLowerCase() : '';
                                    return termA.localeCompare(termB);
                                }).appendTo($list);

                            } else {
                                $status.text(response.data.message || response.data || 'Error adding term.').removeClass('success').addClass('error');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            $status.text('AJAX error: ' + textStatus).removeClass('success').addClass('error');
                        },
                        complete: function() {
                            $button.prop('disabled', false);
                            $spinner.removeClass('is-active');
                            setTimeout(() => $status.text(''), 3000);
                        }
                    });
                });

                $('.ycr-taxonomy-manager').on('click', '.ycr-edit-term-btn', function(e) {
                    e.preventDefault();
                    const $li = $(this).closest('li');
                    $li.find('.ycr-term-name, .ycr-term-actions').hide();
                    $li.find('.ycr-edit-term-inline').show().find('.ycr-edit-term-input').focus();
                });

                $('.ycr-taxonomy-manager').on('click', '.ycr-cancel-edit-btn', function(e) {
                    e.preventDefault();
                    const $li = $(this).closest('li');
                    $li.find('.ycr-edit-term-inline').hide().find('.ycr-edit-status').text('');
                    $li.find('.ycr-term-name, .ycr-term-actions').show();
                    const originalTerm = $li.data('term');
                    $li.find('.ycr-edit-term-input').val(originalTerm);
                });

                $('.ycr-taxonomy-manager').on('click', '.ycr-save-edit-btn', function(e) {
                    e.preventDefault();
                    const $button = $(this);
                    const $editDiv = $button.closest('.ycr-edit-term-inline');
                    const $li = $editDiv.closest('li');
                    const $spinner = $editDiv.find('.spinner');
                    const $status = $editDiv.find('.ycr-edit-status');
                    const taxonomyKey = $li.closest('.ycr-terms-list').data('taxonomy');
                    const oldTermName = $li.data('term');
                    const newTermName = $editDiv.find('.ycr-edit-term-input').val().trim();
                    const editNonceValue = $li.data('nonce-edit');
                    let ajaxResponseSuccess = false;


                    if (!newTermName) {
                        $status.text('Term name cannot be empty.').removeClass('success').addClass('error');
                        return;
                    }
                    if (newTermName === oldTermName) {
                        $status.text('Name unchanged.').removeClass('error').addClass('success');
                         setTimeout(() => {
                            $editDiv.hide();
                            $status.text('');
                            $li.find('.ycr-term-name, .ycr-term-actions').show();
                        }, 1500);
                        return;
                    }

                    $button.prop('disabled', true).siblings('button').prop('disabled', true);
                    $spinner.addClass('is-active');
                    $status.text('').removeClass('success error');

                    $.ajax({
                        url: ycrAdminAjax.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ycr_edit_taxonomy_term',
                            nonce_field_edit: editNonceValue,
                            taxonomy_key: taxonomyKey,
                            old_term_name: oldTermName,
                            new_term_name: newTermName
                        },
                        success: function(response) {
                            ajaxResponseSuccess = response.success;
                            if (response.success) {
                                $status.text(response.data.message).removeClass('error').addClass('success');
                                $li.data('term', response.data.new_term);
                                $li.find('.ycr-term-name').text(response.data.new_term);
                                $editDiv.find('.ycr-edit-term-input').val(response.data.new_term);

                                if (response.data.new_nonce_edit) {
                                    $li.data('nonce-edit', response.data.new_nonce_edit);
                                    $li.attr('data-nonce-edit', response.data.new_nonce_edit);
                                }
                                if (response.data.new_nonce_delete) {
                                    $li.data('nonce-delete', response.data.new_nonce_delete);
                                    $li.attr('data-nonce-delete', response.data.new_nonce_delete);
                                }

                                setTimeout(() => {
                                    $editDiv.hide();
                                    $status.text('');
                                    $li.find('.ycr-term-name, .ycr-term-actions').show();
                                    const $list = $li.parent();
                                    $list.children('li').sort(function(a,b){
                                       const termA = $(a).data('term') ? $(a).data('term').toString().toLowerCase() : '';
                                       const termB = $(b).data('term') ? $(b).data('term').toString().toLowerCase() : '';
                                       return termA.localeCompare(termB);
                                    }).appendTo($list);
                                }, 1500);
                            } else {
                                $status.text(response.data.message || response.data || 'Error updating term.').removeClass('success').addClass('error');
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            $status.text('AJAX error: ' + textStatus).removeClass('success').addClass('error');
                        },
                        complete: function() {
                            $button.prop('disabled', false).siblings('button').prop('disabled', false);
                            $spinner.removeClass('is-active');
                             if(!ajaxResponseSuccess) {
                                setTimeout(() => $status.text(''), 3000);
                             }
                        }
                    });
                });

                $('.ycr-taxonomy-manager').on('click', '.ycr-delete-term-btn', function(e) {
                    e.preventDefault();

                    if (!confirm('Are you sure you want to delete this term? This will remove it from all stations.')) {
                        return;
                    }
                    const $button = $(this);
                    const $li = $button.closest('li');
                    const taxonomyKey = $li.closest('.ycr-terms-list').data('taxonomy');
                    const termName = $li.data('term');
                    const deleteNonceValue = $li.data('nonce-delete');
                    const $actions = $button.closest('.ycr-term-actions');

                    $actions.find('button').hide();
                    const $spinner = $('<span class="spinner is-active" style="display:inline-block; vertical-align:middle;"></span>');
                    $actions.append($spinner);

                    $.ajax({
                        url: ycrAdminAjax.ajaxUrl,
                        type: 'POST',

                        data: {
                            action: 'ycr_delete_taxonomy_term',
                            nonce_field_delete: deleteNonceValue,
                            taxonomy_key: taxonomyKey,
                            term_name: termName
                        },
                        success: function(response) {
                            if (response.success) {
                                $li.fadeOut(300, function() {
                                    $(this).remove();
                                    const $list = $('#ycr-taxonomy-section-' + taxonomyKey).find('.ycr-terms-list');
                                    if ($list.children().length === 0) {
                                        let taxLabelPlural = taxonomyKey.endsWith('s') ? taxonomyKey : taxonomyKey + 's';
                                        if (taxonomyKey === 'country') taxLabelPlural = 'countries';
                                        else if (taxonomyKey === 'language') taxLabelPlural = 'languages';
                                        $list.parent().append('<p>No ' + taxLabelPlural + ' added yet.</p>');
                                        $list.remove();
                                    }
                                });
                            } else {
                                alert('Error: ' + (response.data.message || response.data || 'Could not delete term.'));
                                $spinner.remove();
                                $actions.find('button').show();
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            alert('AJAX error: ' + textStatus + '. Could not delete term.');
                            $spinner.remove();
                            $actions.find('button').show();
                        }
                    });
                });
            });
        </script>
        <?php
    }
}

if (!function_exists('ycr_render_need_help_page')) {
    function ycr_render_need_help_page() {
        // Path to the need_help.php file
        $need_help_file_path = YCR_PLUGIN_PATH . 'includes/need_help.php';

        if ( file_exists( $need_help_file_path ) ) {
            require_once $need_help_file_path; // Include the file

            // Check if the function from the included file exists and call it
            if ( function_exists( 'ycr_render_actual_need_help_content' ) ) {
                ycr_render_actual_need_help_content();
            } else {
                // Fallback or error message if the function is not found after including
                echo '<div class="wrap"><p>Error: Could not load the help content. The required function is missing.</p></div>';
            }
        } else {
            // Fallback or error message if the file itself is missing
            echo '<div class="wrap"><p>Error: Could not load the help content. File not found at: ' . esc_html($need_help_file_path) . '</p></div>';
        }
    }
}


if (!function_exists('custom_radio_player_settings_page')) {
    function custom_radio_player_settings_page() {
        $stations_option = get_option('radio_player_stations', []);
        $default_station_idx = get_option('radio_player_default_station', 0);
        $disclaimer_text = get_option('radio_player_disclaimer_text', 'This website provides links to various radio stations. We are not responsible for the content.');
        $custom_css = get_option('radio_player_custom_css', '');
        $default_admin_logo = YCR_PLUGIN_URL . 'images/default-admin-logo.png';
        $master_countries = get_option('radio_player_countries', []); sort($master_countries);
        $master_languages = get_option('radio_player_languages', []); sort($master_languages);
        ?>
        <div class="wrap ycr-admin-wrap">
            <h1><span class="dashicons dashicons-format-audio" style="font-size: 1.3em; margin-right: 5px; vertical-align: middle;"></span>Radio Player Settings</h1>
            <style>
                .ycr-admin-wrap h1 { margin-bottom: 20px; font-weight: 600; }
                .ycr-admin-wrap h2 { margin-top: 35px; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd; font-size: 1.4em; }
                #stations-table { margin-bottom: 20px; border: 1px solid #e0e0e0; }
                #stations-table th { background-color: #f5f5f5; font-weight: 600; padding: 12px 10px; }
                #stations-table td { vertical-align: middle; padding: 10px; }
                #stations-table .column-name { width: 20%; } #stations-table .column-url { width: 25%; }
                #stations-table .column-logo { width: 18%; } #stations-table .column-country { width: 10%; }
                #stations-table .column-language { width: 10%; } #stations-table .column-pin { width: 5%; text-align: center; }
                #stations-table .column-action { width: 12%; text-align: center; }
                .station-logo-preview { max-width: 50px; max-height: 50px; vertical-align: middle; border:1px solid #ccc; margin-right: 10px; background-color: #fff; padding: 2px; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.07); }
                .station-logo-preview[src$="default-admin-logo.png"] { opacity:0.6; }
                #stations-table .button { margin-right: 5px; vertical-align: middle; }
                #stations-table .button.remove-station, #stations-table .button.remove-logo-button { color: #b32d2e; border-color: #b32d2e; }
                #stations-table .button.remove-station:hover, #stations-table .button.remove-logo-button:hover { background-color: #b32d2e; color: #fff; }
                .station-country-select, .station-language-select, .new-country-input, .new-language-input,
                #radio_player_default_station_select, textarea[name="radio_player_disclaimer_text"], textarea[name="radio_player_custom_css"] { width: 100%; box-sizing: border-box; padding: 8px; border-radius: 4px; border: 1px solid #ddd; }
                .new-country-input, .new-language-input { margin-top: 8px; border-color: #007cba; }
                #stations-table input[type="text"].new-country-input::placeholder, #stations-table input[type="text"].new-language-input::placeholder { font-style: italic; color: #888; }
                #stations-sortable-list .ui-state-highlight { height: 60px; background: #e6f7ff; border: 1px dashed #90c2de; }
                #add-station { margin-top: 0px; margin-bottom: 0px; margin-right: 10px; width: auto; height: auto; font-size: 14px; padding: 8px 15px; }
                .ycr-admin-wrap .form-table:not(#stations-table) td { padding: 15px 10px 15px 0; }
                .ycr-admin-wrap select#radio_player_default_station_select { max-width: 350px; }
                .ycr-admin-wrap textarea[name="radio_player_disclaimer_text"], .ycr-admin-wrap textarea[name="radio_player_custom_css"] { max-width: 700px; min-height: 100px; }
                #stations-table .regular-text { width: 100%; box-sizing: border-box; }
                .ycr-station-filters { margin-bottom: 20px; padding: 15px; background-color: #fdfdfd; border: 1px solid #ccd0d4; border-radius: 4px; display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
                .ycr-station-filters > div { display: flex; flex-direction: column; flex-grow: 1; flex-basis: 0; min-width: 160px; }
                .ycr-station-filters > div.ycr-add-station-container { min-width: auto; flex-grow: 0; flex-basis: auto; align-items: center; justify-content: center; }
                .ycr-station-filters > div.ycr-add-station-container label { display: none; }
                .ycr-station-filters label { margin-bottom: 5px; font-weight: normal; font-size: 13px; color: #2c3338; }
                .ycr-station-filters input[type="text"], .ycr-station-filters select { width: 100%; box-sizing: border-box; }
                .ycr-pagination-controls { margin-top: 15px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; padding: 0 5px; }
                .ycr-pagination-controls .page-info { font-style: italic; color: #50575e; font-size: 13px; }
                .ycr-pagination-controls .button { margin-left: 5px; }
                .ycr-row-save-spinner, .ycr-row-delete-spinner { display: inline-block; vertical-align: middle; visibility: hidden; margin-left: 5px; }
                .ycr-row-save-spinner.is-active, .ycr-row-delete-spinner.is-active { visibility: visible; }
                .ycr-row-status-message { display: inline-block; margin-left: 8px; font-size: 12px; font-style: italic; }
                .ycr-save-row-btn.hidden-by-default { display: none; }
                .form-table th { padding: 10px; }
                .form-table td p.description { font-size: 13px; }
            </style>
            <form method="post" action="options.php">
                <?php settings_fields('radio_player_settings_group'); ?>
                <h2>Manage Stations <a href="<?php echo admin_url('admin.php?page=ycr_manage_taxonomies'); ?>" class="page-title-action">Manage Countries/Languages</a></h2>
                <p><em>Drag and drop rows to reorder stations. Select an existing country/language, or choose "Add New..." to create a new one. The "Save" button in each row appears when you make changes. New terms added here will be available globally. For advanced term management (edit/delete), use the "Manage Taxonomies" link above.</em></p>

                <div class="ycr-station-filters">
                    <div><label for="ycr-station-search-input">Search Stations:</label><input type="text" id="ycr-station-search-input" placeholder="Name, URL, Country, Language..." class="regular-text"></div>
                    <div><label for="ycr-country-filter-select">Filter by Country:</label><select id="ycr-country-filter-select" class="regular-text"><option value="">All Countries</option><?php if(!empty($master_countries)) foreach($master_countries as $cn) echo '<option value="'.esc_attr($cn).'">'.esc_html($cn).'</option>'; ?></select></div>
                    <div><label for="ycr-language-filter-select">Filter by Language:</label><select id="ycr-language-filter-select" class="regular-text"><option value="">All Languages</option><?php if(!empty($master_languages)) foreach($master_languages as $ln) echo '<option value="'.esc_attr($ln).'">'.esc_html($ln).'</option>'; ?></select></div>
                    <div><label for="ycr-items-per-page-select">Show per page:</label><select id="ycr-items-per-page-select" class="regular-text"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option><option value="-1">Show All</option></select></div>
                    <div class="ycr-add-station-container"><label style="visibility: hidden;"> </label><button type="button" id="add-station" class="button button-primary"><span class="dashicons dashicons-plus-alt" style="vertical-align: text-bottom; margin-right: 5px;"></span> Add Station</button></div>
                </div>

                <table class="form-table wp-list-table widefat fixed striped" id="stations-table" style="cursor: default;">
                    <thead><tr><th class="column-name">Station Name</th><th class="column-url">Stream URL</th><th class="column-logo">Logo</th><th class="column-country">Country</th><th class="column-language">Language</th><th class="column-pin">Pin (Featured)</th><th class="column-action">Action</th></tr></thead>
                    <tbody id="stations-sortable-list" style="cursor: move;">
                        <?php if (!empty($stations_option) && is_array($stations_option)): foreach ($stations_option as $index => $station): if (!is_array($station)) continue;
                                $logo_url = esc_url($station['logo'] ?? ''); $station_country = $station['country'] ?? ''; $station_language = $station['language'] ?? ''; $station_pinned = isset($station['pinned']) && $station['pinned'] ? true : false;
                            ?><tr data-is-dirty="false" data-db-id="<?php echo esc_attr($index); ?>">
                                <td class="column-name"><input type="text" name="radio_player_stations[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($station['name'] ?? ''); ?>" required class="regular-text station-name-input ycr-row-input"></td>
                                <td class="column-url"><input type="url" name="radio_player_stations[<?php echo esc_attr($index); ?>][url]" value="<?php echo esc_url($station['url'] ?? ''); ?>" required class="regular-text station-url-input ycr-row-input"></td>
                                <td class="column-logo">
                                    <img src="<?php echo !empty($logo_url) ? $logo_url : $default_admin_logo; ?>" class="station-logo-preview" alt="Logo Preview">
                                    <input type="hidden" name="radio_player_stations[<?php echo esc_attr($index); ?>][logo]" value="<?php echo $logo_url; ?>" class="station-logo-url-input ycr-row-input">
                                    <button type="button" class="button upload-logo-button ycr-row-edit-trigger"><?php echo !empty($logo_url) ? 'Change Logo' : 'Upload Logo'; ?></button>
                                    <button type="button" class="button remove-logo-button ycr-row-edit-trigger" style="<?php echo empty($logo_url) ? 'display:none;' : ''; ?>">Remove</button></td>
                                <td class="column-country">
                                    <select class="station-country-select regular-text ycr-row-input"><option value="">-- Select Country --</option><?php foreach ($master_countries as $cn) echo '<option value="'.esc_attr($cn).'" '.selected($station_country, $cn, false).'>'.esc_html($cn).'</option>'; if (!empty($station_country) && !in_array($station_country, $master_countries)) echo '<option value="'.esc_attr($station_country).'" selected="selected">'.esc_html($station_country).' (Custom)</option>'; ?><option value="_add_new_">Add New Country...</option></select>
                                    <input type="text" class="new-country-input regular-text ycr-row-input" style="display:none;" placeholder="Enter new country"><input type="hidden" name="radio_player_stations[<?php echo esc_attr($index); ?>][country]" value="<?php echo esc_attr($station_country); ?>" class="actual-country-value-input ycr-row-input"></td>
                                <td class="column-language">
                                    <select class="station-language-select regular-text ycr-row-input"><option value="">-- Select Language --</option><?php foreach ($master_languages as $ln) echo '<option value="'.esc_attr($ln).'" '.selected($station_language, $ln, false).'>'.esc_html($ln).'</option>'; if (!empty($station_language) && !in_array($station_language, $master_languages)) echo '<option value="'.esc_attr($station_language).'" selected="selected">'.esc_html($station_language).' (Custom)</option>'; ?><option value="_add_new_">Add New Language...</option></select>
                                    <input type="text" class="new-language-input regular-text ycr-row-input" style="display:none;" placeholder="Enter new language"><input type="hidden" name="radio_player_stations[<?php echo esc_attr($index); ?>][language]" value="<?php echo esc_attr($station_language); ?>" class="actual-language-value-input ycr-row-input"></td>
                                <td class="column-pin"><input type="checkbox" name="radio_player_stations[<?php echo esc_attr($index); ?>][pinned]" value="1" class="station-pin-checkbox ycr-row-input" <?php checked($station_pinned); ?> title="Mark as Featured"></td>
                                <td class="column-action"><button type="button" class="button button-primary ycr-save-row-btn hidden-by-default" style="margin-right: 5px;">Save</button><button type="button" class="button remove-station">Remove</button><span class="spinner ycr-row-save-spinner"></span><span class="ycr-row-status-message"></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <div class="ycr-pagination-controls"><span class="page-info" id="ycr-page-info-display"></span><div><button type="button" id="ycr-prev-page-btn" class="button">Previous</button><button type="button" id="ycr-next-page-btn" class="button">Next</button></div></div>

                <h2>Player Settings</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="radio_player_default_station_select">Default Station</label></th>
                        <td><select name="radio_player_default_station" id="radio_player_default_station_select"><?php if(!empty($stations_option) && is_array($stations_option)) { foreach($stations_option as $idx => $st) { if(is_array($st) && !empty($st['name'])) echo '<option value="'.esc_attr($idx).'" '.selected($default_station_idx, $idx, false).'>'.esc_html($st['name']).'</option>';}} else echo '<option value="">No stations configured</option>'; ?></select><p class="description">The station that loads when the player first appears.</p></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="radio_player_recently_added_limit">Recently Added Stations Limit</label></th>
                        <td><input type="number" id="radio_player_recently_added_limit" name="radio_player_recently_added_limit" value="<?php echo esc_attr(get_option('radio_player_recently_added_limit', 24)); ?>" min="1" class="regular-text" style="width: 100px;"><p class="description">How many recently added stations are shown by default on the frontend (admin-pinned/featured stations are shown first within this limit). Use 0 for no limit if no shortcode filters are applied.</p></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="radio_player_frontend_pagination_per_page">Frontend Pagination</label></th>
                        <td><input type="number" id="radio_player_frontend_pagination_per_page" name="radio_player_frontend_pagination_per_page" value="<?php echo esc_attr(get_option('radio_player_frontend_pagination_per_page', 24)); ?>" min="1" class="regular-text" style="width: 100px;"><p class="description">Number of stations per page on the frontend if pagination is active for the main station list.</p></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="radio_player_stations_per_row">Stations per Row (Desktop)</label></th>
                        <td><select name="radio_player_stations_per_row" id="radio_player_stations_per_row"><?php $current_spr = get_option('radio_player_stations_per_row', 6); $opts = [3,4,5,6,8,10]; foreach($opts as $n) echo '<option value="'.esc_attr($n).'" '.selected($current_spr, $n, false).'>'.esc_html($n).'</option>'; ?></select><p class="description">Control how many stations are displayed per row in the frontend grid layout (desktops/large screens).</p></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="radio_player_stations_per_row_mobile">Stations per Row (Mobile)</label></th>
                        <td><select name="radio_player_stations_per_row_mobile" id="radio_player_stations_per_row_mobile"><?php $current_sprm = get_option('radio_player_stations_per_row_mobile', 3); $mopts = [2,3,4]; foreach($mopts as $n) echo '<option value="'.esc_attr($n).'" '.selected($current_sprm, $n, false).'>'.esc_html($n).'</option>'; ?></select><p class="description">Control stations per row on smaller screens (e.g., less than 576px wide).</p></td>
                    </tr>
                    <tr valign="top" style="border-top: 1px dashed #ccc; padding-top:15px;">
                        <th scope="row" colspan="2"><h3 style="margin-bottom:0; margin-top:10px;">Login Button Settings (for Popups)</h3></th>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="radio_player_login_link_type">Login Link Type</label></th>
                        <td>
                            <select name="radio_player_login_link_type" id="radio_player_login_link_type">
                                <option value="direct" <?php selected(get_option('radio_player_login_link_type', 'direct'), 'direct'); ?>>Direct Link</option>
                                <option value="modal" <?php selected(get_option('radio_player_login_link_type', 'direct'), 'modal'); ?>>Popup Modal Trigger</option>
                            </select>
                            <p class="description">How the "Login" button in plugin dialogs should work.
                                <br><strong>Direct Link:</strong> Goes to the URL specified below.
                                <br><strong>Popup Modal Trigger:</strong> Behaves like a button/link designed to open a modal (e.g., your theme's login popup). You'll need to provide the correct CSS class for your modal trigger in the "Login Link CSS Class" field.
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="radio_player_login_link_url">Login Link URL</label></th>
                        <td>
                            <input type="text" id="radio_player_login_link_url" name="radio_player_login_link_url" value="<?php echo esc_attr(get_option('radio_player_login_link_url', '#')); ?>" class="regular-text">
                            <p class="description">The URL for the login page if "Direct Link" is selected. If "Popup Modal Trigger" is selected, this can be '#' or the actual page URL if your modal trigger requires it (e.g. <code><?php echo esc_html(home_url('/wp-login.php')); ?></code> or your custom login page URL).</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="radio_player_login_link_class">Login Link CSS Class(es)</label></th>
                        <td>
                            <input type="text" id="radio_player_login_link_class" name="radio_player_login_link_class" value="<?php echo esc_attr(get_option('radio_player_login_link_class', '')); ?>" class="regular-text">
                            <p class="description">CSS class(es) for the login link/button, separated by spaces. Important if using "Popup Modal Trigger" (e.g., <code>open-login-popup your-theme-modal-class</code>). The classes <code>button</code> and <code>ycr-dialog-login-btn</code> are added automatically by the plugin.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="radio_player_login_button_text">Login Button Text</label></th>
                        <td>
                            <input type="text" id="radio_player_login_button_text" name="radio_player_login_button_text" value="<?php echo esc_attr(get_option('radio_player_login_button_text', 'Login')); ?>" class="regular-text">
                            <p class="description">Text displayed on the login button inside dialogs.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="radio_player_login_icon_class">Login Button Icon Class</label></th>
                        <td>
                            <input type="text" id="radio_player_login_icon_class" name="radio_player_login_icon_class" value="<?php echo esc_attr(get_option('radio_player_login_icon_class', 'fas fa-sign-in-alt')); ?>" class="regular-text">
                            <p class="description">Font Awesome class for the icon (e.g., <code>fas fa-user</code>, <code>os-icon os-icon-head</code>). Leave blank for no icon.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="radio_player_disclaimer_text">Disclaimer Message</label></th>
                        <td><textarea name="radio_player_disclaimer_text" id="radio_player_disclaimer_text" rows="3"><?php echo esc_textarea($disclaimer_text); ?></textarea><p class="description">This message will appear below the player. Leave blank to hide.</p></td>
                    </tr>
                     <tr valign="top">
                        <th scope="row"><label for="radio_player_custom_css">Custom CSS</label></th>
                        <td><textarea name="radio_player_custom_css" id="radio_player_custom_css" rows="8" placeholder="/* e.g., .custom-radio-player-wrapper { border: 1px solid #ccc; } */"><?php echo esc_textarea($custom_css); ?></textarea><p class="description">Add custom CSS rules to style the player and station list on the frontend.</p></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
            // Ensure this code is only for the main settings page, not the taxonomy page
            if (!$('.ycr-taxonomy-manager').length) {
                const tableBody = $('#stations-sortable-list');
                const addStationButton = $('#add-station');
                const defaultAdminLogo = '<?php echo esc_js($default_admin_logo); ?>';
                const defaultStationSelect = $('#radio_player_default_station_select');
                let globalMevcutCountries = <?php echo json_encode($master_countries); ?>;
                let globalMevcutLanguages = <?php echo json_encode($master_languages); ?>;
                const searchInput = $('#ycr-station-search-input');
                const countryFilterSelect = $('#ycr-country-filter-select');
                const languageFilterSelect = $('#ycr-language-filter-select');
                const itemsPerPageSelect = $('#ycr-items-per-page-select');
                const prevPageBtn = $('#ycr-prev-page-btn');
                const nextPageBtn = $('#ycr-next-page-btn');
                const pageInfoDisplay = $('#ycr-page-info-display');
                let currentPage = 1;
                let stationRowsCache = [];
                const ycAdminAjaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
                const ycSaveNonce = '<?php echo esc_js(wp_create_nonce('yc_save_station_nonce')); ?>';
                const ycDeleteNonce = '<?php echo esc_js(wp_create_nonce('yc_delete_station_nonce')); ?>';

                function markRowAsDirty($row) { $row.data('is-dirty', true).attr('data-is-dirty', 'true').find('.ycr-save-row-btn').removeClass('hidden-by-default').show(); }
                function markRowAsClean($row) { $row.data('is-dirty', false).attr('data-is-dirty', 'false').find('.ycr-save-row-btn').hide(); }
                function initializeRowEventListeners($row) { $row.find('.ycr-row-input, .station-country-select, .station-language-select, .station-pin-checkbox').on('input change', function() { markRowAsDirty($row); }); }
                function updateDbIdForRow($row, newDbId) { $row.data('db-id', newDbId).attr('data-db-id', newDbId); }
                function refreshAllClientDbIds() { $('#stations-sortable-list tr').each(function(newVisualIndex) { updateDbIdForRow($(this), newVisualIndex); }); }
                function updateStationRowsCache() { stationRowsCache = []; tableBody.find('tr').each(function() { stationRowsCache.push($(this)); }); }

                function updateMainFilterDropdown(taxonomyKey, globalList) {
                    let $filterSelect;
                    if (taxonomyKey === 'country') $filterSelect = countryFilterSelect; else if (taxonomyKey === 'language') $filterSelect = languageFilterSelect;
                    if ($filterSelect && $filterSelect.length) {
                        const currentFilterVal = $filterSelect.val(); let pluralLabel = taxonomyKey.charAt(0).toUpperCase() + taxonomyKey.slice(1);
                        if (taxonomyKey === 'country') pluralLabel = 'Countries'; else if (taxonomyKey.endsWith('y') && taxonomyKey !== 'country') pluralLabel = pluralLabel.slice(0, -1) + 'ies'; else if (taxonomyKey !== 'country') pluralLabel += 's';
                        $filterSelect.empty().append('<option value="">All ' + pluralLabel + '</option>');
                        globalList.sort().forEach(term => { if (term) $filterSelect.append($('<option></option>').val(term).text(term)); });
                        if (globalList.includes(currentFilterVal)) $filterSelect.val(currentFilterVal); else $filterSelect.val("");
                    }
                }
                function rebuildGlobalTaxonomiesAndRefreshAllDropdowns(newMasterCountries, newMasterLanguages) {
                    globalMevcutCountries = Array.isArray(newMasterCountries) ? newMasterCountries.sort() : globalMevcutCountries.sort();
                    globalMevcutLanguages = Array.isArray(newMasterLanguages) ? newMasterLanguages.sort() : globalMevcutLanguages.sort();
                    updateMainFilterDropdown('country', globalMevcutCountries); updateMainFilterDropdown('language', globalMevcutLanguages);
                    stationRowsCache.forEach(function($row) { initializeTaxonomySelectorForRow($row, 'country', globalMevcutCountries); initializeTaxonomySelectorForRow($row, 'language', globalMevcutLanguages); });
                }
                function applyFiltersAndPagination() {
                    if (!tableBody.length) return;
                    const searchTerm = searchInput.val().toLowerCase().trim(), selectedCountryFilter = countryFilterSelect.val(), selectedLanguageFilter = languageFilterSelect.val();
                    let itemsPerPage = parseInt(itemsPerPageSelect.val(), 10); if (itemsPerPage === -1 || isNaN(itemsPerPage) || itemsPerPage <= 0) itemsPerPage = stationRowsCache.length || 1;
                    let filteredRows = [];
                    stationRowsCache.forEach(function($row) {
                        const sName = $row.find('.station-name-input').val().toLowerCase(), sUrl = $row.find('.station-url-input').val().toLowerCase(),
                              sCountry = $row.find('td.column-country .actual-country-value-input').val(), sLang = $row.find('td.column-language .actual-language-value-input').val();
                        let mSearch = true; if (searchTerm) mSearch = sName.includes(searchTerm) || sUrl.includes(searchTerm) || (sCountry && sCountry.toLowerCase().includes(searchTerm)) || (sLang && sLang.toLowerCase().includes(searchTerm));
                        let mCountry = true; if (selectedCountryFilter) mCountry = sCountry === selectedCountryFilter;
                        let mLang = true; if (selectedLanguageFilter) mLang = sLang === selectedLanguageFilter;
                        if (mSearch && mCountry && mLang) filteredRows.push($row);
                    });
                    tableBody.children('tr').detach();
                    const totalFilteredItems = filteredRows.length, totalPages = Math.max(1, Math.ceil(totalFilteredItems / itemsPerPage));
                    currentPage = Math.max(1, Math.min(currentPage, totalPages));
                    const startIndex = (currentPage - 1) * itemsPerPage, endIndex = Math.min(startIndex + itemsPerPage, totalFilteredItems);
                    for (let i = startIndex; i < endIndex; i++) if (filteredRows[i]) tableBody.append(filteredRows[i]);
                    const totalStationsInList = stationRowsCache.length; let pageInfoText = `Page ${currentPage} of ${totalPages}. `;
                    if (totalFilteredItems > 0) pageInfoText += `Showing ${startIndex + 1}-${Math.min(endIndex, totalFilteredItems)} of ${totalFilteredItems} stations.`;
                    else pageInfoText += totalStationsInList > 0 ? `No stations match criteria.` : `No stations configured.`;
                    if (totalFilteredItems !== totalStationsInList && totalStationsInList > 0 && totalFilteredItems > 0) pageInfoText += ` (${totalStationsInList} total stations)`;
                    else if (totalStationsInList === 0) pageInfoText = `No stations configured.`;
                    pageInfoDisplay.text(pageInfoText); prevPageBtn.prop('disabled', currentPage === 1); nextPageBtn.prop('disabled', currentPage === totalPages || totalFilteredItems === 0);
                }
                searchInput.on('keyup input', function() { currentPage = 1; applyFiltersAndPagination(); });
                countryFilterSelect.on('change', function() { currentPage = 1; applyFiltersAndPagination(); });
                languageFilterSelect.on('change', function() { currentPage = 1; applyFiltersAndPagination(); });
                itemsPerPageSelect.on('change', function() { currentPage = 1; applyFiltersAndPagination(); });
                prevPageBtn.on('click', function() { if (currentPage > 1) { currentPage--; applyFiltersAndPagination(); } });
                nextPageBtn.on('click', function() {
                    let ipp = parseInt(itemsPerPageSelect.val(),10); if(ipp === -1 || isNaN(ipp) || ipp <=0) ipp = stationRowsCache.length || 1;
                    const st = searchInput.val().toLowerCase().trim(), scf = countryFilterSelect.val(), slf = languageFilterSelect.val();
                    const tfi = stationRowsCache.filter(function($r){ const sn=$r.find('.station-name-input').val().toLowerCase(),su=$r.find('.station-url-input').val().toLowerCase(),scr=$r.find('td.column-country .actual-country-value-input').val(),slr=$r.find('td.column-language .actual-language-value-input').val(); let ms=true;if(st)ms=sn.includes(st)||su.includes(st)||(scr&&scr.toLowerCase().includes(st))||(slr&&slr.toLowerCase().includes(st));let mc=true;if(scf)mc=scr===scf;let ml=true;if(slf)ml=slr===slf;return ms&&mc&&ml; }).length;
                    const tp = Math.max(1,Math.ceil(tfi/ipp)); if(currentPage < tp){currentPage++; applyFiltersAndPagination();}
                });
                function populateSingleTaxonomyDropdown($s, list, selVal='', taxKey){
                    const curSel=$s.val(); $s.empty().append('<option value="">-- Select '+taxKey.charAt(0).toUpperCase()+taxKey.slice(1)+' --</option>'); let found=false;
                    list.forEach(t => {if(t){$s.append($('<option></option>').val(t).text(t)); if(t===selVal)found=true;}});
                    if(selVal && selVal!=='' && !found) $s.append($('<option></option>').val(selVal).text(selVal+' (Custom)').prop('selected',true));
                    $s.append('<option value="_add_new_">Add New '+taxKey.charAt(0).toUpperCase()+taxKey.slice(1)+'...</option>');
                    if(selVal&&found)$s.val(selVal); else if(selVal&&!found&&selVal!==''){} else if(curSel==='_add_new_')$s.val('_add_new_'); else $s.val('');
                }
                function initializeTaxonomySelectorForRow($r, taxKey, gList){
                    const $ts=$r.find('.station-'+taxKey+'-select'), $ni=$r.find('.new-'+taxKey+'-input'), $av=$r.find('td.column-'+taxKey+' .actual-'+taxKey+'-value-input'); let iVal=$av.val();
                    populateSingleTaxonomyDropdown($ts,gList,iVal,taxKey);
                    if((iVal&&!gList.includes(iVal)&&iVal!=='')||$ts.val()==='_add_new_') $ni.val(iVal&&!gList.includes(iVal)?iVal:'').show(); else $ni.hide();
                    $ts.off('change.ycr-'+taxKey).on('change.ycr-'+taxKey,function(){const v=$(this).val(); if(v==='_add_new_'){$ni.val('').show().focus();$av.val('');}else{$ni.hide().val('');$av.val(v);} markRowAsDirty($(this).closest('tr'));});
                    $ni.off('input.ycr-'+taxKey+' blur.ycr-'+taxKey).on('input.ycr-'+taxKey+' blur.ycr-'+taxKey,function(){if($ts.val()==='_add_new_'){$av.val($(this).val().trim()); markRowAsDirty($(this).closest('tr'));}});
                }
                tableBody.find('tr').each(function(){ initializeTaxonomySelectorForRow($(this),'country',globalMevcutCountries); initializeTaxonomySelectorForRow($(this),'language',globalMevcutLanguages); initializeRowEventListeners($(this)); if($(this).attr('data-is-dirty')==='false')markRowAsClean($(this));});
                function reindexStationsAndDefaultDropdown(){
                    var prevName=''; var curVal=defaultStationSelect.val(); if(curVal!==''&&curVal!==null&&defaultStationSelect.find('option:selected').length>0)prevName=defaultStationSelect.find('option:selected').text();
                    defaultStationSelect.empty();
                    $('#stations-sortable-list tr').each(function(nIdx){const $r=$(this);$r.find('input[name^="radio_player_stations"],select[name^="radio_player_stations"],input[type="hidden"][name^="radio_player_stations"],input[type="checkbox"][name^="radio_player_stations"]').each(function(){var na=$(this).attr('name');if(na)$(this).attr('name',na.replace(/radio_player_stations\[\d*\]/,'radio_player_stations['+nIdx+']'));});var sName=$r.find('.station-name-input').val();if(sName)defaultStationSelect.append($('<option></option>').val(nIdx).text(sName));});
                    if(defaultStationSelect.children().length===0)defaultStationSelect.append('<option value="">No stations configured</option>');
                    if(prevName){let f=false;defaultStationSelect.find('option').each(function(){if($(this).text()===prevName){$(this).prop('selected',true);f=true;return false;}});if(!f&&defaultStationSelect.find('option').length>0)defaultStationSelect.find('option:first').prop('selected',true);}else if(defaultStationSelect.find('option').length>0)defaultStationSelect.find('option:first').prop('selected',true);
                }
                tableBody.sortable({placeholder:"ui-state-highlight",helper:function(e,ui){ui.children().each(function(){$(this).width($(this).width());});return ui;},stop:function(ev,ui){updateStationRowsCache();stationRowsCache.forEach(function($r){markRowAsDirty($r);});reindexStationsAndDefaultDropdown();}}).disableSelection();
                addStationButton.on('click',function(){const nRIdx=$('#stations-sortable-list tr').length; const nRHtml=`<tr data-is-dirty="true" data-db-id="-1"><td class="column-name"><input type="text" name="radio_player_stations[${nRIdx}][name]" required class="regular-text station-name-input ycr-row-input"></td><td class="column-url"><input type="url" name="radio_player_stations[${nRIdx}][url]" required class="regular-text station-url-input ycr-row-input"></td><td class="column-logo"><img src="${defaultAdminLogo}" class="station-logo-preview" alt="Logo Preview"><input type="hidden" name="radio_player_stations[${nRIdx}][logo]" class="station-logo-url-input ycr-row-input"><button type="button" class="button upload-logo-button ycr-row-edit-trigger">Upload Logo</button><button type="button" class="button remove-logo-button ycr-row-edit-trigger" style="display:none;">Remove</button></td><td class="column-country"><select class="station-country-select regular-text ycr-row-input"></select><input type="text" class="new-country-input regular-text ycr-row-input" style="display:none;" placeholder="Enter new country"><input type="hidden" name="radio_player_stations[${nRIdx}][country]" value="" class="actual-country-value-input ycr-row-input"></td><td class="column-language"><select class="station-language-select regular-text ycr-row-input"></select><input type="text" class="new-language-input regular-text ycr-row-input" style="display:none;" placeholder="Enter new language"><input type="hidden" name="radio_player_stations[${nRIdx}][language]" value="" class="actual-language-value-input ycr-row-input"></td><td class="column-pin"><input type="checkbox" name="radio_player_stations[${nRIdx}][pinned]" value="1" class="station-pin-checkbox ycr-row-input" title="Mark as Featured"></td><td class="column-action"><button type="button" class="button button-primary ycr-save-row-btn" style="margin-right:5px;">Save</button><button type="button" class="button remove-station">Remove</button><span class="spinner ycr-row-save-spinner"></span><span class="ycr-row-status-message"></span></td></tr>`; const $nR=$(nRHtml);tableBody.prepend($nR);updateStationRowsCache();initializeTaxonomySelectorForRow($nR,'country',globalMevcutCountries);initializeTaxonomySelectorForRow($nR,'language',globalMevcutLanguages);initializeRowEventListeners($nR);reindexStationsAndDefaultDropdown();applyFiltersAndPagination();$nR.find('.station-name-input').focus();});
                tableBody.on('click','.remove-station',function(){const $b=$(this),$r=$b.closest('tr'),dId=$r.attr('data-db-id'),$st=$r.find('.ycr-row-status-message'),$sp=$r.find('.ycr-row-save-spinner');if(!confirm('Are you sure?'))return;$b.prop('disabled',true).siblings('.ycr-save-row-btn').prop('disabled',true);$sp.addClass('is-active');$st.text('Deleting...').css('color','');$.ajax({url:ycAdminAjaxUrl,type:'POST',data:{action:'yc_delete_single_station',nonce:ycDeleteNonce,db_id_to_delete:dId},success:function(res){if(res.success){stationRowsCache=stationRowsCache.filter(cr=>!cr.is($r));$r.remove();refreshAllClientDbIds();reindexStationsAndDefaultDropdown();applyFiltersAndPagination();}else{$st.text(res.data.message||'Error.').css('color','red');$b.prop('disabled',false).siblings('.ycr-save-row-btn').prop('disabled',false);}},error:function(){$st.text('AJAX err.').css('color','red');$b.prop('disabled',false).siblings('.ycr-save-row-btn').prop('disabled',false);},complete:function(){$sp.removeClass('is-active');if($r.closest('body').length)setTimeout(()=>$st.text(''),3000);}});});
                tableBody.on('click','.upload-logo-button.ycr-row-edit-trigger, .remove-logo-button.ycr-row-edit-trigger',function(e){markRowAsDirty($(this).closest('tr'));});
                tableBody.on('click','.upload-logo-button',function(e){e.preventDefault();var b=$(this),r=b.closest('tr'),iF=r.find('.station-logo-url-input'),iP=r.find('.station-logo-preview'),rB=r.find('.remove-logo-button');var f=wp.media({title:'Select Logo',button:{text:'Use logo'},multiple:false});f.on('select',function(){var att=f.state().get('selection').first().toJSON();iF.val(att.url).trigger('change');iP.attr('src',att.url).css('opacity',1);b.text('Change');rB.show();markRowAsDirty(r);}).open();});
                tableBody.on('click','.remove-logo-button',function(e){e.preventDefault();var b=$(this),r=b.closest('tr'),iF=r.find('.station-logo-url-input'),iP=r.find('.station-logo-preview'),uB=r.find('.upload-logo-button');iF.val('').trigger('change');iP.attr('src',defaultAdminLogo).css('opacity',0.5);uB.text('Upload');b.hide();markRowAsDirty(r);});
                tableBody.on('click','.ycr-save-row-btn',function(){const $b=$(this),$r=$b.closest('tr'),$sp=$r.find('.ycr-row-save-spinner'),$st=$r.find('.ycr-row-status-message');const sN=$r.find('.station-name-input').val(),sU=$r.find('.station-url-input').val(),sL=$r.find('.station-logo-url-input').val(),sC=$r.find('td.column-country .actual-country-value-input').val(),sLa=$r.find('td.column-language .actual-language-value-input').val(),sP=$r.find('.station-pin-checkbox').is(':checked')?'1':'0';const dbId=$r.attr('data-db-id');let vIdx=0;$('#stations-sortable-list tr').each(function(i,el){if(el===$r[0]){vIdx=i;return false;}});if(!sN.trim()||!sU.trim()){$st.text('Name & URL required.').css('color','red');setTimeout(()=>$st.text(''),3000);return;}$b.prop('disabled',true).siblings('.remove-station').prop('disabled',true);$sp.addClass('is-active');$st.text('Saving...').css('color','');$.ajax({url:ycAdminAjaxUrl,type:'POST',data:{action:'yc_save_single_station',nonce:ycSaveNonce,db_id:dbId,visual_index:vIdx,station_data:{name:sN,url:sU,logo:sL,country:sC,language:sLa,pinned:sP}},success:function(res){if(res.success){$st.text(res.data.message||'Saved!').css('color','green');markRowAsClean($r);if(dbId==="-1" && typeof res.data.new_db_id !== 'undefined')updateDbIdForRow($r, res.data.new_db_id);rebuildGlobalTaxonomiesAndRefreshAllDropdowns(res.data.master_countries,res.data.master_languages);reindexStationsAndDefaultDropdown();updateStationRowsCache();refreshAllClientDbIds();applyFiltersAndPagination();}else{$st.text(res.data?(res.data.message||res.data):'Error.').css('color','red');markRowAsDirty($r);}},error:function(){$st.text('AJAX err.').css('color','red');markRowAsDirty($r);},complete:function(){$b.prop('disabled',false).siblings('.remove-station').prop('disabled',false);$sp.removeClass('is-active');setTimeout(()=>$st.text(''),3000);}});});
                updateStationRowsCache(); stationRowsCache.forEach(function($r){if($r.attr('data-is-dirty')==='false')markRowAsClean($r);else markRowAsDirty($r);});
                reindexStationsAndDefaultDropdown(); rebuildGlobalTaxonomiesAndRefreshAllDropdowns(globalMevcutCountries,globalMevcutLanguages); applyFiltersAndPagination();
            }
        });
        </script>
        <?php
    }
}

function ycr_render_frontend_station_item_html($station_data, $is_user_personally_pinned = false, $is_admin_pinned = false, $section_context = 'mainGrid') {
    $default_logo = YCR_PLUGIN_URL . 'images/default-logo.png';
    $logo_src = !empty($station_data['logo']) ? esc_url($station_data['logo']) : esc_url($default_logo);
    $name = esc_html($station_data['name'] ?? 'Unknown Station');
    $url = esc_url($station_data['url'] ?? '#');
    $country_text = !empty($station_data['country']) ? esc_html($station_data['country']) : '';

    $item_classes = 'station-grid-item';
    if ($section_context === 'myFavorites') $item_classes .= ' ycr-in-my-favorites';
    if ($section_context === 'featured') $item_classes .= ' ycr-in-featured';

    $user_pin_button_html = ''; $pin_icon_class = 'far fa-heart';
    if (is_user_logged_in()) {
        $pin_button_classes = 'ycr-pin-station-btn ';
        if ($is_user_personally_pinned) { $pin_icon_class = 'fas fa-heart'; $pin_button_classes .= 'is-user-personally-pinned'; $pin_title = "Remove from My Favorites"; }
        else { $pin_title = "Add to My Favorites"; }
        $user_pin_button_html = '<button class="' . esc_attr($pin_button_classes) . '" title="' . esc_attr($pin_title) . '" data-station-url="' . esc_url($url) . '"><i class="' . esc_attr($pin_icon_class) . '"></i></button>';
    } else {
        $pin_button_classes = 'ycr-pin-station-btn ycr-guest-pin-attempt'; $pin_title = "Log in to add to My Favorites";
        $user_pin_button_html = '<button class="' . esc_attr($pin_button_classes) . '" title="' . esc_attr($pin_title) . '" data-station-url="' . esc_url($url) . '"><i class="' . esc_attr($pin_icon_class) . '"></i></button>';
    }

    $admin_pin_indicator_html_content = ''; // Store only the span content
    if ($is_admin_pinned && ($section_context !== 'myFavorites' || $section_context === 'featured')) {
         // Removed leading space from the span itself
         $admin_pin_indicator_html_content = '<span class="ycr-admin-pinned-badge" title="Featured Station"><i class="fas fa-star"></i></span>';
    }

    $html = '<div class="' . esc_attr($item_classes) . '" data-station-url="' . esc_url($url) . '" data-station-logo="' . esc_url($logo_src) . '" data-station-name="' . esc_attr($name) . '" data-station-country="' . esc_attr($station_data['country'] ?? '') . '" data-station-language="' . esc_attr($station_data['language'] ?? '') . '" data-is-admin-pinned="' . ($is_admin_pinned ? 'true' : 'false') . '" tabindex="0">';
    $html .= '<div class="station-logo-wrapper"><img src="' . esc_url($logo_src) . '" alt="' . esc_attr($name) . ' Logo"></div>';

    // Start of details text
    $html .= '<div class="station-grid-details-text">';
    // Line with pin button and name
    $html .=   '<div class="station-name-line">' . $user_pin_button_html . '<span class="station-grid-name">' . $name . '</span></div>'; // Badge removed from here
    // Admin badge (if exists) on its own line, before country
    if (!empty($admin_pin_indicator_html_content)) {
        $html .= $admin_pin_indicator_html_content;
    }
    // Country
    $html .=   '<p class="station-grid-country">' . $country_text . '</p>';
    $html .= '</div>'; // End of station-grid-details-text
    $html .= '</div>'; // End of station-grid-item

    return $html;
}

function custom_radio_player_shortcode($atts = []) {
    $atts = shortcode_atts( array(
        'language' => '',
        'country'  => '',
    ), $atts, 'custom_radio_player' );

    $filter_language = !empty($atts['language']) ? sanitize_text_field($atts['language']) : '';
    $filter_country = !empty($atts['country']) ? sanitize_text_field($atts['country']) : '';

    $all_stations_data = get_option('radio_player_stations', []);
    $disclaimer_text = get_option('radio_player_disclaimer_text', ''); $custom_css = get_option('radio_player_custom_css', '');
    $default_station_index = (int) get_option('radio_player_default_station', 0);
    $recently_added_limit_setting = (int) get_option('radio_player_recently_added_limit', 24);
    $frontend_pagination_per_page = (int) get_option('radio_player_frontend_pagination_per_page', 24);
    $stations_per_row_setting = (int) get_option('radio_player_stations_per_row', 6);
    $stations_per_row_mobile_setting_php = (int) get_option('radio_player_stations_per_row_mobile', 3);
    if (!in_array($stations_per_row_mobile_setting_php, [2, 3, 4])) $stations_per_row_mobile_setting_php = 3;
    $grid_base_classes_php = "radio-station-grid stations-per-row-" . esc_attr($stations_per_row_setting) . " stations-per-row-mobile-" . esc_attr($stations_per_row_mobile_setting_php);

    $stations_raw = [];
    if (is_array($all_stations_data)) {
        $stations_raw = array_filter($all_stations_data, function($s) use ($filter_language, $filter_country) {
            if (!is_array($s) || empty($s['url']) || empty($s['name'])) return false;
            $lang_match = true;
            if ($filter_language && (empty($s['language']) || strtolower($s['language']) !== strtolower($filter_language))) {
                $lang_match = false;
            }
            $country_match = true;
            if ($filter_country && (empty($s['country']) || strtolower($s['country']) !== strtolower($filter_country))) {
                $country_match = false;
            }
            return $lang_match && $country_match;
        });
        $stations_raw = array_values($stations_raw); // Re-index
    }

    if (empty($stations_raw)) {
        $no_stations_message = '<p>No radio stations available';
        if ($filter_language || $filter_country) {
            $no_stations_message .= ' matching the specified criteria';
            if ($filter_language) $no_stations_message .= ' (Language: ' . esc_html($filter_language) . ')';
            if ($filter_country) $no_stations_message .= ' (Country: ' . esc_html($filter_country) . ')';
        }
        $no_stations_message .= '.</p>';
        return $no_stations_message;
    }

    $admin_pinned = []; $regular_temp = [];
    foreach ($stations_raw as $s_idx => $s_data) {
        $s_data['_original_idx_after_filter'] = $s_idx; // Keep track of index within $stations_raw
        if (!empty($s_data['pinned'])) $admin_pinned[] = $s_data; else $regular_temp[] = $s_data;
    }
    // Sort regular stations by added_timestamp DESC (newest first) if available
    // Otherwise, maintain their original order (which is usually the admin order)
    usort($regular_temp, function($a, $b) {
        $ts_a = isset($a['added_timestamp']) ? (int)$a['added_timestamp'] : 0;
        $ts_b = isset($b['added_timestamp']) ? (int)$b['added_timestamp'] : 0;
        if ($ts_a === $ts_b) {
            // If timestamps are same (or both 0), sort by original index to keep admin order
             return ($a['_original_idx_after_filter'] < $b['_original_idx_after_filter']) ? -1 : 1;
        }
        return ($ts_a > $ts_b) ? -1 : 1; // Sort by timestamp DESC
    });
    $stations = array_merge($admin_pinned, $regular_temp);


    $initial_station = null;
    $filtered_default_station_found = false;

    // Try to find the *original* default station (from all_stations_data) within the $stations_raw list
    if (isset($all_stations_data[$default_station_index])) {
        $original_default_station_url = $all_stations_data[$default_station_index]['url'] ?? null;
        if ($original_default_station_url) {
            foreach ($stations_raw as $s_item) { // $stations_raw is already filtered by shortcode
                if ($s_item['url'] === $original_default_station_url) {
                    $initial_station = $s_item;
                    $filtered_default_station_found = true;
                    break;
                }
            }
        }
    }

    // If original default not found in filtered list, or not set, use first admin-pinned from filtered list
    if (!$filtered_default_station_found && !empty($admin_pinned)) {
        $initial_station = $admin_pinned[0];
    }
    // Else, use the first station from the (already sorted) $stations list
    elseif (!$initial_station && !empty($stations)) {
        $initial_station = $stations[0];
    }


    if (isset($_COOKIE['selected_station_url'])) {
        $cookie_url = esc_url_raw(wp_unslash($_COOKIE['selected_station_url']));
        foreach($stations as $s) { // $stations is already filtered and sorted
            if (is_array($s) && isset($s['url']) && $s['url'] === $cookie_url) {
                // Ensure the cookie station is allowed for the user
                if(is_user_logged_in() || !empty($s['pinned'])){
                    $initial_station = $s;
                }
                break;
            }
        }
    }
    
    // Final fallback if initial_station is still null or invalid (e.g. cookie station was restricted)
    if ((!$initial_station || !is_array($initial_station) || empty($initial_station['url'])) && !empty($stations)) {
        // Try first admin-pinned from $stations, then first overall from $stations
        $first_allowed_fallback = null;
        foreach($stations as $s_fallback) {
            if (is_user_logged_in() || !empty($s_fallback['pinned'])) {
                $first_allowed_fallback = $s_fallback;
                break;
            }
        }
        if ($first_allowed_fallback) {
            $initial_station = $first_allowed_fallback;
        } elseif (!empty($stations[0])) { // If no "allowed" station found (e.g. all non-pinned and user logged out), take first one anyway. JS will block it.
            $initial_station = $stations[0];
        }
    }


    if (!$initial_station || !is_array($initial_station) || empty($initial_station['url'])) {
         $error_msg = '<p>Error: Could not determine initial station';
         if ($filter_language || $filter_country) $error_msg .= ' for the current filter';
         $error_msg .= ' or no stations are accessible to you currently.</p>';
         return $error_msg;
    }


    $sel_url = esc_url($initial_station['url']); $sel_logo_fb = YCR_PLUGIN_URL . 'images/default-logo.png';
    $sel_logo = esc_url(!empty($initial_station['logo']) ? $initial_station['logo'] : $sel_logo_fb);
    $sel_name = esc_attr($initial_station['name']); $sel_country = esc_attr($initial_station['country'] ?? '');
    $sel_is_admin_pinned = !empty($initial_station['pinned']);


    $output = ''; if (!empty(trim($custom_css))) $output .= '<style type="text/css">' . esc_html(trim($custom_css)) . '</style>';
    $output .= '<div class="custom-radio-player-wrapper">';
    $output .= '<div class="ycr-player-controls-header">';
    $output .= '<div class="popup-player-button-container"><button id="yc-open-popup-player-btn" class="button">Popup Player <i class="fas fa-external-link-alt"></i></button></div>';
    if (is_user_logged_in()) $output .= '<div class="ycr-my-favorites-toggle-container"><button id="ycr-toggle-my-favorites-btn" class="button">My Favorite Stations <i class="fas fa-heart"></i></button></div>';
    $output .= '</div>';
    $output .= '<div class="custom-radio-player" id="player-container" '
             . 'data-current-station-url="'. $sel_url .'" '
             . 'data-current-station-name="'. $sel_name .'" '
             . 'data-current-station-logo="'. $sel_logo .'" '
             . 'data-current-station-country="'. $sel_country .'" '
             . 'data-current-station-admin-pinned="' . ($sel_is_admin_pinned ? 'true' : 'false') . '">' // Added admin pinned status
             . '<div class="radio-logo"><img id="station-logo" src="' . $sel_logo . '" alt="' . $sel_name . ' Logo"></div>'
             . '<audio id="radio-player" controls><source id="radio-source" src="' . $sel_url . '" type="audio/mpeg">No audio support.</audio>';
    if (!empty(trim($disclaimer_text))) $output .= '<div class="disclaimer-info"><i class="fas fa-info-circle"></i><div class="disclaimer-text">' . wp_kses_post($disclaimer_text) . '</div></div>';
    $output .= '<div class="sound-bar paused"><div class="bar"></div><div class="bar"></div><div class="bar"></div><div class="bar"></div><div class="bar"></div></div></div>';
    $output .= '<div id="ycr-user-action-notice-area" style="text-align:center; margin:15px 0;"></div>';

    $user_pinned_html = ''; $user_pinned_urls_php = [];
    if (is_user_logged_in()) {
        $uid = get_current_user_id(); $user = wp_get_current_user(); $user_name_php = $user->display_name;
        $user_pinned_urls_php = get_user_meta($uid, '_ycr_user_pinned_stations', true);
        if (!is_array($user_pinned_urls_php)) $user_pinned_urls_php = [];
        $user_pinned_urls_php = array_values(array_unique(array_filter($user_pinned_urls_php, 'esc_url_raw')));
        $my_fav_obj = [];
        if (!empty($user_pinned_urls_php)) { foreach ($stations as $s_item) { if (in_array($s_item['url'], $user_pinned_urls_php)) $my_fav_obj[] = $s_item; } }
        $fav_title_php = !empty($user_name_php) ? esc_html($user_name_php) . "'s Favorite Stations" : "My Favorite Stations";
        $user_pinned_html .= '<div id="ycr-my-favorites-section" class="ycr-dynamic-section" style="display:none;"><h2 id="ycr-my-favorites-title">' . $fav_title_php . '</h2>';
        if (!empty($my_fav_obj)) {
            $user_pinned_html .= '<div class="' . esc_attr($grid_base_classes_php) . ' ycr-user-pinned-grid">';
            foreach ($my_fav_obj as $fav_s) $user_pinned_html .= ycr_render_frontend_station_item_html($fav_s, true, !empty($fav_s['pinned']), 'myFavorites');
            $user_pinned_html .= '</div>';
        } else $user_pinned_html .= '<p class="ycr-no-favorites-message" style="text-align:center;">You haven\'t added any favorite stations yet. Click the <i class="far fa-heart"></i> icon on a station to add it here!</p>';
        $user_pinned_html .= '</div>';
    }
    $output .= $user_pinned_html;
    $admin_feat_html = ''; $admin_pinned_disp = [];
    foreach ($stations as $s_item) { if (!empty($s_item['pinned'])) $admin_pinned_disp[] = $s_item; }
	if (!empty($admin_pinned_disp)) {
        $admin_feat_html .= '<div id="ycr-featured-stations-section" class="ycr-dynamic-section"><h2>Featured Stations</h2>';
        $admin_feat_html .= '<div class="' . esc_attr($grid_base_classes_php) . ' ycr-admin-pinned-grid">';
        foreach ($admin_pinned_disp as $admin_s) {
            $is_also_user_pin = is_user_logged_in() && in_array($admin_s['url'], $user_pinned_urls_php);
            $admin_feat_html .= ycr_render_frontend_station_item_html($admin_s, $is_also_user_pin, true, 'featured');
        }
        $admin_feat_html .= '</div></div>';
    }
    $output .= $admin_feat_html;

    $output .= '<div id="ycr-browse-stations-section" class="ycr-dynamic-section">';
    $output .= '<div class="radio-search-container" style="margin: 20px 0 15px; position: relative;"><i class="fas fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #777;"></i><input type="text" id="yc-radio-station-search" placeholder="Search stations by name, country or language..." style="width: 100%; padding: 10px 10px 10px 35px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px;"></div>';

    // Conditionally show language filters
    $show_language_filters = empty($filter_language); // Hide if language is pre-filtered by shortcode
    if ($show_language_filters) {
        $unique_langs = [];
        // Use $stations which is already filtered by shortcode atts and sorted
        foreach($stations as $s_item) {
            if(!empty($s_item['language']) && !in_array($s_item['language'], $unique_langs)) $unique_langs[] = $s_item['language'];
        }
        sort($unique_langs);
        if(!empty($unique_langs)) {
            $output .= '<div class="ycr-language-filters"><button class="ycr-lang-filter-btn active" data-lang="">All Languages</button>';
            foreach($unique_langs as $lang) $output .= '<button class="ycr-lang-filter-btn" data-lang="'.esc_attr($lang).'">'.esc_html($lang).'</button>';
            $output .= '</div>';
        }
    }

    $all_stations_js = [];
    // $stations is already filtered by shortcode atts and sorted (admin pinned first, then by timestamp or original order)
    foreach ($stations as $s_item) {
        $all_stations_js[] = [
            'name' => $s_item['name'] ?? '',
            'url' => $s_item['url'] ?? '',
            'logo' => !empty($s_item['logo']) ? $s_item['logo'] : $sel_logo_fb,
            'country' => $s_item['country'] ?? '',
            'language' => $s_item['language'] ?? '',
            'isAdminPinned' => !empty($s_item['pinned']),
        ];
    }

    $base_browse_heading_php = 'Browse All Stations';
    if ($filter_language && $filter_country) {
        $base_browse_heading_php = sprintf('Stations in %s (%s)', esc_html($filter_language), esc_html($filter_country));
    } elseif ($filter_language) {
        $base_browse_heading_php = sprintf('Stations in %s', esc_html($filter_language));
    } elseif ($filter_country) {
        $base_browse_heading_php = sprintf('Stations from %s', esc_html($filter_country));
    }

    $output .= '<div id="ycr-main-grid-heading-wrapper"><h2>' . $base_browse_heading_php . '</h2></div>';
    $output .= '<div id="ycr-station-grid-dynamic-container"></div>';
    $output .= '<div id="ycr-pagination-controls-frontend" style="margin-top: 20px; text-align: center; display:none;"></div>';
    $output .= '<p id="ycr-no-stations-found" style="display:none; text-align:center; margin-top:20px; padding:10px; background-color:#f9f9f9; border:1px solid #eee; border-radius:4px;">No stations found.</p>';
    $output .= '</div></div>';

    $output .= "<style>
        /* Styles remain the same */
        .ycr-player-controls-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; position: relative; z-index: 2;}
        .ycr-my-favorites-toggle-container, .popup-player-button-container { position: relative; z-index: 2; }
        .ycr-my-favorites-toggle-container button, .popup-player-button-container button { padding: 8px 12px; font-size: 0.9em; }
        .ycr-my-favorites-toggle-container button i, .popup-player-button-container button i { margin-left: 5px; }
        .ycr-dynamic-section { position: relative; z-index: 1; }
        .ycr-dynamic-section h2 { margin-top: 25px; margin-bottom: 15px; font-size: 1.4em; border-bottom: 1px solid #e0e0e0; padding-bottom: 8px;}
        .radio-station-grid { display: grid; gap: 10px; width: 100%; box-sizing: border-box; align-items: stretch; }
        .radio-station-grid.stations-per-row-3 { grid-template-columns: repeat(3, 1fr); }
        .radio-station-grid.stations-per-row-4 { grid-template-columns: repeat(4, 1fr); }
        .radio-station-grid.stations-per-row-5 { grid-template-columns: repeat(5, 1fr); }
        .radio-station-grid.stations-per-row-6 { grid-template-columns: repeat(6, 1fr); }
        .radio-station-grid.stations-per-row-8 { grid-template-columns: repeat(8, 1fr); }
        .radio-station-grid.stations-per-row-10 { grid-template-columns: repeat(10, 1fr); }
        .station-grid-item { border: 1px solid #eee; padding: 10px; text-align: center; background-color: #fff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; align-items: center; justify-content: space-between; box-sizing: border-box; word-break: break-word; transition: all 0.3s ease-in-out; height: 100%; }
        .station-logo-wrapper { position: relative; width: 100%; padding-bottom: 100%; height: 0; overflow: hidden; margin-bottom: 8px; border-radius: 4px; flex-shrink:0; }
        .station-grid-item img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; border-radius: 4px; display: block; }
        .station-grid-item .ycr-pin-station-btn { position: absolute; top: 15px; right: 10px; background-color: rgba(255, 255, 255, 0.8); border-radius: 50%; width: 28px; height: 28px; font-size: 1em; padding: 0; display: flex; align-items: center; justify-content: center; line-height: 1; border: none; cursor:pointer; color: #a0a0a0; transition: color 0.2s ease, transform 0.2s ease; z-index: 2; }
        .station-grid-item .ycr-pin-station-btn i.fa-heart, .station-grid-item .ycr-pin-station-btn i.fas.fa-heart, .station-grid-item .ycr-pin-station-btn i.far.fa-heart { font-size: 0.8em; }
        .station-grid-item .ycr-pin-station-btn:hover { color: #555; transform: scale(1.1); }
        .station-grid-item .ycr-pin-station-btn.is-user-personally-pinned .fas.fa-heart { color: #ff4500; }
        .station-grid-item .ycr-pin-station-btn.is-user-personally-pinned:hover .fas.fa-heart { color: #cc3700; }
        .ycr-admin-pinned-badge {
    display: block; /* Make it take its own line */
    color: #0073aa;
    font-size: 0.8em; /* Adjust as preferred */
    margin-top: 3px;  /* Space between name and badge */
    margin-bottom: 3px; /* Space between badge and country if country exists */
    line-height: 1; /* Ensure icon aligns well if text is added later */
    /* text-align: center; is inherited from .station-grid-item */
}
        .station-grid-details-text { width: 100%; margin-top: auto; padding-top: 5px; }
        .station-name-line {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 3px; /* Existing, adjust if needed */
    width: 100%; /* Ensure it takes full width to center content within it */
}
        .station-grid-details-text .station-grid-name {
    font-size: 0.95em;
    line-height: 1.3;
    margin: 0 5px; /* Existing */
    /* flex-grow: 1; /* Uncomment if you want name to take more space */
    /* text-align: center; /* Or left, depending on desired alignment with pin */
}
        .station-grid-details-text .station-grid-country {
    font-size: 0.85em;
    color: #666;
    margin: 0; /* Reset margin if any was previously added affecting spacing */
    text-align: center;
}
        .ycr-pin-station-btn.ycr-loading i { animation: ycrPinSpin 1s linear infinite; }
        @keyframes ycrPinSpin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .ycr-language-filters { margin-bottom: 20px; text-align: center; }
        .ycr-lang-filter-btn { margin: 0 3px 8px; padding: 8px 15px; cursor: pointer; border: 1px solid #ccc; background-color: #f7f7f7; border-radius: 4px; font-size:0.9em; transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease; }
        .ycr-lang-filter-btn:hover { background-color: #e9e9e9; border-color: #bbb; }
        .ycr-lang-filter-btn.active { background-color: #0073aa; color: white; border-color: #0073aa;}
        .station-grid-item.is-playing { border: 2px solid #0073aa; box-shadow: 0 0 10px rgba(0, 115, 170, 0.4); background-color: #e6f7ff; transform: scale(1.02); }
        .station-grid-item.is-playing .station-logo-wrapper::after { content: ''; position: absolute; top: 50%; left: 50%; width: 0; height: 0; background: rgba(0, 115, 170, 0.5); border-radius: 50%; opacity: 0; animation: pulse 1.5s infinite; transform: translate(-50%, -50%); z-index: 1; }
        @keyframes pulse { 0% { width: 0; height: 0; opacity: 0; } 50% { width: 100%; height: 100%; opacity: 0.4; } 100% { width: 150%; height: 150%; opacity: 0; } }
        .ycr-popup-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); z-index: 99999; justify-content: center; align-items: center; overflow: auto; }
        .ycr-popup-content { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); max-width: 400px; width: 90%; position: relative; text-align: center; animation: fadeIn 0.3s ease-out; }
        .ycr-popup-close-btn { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; background: none; border: none; color: #555; line-height: 1; padding: 0; }
        .ycr-popup-close-btn:hover { color: #000; }
        .ycr-dialog-login-btn { margin-top: 0px !important; display: inline-block !important; text-decoration:none; }
        .ycr-dialog-login-btn i { margin-right: 5px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 1200px) { .radio-station-grid.stations-per-row-10 { grid-template-columns: repeat(8, 1fr); } }
        @media (max-width: 992px) { .radio-station-grid.stations-per-row-10, .radio-station-grid.stations-per-row-8 { grid-template-columns: repeat(6, 1fr); } }
        @media (max-width: 768px) { .radio-station-grid.stations-per-row-10, .radio-station-grid.stations-per-row-8, .radio-station-grid.stations-per-row-6 { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 575.98px) {
            .radio-station-grid { grid-template-columns: repeat(var(--ycr-stations-per-row-mobile, 3), 1fr) !important; gap: 8px !important; }
            .radio-station-grid.stations-per-row-mobile-2 { grid-template-columns: repeat(2, 1fr) !important; }
            .radio-station-grid.stations-per-row-mobile-3 { grid-template-columns: repeat(3, 1fr) !important; }
            .radio-station-grid.stations-per-row-mobile-4 { grid-template-columns: repeat(4, 1fr) !important; }
            .station-grid-item img { max-width: 100% !important; max-height: 80px !important; object-fit: contain !important; }
            .station-grid-item .ycr-pin-station-btn { width:26px !important; height:26px !important; font-size:0.9em !important; top:2px !important; right:2px !important;}
            .station-grid-item .ycr-pin-station-btn i.fa-heart, .station-grid-item .ycr-pin-station-btn i.fas.fa-heart, .station-grid-item .ycr-pin-station-btn i.far.fa-heart { font-size: 0.75em !important; }
        }
        @keyframes ycrHeartbeat { 0% { transform: scale(1); } 14% { transform: scale(1.15); } 28% { transform: scale(1); } 42% { transform: scale(1.15); } 70% { transform: scale(1); } 100% { transform: scale(1); } }
        #ycr-toggle-my-favorites-btn i.fa-heart, #ycr-toggle-my-favorites-btn i.fas.fa-heart, #ycr-toggle-my-favorites-btn i.far.fa-heart { animation: ycrHeartbeat 2.5s infinite ease-in-out; display: inline-block; }
        #ycr-toggle-my-favorites-btn i.fas.fa-heart { color: #ff4500; }
        .station-grid-item .ycr-pin-station-btn.ycr-loading i.fa-spinner { animation: ycrPinSpin 1s linear infinite; font-size: 1em; transform: none; }
		div#ycr-featured-stations-section { display: none !important;} /* This was already here */
    </style>";

    ob_start();
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Override global settings with shortcode specific values if present
        ycRadioPlayerGlobal.filteredLanguageByShortcode = '<?php echo esc_js($filter_language); ?>';
        ycRadioPlayerGlobal.filteredCountryByShortcode = '<?php echo esc_js($filter_country); ?>';
        ycRadioPlayerGlobal.baseBrowseHeading = '<?php echo esc_js($base_browse_heading_php); ?>';

        const allStationsDataForMainGrid = <?php echo json_encode($all_stations_js); ?>; // This list is ALREADY filtered by shortcode and sorted
        const recentlyAddedLimitDefault = <?php echo esc_js($recently_added_limit_setting); ?>;
        const itemsPerPageSetting = <?php echo esc_js($frontend_pagination_per_page); ?>;

        const mainDynamicGridContainer = document.getElementById('ycr-station-grid-dynamic-container');
        const mainGridHeadingWrapper = document.getElementById('ycr-main-grid-heading-wrapper');
        const paginationContainer = document.getElementById('ycr-pagination-controls-frontend');
        const noResultsMessageEl = document.getElementById('ycr-no-stations-found');
        const searchInputGlobal = document.getElementById('yc-radio-station-search');
        const languageFilterButtons = document.querySelectorAll('.ycr-lang-filter-btn');
        const userActionNoticeArea = document.getElementById('ycr-user-action-notice-area');
        const playerContainerEl = document.getElementById('player-container');
        const radioPlayer = document.getElementById('radio-player');
        const source = document.getElementById('radio-source'); // Keep this if used for initial load or checks
        const logoImg = document.getElementById('station-logo');
        const myFavoritesSection = document.getElementById('ycr-my-favorites-section');
        const featuredStationsSection = document.getElementById('ycr-featured-stations-section');
        const browseStationsSection = document.getElementById('ycr-browse-stations-section');
        const toggleMyFavoritesBtn = document.getElementById('ycr-toggle-my-favorites-btn');
        const openPopupButton = document.getElementById('yc-open-popup-player-btn');

        let currentVisibleStationsForMainGrid = [];
        let currentPageForJS = 1;
        let currentLanguageFilterJS = ycRadioPlayerGlobal.filteredLanguageByShortcode;
        let currentView = 'all';

        function setupDialogLoginButton(dialogElement) {
            const loginLink = dialogElement.querySelector('.ycr-dialog-login-btn');
            if (loginLink) {
                loginLink.href = ycRadioPlayerGlobal.loginLinkUrl;
                loginLink.innerHTML = '';
                const iconEl = document.createElement('i');
                if(ycRadioPlayerGlobal.loginIconClass && ycRadioPlayerGlobal.loginIconClass.trim() !== '') {
                    ycRadioPlayerGlobal.loginIconClass.split(' ').forEach(cls => {
                        if(cls.trim()) iconEl.classList.add(cls.trim());
                    });
                    loginLink.appendChild(iconEl);
                }
                const textSpan = document.createElement('span');
                textSpan.textContent = (ycRadioPlayerGlobal.loginIconClass && ycRadioPlayerGlobal.loginIconClass.trim() !== '' ? ' ' : '') + ycRadioPlayerGlobal.loginButtonText;
                loginLink.appendChild(textSpan);
                if (ycRadioPlayerGlobal.loginLinkClass) {
                    loginLink.className = 'button ycr-dialog-login-btn'; // Reset classes first
                    ycRadioPlayerGlobal.loginLinkClass.split(' ').forEach(cls => {
                        if(cls.trim()) loginLink.classList.add(cls.trim());
                    });
                }
                loginLink.addEventListener('click', function() {
                    if (dialogElement && dialogElement.classList.contains('ycr-popup-overlay')) {
                        dialogElement.style.display = 'none';
                    }
                });
            }
        }

        function showNotice(message, type = 'info', targetElement = userActionNoticeArea) {
            if (type === 'info' || type === 'error') {
                if (!targetElement) targetElement = userActionNoticeArea || document.body;
                let existingNoticeInTarget = targetElement.querySelector('.ycr-user-notice:not(#ycr-login-popup):not(#ycr-access-restricted-popup)');
                if (existingNoticeInTarget) existingNoticeInTarget.remove();

            }
            let popupElementId = ''; let noticeTemplate = '';
            if (type === 'login') { popupElementId = 'ycr-login-popup'; noticeTemplate = ycRadioPlayerGlobal.loginNoticeTemplate; }
            else if (type === 'access_restricted') { popupElementId = 'ycr-access-restricted-popup'; noticeTemplate = ycRadioPlayerGlobal.accessRestrictedNoticeTemplate; }

            if (popupElementId) {
                let popup = document.getElementById(popupElementId);
                if (!popup) {
                    const tempDiv = document.createElement('div'); tempDiv.innerHTML = noticeTemplate.trim();
                    popup = tempDiv.firstChild;
                    if (popup) {
                        document.body.appendChild(popup); setupDialogLoginButton(popup);
                        const closeButton = popup.querySelector('.ycr-popup-close-btn');
                        if (closeButton) closeButton.addEventListener('click', function() { popup.style.display = 'none'; });
                        popup.addEventListener('click', function(e) { if (e.target === popup) popup.style.display = 'none'; });
                    }
                } else { setupDialogLoginButton(popup); } // Re-setup button in case classes/text changed in admin
                if (popup) popup.style.display = 'flex'; return;
            }

            if (!targetElement) targetElement = userActionNoticeArea || document.body;
            let noticeHTML = ''; const tempDivInfoError = document.createElement('div');
            if (type === 'error') { tempDivInfoError.innerHTML = ycRadioPlayerGlobal.errorNoticeTemplate.trim(); if(tempDivInfoError.firstChild) tempDivInfoError.firstChild.textContent = message; }
            else { tempDivInfoError.innerHTML = ycRadioPlayerGlobal.infoNoticeTemplate.trim(); if(tempDivInfoError.firstChild) tempDivInfoError.firstChild.textContent = message; }
            noticeHTML = tempDivInfoError.innerHTML;
            let noticeElementToDisplay;
            if (targetElement === userActionNoticeArea && userActionNoticeArea) { userActionNoticeArea.innerHTML = noticeHTML; noticeElementToDisplay = userActionNoticeArea.firstChild; }
            else if (targetElement && targetElement.insertAdjacentHTML) {
                let existingTempNotice = targetElement.nextElementSibling;
                if (existingTempNotice && existingTempNotice.classList.contains('ycr-user-notice')  && !existingTempNotice.id) existingTempNotice.remove();
                targetElement.insertAdjacentHTML('afterend', noticeHTML); noticeElementToDisplay = targetElement.nextElementSibling;
            } else { const tempWrapper = document.createElement('div'); tempWrapper.innerHTML = noticeHTML; noticeElementToDisplay = tempWrapper.firstChild; if (noticeElementToDisplay) document.body.appendChild(noticeElementToDisplay); }
            if (noticeElementToDisplay && (type === 'info' || type === 'error')) { noticeElementToDisplay.style.display = 'block'; setTimeout(() => { if(noticeElementToDisplay && noticeElementToDisplay.parentNode) noticeElementToDisplay.parentNode.removeChild(noticeElementToDisplay); }, 4000); }
        }

        function renderMainGridStationItemHTML(station, sectionContext = 'mainGrid') {
            const countryDisplay = station.country ? `<p class="station-grid-country">${station.country}</p>` : '<p class="station-grid-country"></p>';
            const stationLogoSrc = station.logo || ycRadioPlayerGlobal.defaultLogoUrl;
            let itemClasses = 'station-grid-item';
            if (sectionContext === 'myFavorites') itemClasses += ' ycr-in-my-favorites';
            else if (sectionContext === 'featured') itemClasses += ' ycr-in-featured';
            const isUserPersonallyPinned = ycRadioPlayerGlobal.isLoggedIn && ycRadioPlayerGlobal.userPinnedStations.includes(station.url);
            const isAdminGloballyPinned = station.isAdminPinned; // This comes from allStationsDataForMainGrid
            let userPinButtonHTML = '', pinButtonClasses = 'ycr-pin-station-btn ', pinTitle = '', iconClass = 'far fa-heart';
            if (ycRadioPlayerGlobal.isLoggedIn) {
                if (isUserPersonallyPinned) { iconClass = 'fas fa-heart'; pinButtonClasses += 'is-user-personally-pinned'; pinTitle = "Remove from My Favorites"; }
                else pinTitle = "Add to My Favorites";
            } else { pinButtonClasses += 'ycr-guest-pin-attempt'; pinTitle = "Log in to add to My Favorites"; }
            userPinButtonHTML = `<button class="${pinButtonClasses}" title="${pinTitle}" data-station-url="${station.url}"><i class="${iconClass}"></i></button>`;

            let adminPinIndicatorHTML = '';
            if (isAdminGloballyPinned && (sectionContext !== 'myFavorites' || sectionContext === 'featured')) {
                adminPinIndicatorHTML = `<span class="ycr-admin-pinned-badge" title="Featured Station"><i class="fas fa-star"></i></span>`;
            }

            return `
                <div class="${itemClasses}" data-station-url="${station.url}" data-station-logo="${stationLogoSrc}" data-station-name="${station.name}" data-station-country="${station.country || ''}" data-station-language-searchable="${(station.language || '').toLowerCase()}" data-is-admin-pinned="${isAdminGloballyPinned ? 'true' : 'false'}" tabindex="0">
                    <div class="station-logo-wrapper"><img src="${stationLogoSrc}" alt="${station.name} Logo"></div>
                    <div class="station-grid-details-text">
                        <div class="station-name-line">${userPinButtonHTML}<span class="station-grid-name">${station.name}</span></div>
                        ${adminPinIndicatorHTML}
                        ${countryDisplay}
                    </div>
                </div>`;
        }

        function reAttachMainPlayerListeners(containerElement = document) {
            const actualContainer = (containerElement === document) ? (document.querySelector('.custom-radio-player-wrapper') || document) : containerElement;
            if (!actualContainer) return;

            let stationItemsToProcess = [];
            if (actualContainer.classList && actualContainer.classList.contains('station-grid-item')) {
                 stationItemsToProcess.push(actualContainer);
            } else {
                 stationItemsToProcess = Array.from(actualContainer.querySelectorAll('.station-grid-item'));
            }

            stationItemsToProcess.forEach(item => {
                // Clone and replace to remove old listeners effectively if this function is called multiple times on same elements
                const newItem = item.cloneNode(true);
                if (item.parentNode) item.parentNode.replaceChild(newItem, item);

                newItem.removeEventListener('click', handleStationItemClick); // Ensure no duplicates
                newItem.removeEventListener('keypress', handleStationItemKeyPress);

                newItem.addEventListener('click', handleStationItemClick);
                newItem.addEventListener('keypress', handleStationItemKeyPress);
            });
        }
        
        function handleStationItemKeyPress(e) {
             if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                handleStationItemClick.call(this, e);
            }
        }


        function highlightNowPlayingStation(stationElement) {
            document.querySelectorAll('.station-grid-item.is-playing').forEach(el => el.classList.remove('is-playing'));
            if (stationElement) stationElement.classList.add('is-playing');
        }

        // CORRECTED PART BELOW:
        function handleStationItemClick(event) {
            if (event.target.closest('.ycr-pin-station-btn')) return;

            const stationItem = this;
            const isAdminPinned = stationItem.dataset.isAdminPinned === 'true';

            if (!ycRadioPlayerGlobal.isLoggedIn && !isAdminPinned) {
                event.preventDefault(); event.stopPropagation();
                showNotice('', 'access_restricted'); return;
            }

            event.preventDefault();
            const stationUrl = this.dataset.stationUrl, stationName = this.dataset.stationName,
                  stationLogo = this.dataset.stationLogo || ycRadioPlayerGlobal.defaultLogoUrl, stationCountry = this.dataset.stationCountry;
            
            // Note: `source` and `logoImg` are already defined in the outer scope.
            // `radioPlayer` and `playerContainerEl` are also in outer scope.

            if (radioPlayer && logoImg && playerContainerEl) { // Simplified the check slightly
                // Pause the player before changing the source (good practice)
                radioPlayer.pause();

                // Set the src attribute directly on the audio element
                radioPlayer.src = stationUrl;
                
                // Attempt to play the new source
                // radioPlayer.load(); // Usually implicit, but can be uncommented if issues persist
                
                const playPromise = radioPlayer.play();
                if (playPromise !== undefined) {
                    playPromise.catch(err => {
                        console.warn("YCR Frontend: Playback failed for new station.", err);
                        // Optional: Update UI if playback fails (e.g., remove 'is-playing' highlight)
                    });
                }

                // Update logo and dataset attributes as before
                logoImg.setAttribute('src', stationLogo); 
                logoImg.setAttribute('alt', stationName + ' Logo');
                playerContainerEl.dataset.currentStationUrl = stationUrl;
                playerContainerEl.dataset.currentStationName = stationName;
                playerContainerEl.dataset.currentStationLogo = stationLogo;
                playerContainerEl.dataset.currentStationCountry = stationCountry;
                playerContainerEl.dataset.currentStationAdminPinned = isAdminPinned ? 'true' : 'false';

                if(document.title.includes('Radio Player') || document.title.includes(playerContainerEl.dataset.currentStationName)) {
                     document.title = stationName + ' - ' + (document.title.split(' - ')[1] || 'Radio Player');
                }
                const d = new Date(); d.setTime(d.getTime() + (30*24*60*60*1000));
                document.cookie = "selected_station_url=" + encodeURIComponent(stationUrl) + ";expires=" + d.toUTCString() + ";path=/;SameSite=Lax";
                highlightNowPlayingStation(this);
            }
        }
        // END OF CORRECTED PART

        function displayCurrentStationsInMainGrid() {
            if (!mainDynamicGridContainer) return;
            mainDynamicGridContainer.innerHTML = '';
            const totalItemsToDisplay = currentVisibleStationsForMainGrid.length;
            const totalPages = Math.max(1, Math.ceil(totalItemsToDisplay / itemsPerPageSetting));
            currentPageForJS = Math.max(1, Math.min(currentPageForJS, totalPages));
            const startIndex = (currentPageForJS - 1) * itemsPerPageSetting;
            const endIndex = startIndex + itemsPerPageSetting;
            const paginatedItems = currentVisibleStationsForMainGrid.slice(startIndex, endIndex);
            if (paginatedItems.length === 0) {
                noResultsMessageEl.textContent = (searchInputGlobal && searchInputGlobal.value.trim() !== "" || currentLanguageFilterJS !== "" && ycRadioPlayerGlobal.filteredLanguageByShortcode === "") ? 'No stations found matching your criteria.' : 'No stations to display in this section.';
                noResultsMessageEl.style.display = 'block';
                if(paginationContainer) paginationContainer.style.display = 'none';
                if(mainGridHeadingWrapper) mainGridHeadingWrapper.style.display = (searchInputGlobal && searchInputGlobal.value.trim() === "" && currentLanguageFilterJS === "" && !ycRadioPlayerGlobal.filteredCountryByShortcode && !ycRadioPlayerGlobal.filteredLanguageByShortcode) ? 'none' : 'block';

            } else {
                noResultsMessageEl.style.display = 'none';
                if(mainGridHeadingWrapper) mainGridHeadingWrapper.style.display = 'block';
                let htmlContent = `<div class="radio-station-grid stations-per-row-${ycRadioPlayerGlobal.stationsPerRow} stations-per-row-mobile-${ycRadioPlayerGlobal.stationsPerRowMobile}">`;
                paginatedItems.forEach(station => { htmlContent += renderMainGridStationItemHTML(station, 'mainGrid'); });
                htmlContent += `</div>`;
                mainDynamicGridContainer.innerHTML = htmlContent;
                renderPaginationControls(totalPages, totalItemsToDisplay);
            }
            reAttachMainPlayerListeners(mainDynamicGridContainer); // Re-attach to newly rendered items
            const currentPlayingUrl = playerContainerEl.dataset.currentStationUrl;
            if (currentPlayingUrl) {
                const playingStationElement = mainDynamicGridContainer.querySelector(`.station-grid-item[data-station-url="${currentPlayingUrl}"]`);
                highlightNowPlayingStation(playingStationElement);
            }
        }

        function renderPaginationControls(totalPages, totalItems) {
            if (!paginationContainer) return;
            paginationContainer.innerHTML = '';
            if (totalPages <= 1) { paginationContainer.style.display = 'none'; return; }
            paginationContainer.style.display = 'block';
            let paginationHTML = '';
            if (currentPageForJS > 1) paginationHTML += `<button class="ycr-page-btn" data-page="${currentPageForJS - 1}">« Previous</button>`;
            paginationHTML += `<span style="margin: 0 10px;">Page ${currentPageForJS} of ${totalPages}</span>`;
            if (currentPageForJS < totalPages) paginationHTML += `<button class="ycr-page-btn" data-page="${currentPageForJS + 1}">Next »</button>`;
            paginationContainer.innerHTML = paginationHTML;
            paginationContainer.querySelectorAll('.ycr-page-btn').forEach(button => {
                button.addEventListener('click', function() {
                    currentPageForJS = parseInt(this.dataset.page);
                    displayCurrentStationsInMainGrid();
                    if (playerContainerEl) playerContainerEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
        }

        function filterAndPrepareStationsForMainGrid() {
            currentPageForJS = 1;
            let stationsToProcess = [...allStationsDataForMainGrid];
            let mainGridHeadingText = ycRadioPlayerGlobal.baseBrowseHeading;

            // Apply frontend language filter if not set by shortcode
            if (ycRadioPlayerGlobal.filteredLanguageByShortcode === "" && currentLanguageFilterJS !== "") {
                stationsToProcess = stationsToProcess.filter(station => (station.language || '').toLowerCase() === currentLanguageFilterJS.toLowerCase());
                mainGridHeadingText = stationsToProcess.length > 0 ? `Stations in ${currentLanguageFilterJS}` : `No stations found for ${currentLanguageFilterJS}`;
            }

            const searchTermGlobalVal = searchInputGlobal ? searchInputGlobal.value.toLowerCase().trim() : '';
            if (searchTermGlobalVal !== "") {
                stationsToProcess = stationsToProcess.filter(station => {
                    const nameMatch = (station.name || '').toLowerCase().includes(searchTermGlobalVal);
                    const countryMatch = (station.country || '').toLowerCase().includes(searchTermGlobalVal);
                    const languageMatch = (station.language || '').toLowerCase().includes(searchTermGlobalVal);
                    return nameMatch || countryMatch || languageMatch;
                });
                let searchPrefix = stationsToProcess.length > 0 ? `Search results for "${searchTermGlobalVal}"` : `No stations found for "${searchTermGlobalVal}"`;
                if(ycRadioPlayerGlobal.filteredLanguageByShortcode && ycRadioPlayerGlobal.filteredCountryByShortcode) {
                    mainGridHeadingText = `${searchPrefix} in ${ycRadioPlayerGlobal.filteredLanguageByShortcode} (${ycRadioPlayerGlobal.filteredCountryByShortcode})`;
                } else if (ycRadioPlayerGlobal.filteredLanguageByShortcode) {
                     mainGridHeadingText = `${searchPrefix} in ${ycRadioPlayerGlobal.filteredLanguageByShortcode}`;
                } else if (ycRadioPlayerGlobal.filteredCountryByShortcode) {
                     mainGridHeadingText = `${searchPrefix} from ${ycRadioPlayerGlobal.filteredCountryByShortcode}`;
                } else if (currentLanguageFilterJS && ycRadioPlayerGlobal.filteredLanguageByShortcode === "") {
                    mainGridHeadingText = `${searchPrefix} in ${currentLanguageFilterJS}`;
                } else {
                    mainGridHeadingText = searchPrefix;
                }
            }

            if(mainGridHeadingWrapper) {
                const headingElement = mainGridHeadingWrapper.querySelector('h2');
                if (headingElement) headingElement.textContent = mainGridHeadingText;
            }

            const noShortcodeFilters = ycRadioPlayerGlobal.filteredLanguageByShortcode === "" && ycRadioPlayerGlobal.filteredCountryByShortcode === "";
            const noFrontendFilters = (currentLanguageFilterJS === "" || ycRadioPlayerGlobal.filteredLanguageByShortcode !== "") && searchTermGlobalVal === "";

            if (noShortcodeFilters && noFrontendFilters && recentlyAddedLimitDefault > 0 && stationsToProcess.length > recentlyAddedLimitDefault && currentView === 'all') {
                currentVisibleStationsForMainGrid = stationsToProcess.slice(0, recentlyAddedLimitDefault);
            } else {
                 currentVisibleStationsForMainGrid = stationsToProcess;
            }
            // Admin pinned stations are already at the top due to PHP sorting for allStationsDataForMainGrid ($all_stations_js)
            displayCurrentStationsInMainGrid();
        }


        if (toggleMyFavoritesBtn) {
            toggleMyFavoritesBtn.addEventListener('click', function() {
                const favSectionTitleEl = document.getElementById('ycr-my-favorites-title');
                if (currentView === 'favorites') {
                    if (myFavoritesSection) myFavoritesSection.style.display = 'none';
                    if (featuredStationsSection) featuredStationsSection.style.display = 'block'; // Or as per its original display logic
                    if (browseStationsSection) browseStationsSection.style.display = 'block';
                    this.innerHTML = 'My Favorite Stations <i class="fas fa-heart"></i>';
                    currentView = 'all';
                    filterAndPrepareStationsForMainGrid(); // Refresh main grid
                } else {
                    if (myFavoritesSection) {
                        myFavoritesSection.style.display = 'block';
                        if(favSectionTitleEl) favSectionTitleEl.textContent = ycRadioPlayerGlobal.favoritesTitle;
                        reAttachMainPlayerListeners(myFavoritesSection); // Attach listeners to favorite items
                    }
                    if (featuredStationsSection) featuredStationsSection.style.display = 'none';
                    if (browseStationsSection) browseStationsSection.style.display = 'none';
                    this.innerHTML = 'Show All Stations <i class="fas fa-list-ul"></i>';
                    currentView = 'favorites';
                    const currentPlayingUrl = playerContainerEl.dataset.currentStationUrl;
                    if (currentPlayingUrl && myFavoritesSection) {
                        const playingStationElement = myFavoritesSection.querySelector(`.station-grid-item[data-station-url="${currentPlayingUrl}"]`);
                        highlightNowPlayingStation(playingStationElement);
                    }
                }
            });
        }

        if (languageFilterButtons.length > 0) {
            languageFilterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    languageFilterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    currentLanguageFilterJS = this.dataset.lang;
                    if (currentView === 'favorites' && toggleMyFavoritesBtn) toggleMyFavoritesBtn.click(); // Switch to all view
                    filterAndPrepareStationsForMainGrid();
                });
            });
        }


        if (searchInputGlobal) {
            searchInputGlobal.addEventListener('input', function() {
                 if (currentView === 'favorites' && toggleMyFavoritesBtn) toggleMyFavoritesBtn.click(); // Switch to all view
                filterAndPrepareStationsForMainGrid();
            });
        }

        document.body.addEventListener('click', function(event) {
            let ajaxSuccessResponse = null;
            const pinButton = event.target.closest('.ycr-pin-station-btn');
            if (!pinButton) return;
            event.preventDefault();
            const stationUrlToToggle = pinButton.dataset.stationUrl;
            if (ycRadioPlayerGlobal.isLoggedIn) {
                jQuery.ajax({
                    url: ycRadioPlayerGlobal.ajaxUrl, type: 'POST',
                    data: { action: 'ycr_toggle_pin_station', nonce: ycRadioPlayerGlobal.pinNonce, station_url: stationUrlToToggle },
                    beforeSend: function() {
                        pinButton.disabled = true; pinButton.classList.add('ycr-loading');
                        const icon = pinButton.querySelector('i'); if(icon) icon.className = 'fas fa-spinner fa-spin';
                    },
                    success: function(response) {
                        ajaxSuccessResponse = response;
                        if (response.success) {
                            ycRadioPlayerGlobal.userPinnedStations = response.data.pinned_list || [];
                            const isNowPinned = response.data.is_pinned_now;
                            if (isNowPinned) {
                                // For simplicity, reload if adding to favorites to refresh the My Favorites section correctly
                                // Could be optimized to add dynamically, but reload is safer for now.
                                window.location.reload();
                            }
                            else { // Removed from favorites
                                document.querySelectorAll(`.ycr-pin-station-btn[data-station-url="${stationUrlToToggle}"]`).forEach(btn => {
                                    btn.classList.remove('is-user-personally-pinned');
                                    const icon = btn.querySelector('i'); if(icon) icon.className = 'far fa-heart';
                                    btn.title = "Add to My Favorites";
                                });
                                if (myFavoritesSection) {
                                    const favGrid = myFavoritesSection.querySelector('.ycr-user-pinned-grid');
                                    if (favGrid) {
                                        const existingFavItem = favGrid.querySelector(`.station-grid-item[data-station-url="${stationUrlToToggle}"]`);
                                        if (existingFavItem) existingFavItem.remove();
                                        const favItemCount = favGrid.querySelectorAll('.station-grid-item').length;
                                        let noFavMsgPlaceholder = myFavoritesSection.querySelector('p.ycr-no-favorites-message');
                                        if (favItemCount === 0) {
                                            if (!noFavMsgPlaceholder) {
                                                noFavMsgPlaceholder = document.createElement('p'); noFavMsgPlaceholder.className = 'ycr-no-favorites-message';
                                                noFavMsgPlaceholder.style.textAlign = 'center';
                                                noFavMsgPlaceholder.innerHTML = 'You haven\'t added any favorite stations yet. Click the <i class="far fa-heart"></i> icon on a station to add it here!';
                                                favGrid.appendChild(noFavMsgPlaceholder); // Append to grid or after heading
                                            }
                                            noFavMsgPlaceholder.style.display = 'block';
                                        } else { if (noFavMsgPlaceholder) noFavMsgPlaceholder.style.display = 'none'; }
                                    }
                                }
                                showNotice(response.data.message, 'info');
                            }
                        } else { showNotice(response.data.message || 'Could not update pin.', 'error'); }
                    },
                    error: function() { showNotice('Error: Could not connect to update pin.', 'error'); },
                    complete: function() {
                        const isReloading = ycRadioPlayerGlobal.isLoggedIn && ajaxSuccessResponse && ajaxSuccessResponse.success && ajaxSuccessResponse.data.is_pinned_now;
                        if (!isReloading) {
                             document.querySelectorAll(`.ycr-pin-station-btn[data-station-url="${stationUrlToToggle}"]`).forEach(btn => {
                                btn.disabled = false; btn.classList.remove('ycr-loading');
                                const isPinned = btn.classList.contains('is-user-personally-pinned'); // Check current state
                                const icon = btn.querySelector('i'); if(icon) icon.className = isPinned ? 'fas fa-heart' : 'far fa-heart';
                            });
                        }
                    }
                });
            } else { showNotice('', 'login'); }
        });

        if (openPopupButton) {
            openPopupButton.addEventListener('click', function() {
                if (radioPlayer && !radioPlayer.paused) { radioPlayer.pause(); const sb = document.querySelector('#player-container .sound-bar'); if (sb) sb.classList.add('paused'); }
                if (ycRadioPlayerGlobal && ycRadioPlayerGlobal.popupUrl) {
                    const pc = document.getElementById('player-container');
                    let su = '', sn = '', sl = '', sc = '', sap = 'false';
                    if (pc) {
                        su = pc.dataset.currentStationUrl || '';
                        sn = pc.dataset.currentStationName || '';
                        sl = pc.dataset.currentStationLogo || '';
                        sc = pc.dataset.currentStationCountry || '';
                        sap = pc.dataset.currentStationAdminPinned === 'true' ? 'true' : 'false'; // Get from dataset
                    }
                    let pt = ycRadioPlayerGlobal.popupUrl;
                    if (su) { pt += (pt.includes('?') ? '&' : '?') + `station_url=${encodeURIComponent(su)}&station_name=${encodeURIComponent(sn)}&station_logo=${encodeURIComponent(sl)}&station_country=${encodeURIComponent(sc)}&is_admin_pinned=${sap}`; }
                    window.open(pt, 'ycRadioPopup', 'width=550,height=930,resizable=yes,scrollbars=yes');
                } else showNotice('Popup player URL is not configured.', 'error');
            });
        }

        // Initial setup
        if (ycRadioPlayerGlobal.filteredLanguageByShortcode !== "" && languageFilterButtons.length > 0) {
            languageFilterButtons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.lang.toLowerCase() === ycRadioPlayerGlobal.filteredLanguageByShortcode.toLowerCase()) {
                    btn.classList.add('active');
                }
            });
        }

        filterAndPrepareStationsForMainGrid();
        reAttachMainPlayerListeners(document.getElementById('ycr-my-favorites-section'));
        reAttachMainPlayerListeners(document.getElementById('ycr-featured-stations-section'));
        const initialPlayingUrl = playerContainerEl.dataset.currentStationUrl;
        if (initialPlayingUrl) {
            const initialPlayingElement = document.querySelector(`.station-grid-item[data-station-url="${initialPlayingUrl}"]`);
            highlightNowPlayingStation(initialPlayingElement);
        }
    });
    </script>
    <?php
    $output .= ob_get_clean();
    return $output;
}
add_shortcode('custom_radio_player', 'custom_radio_player_shortcode');


add_action('wp_ajax_ycr_toggle_pin_station', 'ycr_ajax_toggle_pin_station_handler');
function ycr_ajax_toggle_pin_station_handler() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ycr_pin_station_nonce' ) ) {
        wp_send_json_error(['message' => 'Security check failed.'], 403); return;
    }
    if (!is_user_logged_in()) { wp_send_json_error(['message' => 'You must be logged in to manage favorites.'], 401); return; }
    $user_id = get_current_user_id();
    $station_url = isset($_POST['station_url']) ? esc_url_raw( wp_unslash( $_POST['station_url'] ) ) : null;
    if (!$station_url) { wp_send_json_error(['message' => 'Station information missing.'], 400); return; }
    $meta_key = '_ycr_user_pinned_stations';
    $pinned_stations_by_user = get_user_meta($user_id, $meta_key, true);
    if (!is_array($pinned_stations_by_user)) $pinned_stations_by_user = [];
    $is_now_pinned = false; $message = '';
    if (in_array($station_url, $pinned_stations_by_user)) {
        $pinned_stations_by_user = array_values(array_diff($pinned_stations_by_user, [$station_url]));
        $message = 'Station removed from My Favorites!'; $is_now_pinned = false;
    } else {
        $pinned_stations_by_user[] = $station_url;
        $message = 'Station added to My Favorites!'; $is_now_pinned = true;
    }
    $pinned_stations_by_user = array_values(array_unique($pinned_stations_by_user));
    update_user_meta($user_id, $meta_key, $pinned_stations_by_user);
    wp_send_json_success([ 'message' => $message, 'is_pinned_now' => $is_now_pinned, 'pinned_list' => $pinned_stations_by_user ]);
}

add_action('template_redirect', 'yc_handle_popup_player_request');
function yc_handle_popup_player_request() {
    if (isset($_GET['yc_radio_action']) && $_GET['yc_radio_action'] === 'popup_player') { yc_render_popup_player_page(); exit; }
}

function yc_render_popup_player_page() {
    $all_stations_data = get_option('radio_player_stations', []); $stations = [];
    if (is_array($all_stations_data)) {
        $stations = array_filter($all_stations_data, function($s){ return is_array($s) && !empty($s['url']) && !empty($s['name']); });
        $stations = array_values($stations); // Re-index
    }
    $default_logo = YCR_PLUGIN_URL . 'images/default-logo.png';

    $url = isset($_GET['station_url']) ? esc_url_raw(wp_unslash($_GET['station_url'])) : '';
    $name = isset($_GET['station_name']) ? sanitize_text_field(wp_unslash($_GET['station_name'])) : 'Radio Player';
    $logo = isset($_GET['station_logo']) ? esc_url_raw(wp_unslash($_GET['station_logo'])) : $default_logo;
    $country = isset($_GET['station_country']) ? sanitize_text_field(wp_unslash($_GET['station_country'])) : '';
    $is_admin_pinned_initial = isset($_GET['is_admin_pinned']) && $_GET['is_admin_pinned'] === 'true';

    if (empty($url) && !empty($stations)) { // If URL not passed via GET, load default
        $default_station_index = (int) get_option('radio_player_default_station', 0);
        $station_to_load_as_default = null;

        if (isset($stations[$default_station_index])) {
            $station_to_load_as_default = $stations[$default_station_index];
        } elseif (!empty($stations[0])) {
            $station_to_load_as_default = $stations[0];
        }

        if ($station_to_load_as_default) {
            $url = esc_url($station_to_load_as_default['url']);
            $name = esc_html($station_to_load_as_default['name']);
            $logo = esc_url(!empty($station_to_load_as_default['logo']) ? $station_to_load_as_default['logo'] : $default_logo);
            $country = esc_html($station_to_load_as_default['country'] ?? '');
            $is_admin_pinned_initial = !empty($station_to_load_as_default['pinned']);
        }
    }


    $site_logo_url = '';
    if (function_exists('get_site_icon_url') && get_site_icon_url(64)) $site_logo_url = get_site_icon_url(64);
    elseif (function_exists('get_custom_logo') && has_custom_logo()) { $id = get_theme_mod('custom_logo'); $img = wp_get_attachment_image_src($id, 'medium'); if ($img) $site_logo_url = $img[0]; }
    $ver = '1.8.7';
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html($name); ?> - Radio Player</title>
        <?php
        echo '<link rel="stylesheet" href="' . esc_url(YCR_PLUGIN_URL . 'css/style_v10.css?ver=' . $ver) . '" type="text/css" media="all" />';
        echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css?ver=' . $ver . '" type="text/css" media="all" />';
        ?>
        <style>
            #yc-popup-loader { position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: #f0f0f0; z-index: 9999; display: flex; justify-content: center; align-items: center; }
            .yc-spinner { border: 5px solid #f3f3f3; border-top: 5px solid #3498db; border-radius: 50%; width: 50px; height: 50px; animation: ycSpin 1s linear infinite; }
            @keyframes ycSpin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background-color: #f0f0f0; display: flex; flex-direction: column; align-items: center; box-sizing: border-box; overflow-y: auto; }
            .popup-wrapper { width: 100%; max-width: 600px; padding: 15px; box-sizing: border-box; visibility: hidden; }
            .popup-header { display: none !important; background-color: #ffffff; padding: 15px 20px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; box-sizing: border-box; border-radius: 6px; }
            .popup-header img { max-height: 55px; width: auto; vertical-align: middle; }
            .popup-player-container { background-color: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; box-sizing: border-box; margin-bottom: 10px; }
            #yc-popup-notice-area { color: #c00; margin-bottom: 10px; padding: 8px; border: 1px solid #f5c6cb; background-color: #f8d7da; border-radius: 4px; display: none; }
            #popup-adsense-placeholder { border: 1px dashed #ccc; width:100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #777; background-color: #f9f9f9; min-height:90px; padding:10px; box-sizing: border-box; }
            #popup-adsense-placeholder ins.adsbygoogle { margin: 0 auto; }
            .popup-player-container .custom-radio-player { display: flex; flex-direction: column; align-items: center; }
            .popup-player-container .player-core-elements { display: flex; align-items: center; width: 100%; margin-bottom: 15px; }
            .popup-player-container .radio-logo { margin-right: 15px; flex-shrink: 0; }
            .popup-player-container .radio-logo img#popup-station-logo { display: block; width: 50px; height: 50px; border-radius: 50%; border: 1px solid #eee; object-fit: cover; margin: 22px 0px 0px 20px; }
            .popup-player-container .audio-and-soundbar { flex-grow: 1; display: flex; flex-direction: column; justify-content: center; }
            .popup-player-container audio#popup-radio-player { width: 100%; margin-bottom: 10px; }
            #popup-player-instance .sound-bar { display: flex; justify-content: center; align-items: flex-end; height: 25px; width: 100px; margin: 5px auto 10px auto; }
            #popup-player-instance .sound-bar.paused .bar { animation-play-state: paused; height: 5px; }
            #popup-player-instance .sound-bar .bar { background-color: #555; width: 6px; margin: 0 2px; animation: ycPopupSoundBarAnimation_v2 0.9s infinite ease-in-out alternate; }
            #popup-player-instance .sound-bar .bar:nth-child(1) { animation-delay: 0s; } #popup-player-instance .sound-bar .bar:nth-child(2) { animation-delay: 0.1s; }
            #popup-player-instance .sound-bar .bar:nth-child(3) { animation-delay: 0.2s; } #popup-player-instance .sound-bar .bar:nth-child(4) { animation-delay: 0.3s; }
            #popup-player-instance .sound-bar .bar:nth-child(5) { animation-delay: 0.4s; }
            @keyframes ycPopupSoundBarAnimation_v2 { 0% { height: 3px; } 25% { height: 18px; } 50% { height: 8px; } 75% { height: 22px; } 100% { height: 3px; } }
            .popup-player-container select#popup-station-selector { padding: 8px 10px; margin-top: 5px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; background-color: #ffffff; color: #333333; box-sizing: border-box; font-size: 15px;  max-width: 350px; }
            .popup-station-info { text-align: left; margin-bottom: 5px; } .popup-station-info .name { font-weight: bold; } .popup-station-info .country { font-size: 0.9em; color: #555; }
        </style>
    </head>
    <body>
        <div id="yc-popup-loader"><div class="yc-spinner"></div></div>
        <div class="popup-wrapper">
            <?php if (!empty($site_logo_url)): ?>
            <div class="popup-header"><img src="<?php echo esc_url($site_logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?> Logo"></div>
            <?php endif; ?>
            <div class="popup-player-container">
                <div id="yc-popup-notice-area"></div>
                <div class="custom-radio-player" id="popup-player-instance">
                    <div class="player-core-elements">
                        <div class="radio-logo"><img id="popup-station-logo" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($name); ?> Logo"></div>
                        <div class="audio-and-soundbar">
                            <div class="popup-station-info">
                                <span id="popup-station-name-current" class="name"><?php echo esc_html($name); ?></span>
                                <span id="popup-station-country-current" class="country"><?php if($country) echo '('.esc_html($country).')'; ?></span>
                            </div>
                            <audio id="popup-radio-player" controls autoplay><source id="popup-radio-source" src="<?php echo esc_url($url); ?>" type="audio/mpeg">No audio support.</audio>
                            <div class="sound-bar paused"><div class="bar"></div><div class="bar"></div><div class="bar"></div><div class="bar"></div><div class="bar"></div></div>
                        </div>
                    </div>
                    <?php if (!empty($stations)): ?>
                    <select id="popup-station-selector">
                        <?php foreach ($stations as $s_item_popup):
                            $s_is_admin_pinned = !empty($s_item_popup['pinned']);
                        ?>
                            <option value="<?php echo esc_url($s_item_popup['url']); ?>"
                                    data-logo="<?php echo esc_url(!empty($s_item_popup['logo']) ? $s_item_popup['logo'] : $default_logo); ?>"
                                    data-name="<?php echo esc_attr($s_item_popup['name']); ?>"
                                    data-country="<?php echo esc_attr($s_item_popup['country'] ?? ''); ?>"
                                    data-is-admin-pinned="<?php echo $s_is_admin_pinned ? 'true' : 'false'; ?>"
                                    <?php selected($url, $s_item_popup['url']); ?>>
                                <?php echo esc_html($s_item_popup['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?><p>No stations available.</p><?php endif; ?>
                </div>
            </div>
            <div id="popup-adsense-placeholder">
                 <p><script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2580918839014029" crossorigin="anonymous"></script>
                <ins class="adsbygoogle" style="display:inline-block;width:457px;height:450px" data-ad-client="ca-pub-2580918839014029" data-ad-slot="5277614192"></ins>
                <script> (adsbygoogle = window.adsbygoogle || []).push({});</script></p>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const isLoggedIn = <?php echo json_encode(is_user_logged_in()); ?>;
            const initialStationUrlFromPHP = '<?php echo esc_js($url); ?>';
            const initialStationIsAdminPinned = <?php echo json_encode($is_admin_pinned_initial); ?>;

            const ldr = document.getElementById('yc-popup-loader'), wrp = document.querySelector('.popup-wrapper'), aud = document.getElementById('popup-radio-player'),
                  src = document.getElementById('popup-radio-source'), sel = document.getElementById('popup-station-selector'),
                  slogo = document.getElementById('popup-station-logo'), sb = document.querySelector('#popup-player-instance .sound-bar'),
                  dLogo = '<?php echo esc_js($default_logo); ?>', siteLogo = '<?php echo esc_js($site_logo_url); ?>',
                  cName = document.getElementById('popup-station-name-current'), cCountry = document.getElementById('popup-station-country-current'),
                  popupNoticeArea = document.getElementById('yc-popup-notice-area');

            let previouslySelectedValidStation = { url: '', logo: '', name: '', country: '', isAdminPinned: false };

            function showPopupNotice(message) {
                if (popupNoticeArea) {
                    popupNoticeArea.textContent = message;
                    popupNoticeArea.style.display = 'block';
                    setTimeout(() => {
                        if (popupNoticeArea) {
                             popupNoticeArea.style.display = 'none';
                             popupNoticeArea.textContent = '';
                        }
                    }, 4000);
                } else {
                    alert(message); // Fallback
                }
            }

            function updSb() { if (!aud || !sb) return; let p = aud.paused || aud.ended || aud.error || !aud.currentSrc || aud.readyState < 2; sb.classList.toggle('paused', p); }
            if (aud) { ['playing','play','pause','ended','error','emptied','stalled','waiting','loadstart','loadedmetadata','canplay','canplaythrough','suspend','abort'].forEach(ev => aud.addEventListener(ev, updSb)); setTimeout(updSb, 250); }
            function rAd() { try { if (typeof adsbygoogle !== 'undefined' && typeof adsbygoogle.push === 'function') (adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) { console.error("YCR AdSense Error:", e); } }

            function loadS(targetUrl, targetLogo, targetName, targetCountry, targetIsAdminPinned) {
                if (!isLoggedIn && !targetIsAdminPinned) {
                    showPopupNotice('Log in or create an account to access more stations.');
                    // Revert to previously selected valid station in dropdown
                    if (sel && previouslySelectedValidStation.url) {
                        for (let i = 0; i < sel.options.length; i++) {
                            if (sel.options[i].value === previouslySelectedValidStation.url) {
                                sel.selectedIndex = i;
                                break;
                            }
                        }
                    } else if (sel) { // If no previously valid, try to find first allowed in dropdown
                         for (let i = 0; i < sel.options.length; i++) {
                            const optAdminPinned = sel.options[i].dataset.isAdminPinned === 'true';
                            if (isLoggedIn || optAdminPinned) {
                                sel.selectedIndex = i;
                                const o = sel.options[i];
                                loadS(o.value, o.dataset.logo, o.dataset.name, o.dataset.country, optAdminPinned); // Recursive call to load the first allowed one
                                return; // Exit after attempting to load first allowed
                            }
                        }
                        // If still no allowed station found in dropdown (e.g. all restricted for logged out user)
                        aud.pause(); src.src = ''; cName.textContent = 'No playable station'; cCountry.textContent = ''; document.title = 'No playable station';
                    }
                    return; // Do not load the new restricted station
                }

                if (aud && src) {
                    src.src = targetUrl; aud.load(); aud.play().catch(e => console.warn("YCR Popup: Autoplay failed.", e));
                    if (slogo) { slogo.src = targetLogo || dLogo; slogo.alt = targetName ? targetName + ' Logo' : 'Station Logo'; }
                    if (cName) cName.textContent = targetName || 'Radio Player'; if (cCountry) cCountry.textContent = targetCountry ? '(' + targetCountry + ')' : '';
                    document.title = (targetName || 'Radio Player') + ' - Popup Player'; setTimeout(rAd, 500);

                    // Update previously selected valid station
                    previouslySelectedValidStation = { url: targetUrl, logo: targetLogo, name: targetName, country: targetCountry, isAdminPinned: targetIsAdminPinned };
                }
            }

            if (sel) sel.addEventListener('change', function() {
                const o = this.options[this.selectedIndex];
                const baseUrl = window.location.origin + window.location.pathname;

                // Build the new URL with query parameters for the selected station
                const newUrl = baseUrl +
                    '?yc_radio_action=popup_player' +
                    '&station_url=' + encodeURIComponent(o.value) +
                    '&station_name=' + encodeURIComponent(o.dataset.name) +
                    '&station_logo=' + encodeURIComponent(o.dataset.logo) +
                    '&station_country=' + encodeURIComponent(o.dataset.country) +
                    '&is_admin_pinned=' + encodeURIComponent(o.dataset.isAdminPinned);

                // Reload the popup page with the new URL to refresh the player and ads
                window.location.href = newUrl;
            });

            // --- Initial Load Logic ---
            const initialName = sel?.options[sel.selectedIndex]?.dataset.name || '<?php echo esc_js($name); ?>';
            const initialLogo = sel?.options[sel.selectedIndex]?.dataset.logo || '<?php echo esc_js($logo); ?>';
            const initialCountry = sel?.options[sel.selectedIndex]?.dataset.country || '<?php echo esc_js($country); ?>';

            // ALWAYS set the visual info first so the user sees what they clicked on.
            if (slogo) { slogo.alt = (initialName || 'Station') + ' Logo'; slogo.src = initialLogo || dLogo; }
            if (cName) cName.textContent = initialName;
            if (cCountry) cCountry.textContent = initialCountry ? '('+initialCountry+')' : '';
            document.title = (initialName || 'Radio Player') + ' - Popup Player';

            // Now, check for permissions and adjust player state and on-screen text if needed.
            if (!isLoggedIn && !initialStationIsAdminPinned && initialStationUrlFromPHP) {
                // Restricted Station Detected: Show the persistent notice, stop the player, and overwrite the station name with a clear message.
                showPopupNotice('Login or register to play this station');
                
                aud.pause();
                src.src = ''; // Stop playback ability
                
                // Overwrite the station name text with your required message. The logo remains visible.
                if (cName) cName.textContent = 'Login or register to play this station'; 
                if (cCountry) cCountry.textContent = ''; // Hide country text to make it cleaner
                document.title = 'Login Required - Popup Player';

            } else if (initialStationUrlFromPHP) {
                // Allowed Station: This is the normal flow. Set the valid station data for future reference.
                previouslySelectedValidStation = {
                    url: initialStationUrlFromPHP,
                    logo: initialLogo,
                    name: initialName,
                    country: initialCountry,
                    isAdminPinned: initialStationIsAdminPinned
                };
                
            } else {
                // Fallback for when no station was loaded via the URL.
                if (cName) cName.textContent = 'No station selected';
                document.title = 'No station selected - Popup Player';
            }


            function onRdy() {
                if (ldr) ldr.style.display = 'none'; if (wrp) wrp.style.visibility = 'visible';
                if (siteLogo) { const h = document.querySelector('.popup-header'); if (h) h.style.display = 'block'; } updSb();
                if (typeof adsbygoogle !== 'undefined' && typeof adsbygoogle.push === 'function' ) { if (window.adsbygoogle && adsbygoogle.loaded) rAd(); else { window.addEventListener('adsbygoogle.loaded', rAd, { once: true }); setTimeout(rAd, 2500); }} else setTimeout(rAd, 2500);
            }
            if (document.readyState === 'complete' || (document.readyState !== 'loading' && !document.documentElement.doScroll)) onRdy(); else document.addEventListener('DOMContentLoaded', onRdy);
            setTimeout(function() { if (ldr && ldr.style.display !== 'none') ldr.style.display = 'none'; if (wrp && wrp.style.visibility !== 'visible') wrp.style.visibility = 'visible'; }, 3500);
        });
        </script>
    </body>
    </html>
    <?php
}

// --- TAXONOMY AJAX HANDLERS ---
add_action('wp_ajax_ycr_add_taxonomy_term', 'ycr_ajax_add_taxonomy_term');
function ycr_ajax_add_taxonomy_term() {
    $tax_key_raw = isset($_POST['taxonomy_key']) ? wp_unslash($_POST['taxonomy_key']) : '';
    $nonce_value_raw = isset($_POST['nonce_field_add']) ? wp_unslash($_POST['nonce_field_add']) : '';
    $term_name_raw = isset($_POST['term_name']) ? trim(wp_unslash($_POST['term_name'])) : '';

    $tax_key = sanitize_key($tax_key_raw);
    $nonce_value = sanitize_text_field($nonce_value_raw);

    $valid_tax_keys = ['country', 'language'];
    if ( !in_array( $tax_key, $valid_tax_keys, true ) ) {
        wp_send_json_error( ['message' => 'Invalid taxonomy key specified. Received: ' . esc_html($tax_key_raw) ], 400 );
        return;
    }

    $expected_nonce_action = 'ycr_add_term_' . $tax_key;
    if ( empty($nonce_value) || !wp_verify_nonce( $nonce_value, $expected_nonce_action ) ) {
        wp_send_json_error( ['message' => 'ADD: Security check failed (nonce). Expected action: ' . esc_html($expected_nonce_action) .'. Received nonce value: '. esc_html($nonce_value) ], 403 );
        return;
    }

    if ( !current_user_can('manage_options') ) {
        wp_send_json_error( ['message' => 'You do not have permission to perform this action.'], 403 );
        return;
    }

    $name = sanitize_text_field( $term_name_raw );
    if ( empty($name) ) {
        wp_send_json_error( ['message' => 'Term name cannot be empty.'], 400 );
        return;
    }

    $option_name_suffix = ($tax_key === 'country') ? 'countries' : 'languages';
    $opt = 'radio_player_' . $option_name_suffix;

    $terms = get_option($opt, []);
    if (!is_array($terms)) $terms = [];

    if (in_array($name, $terms, true)) {
        wp_send_json_error(['message' => ucfirst($tax_key) . ' term "' . esc_html($name) . '" already exists.'], 409);
        return;
    }

    $terms[] = $name;
    sort($terms);
    update_option($opt, $terms);

    $new_term_name = $name;
    $new_nonce_edit = wp_create_nonce('ycr_edit_term_' . $tax_key . '_' . $new_term_name);
    $new_nonce_delete = wp_create_nonce('ycr_delete_term_' . $tax_key . '_' . $new_term_name);

    wp_send_json_success([
        'term' => $new_term_name,
        'message' => ucfirst($tax_key) . ' "' . esc_html($new_term_name) . '" added successfully.',
        'new_nonce_edit' => $new_nonce_edit,
        'new_nonce_delete' => $new_nonce_delete
    ]);
}

add_action('wp_ajax_ycr_edit_taxonomy_term', 'ycr_ajax_edit_taxonomy_term');
function ycr_ajax_edit_taxonomy_term() {
    $tax_key_raw = isset($_POST['taxonomy_key']) ? wp_unslash($_POST['taxonomy_key']) : '';
    $nonce_value_raw = isset($_POST['nonce_field_edit']) ? wp_unslash($_POST['nonce_field_edit']) : '';
    $old_term_name_raw = isset($_POST['old_term_name']) ? trim(wp_unslash($_POST['old_term_name'])) : '';
    $new_term_name_raw = isset($_POST['new_term_name']) ? trim(wp_unslash($_POST['new_term_name'])) : '';

    $tax_key = sanitize_key($tax_key_raw);
    $nonce_value = sanitize_text_field($nonce_value_raw);
    $old_name = sanitize_text_field( $old_term_name_raw );

    $valid_tax_keys = ['country', 'language'];
    if ( !in_array( $tax_key, $valid_tax_keys, true ) ) {
        wp_send_json_error( ['message' => 'Invalid taxonomy key specified. Received: ' . esc_html($tax_key_raw)], 400 );
        return;
    }
    if ( empty($old_name) ) {
        wp_send_json_error( ['message' => 'Old term name is missing or invalid.'], 400 );
        return;
    }

    $expected_nonce_action = 'ycr_edit_term_' . $tax_key . '_' . $old_name;
    if ( empty($nonce_value) || !wp_verify_nonce( $nonce_value, $expected_nonce_action ) ) {
        wp_send_json_error( ['message' => 'EDIT: Security check failed (nonce). Expected action: ' . esc_html($expected_nonce_action) .'. Received nonce value: '. esc_html($nonce_value) ], 403 );
        return;
    }

    if ( !current_user_can('manage_options') ) {
        wp_send_json_error( ['message' => 'You do not have permission to perform this action.'], 403 );
        return;
    }

    $new_name = sanitize_text_field( $new_term_name_raw );
    if ( empty($new_name) ) {
        wp_send_json_error( ['message' => 'New term name cannot be empty.'], 400 );
        return;
    }

    if ( $old_name === $new_name ) {
        wp_send_json_success([
            'message' => 'No changes made as old and new names are the same.',
            'old_term' => $old_name,
            'new_term' => $new_name
        ]);
        return;
    }

    $option_name_suffix = ($tax_key === 'country') ? 'countries' : 'languages';
    $opt = 'radio_player_' . $option_name_suffix;

    $terms = get_option($opt, []);
    if (!is_array($terms)) $terms = [];

    if (!in_array($old_name, $terms, true)) {
        wp_send_json_error(['message' => 'Old term "' . esc_html($old_name) . '" not found in the list.'], 404);
        return;
    }

    $temp_terms_for_check = array_filter($terms, function($t) use ($old_name) {
        return $t !== $old_name;
    });
    if (in_array($new_name, $temp_terms_for_check, true)) {
        wp_send_json_error(['message' => 'The new ' . strtolower($tax_key) . ' name "' . esc_html($new_name) . '" already exists.'], 409);
        return;
    }

    $terms = array_map(function($t) use ($old_name, $new_name) {
        return $t === $old_name ? $new_name : $t;
    }, $terms);
    sort($terms);
    update_option($opt, $terms);

    $stations = get_option('radio_player_stations', []);
    $stations_updated = false;
    if (is_array($stations)) {
        foreach ($stations as $index => $station_item) {
            if (isset($station_item[$tax_key]) && $station_item[$tax_key] === $old_name) {
                $stations[$index][$tax_key] = $new_name;
                $stations_updated = true;
            }
        }
        if ($stations_updated) {
            update_option('radio_player_stations', $stations);
        }
    }

    $updated_term_name = $new_name;
    $updated_nonce_edit = wp_create_nonce('ycr_edit_term_' . $tax_key . '_' . $updated_term_name);
    $updated_nonce_delete = wp_create_nonce('ycr_delete_term_' . $tax_key . '_' . $updated_term_name);

    wp_send_json_success([
        'old_term' => $old_name,
        'new_term' => $updated_term_name,
        'message' => ucfirst($tax_key) . ' "' . esc_html($old_name) . '" updated to "' . esc_html($updated_term_name) . '" successfully.',
        'new_nonce_edit' => $updated_nonce_edit,
        'new_nonce_delete' => $updated_nonce_delete
    ]);
}

add_action('wp_ajax_ycr_delete_taxonomy_term', 'ycr_ajax_delete_taxonomy_term');
function ycr_ajax_delete_taxonomy_term() {
    $tax_key_raw = isset($_POST['taxonomy_key']) ? wp_unslash($_POST['taxonomy_key']) : '';
    $nonce_value_raw = isset($_POST['nonce_field_delete']) ? wp_unslash($_POST['nonce_field_delete']) : '';
    $term_to_delete_raw = isset($_POST['term_name']) ? trim(wp_unslash($_POST['term_name'])) : '';

    $tax_key = sanitize_key($tax_key_raw);
    $nonce_value = sanitize_text_field($nonce_value_raw);
    $term_del = sanitize_text_field( $term_to_delete_raw );

    $valid_tax_keys = ['country', 'language'];
    if ( !in_array( $tax_key, $valid_tax_keys, true ) ) {
        wp_send_json_error( ['message' => 'Invalid taxonomy key specified. Received: ' . esc_html($tax_key_raw) ], 400 );
        return;
    }
    if ( empty($term_del) ) {
        wp_send_json_error( ['message' => 'Term name to delete is missing or invalid.'], 400 );
        return;
    }

    $expected_nonce_action = 'ycr_delete_term_' . $tax_key . '_' . $term_del;
    if ( empty($nonce_value) || !wp_verify_nonce( $nonce_value, $expected_nonce_action ) ) {
        wp_send_json_error( ['message' => 'DELETE: Security check failed (nonce). Expected action: ' . esc_html($expected_nonce_action) .'. Received nonce value: '. esc_html($nonce_value) ], 403 );
        return;
    }

    if ( !current_user_can('manage_options') ) {
        wp_send_json_error( ['message' => 'You do not have permission to perform this action.'], 403 );
        return;
    }

    $option_name_suffix = ($tax_key === 'country') ? 'countries' : 'languages';
    $opt = 'radio_player_' . $option_name_suffix;

    $terms = get_option($opt, []);
    if (!is_array($terms)) $terms = [];

    if (!in_array($term_del, $terms, true)) {
        wp_send_json_error(['message' => ucfirst($tax_key) . ' term "' . esc_html($term_del) . '" to delete not found.'], 404);
        return;
    }

    $terms = array_values(array_filter($terms, function($t) use ($term_del) {
        return $t !== $term_del;
    }));
    update_option($opt, $terms);

    $stations = get_option('radio_player_stations', []);
    $stations_updated = false;
    if (is_array($stations)) {
        foreach ($stations as $index => $station_item) {
            if (isset($station_item[$tax_key]) && $station_item[$tax_key] === $term_del) {
                $stations[$index][$tax_key] = '';
                $stations_updated = true;
            }
        }
        if ($stations_updated) {
            update_option('radio_player_stations', $stations);
        }
    }

    wp_send_json_success(['term' => $term_del, 'message' => ucfirst($tax_key) . ' "' . esc_html($term_del) . '" deleted successfully.']);
}

// --- STATION AJAX HANDLERS ---
add_action('wp_ajax_yc_save_single_station', 'ycr_ajax_save_single_station_handler');
if (!function_exists('ycr_ajax_save_single_station_handler')) {
    function ycr_ajax_save_single_station_handler() {
        check_ajax_referer('yc_save_station_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
            return;
        }

        $all_stations_option = get_option('radio_player_stations', []);
        if (!is_array($all_stations_option)) {
            $all_stations_option = [];
        }

        $station_data_from_post = isset($_POST['station_data']) && is_array($_POST['station_data']) ? $_POST['station_data'] : [];

        // ycr_sanitize_stations_callback expects an array of stations, so wrap it
        $sanitized_station_array_wrapper = ycr_sanitize_stations_callback([$station_data_from_post]);
        if (empty($sanitized_station_array_wrapper) || !is_array($sanitized_station_array_wrapper[0])) {
             wp_send_json_error(['message' => 'Invalid station data provided.'], 400);
            return;
        }
        $station_data_sanitized = $sanitized_station_array_wrapper[0];


        $original_db_id = isset($_POST['db_id']) ? intval($_POST['db_id']) : -1; // This is the original index in the option array
        $target_visual_index = isset($_POST['visual_index']) ? intval($_POST['visual_index']) : 0; // This is the desired new index after potential reordering

        if (empty($station_data_sanitized['name']) || empty($station_data_sanitized['url'])) {
            wp_send_json_error(['message' => 'Station Name and Stream URL are required.'], 400);
            return;
        }

        $master_countries = get_option('radio_player_countries', []);
        if (!is_array($master_countries)) $master_countries = [];
        $master_languages = get_option('radio_player_languages', []);
        if (!is_array($master_languages)) $master_languages = [];

        if (!empty($station_data_sanitized['country']) && !in_array($station_data_sanitized['country'], $master_countries)) {
            $master_countries[] = $station_data_sanitized['country'];
            sort($master_countries);
            update_option('radio_player_countries', $master_countries);
        }
        if (!empty($station_data_sanitized['language']) && !in_array($station_data_sanitized['language'], $master_languages)) {
            $master_languages[] = $station_data_sanitized['language'];
            sort($master_languages);
            update_option('radio_player_languages', $master_languages);
        }

        $saved_station_actual_index = -1;
        $current_stations_array = $all_stations_option;

        if ($original_db_id == -1) { // New station
            $station_data_sanitized['added_timestamp'] = time();
            // Add to the beginning (or specified target_visual_index, though for new it's often prepended)
            array_splice($current_stations_array, $target_visual_index, 0, [$station_data_sanitized]);
            $saved_station_actual_index = $target_visual_index; // The new actual index
        } else { // Existing station
            if (isset($current_stations_array[$original_db_id])) {
                $item_to_update_and_move = $current_stations_array[$original_db_id];
                
                // Preserve existing 'added_timestamp' if it exists, otherwise set it (should exist from previous save)
                if (!isset($item_to_update_and_move['added_timestamp'])) {
                    $item_to_update_and_move['added_timestamp'] = time();
                }
                // Merge new data, ensuring 'added_timestamp' is preserved from $item_to_update_and_move
                $station_data_sanitized['added_timestamp'] = $item_to_update_and_move['added_timestamp'];
                $item_to_update_and_move = $station_data_sanitized; // Now $item_to_update_and_move has all merged data

                // Remove from old position
                array_splice($current_stations_array, $original_db_id, 1);
                // Insert at new visual position
                array_splice($current_stations_array, $target_visual_index, 0, [$item_to_update_and_move]);
                $saved_station_actual_index = $target_visual_index; // The new actual index
            } else {
                wp_send_json_error(['message' => 'Original station to update not found (ID: ' . $original_db_id . ').'], 404);
                return;
            }
        }

        $final_stations_array = array_values($current_stations_array); // Re-index numerically
        update_option('radio_player_stations', $final_stations_array);

        wp_send_json_success([
            'message' => 'Station saved successfully!',
            'new_db_id' => $saved_station_actual_index, // This is the new actual index in the saved option
            'master_countries' => $master_countries,
            'master_languages' => $master_languages,
        ]);
    }
}

add_action('wp_ajax_yc_delete_single_station', 'ycr_ajax_delete_single_station_handler');
if (!function_exists('ycr_ajax_delete_single_station_handler')) {
    function ycr_ajax_delete_single_station_handler() {
        check_ajax_referer('yc_delete_station_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
            return;
        }

        $db_id_to_delete = isset($_POST['db_id_to_delete']) ? intval($_POST['db_id_to_delete']) : -1;

        if ($db_id_to_delete < 0) {
            wp_send_json_error(['message' => 'Invalid station ID.'], 400);
            return;
        }

        $stations = get_option('radio_player_stations', []);
        if (!is_array($stations)) {
            $stations = [];
        }

        if (isset($stations[$db_id_to_delete])) {
            array_splice($stations, $db_id_to_delete, 1);
            $stations = array_values($stations); // Re-index
            update_option('radio_player_stations', $stations);
            wp_send_json_success(['message' => 'Station deleted.']);
        } else {
            wp_send_json_error(['message' => 'Station not found for deletion.'], 404);
        }
    }
}