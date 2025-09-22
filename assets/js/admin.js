// admin.js - Scripts pour l'interface d'administration

jQuery(document).ready(function($) {
    
    // Fonction de copie dans le presse-papier
    $('.cf-copy-btn, .cf-copy-inline').on('click', function(e) {
        e.preventDefault();
        
        let textToCopy = '';
        
        if ($(this).hasClass('cf-copy-inline')) {
            textToCopy = $(this).data('text');
        } else {
            const targetId = $(this).data('target');
            if (targetId) {
                textToCopy = $('#' + targetId).val();
            } else {
                textToCopy = $(this).prev('input').val();
            }
        }
        
        // Créer un élément temporaire pour copier
        const tempInput = $('<input>');
        $('body').append(tempInput);
        tempInput.val(textToCopy).select();
        
        try {
            document.execCommand('copy');
            
            // Feedback visuel
            const originalText = $(this).html();
            $(this).html('<span class="dashicons dashicons-yes"></span> ' + cf_admin.copy_success);
            
            setTimeout(() => {
                $(this).html(originalText);
            }, 2000);
        } catch (err) {
            alert(cf_admin.copy_error);
        }
        
        tempInput.remove();
    });
    
    // Prévisualisation du calculateur
    $('.cf-preview-btn, .cf-preview-calc').on('click', function(e) {
        e.preventDefault();
        
        const calculatorId = $(this).data('calculator');
        const modal = $('#cf-preview-modal');
        const container = $('#cf-preview-container');
        
        // Charger le calculateur via AJAX
        container.html('<div class="cf-loading"></div> Chargement...');
        modal.show();
        
        $.ajax({
            url: cf_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'cf_preview_calculator',
                nonce: cf_admin.nonce,
                calculator_id: calculatorId
            },
            success: function(response) {
                if (response.success) {
                    container.html(response.data);
                } else {
                    container.html('<div class="cf-error">Erreur lors du chargement</div>');
                }
            },
            error: function() {
                container.html('<div class="cf-error">Erreur de connexion</div>');
            }
        });
    });
    
    // Fermer la modal
    $('.cf-modal-close').on('click', function() {
        $(this).closest('.cf-modal').hide();
    });
    
    // Fermer en cliquant à l'extérieur
    $('.cf-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Réinitialiser les paramètres
    $('.cf-reset-settings').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(cf_admin.confirm_reset)) {
            return;
        }
        
        const calculatorId = $(this).data('calculator');
        const button = $(this);
        
        button.prop('disabled', true);
        
        $.ajax({
            url: cf_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'cf_reset_settings',
                nonce: cf_admin.nonce,
                calculator_id: calculatorId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Erreur lors de la réinitialisation');
                    button.prop('disabled', false);
                }
            },
            error: function() {
                alert('Erreur de connexion');
                button.prop('disabled', false);
            }
        });
    });
    
    // Export de configuration
    $('.cf-export-btn').on('click', function(e) {
        e.preventDefault();
        
        const exportType = $('input[name="export_type"]:checked').val();
        let calculators = [];
        
        if (exportType === 'selected') {
            $('input[name="export_calculators[]"]:checked').each(function() {
                calculators.push($(this).val());
            });
            
            if (calculators.length === 0) {
                alert('Veuillez sélectionner au moins un calculateur');
                return;
            }
        }
        
        $.ajax({
            url: cf_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'cf_export_config',
                nonce: cf_admin.nonce,
                export_type: exportType,
                calculators: calculators
            },
            success: function(response) {
                if (response.success) {
                    // Créer et télécharger le fichier
                    const blob = new Blob([response.data.data], {type: 'application/json'});
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                }
            }
        });
    });
    
    // Export config individuelle
    $('.cf-export-config').on('click', function(e) {
        e.preventDefault();
        
        const calculatorId = $(this).data('calculator');
        
        $.ajax({
            url: cf_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'cf_export_config',
                nonce: cf_admin.nonce,
                export_type: 'selected',
                calculators: [calculatorId]
            },
            success: function(response) {
                if (response.success) {
                    const blob = new Blob([response.data.data], {type: 'application/json'});
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = response.data.filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                }
            }
        });
    });
    
    // Gestion de l'import
    $('.cf-import-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!confirm(cf_admin.confirm_import)) {
            return;
        }
        
        const fileInput = this.import_file;
        const file = fileInput.files[0];
        
        if (!file) {
            alert('Veuillez sélectionner un fichier');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const importData = e.target.result;
            
            $.ajax({
                url: cf_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'cf_import_config',
                    nonce: cf_admin.nonce,
                    import_data: importData,
                    backup_current: $('input[name="backup_current"]').is(':checked')
                },
                success: function(response) {
                    if (response.success) {
                        alert('Import réussi !');
                        location.reload();
                    } else {
                        alert('Erreur lors de l\'import : ' + response.data);
                    }
                }
            });
        };
        reader.readAsText(file);
    });
    
    // Charger un template
    $('.cf-load-template').on('click', function(e) {
        e.preventDefault();
        
        const template = $(this).data('template');
        
        if (!confirm('Charger ce template remplacera la configuration actuelle. Continuer ?')) {
            return;
        }
        
        // Ici, vous pourriez charger des templates prédéfinis
        alert('Template ' + template + ' chargé (fonctionnalité à implémenter)');
    });
    
    // Afficher/masquer les options d'export
    $('input[name="export_type"]').on('change', function() {
        if ($(this).val() === 'selected') {
            $('.cf-calculator-checkboxes').slideDown();
        } else {
            $('.cf-calculator-checkboxes').slideUp();
        }
    });
    
    // Vider le cache
    $('.cf-clear-cache').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Voulez-vous vraiment vider le cache ?')) {
            return;
        }
        
        $.ajax({
            url: cf_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'cf_clear_cache',
                nonce: cf_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Cache vidé avec succès');
                }
            }
        });
    });
    
    // Graphiques si Chart.js est chargé
    if (typeof Chart !== 'undefined') {
        // Graphique d'évolution
        const evolutionCanvas = document.getElementById('cf-evolution-chart');
        if (evolutionCanvas) {
            const evolutionChart = new Chart(evolutionCanvas, {
                type: 'line',
                data: {
                    labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
                    datasets: [{
                        label: 'Vues',
                        data: [12, 19, 3, 5, 2, 3, 7],
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }, {
                        label: 'Calculs',
                        data: [8, 12, 2, 3, 1, 2, 5],
                        borderColor: 'rgb(255, 99, 132)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
        }
        
        // Graphique de répartition
        const repartitionCanvas = document.getElementById('cf-repartition-chart');
        if (repartitionCanvas) {
            const repartitionChart = new Chart(repartitionCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Frais achat', 'Capacité emprunt', 'Mensualités', 'Autres'],
                    datasets: [{
                        data: [300, 150, 100, 50],
                        backgroundColor: [
                            'rgb(255, 99, 132)',
                            'rgb(54, 162, 235)',
                            'rgb(255, 205, 86)',
                            'rgb(75, 192, 192)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        }
    }
    
    // Export des statistiques en CSV
    $('.cf-export-stats').on('click', function(e) {
        e.preventDefault();
        
        const period = $('#cf-stats-period').val();
        
        $.ajax({
            url: cf_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'cf_export_stats_csv',
                nonce: cf_admin.nonce,
                period: period
            },
            success: function(response) {
                if (response.success) {
                    // Créer et télécharger le CSV
                    const blob = new Blob([response.data], {type: 'text/csv'});
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'stats-calculateurs-' + period + '.csv';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                }
            }
        });
    });
    
    // Changement de période des stats
    $('#cf-stats-period').on('change', function() {
        const period = $(this).val();
        window.location.href = window.location.pathname + '?page=cf-stats&period=' + period;
    });
});