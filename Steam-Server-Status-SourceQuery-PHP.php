<?php
/*
Plugin Name: Steam Server Status SourceQuery PHP
Description: Affiche le nombre de joueurs connectés sur un ou plusieurs serveurs Steam avec personnalisation avancée.
Version: 1.2
Author: Skylide
GitHub Plugin URI: skylidefr/Steam-Server-Status-SourceQuery-PHP
GitHub Branch: main
*/

if (!defined('ABSPATH')) exit;

require __DIR__ . '/SourceQuery/bootstrap.php';
use xPaw\SourceQuery\SourceQuery;

// Système de mise à jour GitHub
add_action('init', 'steam_status_github_updater_init');
function steam_status_github_updater_init() {
    if (is_admin()) {
        new SteamStatusGitHubUpdater(__FILE__);
    }
}

class SteamStatusGitHubUpdater {
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $github_user;
    private $github_repo;
    private $github_branch;
    private $github_response;

    public function __construct($file) {
        $this->file = $file;
        error_log("Steam Plugin Debug - Constructor called");
        add_action('admin_init', array($this, 'set_plugin_properties'));
        return $this;
    }

    public function set_plugin_properties() {
        error_log("Steam Plugin Debug - set_plugin_properties called");
        
        $this->plugin = get_plugin_data($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active = is_plugin_active($this->basename);
        
        error_log("Steam Plugin Debug - Plugin data: " . print_r($this->plugin, true));
        error_log("Steam Plugin Debug - Basename: " . $this->basename);
        
        // Lire le fichier directement pour extraire les headers personnalisés
        $plugin_content = file_get_contents($this->file);
        preg_match('/GitHub Plugin URI:\s*(.+)/', $plugin_content, $github_matches);
        preg_match('/GitHub Branch:\s*(.+)/', $plugin_content, $branch_matches);
        
        $github_uri = isset($github_matches[1]) ? trim($github_matches[1]) : '';
        $parts = explode('/', $github_uri);
        
        error_log("Steam Plugin Debug - GitHub URI: " . $github_uri);
        
        if (count($parts) >= 2) {
            $this->github_user = trim($parts[0]);
            $this->github_repo = trim($parts[1]);
        }
        
        $this->github_branch = isset($branch_matches[1]) ? trim($branch_matches[1]) : 'main';
        
        error_log("Steam Plugin Debug - GitHub User: " . $this->github_user);
        error_log("Steam Plugin Debug - GitHub Repo: " . $this->github_repo);
        
        if ($this->github_user && $this->github_repo) {
            error_log("Steam Plugin Debug - Adding filters");
            
            add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
            add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
            add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        } else {
            error_log("Steam Plugin Debug - GitHub info missing, filters not added");
        }
    }

    public function modify_transient($transient) {
        if (property_exists($transient, 'checked')) {
            if ($checked = $transient->checked) {
                $this->get_repository_info();
                $new_version = $this->get_new_version();
                
                // Correction: utiliser la version du plugin directement si pas dans $checked
                $current_version = isset($checked[$this->basename]) ? $checked[$this->basename] : $this->plugin['Version'];
                $out_of_date = version_compare($new_version, $current_version, 'gt');
                
                // Debug temporaire
                error_log("Steam Plugin Debug - Current: $current_version, New: $new_version, Out of date: " . ($out_of_date ? 'YES' : 'NO'));
                error_log("Steam Plugin Debug - GitHub Response: " . print_r($this->github_response, true));
                
                if ($out_of_date && $new_version) {
                    $new_files = $this->get_zip_url();
                    $transient->response[$this->basename] = (object) array(
                        'new_version' => $new_version,
                        'slug' => current(explode('/', $this->basename)),
                        'url' => $this->plugin['PluginURI'],
                        'package' => $new_files
                    );
                    error_log("Steam Plugin Debug - Update added to transient");
                }
            }
        }
        return $transient;
    }

    public function plugin_popup($res, $action, $args) {
        if (!empty($args->slug)) {
            if ($args->slug == current(explode('/', $this->basename))) {
                $this->get_repository_info();
                
                $res = (object) array(
                    'name' => $this->plugin['Name'],
                    'slug' => $this->basename,
                    'version' => $this->get_new_version(),
                    'author' => $this->plugin['AuthorName'],
                    'author_profile' => $this->plugin['AuthorURI'],
                    'last_updated' => $this->get_date(),
                    'homepage' => $this->plugin['PluginURI'],
                    'short_description' => $this->plugin['Description'],
                    'sections' => array(
                        'Description' => $this->plugin['Description'],
                        'Updates' => $this->get_changelog(),
                    ),
                    'download_link' => $this->get_zip_url()
                );
                return $res;
            }
        }
        return $res;
    }

    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;
        
        $install_directory = plugin_dir_path($this->file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;
        
        if ($this->active) {
            activate_plugin($this->basename);
        }
        
        return $result;
    }

    private function get_repository_info() {
        if (is_null($this->github_response)) {
            $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->github_user, $this->github_repo);
            error_log("Steam Plugin Debug - Request URI: " . $request_uri);
            
            $response = wp_remote_get($request_uri);
            
            if (is_wp_error($response)) {
                error_log("Steam Plugin Debug - WP Error: " . $response->get_error_message());
            } elseif (wp_remote_retrieve_response_code($response) === 200) {
                $this->github_response = json_decode(wp_remote_retrieve_body($response), true);
                error_log("Steam Plugin Debug - GitHub API Success");
            } else {
                error_log("Steam Plugin Debug - HTTP Error: " . wp_remote_retrieve_response_code($response));
            }
        }
    }

    private function get_new_version() {
        $version = $this->get_github_data();
        return !empty($version['tag_name']) ? ltrim($version['tag_name'], 'v') : false;
    }

    private function get_zip_url() {
        $version = $this->get_github_data();
        return !empty($version['zipball_url']) ? $version['zipball_url'] : false;
    }

    private function get_date() {
        $version = $this->get_github_data();
        return !empty($version['published_at']) ? date('Y-m-d', strtotime($version['published_at'])) : false;
    }

    private function get_changelog() {
        $version = $this->get_github_data();
        return !empty($version['body']) ? $version['body'] : 'Pas de notes de version disponibles.';
    }

    private function get_github_data() {
        $this->get_repository_info();
        return $this->github_response;
    }
}

/* ---------------- ADMIN MENU & SETTINGS ---------------- */
add_action('admin_menu', 'steam_status_menu');
function steam_status_menu() {
    add_options_page('Steam Server Status', 'Steam Server Status SP', 'manage_options', 'steam-status', 'steam_status_settings_page');
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
    ?>
    <div class="wrap">
        <h1>🎮 Réglages - Steam Server Status SourceQuery PHP</h1>
        <form method="post" action="options.php">
            <?php settings_fields('steam_status_options_group'); ?>

            <h2>Configuration des serveurs</h2>
            <table class="form-table" id="steam-servers-table">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Adresse IP</th>
                        <th>Port</th>
                        <th>Supprimer</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($servers as $index => $server): ?>
                    <tr>
                        <td><input type="text" name="steam_servers[<?php echo $index; ?>][name]" value="<?php echo esc_attr($server['name']); ?>" placeholder="Nom du serveur"></td>
                        <td><input type="text" name="steam_servers[<?php echo $index; ?>][ip]" value="<?php echo esc_attr($server['ip']); ?>" placeholder="45.90.160.141"></td>
                        <td><input type="number" name="steam_servers[<?php echo $index; ?>][port]" value="<?php echo esc_attr($server['port']); ?>" placeholder="27015"></td>
                        <td><button type="button" class="button remove-server">❌</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="add-server">➕ Ajouter un serveur</button></p>

            <h2>Options d'affichage</h2>
            <p><label><input type="checkbox" name="steam_show_name" value="1" <?php checked(1,$show_name); ?>> Afficher le nom du serveur en front</label></p>

            <h2>Cache</h2>
            <p><label>Durée du cache (en secondes) : <input type="number" name="steam_cache_duration" value="<?php echo esc_attr($cache_duration); ?>" min="5" step="5"></label></p>

            <h2>Textes personnalisables</h2>
            <table class="form-table">
                <tr><th>Serveur injoignable</th><td><input type="text" name="steam_text_offline" value="<?php echo esc_attr($text_offline); ?>"></td></tr>
                <tr><th>Aucun serveur configuré</th><td><input type="text" name="steam_text_no_servers" value="<?php echo esc_attr($text_no_servers); ?>"></td></tr>
                <tr><th>Serveur introuvable</th><td><input type="text" name="steam_text_not_found" value="<?php echo esc_attr($text_not_found); ?>"></td></tr>
                <tr><th>"Joueurs connectés"</th><td><input type="text" name="steam_text_players" value="<?php echo esc_attr($text_players); ?>"></td></tr>
                <tr><th>Séparateur joueurs/max</th><td><input type="text" name="steam_text_separator" value="<?php echo esc_attr($text_separator); ?>"></td></tr>
                <tr><th>Aucun joueur</th><td><input type="text" name="steam_text_no_players" value="<?php echo esc_attr($text_no_players); ?>"></td></tr>
            </table>

            <h2>Style Online / Offline</h2>
            <p><label><input type="checkbox" name="steam_use_text_colors" value="1" <?php checked(1,$use_text_colors); ?>> Activer la couleur du texte</label></p>
            <p><label><input type="checkbox" name="steam_use_border_colors" value="1" <?php checked(1,$use_border_colors); ?>> Activer la couleur de la bordure</label></p>

            <h2>Couleurs Online / Offline</h2>
            <p>Texte Online : <input type="text" class="steam-color-field" name="steam_color_text_online" value="<?php echo esc_attr($color_text_online); ?>"></p>
            <p>Texte Offline : <input type="text" class="steam-color-field" name="steam_color_text_offline" value="<?php echo esc_attr($color_text_offline); ?>"></p>
            <p>Bordure Online : <input type="text" class="steam-color-field" name="steam_color_border_online" value="<?php echo esc_attr($color_border_online); ?>"></p>
            <p>Bordure Offline : <input type="text" class="steam-color-field" name="steam_color_border_offline" value="<?php echo esc_attr($color_border_offline); ?>"></p>

            <h2>Police</h2>
            <p>Police du texte : <input type="text" name="steam_font_family" value="<?php echo esc_attr($font_family); ?>" placeholder="Ex: Arial, sans-serif"></p>
            <p>Taille du texte (px) : <input type="number" name="steam_font_size" value="<?php echo esc_attr($font_size); ?>" min="8" step="1"></p>

            <h2>Shortcode [steam_status_all]</h2>
            <p>Rendu par défaut : 
                <select name="steam_all_display_default">
                    <option value="table" <?php selected('table',$all_display_default); ?>>Tableau</option>
                    <option value="cards" <?php selected('cards',$all_display_default); ?>>Cartes</option>
                </select>
            </p>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
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

/* ---------------- HELPERS ---------------- */
function steam_query_server($server){
    $res=['error'=>false,'online'=>false,'players'=>0,'max'=>0,'name'=>isset($server['name'])?$server['name']:''];
    $Query = new SourceQuery();
    try{
        $Query->Connect($server['ip'],$server['port'],1,SourceQuery::SOURCE);
        $info = $Query->GetInfo();
        $res['online']=true;
        $res['players']=intval($info['Players']);
        $res['max']=intval($info['MaxPlayers']);
    }catch(Exception $e){ $res['error']=true; $res['online']=false; }
    finally{ $Query->Disconnect(); }
    return $res;
}

function steam_get_server_data_cached($server,$id,$cache_duration){
    $cache_key='steam_status_'.$id;
    $data=get_transient($cache_key);
    if($data===false){
        $data=steam_query_server($server);
        if(empty($data['name']) && !empty($server['name'])) $data['name']=$server['name'];
        set_transient($cache_key,$data,$cache_duration);
    }
    return $data;
}

/* ---------------- SHORTCODES ---------------- */
add_shortcode('steam_status','steam_server_status');
function steam_server_status($atts){
    $servers=get_option('steam_servers',[]);
    if(!is_array($servers)||empty($servers)) return '<div class="steam-status offline">'.esc_html(get_option('steam_text_no_servers','⚠️ Aucun serveur configuré')).'</div>';

    $atts=shortcode_atts(['id'=>0,'show_name'=>get_option('steam_show_name',1)],$atts,'steam_status');
    $id=intval($atts['id']); $show_name=intval($atts['show_name']);
    $cache_duration=intval(get_option('steam_cache_duration',15));

    if(!isset($servers[$id])) return '<div class="steam-status offline">'.esc_html(get_option('steam_text_not_found','⚠️ Serveur introuvable')).'</div>';

    $server=$servers[$id];
    $data=steam_get_server_data_cached($server,$id,$cache_duration);
    $unique_id='steam-status-'.$id;

    $text_players=get_option('steam_text_players','Joueurs connectés :');
    $text_separator=get_option('steam_text_separator','/');
    $text_offline=get_option('steam_text_offline','Serveur injoignable');

    if($data['online']){
        return '<div id="'.$unique_id.'" class="steam-status steam-status-server-'.$id.' online">'.
                ($show_name?'<span class="server-name">'.esc_html($data['name']).'</span>':''). 
                '<span class="label">'.$text_players.'</span>'.
                '<span class="players">'.$data['players'].'</span>'.
                '<span class="separator">'.$text_separator.'</span>'.
                '<span class="maxplayers">'.$data['max'].'</span>'.
                '</div>';
    }else{
        return '<div id="'.$unique_id.'" class="steam-status steam-status-server-'.$id.' offline">'.
                ($show_name?esc_html($data['name']).' : ':'').
                esc_html($text_offline).
                '</div>';
    }
}

add_shortcode('steam_status_all','steam_status_all_shortcode');
function steam_status_all_shortcode($atts){
    $servers=get_option('steam_servers',[]);
    if(!is_array($servers)||empty($servers)) return '<div class="steam-status offline">'.esc_html(get_option('steam_text_no_servers','⚠️ Aucun serveur configuré')).'</div>';

    $display=get_option('steam_all_display_default','table');
    $cache_duration=intval(get_option('steam_cache_duration',15));
    $show_name=get_option('steam_show_name',1);
    $html='';

    if($display==='table'){
        $html.='<table class="steam-status-table"><thead><tr><th>Serveur</th><th>État</th><th>Joueurs</th></tr></thead><tbody>';
        foreach($servers as $i=>$server){
            $data=steam_get_server_data_cached($server,$i,$cache_duration);
            $status=$data['online']?'<span class="online">Online</span>':'<span class="offline">Offline</span>';
            $players=$data['online']?$data['players'].' / '.$data['max']:'0 / 0';
            $html.='<tr><td>'.esc_html($server['name']).'</td><td>'.$status.'</td><td>'.$players.'</td></tr>';
        }
        $html.='</tbody></table>';
    }else{
        $html.='<div class="steam-cards">';
        foreach($servers as $i=>$server){
            $data=steam_get_server_data_cached($server,$i,$cache_duration);
            $status=$data['online']?'<span class="online">Online</span>':'<span class="offline">Offline</span>';
            $players=$data['online']?$data['players'].' / '.$data['max']:'0 / 0';
            $html.='<div class="steam-card steam-status-server-'.$i.'">'.
                ($show_name?'<strong>'.esc_html($server['name']).'</strong><br>':'').
                $status.'<br>'.
                $players.
                '</div>';
        }
        $html.='</div>';
    }
    return $html;
}