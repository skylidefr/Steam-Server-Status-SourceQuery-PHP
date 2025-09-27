jQuery(document).ready(function($) {
    // Initialisation du color picker
    $('.steam-color-field').wpColorPicker();
    
    // Fonction pour attacher les événements de suppression
    function attachRemoveEvents() {
        $('.remove-server').off('click').on('click', function() {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce serveur ?')) {
                $(this).closest('tr').remove();
                updateServerIndices();
            }
        });
    }
    
    // Fonction pour mettre à jour les indices après suppression
    function updateServerIndices() {
        $('#steam-servers-table tbody tr').each(function(index) {
            $(this).find('input[name*="steam_servers"]').each(function() {
                var name = $(this).attr('name');
                name = name.replace(/\[\d+\]/, '[' + index + ']');
                $(this).attr('name', name);
            });
            $(this).find('select[name*="steam_servers"]').each(function() {
                var name = $(this).attr('name');
                name = name.replace(/\[\d+\]/, '[' + index + ']');
                $(this).attr('name', name);
            });
        });
    }
    
    // Attacher les événements initiaux
    attachRemoveEvents();
    
    // Ajouter le bouton de test pour chaque serveur existant
    $('#steam-servers-table tbody tr').each(function() {
        if ($(this).find('.test-server').length === 0) {
            $(this).find('td:last').before('<td><button type="button" class="button test-server">Tester</button></td>');
        }
    });
    
    // Gestion de l'ajout de serveur
    $('#add-server').on('click', function() {
        const table = $('#steam-servers-table tbody');
        const index = table.find('tr').length;
        
        // Options de jeu organisées par catégorie
        const gameOptions = `
            <optgroup label="Source Engine">
                <option value="source_cs2">Counter-Strike 2</option>
                <option value="source_csgo">CS:GO</option>
                <option value="source_css">Counter-Strike: Source</option>
                <option value="source_tf2">Team Fortress 2</option>
                <option value="source_l4d2">Left 4 Dead 2</option>
                <option value="source_l4d">Left 4 Dead</option>
                <option value="source_gmod">Garry's Mod</option>
                <option value="source_rust">Rust</option>
                <option value="source_ark">ARK: Survival Evolved</option>
                <option value="source_7dtd">7 Days to Die</option>
                <option value="source_insurgency">Insurgency</option>
                <option value="source_kf">Killing Floor</option>
                <option value="source_kf2">Killing Floor 2</option>
                <option value="source_generic">Autre Source Engine</option>
            </optgroup>
            <optgroup label="Goldsource Engine">
                <option value="goldsource_cs16">Counter-Strike 1.6</option>
                <option value="goldsource_hl1">Half-Life 1</option>
                <option value="goldsource_tfc">Team Fortress Classic</option>
                <option value="goldsource_dod">Day of Defeat</option>
                <option value="goldsource_generic">Autre GoldSource</option>
            </optgroup>
            <optgroup label="Minecraft">
                <option value="minecraft">Minecraft</option>
            </optgroup>
        `;
        
        const row = `
            <tr>
                <td><input type="text" name="steam_servers[${index}][name]" placeholder="Nom du serveur" class="regular-text"></td>
                <td>
                    <select name="steam_servers[${index}][game_type]" class="regular-text">${gameOptions}</select>
                </td>
                <td><input type="text" name="steam_servers[${index}][ip]" placeholder="45.90.160.141" class="regular-text"></td>
                <td><input type="number" name="steam_servers[${index}][port]" placeholder="27015" class="small-text"></td>
                <td><input type="checkbox" name="steam_servers[${index}][show_latency]" value="1"></td>
                <td><button type="button" class="button remove-server">❌ Supprimer</button></td>
            </tr>
        `;
        
        table.append(row);
        attachRemoveEvents();
    });
    
    // Validation des champs
    $('form').on('submit', function(e) {
        let hasError = false;
        
        $('#steam-servers-table tbody tr').each(function() {
            const name = $(this).find('input[name*="[name]"]').val();
            const ip = $(this).find('input[name*="[ip]"]').val();
            const port = $(this).find('input[name*="[port]"]').val();
            
            if (ip && !validateIP(ip)) {
                alert('Adresse IP invalide : ' + ip);
                hasError = true;
                return false;
            }
            
            if (port && (port < 1 || port > 65535)) {
                alert('Port invalide (doit être entre 1 et 65535) : ' + port);
                hasError = true;
                return false;
            }
        });
        
        if (hasError) {
            e.preventDefault();
            return false;
        }
    });
    
    // Fonction de validation d'IP
    function validateIP(ip) {
        const ipRegex = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
        const domainRegex = /^([a-zA-Z0-9]+(-[a-zA-Z0-9]+)*\.)+[a-zA-Z]{2,}$/;
        return ipRegex.test(ip) || domainRegex.test(ip);
    }
    
    // Toggle des options avancées
    $('#toggle-advanced').on('click', function() {
        $('.advanced-settings').slideToggle();
        $(this).text($(this).text() === 'Afficher les options avancées' ? 'Masquer les options avancées' : 'Afficher les options avancées');
    });
    
    // Aperçu en temps réel des couleurs
    $('.steam-color-field').on('change', function() {
        updatePreview();
    });
    
    function updatePreview() {
        const textOnline = $('[name="steam_color_text_online"]').val();
        const textOffline = $('[name="steam_color_text_offline"]').val();
        const borderOnline = $('[name="steam_color_border_online"]').val();
        const borderOffline = $('[name="steam_color_border_offline"]').val();
        
        $('#preview-online').css({
            'color': textOnline,
            'border-color': borderOnline
        });
        
        $('#preview-offline').css({
            'color': textOffline,
            'border-color': borderOffline
        });
    }
    
    // Test de connexion serveur
    $(document).on('click', '.test-server', function() {
        const row = $(this).closest('tr');
        const ip = row.find('input[name*="[ip]"]').val();
        const port = row.find('input[name*="[port]"]').val();
        const gameType = row.find('select[name*="[game_type]"]').val();
        
        if (!ip || !port) {
            alert('Veuillez remplir l\'IP et le port');
            return;
        }
        
        const button = $(this);
        button.prop('disabled', true).text('Test en cours...');
        
        // Simuler un test (en production, faire un appel AJAX)
        setTimeout(function() {
            button.prop('disabled', false).text('Tester');
            alert('Test de connexion:\nIP: ' + ip + '\nPort: ' + port + '\nType: ' + gameType + '\n\nNote: Le test réel nécessite une implémentation AJAX côté serveur.');
        }, 1500);
    });
});