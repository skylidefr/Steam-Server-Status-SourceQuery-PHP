<?php
/**
 * Widget Elementor pour Steam Server Status
 */

if (!defined('ABSPATH')) {
    exit;
}

class SteamStatusElementorWidget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'steam-server-status';
    }

    public function get_title() {
        return esc_html__('Server Status', 'steam-server-status');
    }

    public function get_icon() {
        return 'eicon-play';
    }

    public function get_categories() {
        return ['general'];
    }

    public function get_keywords() {
        return ['steam', 'server', 'minecraft', 'gaming', 'status'];
    }

    protected function register_controls() {
        $plugin = SteamServerStatusPlugin::getInstance();
        $servers = $plugin->getServers();
        
        $server_options = [];
        $server_options['all'] = 'Tous les serveurs';
        
        foreach ($servers as $id => $server) {
            $server_options[$id] = $server['name'] . ' (' . ($server['ip'] ?? 'N/A') . ')';
        }
        
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => esc_html__('Contenu', 'steam-server-status'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'server_selection',
            [
                'label' => esc_html__('Serveur', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'all',
                'options' => $server_options,
            ]
        );

        $this->add_control(
            'display_mode',
            [
                'label' => esc_html__('Mode d\'affichage', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'cards',
                'options' => [
                    'cards' => esc_html__('Cartes', 'steam-server-status'),
                    'table' => esc_html__('Tableau', 'steam-server-status'),
                ],
                'condition' => [
                    'server_selection' => 'all',
                ],
            ]
        );

        $this->add_control(
            'show_server_name',
            [
                'label' => esc_html__('Afficher nom du serveur', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Oui', 'steam-server-status'),
                'label_off' => esc_html__('Non', 'steam-server-status'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => esc_html__('Style', 'steam-server-status'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'text_typography',
                'label' => esc_html__('Typographie', 'steam-server-status'),
                'selector' => '{{WRAPPER}} .steam-status',
            ]
        );

        $this->add_control(
            'text_color_online',
            [
                'label' => esc_html__('Couleur texte Online', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2ecc71',
                'selectors' => [
                    '{{WRAPPER}} .steam-status.online' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'text_color_offline',
            [
                'label' => esc_html__('Couleur texte Offline', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e74c3c',
                'selectors' => [
                    '{{WRAPPER}} .steam-status.offline' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'border_color_online',
            [
                'label' => esc_html__('Couleur bordure Online', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2ecc71',
                'selectors' => [
                    '{{WRAPPER}} .steam-status.online' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'border_color_offline',
            [
                'label' => esc_html__('Couleur bordure Offline', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e74c3c',
                'selectors' => [
                    '{{WRAPPER}} .steam-status.offline' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'border_width',
            [
                'label' => esc_html__('Largeur bordure', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 10,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 1,
                ],
                'selectors' => [
                    '{{WRAPPER}} .steam-status' => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;',
                ],
            ]
        );

        $this->add_control(
            'border_radius',
            [
                'label' => esc_html__('Rayon bordure', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 6,
                ],
                'selectors' => [
                    '{{WRAPPER}} .steam-status' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'padding',
            [
                'label' => esc_html__('Padding', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'default' => [
                    'top' => 10,
                    'right' => 10,
                    'bottom' => 10,
                    'left' => 10,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .steam-status' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'margin',
            [
                'label' => esc_html__('Margin', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'default' => [
                    'top' => 6,
                    'right' => 6,
                    'bottom' => 6,
                    'left' => 6,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .steam-status' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'background_online',
                'label' => esc_html__('Arrière-plan Online', 'steam-server-status'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .steam-status.online',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'background_offline',
                'label' => esc_html__('Arrière-plan Offline', 'steam-server-status'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .steam-status.offline',
            ]
        );

        $this->end_controls_section();

        // Latency Style Section
        $this->start_controls_section(
            'latency_style_section',
            [
                'label' => esc_html__('Style Latence', 'steam-server-status'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'latency_good_color',
            [
                'label' => esc_html__('Couleur latence bonne', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#2ecc71',
                'selectors' => [
                    '{{WRAPPER}} .latency.good' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'latency_medium_color',
            [
                'label' => esc_html__('Couleur latence moyenne', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f39c12',
                'selectors' => [
                    '{{WRAPPER}} .latency.medium' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'latency_bad_color',
            [
                'label' => esc_html__('Couleur latence mauvaise', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e74c3c',
                'selectors' => [
                    '{{WRAPPER}} .latency.bad' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_section();

        // Table Style Section
        $this->start_controls_section(
            'table_style_section',
            [
                'label' => esc_html__('Style Tableau', 'steam-server-status'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'display_mode' => 'table',
                    'server_selection' => 'all',
                ],
            ]
        );

        $this->add_control(
            'table_border_color',
            [
                'label' => esc_html__('Couleur bordures tableau', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ddd',
                'selectors' => [
                    '{{WRAPPER}} .steam-status-table th, {{WRAPPER}} .steam-status-table td' => 'border-color: {{VALUE}}',
                    '{{WRAPPER}} .steam-status-table' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'table_header_background',
                'label' => esc_html__('Arrière-plan en-têtes', 'steam-server-status'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .steam-status-table th',
            ]
        );

        $this->add_control(
            'table_header_text_color',
            [
                'label' => esc_html__('Couleur texte en-têtes', 'steam-server-status'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .steam-status-table th' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $plugin = SteamServerStatusPlugin::getInstance();
        
        echo '<div class="steam-server-elementor-widget">';
        
        if ($settings['server_selection'] === 'all') {
            $servers = $plugin->getServers();
            
            if (empty($servers)) {
                echo '<div class="steam-status offline">Aucun serveur configuré</div>';
                echo '</div>';
                return;
            }
            
            if ($settings['display_mode'] === 'table') {
                echo $this->renderAllServersTable($servers, $plugin);
            } else {
                echo $this->renderAllServersCards($servers, $plugin, $settings);
            }
        } else {
            $server_id = intval($settings['server_selection']);
            echo $plugin->renderServerForElementor($server_id, [
                'show_name' => $settings['show_server_name'] === '1'
            ]);
        }
        
        echo '</div>';
    }

    private function renderAllServersTable($servers, $plugin) {
        $show_version = get_option('steam_show_version', 1);
        
        $html = '<table class="steam-status-table"><thead><tr><th>Serveur</th><th>Type</th><th>État</th><th>Joueurs</th><th>Latence</th>';
        if ($show_version) {
            $html .= '<th>Version</th>';
        }
        $html .= '</tr></thead><tbody>';
        
        foreach ($servers as $i => $server) {
            $data = $plugin->getServerDataCached($server, $i);
            $status = $data['online'] ? '<span class="online">Online</span>' : '<span class="offline">Offline</span>';
            $players = $data['online'] ? $data['players'] . ' / ' . $data['max'] : '0 / 0';
            
            $game_icon = $plugin->getGameIcon($data['game_type'] ?? 'source_generic');
            $supported_games = $plugin->getSupportedGames();
            $protocol = $plugin->getProtocolFromGameType($data['game_type'] ?? 'source_generic');
            $game_name = $supported_games[$protocol][$data['game_type'] ?? 'source_generic'] ?? 'Inconnu';
            
            $latency_display = '-';
            if ($data['online'] && $data['latency'] !== null && ($server['show_latency'] ?? 0) && get_option('steam_show_latency_global', 1)) {
                $latency_class = $plugin->getLatencyClass($data['latency']);
                $latency_display = sprintf('<span class="latency %s">%dms</span>', $latency_class, $data['latency']);
            }
            
            $version_display = '-';
            if ($show_version && $data['version']) {
                $version_display = esc_html($data['version']);
            }
            
            $html .= sprintf(
                '<tr><td>%s %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>',
                $game_icon,
                esc_html($server['name']),
                $game_name,
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

    private function renderAllServersCards($servers, $plugin, $settings) {
        $show_version = get_option('steam_show_version', 1);
        $show_motd = get_option('steam_show_motd', 1);
        $show_name = $settings['show_server_name'] === '1';
        
        $html = '<div class="steam-cards">';
        
        foreach ($servers as $i => $server) {
            $data = $plugin->getServerDataCached($server, $i);
            $status = $data['online'] ? '<span class="online">Online</span>' : '<span class="offline">Offline</span>';
            $players = $data['online'] ? $data['players'] . ' / ' . $data['max'] : '0 / 0';
            
            $game_icon = $plugin->getGameIcon($data['game_type'] ?? 'source_generic');
            $supported_games = $plugin->getSupportedGames();
            $protocol = $plugin->getProtocolFromGameType($data['game_type'] ?? 'source_generic');
            $game_name = $supported_games[$protocol][$data['game_type'] ?? 'source_generic'] ?? 'Inconnu';
            
            $latency_display = '';
            if ($data['online'] && $data['latency'] !== null && ($server['show_latency'] ?? 0) && get_option('steam_show_latency_global', 1)) {
                $latency_class = $plugin->getLatencyClass($data['latency']);
                $latency_display = sprintf(' <span class="latency %s">(%dms)</span>', $latency_class, $data['latency']);
            }
            
            $version_display = '';
            if ($show_version && $data['version']) {
                $version_display = sprintf('<br><span class="server-info">%s</span>', esc_html($data['version']));
            }
            
            $motd_display = '';
            if ($show_motd && $data['motd'] && $data['protocol'] === 'minecraft') {
                $motd_display = sprintf('<br><span class="motd">%s</span>', esc_html($data['motd']));
            }
            
            $css_class = $data['online'] ? 'online' : 'offline';
            
            $html .= sprintf(
                '<div class="steam-status steam-card steam-status-server-%d %s">%s<strong>%s [%s]</strong><br>%s%s<br>%s%s%s</div>',
                $i,
                $css_class,
                $show_name ? '<strong>' . $game_icon . ' ' . esc_html($server['name']) . '</strong><br>' : '',
                $game_icon,
                $game_name,
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

    protected function content_template() {
        ?>
        <#
        var serverSelection = settings.server_selection;
        var displayMode = settings.display_mode;
        var showName = settings.show_server_name;
        #>
        
        <div class="steam-server-elementor-widget">
            <# if ( serverSelection === 'all' ) { #>
                <# if ( displayMode === 'table' ) { #>
                    <div class="steam-status">Aperçu du tableau des serveurs (non disponible en mode édition)</div>
                <# } else { #>
                    <div class="steam-status online">
                        <# if ( showName === '1' ) { #>
                            <span class="server-name">Exemple Serveur</span>
                        <# } #>
                        <span class="label">Joueurs connectés :</span>
                        <span class="players">12</span>
                        <span class="separator">/</span>
                        <span class="maxplayers">24</span>
                    </div>
                <# } #>
            <# } else { #>
                <div class="steam-status online">
                    <# if ( showName === '1' ) { #>
                        <span class="server-name">Serveur Exemple</span>
                    <# } #>
                    <span class="label">Joueurs connectés :</span>
                    <span class="players">8</span>
                    <span class="separator">/</span>
                    <span class="maxplayers">16</span>
                    <span class="latency good">(45ms)</span>
                </div>
            <# } #>
        </div>
        <?php
    }
}