jQuery(document).ready(function($) {
    // Initialisation du color picker
    $('.steam-color-field').wpColorPicker({
        change: function() {
            updatePreview();
        }
    });
    
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
            $(this).find('input, select').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', name);
                }
            });
        });
    }
    
    // Attacher les événements initiaux
    attachRemoveEvents();
    
    // Gestion de l'ajout de serveur
    $('#add-server').on('click', function() {
        const table = $('#steam-servers-table tbody');
        const index = table.find('tr').length;
        const nonce = steamAdminData.nonce;
        
        const row = `
            <tr>
                <td><input type="text" name="steam_servers[${index}][name]" placeholder="Nom du serveur" class="regular-text"></td>
                <td>
                    <select name="steam_servers[${index}][game_type]" class="regular-text">${steamAdminData.gameOptions}</select>
                </td>
                <td><input type="text" name="steam_servers[${index}][ip]" placeholder="45.90.160.141" class="regular-text"></td>
                <td><input type="number" name="steam_servers[${index}][port]" placeholder="27015" class="small-text"></td>
                <td><input type="checkbox" name="steam_servers[${index}][show_latency]" value="1"></td>
                <td><button type="button" class="button test-server" data-nonce="${nonce}">Tester</button></td>
                <td><button type="button" class="button remove-server">❌</button></td>
            </tr>
        `;
        
        table.append(row);
        attachRemoveEvents();
        attachTestEvents();
    });
    
    // Test de connexion serveur
    function attachTestEvents() {
        $('.test-server').off('click').on('click', function() {
            const button = $(this);
            const row = button.closest('tr');
            const ip = row.find('input[name*="[ip]"]').val();
            const port = row.find('input[name*="[port]"]').val();
            const gameType = row.find('select[name*="[game_type]"]').val();
            const nonce = button.data('nonce');
            
            if (!ip || !port) {
                alert('Veuillez remplir l\'IP et le port');
                return;
            }
            
            if (!validateIP(ip)) {
                alert('Adresse IP invalide : ' + ip);
                return;
            }
            
            if (port < 1 || port > 65535) {
                alert('Port invalide (doit être entre 1 et 65535) : ' + port);
                return;
            }
            
            button.prop('disabled', true).text('Test en cours...');
            
            $.ajax({
                url: steamAdminData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'test_server_connection',
                    nonce: nonce,
                    ip: ip,
                    port: port,
                    game_type: gameType
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        let message = '✅ Connexion réussie !\n\n';
                        message += 'Joueurs : ' + data.players + '/' + data.max + '\n';
                        if (data.latency) {
                            message += 'Latence : ' + data.latency + 'ms\n';
                        }
                        if (data.version) {
                            message += 'Version : ' + data.version;
                        }
                        alert(message);
                    } else {
                        alert('❌ Erreur : ' + (response.data || 'Impossible de se connecter au serveur'));
                    }
                },
                error: function() {
                    alert('❌ Erreur de connexion AJAX');
                },
                complete: function() {
                    button.prop('disabled', false).text('Tester');
                }
            });
        });
    }
    
    attachTestEvents();
    
    // Test du webhook Discord
    $('#test-discord-webhook').on('click', function() {
        const button = $(this);
        const webhookUrl = $('#discord-webhook-url').val();
        const nonce = button.data('nonce');
        
        if (!webhookUrl) {
            alert('Veuillez entrer une URL de webhook Discord');
            return;
        }
        
        if (!webhookUrl.includes('discord.com/api/webhooks/')) {
            alert('URL de webhook invalide. Elle doit commencer par https://discord.com/api/webhooks/');
            return;
        }
        
        button.prop('disabled', true).text('Test en cours...');
        
        $.ajax({
            url: steamAdminData.ajaxUrl,
            method: 'POST',
            data: {
                action: 'test_discord_webhook',
                nonce: nonce,
                webhook_url: webhookUrl
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Test réussi ! Vérifiez votre canal Discord.');
                } else {
                    alert('❌ Erreur : ' + (response.data || 'Impossible de se connecter au webhook'));
                }
            },
            error: function() {
                alert('❌ Erreur de connexion');
            },
            complete: function() {
                button.prop('disabled', false).text('Tester');
            }
        });
    });
    
    // Toggle des options Discord dépendantes
    function toggleDiscordOptions() {
        const isEnabled = $('#discord-enable').is(':checked');
        $('.discord-dependent').toggle(isEnabled);
    }
    
    $('#discord-enable').on('change', toggleDiscordOptions);
    toggleDiscordOptions();
    
    // Toggle des champs dépendants des seuils
    $('#notify-player-threshold').on('change', function() {
        $('input[name="discord_player_threshold_value"]').prop('disabled', !$(this).is(':checked'));
    }).trigger('change');
    
    $('#notify-high-latency').on('change', function() {
        $('input[name="discord_latency_threshold"]').prop('disabled', !$(this).is(':checked'));
    }).trigger('change');
    
    // Validation des champs avant soumission
    $('form').on('submit', function(e) {
        let hasError = false;
        
        $('#steam-servers-table tbody tr').each(function() {
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
        
        // Validation webhook Discord
        const discordEnabled = $('#discord-enable').is(':checked');
        const webhookUrl = $('#discord-webhook-url').val();
        
        if (discordEnabled && webhookUrl && !webhookUrl.includes('discord.com/api/webhooks/')) {
            alert('URL de webhook Discord invalide');
            hasError = true;
        }
        
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
    
    // Aperçu en temps réel des couleurs
    function updatePreview() {
        const textOnline = $('input[name="steam_color_text_online"]').val();
        const textOffline = $('input[name="steam_color_text_offline"]').val();
        const borderOnline = $('input[name="steam_color_border_online"]').val();
        const borderOffline = $('input[name="steam_color_border_offline"]').val();
        
        $('#preview-online').css({
            'color': textOnline,
            'border-color': borderOnline
        });
        
        $('#preview-offline').css({
            'color': textOffline,
            'border-color': borderOffline
        });
    }
    
    // Mise à jour de la prévisualisation au changement
    $('input[name="steam_color_text_online"], input[name="steam_color_text_offline"], input[name="steam_color_border_online"], input[name="steam_color_border_offline"]').on('change', updatePreview);
    
    // Confirmation avant de quitter si modifications non sauvegardées
    let formChanged = false;
    
    $('form :input').on('change', function() {
        formChanged = true;
    });
    
    $('form').on('submit', function() {
        formChanged = false;
    });
    
    $(window).on('beforeunload', function() {
        if (formChanged) {
            return 'Vous avez des modifications non sauvegardées. Voulez-vous vraiment quitter cette page ?';
        }
    });
    
    // Aide contextuelle
    $('.description').css({
        'font-style': 'italic',
        'color': '#666',
        'margin-top': '5px'
    });
});