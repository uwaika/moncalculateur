// global.js - Scripts globaux pour tous les calculateurs

(function($) {
    'use strict';
    
    // Vérifier que jQuery est chargé
    if (typeof jQuery === 'undefined') {
        console.error('jQuery n\'est pas chargé');
        return;
    }
    
    // Objet global pour les calculateurs
    window.CFCalculators = window.CFCalculators || {};
    
 
    
    // Fonction d'affichage des résultats
    window.displayResults = function(data) {
        const formatNumber = (num) => {
            return new Intl.NumberFormat('fr-FR', {
                style: 'decimal',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(Math.round(num));
        };
        
        // Résultats principaux
        $('#cf-frais-notaire').text(formatNumber(data.frais_notaire) + ' €');
        $('#cf-montant-max').text(formatNumber(data.montant_max) + ' €');
        
        // Détails
        if (data.details) {
            $('#cf-droits-mutation').text(formatNumber(data.details.droits_mutation) + ' €');
            $('#cf-emoluments').text(formatNumber(data.details.emoluments) + ' €');
            $('#cf-tva').text(formatNumber(data.details.tva) + ' €');
            $('#cf-csi').text(formatNumber(data.details.csi) + ' €');
            $('#cf-frais-divers').text(formatNumber(data.details.frais_divers) + ' €');
            $('#cf-total-notaire').text(formatNumber(data.frais_notaire) + ' €');
            
            // Informations sur les taux
            if (data.info_taux) {
                $('#cf-info-taux-dept').text(data.info_taux.taux_dept);
                $('#cf-info-primo').text(data.info_taux.primo);
                $('#cf-info-taux-total').text(data.info_taux.taux_total);
            }
            
            $('#cf-details').slideDown();
        }
    };
    
    // Au chargement du DOM
    $(document).ready(function() {
        
        // Initialiser les événements pour chaque calculateur présent
        $('.cf-calculator').each(function() {
            const $calc = $(this);
            const type = $calc.data('type');
            
            // Tracker la vue
            if (typeof cf_ajax !== 'undefined' && cf_ajax.ajax_url) {
                $.post(cf_ajax.ajax_url, {
                    action: 'cf_track_usage',
                    nonce: cf_ajax.nonce,
                    calculator_id: type,
                    action_type: 'views'
                });
            }
        });
        
        // Gestion du changement de département
        $(document).on('change', '#cf-departement', function() {
            const code = $(this).val();
            const $infoElement = $('#cf-dept-info');
            const $infoText = $infoElement.find('.cf-info-text');
            
            if (code && cf_ajax.departements && cf_ajax.departements[code]) {
                const deptInfo = cf_ajax.departements[code];
                let message = `Taux de base : ${deptInfo.taux_base.toFixed(2)}%`;
                
                if (deptInfo.hausse_2025) {
                    message += ' | ⚠️ Hausse 2025 : +0,50% (sauf primo-accédants)';
                    $infoElement.addClass('cf-hausse-warning');
                } else {
                    $infoElement.removeClass('cf-hausse-warning');
                }
                
                $infoText.text(message);
                $infoElement.slideDown();
            } else {
                $infoElement.slideUp();
            }
        });
        
        // Gestion du changement de statut primo-accédant
        $(document).on('change', 'input[name="cf-primo"]', function() {
            const isPrimo = $(this).val() === 'oui';
            const typeAchat = $('input[name="cf-type"]:checked').val();
            const $primoInfo = $('.cf-primo-info');
            
            if (isPrimo && typeAchat === 'ancien') {
                $primoInfo.slideDown();
                
                // Mettre à jour l'info département si sélectionné
                const codeDept = $('#cf-departement').val();
                if (codeDept && cf_ajax.departements && cf_ajax.departements[codeDept]) {
                    const deptInfo = cf_ajax.departements[codeDept];
                    if (deptInfo.hausse_2025) {
                        const $infoText = $('#cf-dept-info .cf-info-text');
                        let message = `Taux de base : ${deptInfo.taux_base.toFixed(2)}%`;
                        message += ' | ✅ Hausse 2025 non applicable (primo-accédant)';
                        $infoText.text(message);
                    }
                }
            } else {
                $primoInfo.slideUp();
                
                // Restaurer l'info département normale
                const codeDept = $('#cf-departement').val();
                if (codeDept) {
                    $('#cf-departement').trigger('change');
                }
            }
        });
        
        // Gestion du changement de type d'achat
        $(document).on('change', 'input[name="cf-type"]', function() {
            const typeAchat = $(this).val();
            const isPrimo = $('input[name="cf-primo"]:checked').val() === 'oui';
            
            if (typeAchat === 'neuf') {
                $('.cf-primo-info').slideUp();
            } else if (isPrimo) {
                $('.cf-primo-info').slideDown();
            }
        });
        
        // Bouton calculer
        $(document).on('click', '#cf-calculate', function(e) {
            e.preventDefault();
            calculateFraisAchat();
        });
        
        // Enter pour calculer
        $(document).on('keypress', '#cf-budget', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                calculateFraisAchat();
            }
        });
        
        // Formater l'input du budget
        $(document).on('blur', '#cf-budget', function() {
            const value = parseFloat($(this).val());
            if (!isNaN(value)) {
                $(this).val(Math.round(value));
            }
        });
        
        // Auto-focus sur le premier champ
        $('#cf-budget').focus();
    });
    
    // Exposer les fonctions utiles
    window.CFCalculators = {
        calculate: calculateFraisAchat,
        displayResults: displayResults,
        formatNumber: function(num) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'decimal',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(Math.round(num));
        }
    };
    
})(jQuery);