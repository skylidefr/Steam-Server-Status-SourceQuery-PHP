<?php
/*
Plugin Name: Valheim Server Status
Description: Affiche le nombre de joueurs connectés sur un ou plusieurs serveurs Valheim. Compatible Elementor : styles modifiables depuis l'interface.
Version: 2.3
Author: Skylide
*/

require __DIR__ . '/SourceQuery/bootstrap.php';
use xPaw\SourceQuery\SourceQuery;

// ================== PAGE ADMIN ==================
add_action('admin_menu', 'valheim_status_menu');
function valheim_status_menu() {
    add_options_page(
        'Valheim Server Status',
        'Valheim Status',
        'manage_options',
        'valheim-status',
        'valheim_status_settings_page'
    );
}

add_action('admin_init', 'valheim_status_register_settings');
function valheim_status_register_settings() {
    register_setting('valheim_status_options_group', 'valheim_servers');
    register_setting('valheim_status_options_group', 'valheim_show_name'); 
    register_setting('valheim_status_options_group', 'valheim_cache_duration');
}

function valheim_status_settings_page() {
    $servers = get_option('valheim_servers', []);
    if (!is_array($servers)) $servers = [];

    $show_name = get_option('valheim_show_name', 1);
    $cache_duration = intval(get_option('valheim_cache_duration', 15));
    ?>
    <div class="wrap">
        <h1>⚔️ Réglages - Valheim Server Status</h1>
        <form method="post" action="options.php">
            <?php settings_fields('valheim_status_options_group'); ?>

            <h2>Configuration des serveurs</h2>
            <table class="form-table" id="valheim-servers-table">
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
                        <td><input type="text" name="valheim_servers[<?php echo $index; ?>][name]" value="<?php echo esc_attr($server['name']); ?>" placeholder="Nom du serveur"></td>
                        <td><input type="text" name="valheim_servers[<?php echo $index; ?>][ip]" value="<?php echo esc_attr($server['ip']); ?>" placeholder="45.90.160.141"></td>
                        <td><input type="number" name="valheim_servers[<?php echo $index; ?>][port]" value="<?php echo esc_attr($server['port']); ?>" placeholder="2457"></td>
                        <td><button type="button" class="button remove-server">❌</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="add-server">➕ Ajouter un serveur</button></p>

            <h2>Options d’affichage</h2>
            <p>
                <label>
                    <input type="checkbox" name="valheim_show_name" value="1" <?php checked(1, $show_name); ?>>
                    Afficher le nom du serveur en front
                </label>
            </p>

            <h2>Cache</h2>
            <p>
                <label>
                    Durée du cache (en secondes) : 
                    <input type="number" name="valheim_cache_duration" value="<?php echo esc_attr($cache_duration); ?>" min="5" step="5">
                </label>
                <br><small>Par défaut : 15 secondes.</small>
            </p>

            <?php submit_button(); ?>
        </form>

        <hr>

        <h2>Utilisation</h2>
        <p>Pour afficher le statut d’un serveur Valheim dans une page ou un article, utilisez le shortcode suivant :</p>
        <pre>[valheim_status id="0"]</pre>

        <p>Options disponibles :</p>
        <ul>
            <li><code>id="0"</code> → identifiant du serveur (0 = premier serveur, 1 = deuxième, etc.)</li>
            <li><code>show_name="1"</code> → afficher le nom du serveur</li>
            <li><code>show_name="0"</code> → masquer le nom du serveur</li>
        </ul>

        <p>Exemple :</p>
        <pre>[valheim_status id="1" show_name="0"]</pre>
    </div>

    <script>
        document.getElementById('add-server').addEventListener('click', function() {
            var table = document.querySelector('#valheim-servers-table tbody');
            var index = table.rows.length;
            var row = table.insertRow();
            row.innerHTML = `
                <td><input type="text" name="valheim_servers[${index}][name]" placeholder="Nom du serveur"></td>
                <td><input type="text" name="valheim_servers[${index}][ip]" placeholder="45.90.160.141"></td>
                <td><input type="number" name="valheim_servers[${index}][port]" placeholder="2457"></td>
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
function valheim_server_status($atts) {
    $servers = get_option('valheim_servers', []);
    if (!is_array($servers) || empty($servers)) return '<div class="valheim-status offline">⚠️ Aucun serveur configuré</div>';

    $atts = shortcode_atts([
        'id' => 0,
        'show_name' => get_option('valheim_show_name', 1)
    ], $atts, 'valheim_status');

    $id = intval($atts['id']);
    $show_name = intval($atts['show_name']);
    $cache_duration = intval(get_option('valheim_cache_duration', 15));

    if (!isset($servers[$id])) return '<div class="valheim-status offline">⚠️ Serveur introuvable</div>';

    $server = $servers[$id];
    $cache_key = 'valheim_status_' . $id;
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

    $unique_id = 'valheim-status-' . $id;

    if ($data['online']) {
        return '
        <div id="'.$unique_id.'" class="valheim-status valheim-status-server-'.$id.' online">
            '.($show_name ? '<span class="server-name">'.esc_html($data['name']).'</span><br>' : '').'
            <span class="label">Joueurs connectés :</span>
            <span class="players">'.$data['players'].'</span>
            <span class="separator">/</span>
            <span class="maxplayers">'.$data['max'].'</span>
        </div>';
    } else {
        return '<div id="'.$unique_id.'" class="valheim-status valheim-status-server-'.$id.' offline">'.($show_name ? esc_html($data['name']).' : ' : '').'Serveur injoignable</div>';
    }
}
add_shortcode('valheim_status', 'valheim_server_status');
