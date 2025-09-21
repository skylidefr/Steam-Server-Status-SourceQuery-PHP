<?php
/*
Plugin Name: Steam Server Status
Description: Affiche le nombre de joueurs connectés sur un ou plusieurs serveurs Steam compatibles SourceQuery. Compatible Elementor : styles modifiables depuis l'interface.
Version: 1.0
Author: Skylide
*/

require __DIR__ . '/SourceQuery/bootstrap.php';
use xPaw\SourceQuery\SourceQuery;

// ================== PAGE ADMIN ==================
add_action('admin_menu', 'steam_status_menu');
function steam_status_menu() {
    add_options_page(
        'Steam Server Status',
        'Steam Status',
        'manage_options',
        'steam-status',
        'steam_status_settings_page'
    );
}

add_action('admin_init', 'steam_status_register_settings');
function steam_status_register_settings() {
    register_setting('steam_status_options_group', 'steam_servers');
    register_setting('steam_status_options_group', 'steam_show_name'); 
    register_setting('steam_status_options_group', 'steam_cache_duration');
}

function steam_status_settings_page() {
    $servers = get_option('steam_servers', []);
    if (!is_array($servers)) $servers = [];

    $show_name = get_option('steam_show_name', 1);
    $cache_duration = intval(get_option('steam_cache_duration', 15));
    ?>
    <div class="wrap">
        <h1>🎮 Réglages - Steam Server Status</h1>
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

            <h2>Options d’affichage</h2>
            <p>
                <label>
                    <input type="checkbox" name="steam_show_name" value="1" <?php checked(1, $show_name); ?>>
                    Afficher le nom du serveur en front
                </label>
            </p>

            <h2>Cache</h2>
            <p>
                <label>
                    Durée du cache (en secondes) : 
                    <input type="number" name="steam_cache_duration" value="<?php echo esc_attr($cache_duration); ?>" min="5" step="5">
                </label>
                <br><small>Par défaut : 15 secondes.</small>
            </p>

            <?php submit_button(); ?>
        </form>

        <hr>

        <h2>Utilisation</h2>
        <p>Pour afficher le statut d’un serveur Steam dans une page ou un article, utilisez le shortcode suivant :</p>
        <pre>[steam_status id="0"]</pre>

        <p>Options disponibles :</p>
        <ul>
            <li><code>id="0"</code> → identifiant du serveur (0 = premier serveur, 1 = deuxième, etc.)</li>
            <li><code>show_name="1"</code> → afficher le nom du serveur</li>
            <li><code>show_name="0"</code> → masquer le nom du serveur</li>
        </ul>
    </div>

    <script>
        document.getElementById('add-server').addEventListener('click', function() {
            var table = document.querySelector('#steam-servers-table tbody');
            var index = table.rows.length;
            var row = table.insertRow();
            row.innerHTML = `
                <td><input type="text" name="steam_servers[${index}][name]" placeholder="Nom du serveur"></td>
                <td><input type="text" name="steam_servers[${index}][ip]" placeholder="45.90.160.141"></td>
                <td><input type="number" name="steam_servers[${index}][port]" placeholder="27015"></td>
                <td><button type="button" class="button remove-server">❌</button></td>
            `;
            attachRemoveEvents();
        });

        function attachRemoveEvents() {
            document.querySelectorAll('.remove-server').forEach(btn => {
                btn.addEventListener('click', function() {
                    this.closest('tr').remove();
                });
            });
        }
        attachRemoveEvents();
    </script>
    <?php
}

// ================== SHORTCODE ==================
function steam_server_status($atts) {
    $servers = get_option('steam_servers', []);
    if (!is_array($servers) || empty($servers)) return '<div class="steam-status offline">⚠️ Aucun serveur configuré</div>';

    $atts = shortcode_atts([
        'id' => 0,
        'show_name' => get_option('steam_show_name', 1)
    ], $atts, 'steam_status');

    $id = intval($atts['id']);
    $show_name = intval($atts['show_name']);
    $cache_duration = intval(get_option('steam_cache_duration', 15));

    if (!isset($servers[$id])) return '<div class="steam-status offline">⚠️ Serveur introuvable</div>';

    $server = $servers[$id];
    $cache_key = 'steam_status_' . $id;
    $data = get_transient($cache_key);

    if ($data === false) {
        $Query = new SourceQuery();
        try {
            $Query->Connect($server['ip'], $server['port'], 1, SourceQuery::SOURCE);
            $info = $Query->GetInfo();
            $data = [
                'online' => true,
                'players' => $info['Players'],
                'max' => $info['MaxPlayers'],
                'name' => $server['name']
            ];
        } catch(Exception $e) {
            $data = [
                'online' => false,
                'players' => 0,
                'max' => 0,
                'name' => $server['name']
            ];
        } finally {
            $Query->Disconnect();
        }
        set_transient($cache_key, $data, $cache_duration);
    }

    $unique_id = 'steam-status-' . $id;

    if ($data['online']) {
        return '
        <div id="'.$unique_id.'" class="steam-status steam-status-server-'.$id.' online">
            '.($show_name ? '<span class="server-name">'.esc_html($data['name']).'</span><br>' : '').'
            <span class="label">Joueurs connectés :</span>
            <span class="players">'.$data['players'].'</span>
            <span class="separator">/</span>
            <span class="maxplayers">'.$data['max'].'</span>
        </div>';
    } else {
        return '<div id="'.$unique_id.'" class="steam-status steam-status-server-'.$id.' offline">'.($show_name ? esc_html($data['name']).' : ' : '').'Serveur injoignable</div>';
    }
}
add_shortcode('steam_status', 'steam_server_status');
