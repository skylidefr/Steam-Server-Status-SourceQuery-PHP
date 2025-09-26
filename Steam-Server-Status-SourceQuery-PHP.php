<?php
/*
Plugin Name: Steam Server Status SourceQuery PHP
Description: Affiche le nombre de joueurs connectés sur un ou plusieurs serveurs Steam avec personnalisation avancée des couleurs, bordures, police et taille du texte. Intègre un système de mise à jour via GitHub.
Version: 1.1
Author: Skylide
*/

if (!defined('ABSPATH')) exit;

require __DIR__ . '/SourceQuery/bootstrap.php';
use xPaw\SourceQuery\SourceQuery;

/* ---------------- ADMIN MENU & SETTINGS ---------------- */
add_action('admin_menu', 'steam_status_menu');
function steam_status_menu() {
    add_options_page('Steam Server Status', 'Steam Status', 'manage_options', 'steam-status', 'steam_status_settings_page');
}

add_action('admin_init', 'steam_status_register_settings');
function steam_status_register_settings() {
    register_setting('steam_status_options_group', 'steam_servers');
    register_setting('steam_status_options_group', 'steam_show_name');
    register_setting('steam_status_options_group', 'steam_cache_duration');

    // Textes personnalisables
    register_setting('steam_status_options_group', 'steam_text_offline');
    register_setting('steam_status_options_group', 'steam_text_no_servers');
    register_setting('steam_status_options_group', 'steam_text_not_found');
    register_setting('steam_status_options_group', 'steam_text_players');
    register_setting('steam_status_options_group', 'steam_text_separator');
    register_setting('steam_status_options_group', 'steam_text_no_players');

    // Couleurs et style
    register_setting('steam_status_options_group', 'steam_use_text_colors');
    register_setting('steam_status_options_group', 'steam_use_border_colors');
    register_setting('steam_status_options_group', 'steam_color_text_online');
    register_setting('steam_status_options_group', 'steam_color_text_offline');
    register_setting('steam_status_options_group', 'steam_color_border_online');
    register_setting('steam_status_options_group', 'steam_color_border_offline');

    // Police
    register_setting('steam_status_options_group', 'steam_font_family');
    register_setting('steam_status_options_group', 'steam_font_size');

    // Shortcode display default
    register_setting('steam_status_options_group', 'steam_all_display_default');
}

add_action('admin_enqueue_scripts', 'steam_status_admin_assets');
function steam_status_admin_assets($hook) {
    if ($hook !== 'settings_page_steam-status') return;
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    wp_add_inline_script('wp-color-picker', "
        jQuery(document).ready(function($){
            $('.steam-color-field').wpColorPicker();
            function attachRemoveEvents(){
                $('.remove-server').off('click').on('click', function(){
                    $(this).closest('tr').remove();
                });
            }
            attachRemoveEvents();
            $('#add-server').on('click', function(){
                var table = $('#steam-servers-table tbody');
                var index = table.find('tr').length;
                var row = '<tr>' +
                    '<td><input type=\"text\" name=\"steam_servers['+index+'][name]\" placeholder=\"Nom du serveur\"></td>' +
                    '<td><input type=\"text\" name=\"steam_servers['+index+'][ip]\" placeholder=\"45.90.160.141\"></td>' +
                    '<td><input type=\"number\" name=\"steam_servers['+index+'][port]\" placeholder=\"27015\"></td>' +
                    '<td><button type=\"button\" class=\"button remove-server\">❌</button></td>' +
                '</tr>';
                table.append(row);
                attachRemoveEvents();
            });
        });
    ");
}

/* ---------------- SETTINGS PAGE ---------------- */
function steam_status_settings_page() {
    $servers = get_option('steam_servers', []);
    if (!is_array($servers)) $servers = [];

    $show_name = get_option('steam_show_name', 1);
    $cache_duration = intval(get_option('steam_cache_duration', 15));

    $text_offline = get_option('steam_text_offline','Serveur injoignable');
    $text_no_servers = get_option('steam_text_no_servers','⚠️ Aucun serveur configuré');
    $text_not_found = get_option('steam_text_not_found','⚠️ Serveur introuvable');
    $text_players = get_option('steam_text_players','Joueurs connectés :');
    $text_separator = get_option('steam_text_separator','/');
    $text_no_players = get_option('steam_text_no_players','Aucun joueur en ligne');

    $use_text_colors = get_option('steam_use_text_colors',1);
    $use_border_colors = get_option('steam_use_border_colors',1);

    $color_text_online = get_option('steam_color_text_online','#2ecc71');
    $color_text_offline = get_option('steam_color_text_offline','#e74c3c');
    $color_border_online = get_option('steam_color_border_online','#2ecc71');
    $color_border_offline = get_option('steam_color_border_offline','#e74c3c');

    $font_family = get_option('steam_font_family','Arial, sans-serif');
    $font_size = intval(get_option('steam_font_size', 14));

    $all_display_default = get_option('steam_all_display_default','table');

    // ... le reste de la page reste inchangé ...
}

/* ---------------- FRONTEND STYLES ---------------- */
add_action('wp_head', 'steam_status_front_styles');
function steam_status_front_styles() {
    $font_family = get_option('steam_font_family','Arial, sans-serif');
    $font_size = intval(get_option('steam_font_size', 14));
    $use_text_colors = get_option('steam_use_text_colors',1);
    $use_border_colors = get_option('steam_use_border_colors',1);

    $color_text_online = get_option('steam_color_text_online','#2ecc71');
    $color_text_offline = get_option('steam_color_text_offline','#e74c3c');
    $color_border_online = get_option('steam_color_border_online','#2ecc71');
    $color_border_offline = get_option('steam_color_border_offline','#e74c3c');

    $border_online = $use_border_colors ? "border:1px solid {$color_border_online};" : "border:none;";
    $border_offline = $use_border_colors ? "border:1px solid {$color_border_offline};" : "border:none;";
    $color_online = $use_text_colors ? $color_text_online : "inherit";
    $color_offline = $use_text_colors ? $color_text_offline : "inherit";

    echo "<style>
    .steam-status{ font-family:{$font_family}; font-size:{$font_size}px; padding:10px; border-radius:6px; display:inline-block; margin:6px; }
    .steam-status.online{ {$border_online} color:{$color_online}; background:rgba(0,0,0,0.03); }
    .steam-status.offline{ {$border_offline} color:{$color_offline}; background:rgba(0,0,0,0.02); }
    .steam-status .server-name{ font-weight:700; display:block; margin-bottom:4px; }
    .steam-status .players,.steam-status .maxplayers{ font-weight:600; margin:0 4px; }
    .steam-status-table{ width:100%; border-collapse:collapse; }
    .steam-status-table th,.steam-status-table td{ padding:8px 10px; border:1px solid #ddd; text-align:left; }
    .steam-card{ display:inline-block; vertical-align:top; width:280px; margin:6px; }
    </style>";
}

/* ---------------- HELPERS & SHORTCODES ---------------- */
// ... Reste du code de helpers et shortcodes inchangé ...

/* ---------------- GITHUB UPDATER ---------------- */
if (!class_exists('GitHubPluginUpdater')) {
    class GitHubPluginUpdater {
        private $slug;
        private $proper_folder_name;
        private $github_url;
        private $version;

        public function __construct($config) {
            $this->slug = $config['slug'];
            $this->proper_folder_name = $config['proper_folder_name'];
            $this->github_url = $config['github_url'];
            $this->version = $config['version'];

            add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
            add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        }

        public function check_update($transient) {
            if (empty($transient->checked)) return $transient;

            $remote = $this->get_remote_info();
            if (!$remote) return $transient;

            $remote_version = $remote['tag_name'];
            if (version_compare($this->version, $remote_version, '<')) {
                $obj = new stdClass();
                $obj->slug = $this->slug;
                $obj->new_version = $remote_version;
                $obj->url = $this->github_url;
                $obj->package = $remote['zipball_url'];

                $transient->response[$this->slug] = $obj;
            }

            return $transient;
        }

        public function plugin_info($false, $action, $args) {
            if ($args->slug != $this->slug) return false;

            $remote = $this->get_remote_info();
            if (!$remote) return false;

            $obj = new stdClass();
            $obj->name = $remote['name'];
            $obj->slug = $this->slug;
            $obj->version = $remote['tag_name'];
            $obj->author = 'Skylide';
            $obj->homepage = $this->github_url;
            $obj->requires = '5.0';
            $obj->tested = '6.4';
            $obj->download_link = $remote['zipball_url'];
            $obj->sections = ['Description' => $remote['body']];

            return $obj;
        }

        private function get_remote_info() {
            $url = "https://api.github.com/repos/skylidefr/Steam-Server-Status-SourceQuery-PHP/releases/latest";
            $request = wp_remote_get($url, ['headers' => ['User-Agent' => 'WordPress']]);
            if (is_wp_error($request)) return false;

            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);
            return $data ? $data : false;
        }
    }
}

if (is_admin()) {
    new GitHubPluginUpdater([
        'slug' => plugin_basename(__FILE__),
        'proper_folder_name' => 'Steam-Server-Status-SourceQuery-PHP',
        'github_url' => 'https://github.com/skylidefr/Steam-Server-Status-SourceQuery-PHP',
        'version' => '1.1'
    ]);
}
