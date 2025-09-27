<?php
/*
Plugin Name: Steam Server Status SourceQuery PHP
Description: Affiche le nombre de joueurs connectés sur un ou plusieurs serveurs Steam et Minecraft avec personnalisation avancée.
Version: 1.2.2
Author: Skylide
GitHub Plugin URI: skylidefr/Steam-Server-Status-SourceQuery-PHP
GitHub Branch: main
Author URI: https://github.com/skylidefr/Steam-Server-Status-SourceQuery-PHP/
*/

if (!defined('ABSPATH')) exit;

// Chargement des dépendances
require_once __DIR__ . '/SourceQuery/bootstrap.php';
require_once __DIR__ . '/MinecraftQuery/MinecraftQuery.php';
require_once __DIR__ . '/MinecraftQuery/MinecraftQueryException.php';

use xPaw\SourceQuery\SourceQuery;
use xPaw\MinecraftQuery;

/**
 * Classe principale du plugin
 */
class SteamServerStatusPlugin {
    
    private static $instance = null;
    private $version;
    private $plugin_slug = 'steam-server-status';
    private $plugin_file;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->plugin_file = __FILE__;
        $this->init();
    }
    
    /**
     * Récupère la version du plugin depuis l'en-tête
     */
    private function getVersion() {
        if (!isset($this->version)) {
            $plugin_data = get_plugin_data($this->plugin_file);
            $this->version = $plugin_data['Version'];
        }
        return $this->version;
    }
    
    private function init() {
        add_action('init', [$this, 'initPlugin']);
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('wp_head', [$this, 'addFrontendStyles']);
        
        // Shortcodes
        add_shortcode('steam_status', [$this, 'singleServerShortcode']);
        add_shortcode('steam_status_all', [$this, 'allServersShortcode']);
        
        // Système de mise à jour GitHub
        if (is_admin()) {
            new SteamStatusGitHubUpdater($this->plugin_file);
        }
    }
    
    public function initPlugin() {
        // Actions d'initialisation si nécessaire
    }
    
    public function addAdminMenu() {
        add_options_page(
            'Steam & Minecraft Server Status',
            'Steam & Minecraft Status',
            'manage_options',
            $this->plugin_slug,
            [$this, 'renderSettingsPage']
        );
    }
    
    public function registerSettings() {
        $settings = [
            'steam_servers',
            'steam_show_name',
            'steam_cache_duration',
            'steam_text_offline',
            'steam_text_no_servers',
            'steam_text_not_found',
            'steam_text_players',
            'steam_text_separator',
            'steam_text_no_players',
            'steam_use_text_colors',
            'steam_use_border_colors',
            'steam_color_text_online',
            'steam_color_text_offline',
            'steam_color_border_online',
            'steam_color_border_offline',
            'steam_font_family',
            'steam_font_size',
            'steam_all_display_default',
            // Options latence
            'steam_show_latency_global',
            'steam_latency_cache_duration',
            'steam_latency_threshold_good',
            'steam_latency_threshold_medium',
            // Nouvelles options Minecraft
            'steam_show_motd',
            'steam_show_version'
        ];
        
        foreach ($settings as $setting) {
            register_setting('steam_status_options_group', $setting);
        }
    }
    
    public function enqueueAdminAssets($hook) {
        if ($hook !== 'settings_page_' . $this->plugin_slug) return;
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        wp_enqueue_script(
            'steam-admin-js',
            plugin_dir_url($this->plugin_file) . 'assets/admin.js',
            ['jquery', 'wp-color-picker'],
            $this->getVersion(),
            true
        );
        
        wp_add_inline_script('wp-color-picker', $this->getAdminScript());
    }
    
    private function getAdminScript() {
        return "
        jQuery(document).ready(function($){
            $('.steam-color-field').wpColorPicker();
            
            function attachRemoveEvents(){
                $('.remove-server').off('click').on('click', function(){
                    $(this).closest('tr').remove();
                });
            }
            
            attachRemoveEvents();
            
            $('#add-server').on('click', function(){
                const table = $('#steam-servers-table tbody');
                const index = table.find('tr').length;
                const row = `
                    <tr>
                        <td><input type=\"text\" name=\"steam_servers[\${index}][name]\" placeholder=\"Nom du serveur\"></td>
                        <td>
                            <select name=\"steam_servers[\${index}][type]\">
                                <option value=\"source\">Steam/Source</option>
                                <option value=\"minecraft\">Minecraft</option>
                            </select>
                        </td>
                        <td><input type=\"text\" name=\"steam_servers[\${index}][ip]\" placeholder=\"45.90.160.141\"></td>
                        <td><input type=\"number\" name=\"steam_servers[\${index}][port]\" placeholder=\"27015\"></td>
                        <td><input type=\"checkbox\" name=\"steam_servers[\${index}][show_latency]\" value=\"1\"></td>
                        <td><button type=\"button\" class=\"button remove-server\">❌</button></td>
                    </tr>
                `;
                table.append(row);
                attachRemoveEvents();
            });
        });";
    }
    
    public function addFrontendStyles() {
        $options = $this->getOptions();
        $this->renderInlineCSS($options);
    }
    
    private function getOptions() {
        return [
            'font_family' => get_option('steam_font_family', 'Arial, sans-serif'),
            'font_size' => intval(get_option('steam_font_size', 14)),
            'use_text_colors' => get_option('steam_use_text_colors', 1),
            'use_border_colors' => get_option('steam_use_border_colors', 1),
            'color_text_online' => get_option('steam_color_text_online', '#2ecc71'),
            'color_text_offline' => get_option('steam_color_text_offline', '#e74c3c'),
            'color_border_online' => get_option('steam_color_border_online', '#2ecc71'),
            'color_border_offline' => get_option('steam_color_border_offline', '#e74c3c'),
        ];
    }
    
    private function renderInlineCSS($options) {
        $border_online = $options['use_border_colors'] ? "border:1px solid {$options['color_border_online']};" : "border:none;";
        $border_offline = $options['use_border_colors'] ? "border:1px solid {$options['color_border_offline']};" : "border:none;";
        $color_online = $options['use_text_colors'] ? $options['color_text_online'] : "inherit";
        $color_offline = $options['use_text_colors'] ? $options['color_text_offline'] : "inherit";
        
        echo "<style>
        .steam-status{ 
            font-family:{$options['font_family']}; 
            font-size:{$options['font_size']}px; 
            padding:10px; 
            border-radius:6px; 
            display:inline-block; 
            margin:6px; 
        }
        .steam-status.online{ 
            {$border_online} 
            color:{$color_online}; 
            background:rgba(0,0,0,0.03); 
        }
        .steam-status.offline{ 
            {$border_offline} 
            color:{$color_offline}; 
            background:rgba(0,0,0,0.02); 
        }
        .steam-status .server-name{ 
            font-weight:700; 
            display:block; 
            margin-bottom:4px; 
        }
        .steam-status .players,.steam-status .maxplayers{ 
            font-weight:600; 
            margin:0 4px; 
        }
        .steam-status .latency{
            font-size:0.9em;
            margin-left:8px;
        }
        .steam-status .latency.good{ color:#2ecc71; }
        .steam-status .latency.medium{ color:#f39c12; }
        .steam-status .latency.bad{ color:#e74c3c; }
        .steam-status .server-info{
            font-size:0.85em;
            opacity:0.8;
            display:block;
            margin-top:2px;
        }
        .steam-status .motd{
            font-style:italic;
            font-size:0.9em;
            margin-top:4px;
            display:block;
        }
        .steam-status-table{ 
            width:100%; 
            border-collapse:collapse; 
        }
        .steam-status-table th,.steam-status-table td{ 
            padding:8px 10px; 
            border:1px solid #ddd; 
            text-align:left; 
        }
        .steam-card{ 
            display:inline-block; 
            vertical-align:top; 
            width:300px; 
            margin:6px; 
        }
        </style>";
    }
    
    public function renderSettingsPage() {
        $servers = get_option('steam_servers', []);
        if (!is_array($servers)) $servers = [];
        
        $options = $this->getAllOptions();
        ?>
        <div class="wrap">
            <h1>🎮 Réglages - Steam & Minecraft Server Status</h1>
            <form method="post" action="options.php">
                <?php settings_fields('steam_status_options_group'); ?>

                <h2>Configuration des serveurs</h2>
                <table class="form-table" id="steam-servers-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Adresse IP</th>
                            <th>Port</th>
                            <th>Afficher latence</th>
                            <th>Supprimer</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($servers as $index => $server): ?>
                        <tr>
                            <td><input type="text" name="steam_servers[<?php echo $index; ?>][name]" value="<?php echo esc_attr($server['name']); ?>" placeholder="Nom du serveur"></td>
                            <td>
                                <select name="steam_servers[<?php echo $index; ?>][type]">
                                    <option value="source" <?php selected('source', $server['type'] ?? 'source'); ?>>Steam/Source</option>
                                    <option value="minecraft" <?php selected('minecraft', $server['type'] ?? 'source'); ?>>Minecraft</option>
                                </select>
                            </td>
                            <td><input type="text" name="steam_servers[<?php echo $index; ?>][ip]" value="<?php echo esc_attr($server['ip']); ?>" placeholder="45.90.160.141"></td>
                            <td><input type="number" name="steam_servers[<?php echo $index; ?>][port]" value="<?php echo esc_attr($server['port']); ?>" placeholder="27015"></td>
                            <td><input type="checkbox" name="steam_servers[<?php echo $index; ?>][show_latency]" value="1" <?php checked(1, $server['show_latency'] ?? 0); ?>></td>
                            <td><button type="button" class="button remove-server">❌</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="button" class="button" id="add-server">➕ Ajouter un serveur</button></p>

                <h2>Options d'affichage</h2>
                <p><label><input type="checkbox" name="steam_show_name" value="1" <?php checked(1, $options['show_name']); ?>> Afficher le nom du serveur en front</label></p>
                
                <h2>Options Minecraft</h2>
                <p><label><input type="checkbox" name="steam_show_motd" value="1" <?php checked(1, $options['show_motd']); ?>> Afficher le MOTD des serveurs Minecraft</label></p>
                <p><label><input type="checkbox" name="steam_show_version" value="1" <?php checked(1, $options['show_version']); ?>> Afficher la version des serveurs</label></p>

                <h2>Configuration Latence</h2>
                <p><label><input type="checkbox" name="steam_show_latency_global" value="1" <?php checked(1, $options['show_latency_global']); ?>> Activer l'affichage de la latence globalement</label></p>
                <p><label>Cache latence (secondes) : <input type="number" name="steam_latency_cache_duration" value="<?php echo esc_attr($options['latency_cache_duration']); ?>" min="5" step="5"></label></p>
                <p><label>Seuil "Bonne" latence (ms) : <input type="number" name="steam_latency_threshold_good" value="<?php echo esc_attr($options['latency_threshold_good']); ?>" min="1"></label></p>
                <p><label>Seuil "Moyenne" latence (ms) : <input type="number" name="steam_latency_threshold_medium" value="<?php echo esc_attr($options['latency_threshold_medium']); ?>" min="1"></label></p>

                <h2>Cache</h2>
                <p><label>Durée du cache serveur (en secondes) : <input type="number" name="steam_cache_duration" value="<?php echo esc_attr($options['cache_duration']); ?>" min="5" step="5"></label></p>

                <h2>Textes personnalisables</h2>
                <table class="form-table">
                    <tr><th>Serveur injoignable</th><td><input type="text" name="steam_text_offline" value="<?php echo esc_attr($options['text_offline']); ?>"></td></tr>
                    <tr><th>Aucun serveur configuré</th><td><input type="text" name="steam_text_no_servers" value="<?php echo esc_attr($options['text_no_servers']); ?>"></td></tr>
                    <tr><th>Serveur introuvable</th><td><input type="text" name="steam_text_not_found" value="<?php echo esc_attr($options['text_not_found']); ?>"></td></tr>
                    <tr><th>"Joueurs connectés"</th><td><input type="text" name="steam_text_players" value="<?php echo esc_attr($options['text_players']); ?>"></td></tr>
                    <tr><th>Séparateur joueurs/max</th><td><input type="text" name="steam_text_separator" value="<?php echo esc_attr($options['text_separator']); ?>"></td></tr>
                    <tr><th>Aucun joueur</th><td><input type="text" name="steam_text_no_players" value="<?php echo esc_attr($options['text_no_players']); ?>"></td></tr>
                </table>

                <h2>Style Online / Offline</h2>
                <p><label><input type="checkbox" name="steam_use_text_colors" value="1" <?php checked(1, $options['use_text_colors']); ?>> Activer la couleur du texte</label></p>
                <p><label><input type="checkbox" name="steam_use_border_colors" value="1" <?php checked(1, $options['use_border_colors']); ?>> Activer la couleur de la bordure</label></p>

                <h2>Couleurs Online / Offline</h2>
                <p>Texte Online : <input type="text" class="steam-color-field" name="steam_color_text_online" value="<?php echo esc_attr($options['color_text_online']); ?>"></p>
                <p>Texte Offline : <input type="text" class="steam-color-field" name="steam_color_text_offline" value="<?php echo esc_attr($options['color_text_offline']); ?>"></p>
                <p>Bordure Online : <input type="text" class="steam-color-field" name="steam_color_border_online" value="<?php echo esc_attr($options['color_border_online']); ?>"></p>
                <p>Bordure Offline : <input type="text" class="steam-color-field" name="steam_color_border_offline" value="<?php echo esc_attr($options['color_border_offline']); ?>"></p>

                <h2>Police</h2>
                <p>Police du texte : <input type="text" name="steam_font_family" value="<?php echo esc_attr($options['font_family']); ?>" placeholder="Ex: Arial, sans-serif"></p>
                <p>Taille du texte (px) : <input type="number" name="steam_font_size" value="<?php echo esc_attr($options['font_size']); ?>" min="8" step="1"></p>

                <h2>Shortcode [steam_status_all]</h2>
                <p>Rendu par défaut : 
                    <select name="steam_all_display_default">
                        <option value="table" <?php selected('table', $options['all_display_default']); ?>>Tableau</option>
                        <option value="cards" <?php selected('cards', $options['all_display_default']); ?>>Cartes</option>
                    </select>
                </p>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    private function getAllOptions() {
        return [
            'show_name' => get_option('steam_show_name', 1),
            'cache_duration' => intval(get_option('steam_cache_duration', 15)),
            'text_offline' => get_option('steam_text_offline', 'Serveur injoignable'),
            'text_no_servers' => get_option('steam_text_no_servers', '⚠️ Aucun serveur configuré'),
            'text_not_found' => get_option('steam_text_not_found', '⚠️ Serveur introuvable'),
            'text_players' => get_option('steam_text_players', 'Joueurs connectés :'),
            'text_separator' => get_option('steam_text_separator', '/'),
            'text_no_players' => get_option('steam_text_no_players', 'Aucun joueur en ligne'),
            'use_text_colors' => get_option('steam_use_text_colors', 1),
            'use_border_colors' => get_option('steam_use_border_colors', 1),
            'color_text_online' => get_option('steam_color_text_online', '#2ecc71'),
            'color_text_offline' => get_option('steam_color_text_offline', '#e74c3c'),
            'color_border_online' => get_option('steam_color_border_online', '#2ecc71'),
            'color_border_offline' => get_option('steam_color_border_offline', '#e74c3c'),
            'font_family' => get_option('steam_font_family', 'Arial, sans-serif'),
            'font_size' => intval(get_option('steam_font_size', 14)),
            'all_display_default' => get_option('steam_all_display_default', 'table'),
            // Options latence
            'show_latency_global' => get_option('steam_show_latency_global', 1),
            'latency_cache_duration' => intval(get_option('steam_latency_cache_duration', 5)),
            'latency_threshold_good' => intval(get_option('steam_latency_threshold_good', 80)),
            'latency_threshold_medium' => intval(get_option('steam_latency_threshold_medium', 200)),
            // Options Minecraft
            'show_motd' => get_option('steam_show_motd', 1),
            'show_version' => get_option('steam_show_version', 1),
        ];
    }
    
    // Shortcodes
    public function singleServerShortcode($atts) {
        $servers = get_option('steam_servers', []);
        if (!is_array($servers) || empty($servers)) {
            return $this->renderOfflineStatus(get_option('steam_text_no_servers', '⚠️ Aucun serveur configuré'));
        }
        
        $atts = shortcode_atts([
            'id' => 0,
            'show_name' => get_option('steam_show_name', 1)
        ], $atts, 'steam_status');
        
        $id = intval($atts['id']);
        $show_name = intval($atts['show_name']);
        
        if (!isset($servers[$id])) {
            return $this->renderOfflineStatus(get_option('steam_text_not_found', '⚠️ Serveur introuvable'));
        }
        
        $server = $servers[$id];
        $data = $this->getServerDataCached($server, $id);
        
        return $this->renderServerStatus($data, $id, $show_name);
    }
    
    public function allServersShortcode($atts) {
        $servers = get_option('steam_servers', []);
        if (!is_array($servers) || empty($servers)) {
            return $this->renderOfflineStatus(get_option('steam_text_no_servers', '⚠️ Aucun serveur configuré'));
        }
        
        $display = get_option('steam_all_display_default', 'table');
        $show_name = get_option('steam_show_name', 1);
        
        if ($display === 'table') {
            return $this->renderAllServersTable($servers);
        } else {
            return $this->renderAllServersCards($servers, $show_name);
        }
    }
    
    // Méthodes utilitaires
    private function queryServer($server) {
        $server_type = $server['type'] ?? 'source';
        
        $result = [
            'error' => false,
            'online' => false,
            'players' => 0,
            'max' => 0,
            'name' => $server['name'] ?? '',
            'latency' => null,
            'type' => $server_type,
            'version' => null,
            'motd' => null
        ];
        
        try {
            if ($server_type === 'minecraft') {
                $result = $this->queryMinecraftServer($server, $result);
            } else {
                $result = $this->querySourceServer($server, $result);
            }
        } catch (Exception $e) {
            error_log('Server Query Error: ' . $e->getMessage());
            $result['error'] = true;
            $result['online'] = false;
        }
        
        return $result;
    }
    
    private function queryMinecraftServer($server, $result) {
        $latencies = [];
        $timeout = 1;
        
        // Mesure latence avec 3 tentatives
        for ($i = 0; $i < 3; $i++) {
            $start_time = microtime(true);
            
            $query = new MinecraftQuery();
            $query->Connect($server['ip'], $server['port'], $timeout);
            $info = $query->GetInfo();
            
            $end_time = microtime(true);
            $latencies[] = ($end_time - $start_time) * 1000;
            
            if ($i < 2) usleep(100000); // 100ms pause
        }
        
        // Requête finale pour récupérer toutes les données
        $query = new MinecraftQuery();
        $query->Connect($server['ip'], $server['port'], $timeout);
        $info = $query->GetInfo();
        
        if ($info) {
            $result['online'] = true;
            $result['players'] = intval($info['Players'] ?? 0);
            $result['max'] = intval($info['MaxPlayers'] ?? 0);
            $result['version'] = $info['Version'] ?? null;
            $result['motd'] = $this->cleanMinecraftMotd($info['HostName'] ?? null);
            $result['latency'] = round(array_sum($latencies) / count($latencies));
        }
        
        return $result;
    }
    
    private function querySourceServer($server, $result) {
        $latencies = [];
        $timeout = 1;
        $query = new SourceQuery();
        
        // Mesure latence avec 3 tentatives
        for ($i = 0; $i < 3; $i++) {
            $start_time = microtime(true);
            
            $query->Connect($server['ip'], $server['port'], $timeout, SourceQuery::SOURCE);
            $info = $query->GetInfo();
            
            $end_time = microtime(true);
            $latencies[] = ($end_time - $start_time) * 1000;
            
            $query->Disconnect();
            if ($i < 2) usleep(100000); // 100ms pause
        }
        
        // Requête finale pour récupérer toutes les données
        $query->Connect($server['ip'], $server['port'], $timeout, SourceQuery::SOURCE);
        $info = $query->GetInfo();
        
        $result['online'] = true;
        $result['players'] = intval($info['Players'] ?? 0);
        $result['max'] = intval($info['MaxPlayers'] ?? 0);
        $result['version'] = $info['Version'] ?? null;
        $result['latency'] = round(array_sum($latencies) / count($latencies));
        
        $query->Disconnect();
        
        return $result;
    }
    
    private function cleanMinecraftMotd($motd) {
        if (!$motd) return null;
        
        // Nettoyer les codes couleur Minecraft (§x)
        $motd = preg_replace('/§[0-9a-fk-or]/', '', $motd);
        
        // Nettoyer les codes JSON si présents
        if (is_array($motd)) {
            $motd = isset($motd['text']) ? $motd['text'] : '';
        }
        
        return trim($motd);
    }
    
    private function getServerDataCached($server, $id) {
        $cache_key = 'steam_status_' . $id;
        $latency_cache_key = 'steam_latency_' . $id;
        
        $cache_duration = intval(get_option('steam_cache_duration', 15));
        $latency_cache_duration = intval(get_option('steam_latency_cache_duration', 5));
        
        // Récupérer les données serveur
        $data = get_transient($cache_key);
        $latency = get_transient($latency_cache_key);
        
        if ($data === false) {
            $data = $this->queryServer($server);
            if (empty($data['name']) && !empty($server['name'])) {
                $data['name'] = $server['name'];
            }
            
            // Stocker avec cache séparé pour latence
            $latency_data = $data['latency'];
            unset($data['latency']);
            
            set_transient($cache_key, $data, $cache_duration);
            set_transient($latency_cache_key, $latency_data, $latency_cache_duration);
            
            $data['latency'] = $latency_data;
        } else {
            // Si latence expirée mais pas les données serveur
            if ($latency === false && $data['online']) {
                $temp_data = $this->queryServer($server);
                $latency = $temp_data['latency'];
                set_transient($latency_cache_key, $latency, $latency_cache_duration);
            }
            $data['latency'] = $latency;
        }
        
        return $data;
    }
    
    private function renderOfflineStatus($message) {
        return '<div class="steam-status offline">' . esc_html($message) . '</div>';
    }
    
    private function renderServerStatus($data, $id, $show_name) {
        $servers = get_option('steam_servers', []);
        $server = $servers[$id] ?? [];
        
        $show_latency_global = get_option('steam_show_latency_global', 1);
        $show_latency_server = $server['show_latency'] ?? 0;
        $should_show_latency = $show_latency_global && $show_latency_server;
        
        $show_motd = get_option('steam_show_motd', 1);
        $show_version = get_option('steam_show_version', 1);
        
        $unique_id = 'steam-status-' . $id;
        $text_players = get_option('steam_text_players', 'Joueurs connectés :');
        $text_separator = get_option('steam_text_separator', '/');
        $text_offline = get_option('steam_text_offline', 'Serveur injoignable');
        
        if ($data['online']) {
            $latency_display = '';
            if ($should_show_latency && $data['latency'] !== null) {
                $latency_class = $this->getLatencyClass($data['latency']);
                $latency_display = sprintf(
                    ' <span class="latency %s">(%dms)</span>',
                    $latency_class,
                    $data['latency']
                );
            }
            
            $version_display = '';
            if ($show_version && $data['version']) {
                $version_display = sprintf(' <span class="server-info">(%s)</span>', esc_html($data['version']));
            }
            
            $motd_display = '';
            if ($show_motd && $data['motd'] && $data['type'] === 'minecraft') {
                $motd_display = sprintf('<span class="motd">%s</span>', esc_html($data['motd']));
            }
            
            return sprintf(
                '<div id="%s" class="steam-status steam-status-server-%d online">%s<span class="label">%s</span><span class="players">%d</span><span class="separator">%s</span><span class="maxplayers">%d</span>%s%s%s</div>',
                $unique_id,
                $id,
                $show_name ? '<span class="server-name">' . esc_html($data['name']) . '</span>' : '',
                esc_html($text_players),
                $data['players'],
                esc_html($text_separator),
                $data['max'],
                $latency_display,
                $version_display,
                $motd_display
            );
        } else {
            return sprintf(
                '<div id="%s" class="steam-status steam-status-server-%d offline">%s%s</div>',
                $unique_id,
                $id,
                $show_name ? esc_html($data['name']) . ' : ' : '',
                esc_html($text_offline)
            );
        }
    }
    
    private function getLatencyClass($latency) {
        $good_threshold = intval(get_option('steam_latency_threshold_good', 80));
        $medium_threshold = intval(get_option('steam_latency_threshold_medium', 200));
        
        if ($latency <= $good_threshold) {
            return 'good';
        } elseif ($latency <= $medium_threshold) {
            return 'medium';
        } else {
            return 'bad';
        }
    }
    
    private function renderAllServersTable($servers) {
        $show_version = get_option('steam_show_version', 1);
        
        $html = '<table class="steam-status-table"><thead><tr><th>Serveur</th><th>Type</th><th>État</th><th>Joueurs</th><th>Latence</th>';
        if ($show_version) {
            $html .= '<th>Version</th>';
        }
        $html .= '</tr></thead><tbody>';
        
        foreach ($servers as $i => $server) {
            $data = $this->getServerDataCached($server, $i);
            $status = $data['online'] ? '<span class="online">Online</span>' : '<span class="offline">Offline</span>';
            $players = $data['online'] ? $data['players'] . ' / ' . $data['max'] : '0 / 0';
            
            $server_type = ucfirst($server['type'] ?? 'source');
            if ($server_type === 'Source') $server_type = 'Steam';
            
            $latency_display = '-';
            if ($data['online'] && $data['latency'] !== null && ($server['show_latency'] ?? 0) && get_option('steam_show_latency_global', 1)) {
                $latency_class = $this->getLatencyClass($data['latency']);
                $latency_display = sprintf('<span class="latency %s">%dms</span>', $latency_class, $data['latency']);
            }
            
            $version_display = '-';
            if ($show_version && $data['version']) {
                $version_display = esc_html($data['version']);
            }
            
            $html .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>',
                esc_html($server['name']),
                $server_type,
                $status,
                $players,
                $latency_display
            );
            
            if ($show_version) {
                $html .= sprintf('<td>%s</td>', $version_display);
            }
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    private function renderAllServersCards($servers, $show_name) {
        $show_version = get_option('steam_show_version', 1);
        $show_motd = get_option('steam_show_motd', 1);
        
        $html = '<div class="steam-cards">';
        
        foreach ($servers as $i => $server) {
            $data = $this->getServerDataCached($server, $i);
            $status = $data['online'] ? '<span class="online">Online</span>' : '<span class="offline">Offline</span>';
            $players = $data['online'] ? $data['players'] . ' / ' . $data['max'] : '0 / 0';
            
            $server_type = ucfirst($server['type'] ?? 'source');
            if ($server_type === 'Source') $server_type = 'Steam';
            
            $latency_display = '';
            if ($data['online'] && $data['latency'] !== null && ($server['show_latency'] ?? 0) && get_option('steam_show_latency_global', 1)) {
                $latency_class = $this->getLatencyClass($data['latency']);
                $latency_display = sprintf(' <span class="latency %s">(%dms)</span>', $latency_class, $data['latency']);
            }
            
            $version_display = '';
            if ($show_version && $data['version']) {
                $version_display = sprintf('<br><span class="server-info">%s</span>', esc_html($data['version']));
            }
            
            $motd_display = '';
            if ($show_motd && $data['motd'] && $data['type'] === 'minecraft') {
                $motd_display = sprintf('<br><span class="motd">%s</span>', esc_html($data['motd']));
            }
            
            $html .= sprintf(
                '<div class="steam-card steam-status-server-%d">%s<strong>[%s]</strong><br>%s%s<br>%s%s%s</div>',
                $i,
                $show_name ? '<strong>' . esc_html($server['name']) . '</strong><br>' : '',
                $server_type,
                $status,
                $latency_display,
                $players,
                $version_display,
                $motd_display
            );
        }
        
        $html .= '</div>';
        return $html;
    }
}

/**
 * Classe pour la mise à jour GitHub
 */
class SteamStatusGitHubUpdater {
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $github_user;
    private $github_repo;
    private $github_response;
    
    public function __construct($file) {
        $this->file = $file;
        add_action('admin_init', [$this, 'setPluginProperties']);
    }
    
    public function setPluginProperties() {
        $this->plugin = get_plugin_data($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active = is_plugin_active($this->basename);
        
        $this->parseGitHubInfo();
        
        if ($this->github_user && $this->github_repo) {
            add_filter('pre_set_site_transient_update_plugins', [$this, 'modifyTransient'], 10, 1);
            add_filter('plugins_api', [$this, 'pluginPopup'], 10, 3);
            add_filter('upgrader_post_install', [$this, 'afterInstall'], 10, 3);
        }
    }
    
    private function parseGitHubInfo() {
        $plugin_content = file_get_contents($this->file);
        preg_match('/GitHub Plugin URI:\s*(.+)/', $plugin_content, $github_matches);
        
        if (isset($github_matches[1])) {
            $github_uri = trim($github_matches[1]);
            $parts = explode('/', $github_uri);
            
            if (count($parts) >= 2) {
                $this->github_user = trim($parts[0]);
                $this->github_repo = trim($parts[1]);
            }
        }
    }
    
    public function modifyTransient($transient) {
        if (!property_exists($transient, 'checked') || !$transient->checked) {
            return $transient;
        }
        
        $this->getRepositoryInfo();
        $new_version = $this->getNewVersion();
        $current_version = $transient->checked[$this->basename] ?? $this->plugin['Version'];
        
        if (version_compare($new_version, $current_version, 'gt')) {
            $transient->response[$this->basename] = (object) [
                'new_version' => $new_version,
                'slug' => current(explode('/', $this->basename)),
                'url' => $this->plugin['PluginURI'],
                'package' => $this->getZipUrl()
            ];
        }
        
        return $transient;
    }
    
    public function pluginPopup($res, $action, $args) {
        if (empty($args->slug) || $args->slug !== current(explode('/', $this->basename))) {
            return $res;
        }
        
        $this->getRepositoryInfo();
        
        return (object) [
            'name' => $this->plugin['Name'],
            'slug' => $this->basename,
            'version' => $this->getNewVersion(),
            'author' => $this->plugin['AuthorName'],
            'author_profile' => $this->plugin['AuthorURI'],
            'last_updated' => $this->getDate(),
            'homepage' => $this->plugin['PluginURI'],
            'short_description' => $this->plugin['Description'],
            'sections' => [
                'Description' => $this->plugin['Description'],
                'Updates' => $this->getChangelog(),
            ],
            'download_link' => $this->getZipUrl()
        ];
    }
    
    public function afterInstall($response, $hook_extra, $result) {
        global $wp_filesystem;
        
        $install_directory = plugin_dir_path($this->file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;
        
        if ($this->active) {
            activate_plugin($this->basename);
        }
        
        return $result;
    }
    
    private function getRepositoryInfo() {
        if ($this->github_response !== null) {
            return;
        }
        
        $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->github_user, $this->github_repo);
        $response = wp_remote_get($request_uri, ['timeout' => 10]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $this->github_response = json_decode(wp_remote_retrieve_body($response), true);
        } else {
            $this->github_response = false;
        }
    }
    
    private function getNewVersion() {
        $this->getRepositoryInfo();
        return !empty($this->github_response['tag_name']) ? ltrim($this->github_response['tag_name'], 'v') : false;
    }
    
    private function getZipUrl() {
        $this->getRepositoryInfo();
        return !empty($this->github_response['zipball_url']) ? $this->github_response['zipball_url'] : false;
    }
    
    private function getDate() {
        $this->getRepositoryInfo();
        return !empty($this->github_response['published_at']) ? date('Y-m-d', strtotime($this->github_response['published_at'])) : false;
    }
    
    private function getChangelog() {
        $this->getRepositoryInfo();
        return !empty($this->github_response['body']) ? $this->github_response['body'] : 'Pas de notes de version disponibles.';
    }
}

// Initialisation du plugin
add_action('plugins_loaded', function() {
    SteamServerStatusPlugin::getInstance();
});

// Hook de désactivation pour nettoyer les caches
register_deactivation_hook(__FILE__, function() {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_steam_status_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_steam_status_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_steam_latency_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_steam_latency_%'");
});