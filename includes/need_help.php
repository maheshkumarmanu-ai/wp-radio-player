<?php
/**
 * YemCoders Radio Player - Need Help? Page Content
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure this function is defined globally if not already
if ( ! function_exists( 'ycr_render_actual_need_help_content' ) ) {
    function ycr_render_actual_need_help_content() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <div class="wrap ycr-need-help-page">
            <h1><span class="dashicons dashicons-editor-help" style="font-size: 1.3em; margin-right: 5px; vertical-align: middle;"></span>Radio Player - Need Help?</h1>
            <p>Welcome to the YemCoders Radio Player help section. Here you'll find information on how to use the plugin and troubleshoot common issues.</p>

            <hr>

            <h2><span class="dashicons dashicons-shortcode" style="vertical-align: sub;"></span> Using the Shortcode</h2>
            <p>To display the radio player on any post or page, simply use the following shortcode:</p>
            <pre>[custom_radio_player]</pre>
            <p>This will output the player, the station grid (initially showing recently added/featured stations), search functionality, language filters (if not pre-filtered), and a "Popup Player" button.</p>

            <h3>Filtering Stations with Shortcode Attributes:</h3>
            <p>You can display a player pre-filtered by language and/or country using attributes:</p>
            <ul>
                <li><strong>Filter by Language:</strong>
                    <pre>[custom_radio_player language="English"]</pre>
                    <p><em>Replace "English" with the desired language name (as defined in "Manage Taxonomies").</em></p>
                </li>
                <li><strong>Filter by Country:</strong>
                    <pre>[custom_radio_player country="USA"]</pre>
                    <p><em>Replace "USA" with the desired country name (as defined in "Manage Taxonomies").</em></p>
                </li>
                <li><strong>Filter by Both Language and Country:</strong>
                    <pre>[custom_radio_player language="Spanish" country="Spain"]</pre>
                </li>
            </ul>
            <p>When using these attributes, the "Browse Stations" section will only show stations matching the criteria. If <code>language</code> is specified, the frontend language filter buttons will be hidden for that specific player instance.</p>


            <hr>

            <h2><span class="dashicons dashicons-admin-settings" style="vertical-align: sub;"></span> Plugin Settings</h2>
            <p>You can manage all aspects of the radio player from the "Radio Stations" menu in your WordPress admin dashboard:</p>
            <ul>
                <li><strong>Radio Stations > Radio Player Settings:</strong>
                    <ul>
                        <li>Manage individual radio stations (add, edit, reorder, delete, set logo, country, language, pin as featured).</li>
                        <li>Configure default player settings like the default station, recently added limits, frontend pagination, and stations per row.</li>
                        <li>Customize the login button behavior for popups.</li>
                        <li>Add a disclaimer message.</li>
                        <li>Add custom CSS to style the player.</li>
                    </ul>
                </li>
                <li><strong>Radio Stations > Manage Taxonomies:</strong>
                    <ul>
                        <li>Add, edit, and delete global Countries and Languages. These will be available in the dropdowns when managing stations and for shortcode filtering.</li>
                    </ul>
                </li>
            </ul>

            <hr>

            <h2><span class="dashicons dashicons-admin-users" style="vertical-align: sub;"></span> User Features</h2>
            <ul>
                <li><strong>My Favorite Stations:</strong> Logged-in users can pin their favorite stations by clicking the heart icon (<i class="far fa-heart" style="color: #ff4500;"></i>). Their selections are saved and displayed in a dedicated "My Favorite Stations" section.</li>
                <li><strong>Popup Player:</strong> A "Popup Player" button allows users to open the player in a separate, smaller window, ideal for continuous listening while browsing other sites.</li>
                <li><strong>Search & Filter:</strong> Users can search for stations by name, country, or language. If the player isn't pre-filtered by a shortcode language attribute, language filter buttons will also be available.</li>
            </ul>

            <hr>

            <h2><span class="dashicons dashicons-sos" style="vertical-align: sub;"></span> Troubleshooting / Common Issues</h2>
            <ul>
                <li><strong>Player doesn't play a specific station:</strong>
                    <ul>
                        <li>Ensure the Stream URL is correct and active. Test it directly in a browser or VLC media player.</li>
                        <li>Some streams might be HTTP and your site is HTTPS (or vice-versa), causing mixed content issues. Modern browsers often block insecure audio streams on secure pages. Ensure your stream URLs use HTTPS if your site does.</li>
                        <li>The stream format might not be universally supported (e.g., some AAC+ streams might have issues in certain browsers). MP3 streams are generally the most compatible.</li>
                    </ul>
                </li>
                <li><strong>Station logos not appearing:</strong>
                    <ul>
                        <li>Verify the logo URL is correct and the image is accessible.</li>
                        <li>Check for any console errors in your browser's developer tools related to image loading.</li>
                    </ul>
                </li>
                <li><strong>Shortcode filters not working as expected:</strong>
                    <ul>
                        <li>Ensure the language or country name used in the shortcode attribute (e.g., <code>language="English"</code>) exactly matches the term defined in "Manage Taxonomies" (it is case-insensitive for filtering but good practice to match).</li>
                        <li>Verify that stations actually exist with the specified language/country.</li>
                    </ul>
                </li>
                <li><strong>Changes not reflecting immediately:</strong>
                    <ul>
                        <li>Try clearing your website's cache (plugin cache, server cache) and your browser cache.</li>
                    </ul>
                </li>
                <li><strong>Styling issues:</strong>
                    <ul>
                        <li>Your theme's CSS might be overriding the plugin's styles. You can use the "Custom CSS" section in the plugin settings to add more specific CSS rules, or use your browser's developer tools to inspect elements and identify conflicting styles.</li>
                    </ul>
                </li>
            </ul>

            <hr>

            <h2><span class="dashicons dashicons-email-alt" style="vertical-align: sub;"></span> Contact & Support</h2>
            <p>If you encounter any issues or have questions that are not covered here, please feel free to reach out to the plugin author, Mahesh Kumar, through the WordPress.org support forums for this plugin or via his contact details if provided on the plugin page.</p>
            <p>Thank you for using YemCoders Radio Player!</p>

            <style>
                .ycr-need-help-page h1, .ycr-need-help-page h2, .ycr-need-help-page h3 { margin-bottom: 0.5em; }
                .ycr-need-help-page h2 { margin-top: 1.5em; padding-bottom: 0.3em; border-bottom: 1px solid #ddd;}
                .ycr-need-help-page h3 { margin-top: 1em; font-size: 1.17em; }
                .ycr-need-help-page ul { list-style: disc; margin-left: 20px; margin-bottom: 1em; }
                .ycr-need-help-page ul ul { list-style: circle; margin-top: 5px; }
                .ycr-need-help-page ul li p { margin-top: 0.2em; margin-bottom: 0.5em; }
                .ycr-need-help-page pre {
                    background-color: #f5f5f5;
                    padding: 15px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    white-space: pre-wrap;
                    word-break: break-all;
                    margin-top: 0.5em;
                    margin-bottom: 0.5em;
                }
                .ycr-need-help-page hr { margin: 25px 0; border: 0; border-top: 1px solid #eee; }
            </style>
        </div>
        <?php
    }
}
?>