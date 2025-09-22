<?php
/**
 * Template pour le calculateur de frais d'achat immobilier
 * Variables disponibles : $calc_config, $calc_settings
 */

// Charger les départements
$departements_file = CF_PLUGIN_PATH . 'data/departements.json';
$departements = file_exists($departements_file) ? 
    json_decode(file_get_contents($departements_file), true) : 
    array();
?>

<div class="cf-calculator" data-type="frais_achat">
    <div class="cf-calculator-container">
        <h3>IMMOBILIER : BUDGET GLOBAL ET FRAIS D'ACHAT</h3>
        
        <div class="cf-form-group">
            <label for="cf-budget">Votre budget global</label>
            <div class="cf-input-group">
                <input type="number" 
                       id="cf-budget" 
                       class="cf-input" 
                       value="0" 
                       min="0" 
                       step="1000"
                       placeholder="Ex: 250000">
                <span class="cf-currency">€</span>
            </div>
        </div>
        
        <div class="cf-form-group">
            <label for="cf-departement">Département du bien</label>
            <select id="cf-departement" class="cf-select">
                <option value="">-- Sélectionnez un département --</option>
                <?php
                foreach ($departements as $code => $dept) {
                    echo '<option value="' . esc_attr($code) . '">';
                    echo esc_html($code . ' - ' . $dept['nom']);
                    if ($dept['taux_base'] == 3.80) {
                        echo ' (taux réduit)';
                    }
                    echo '</option>';
                }
                ?>
            </select>
            <div class="cf-dept-info" id="cf-dept-info" style="display:none;">
                <span class="cf-info-text"></span>
            </div>
        </div>
        
        <div class="cf-form-group">
            <label>Type d'achat</label>
            <div class="cf-radio-group">
                <label class="cf-radio">
                    <input type="radio" name="cf-type" value="neuf" checked>
                    <span>Neuf</span>
                </label>
                <label class="cf-radio">
                    <input type="radio" name="cf-type" value="ancien">
                    <span>Ancien</span>
                </label>
            </div>
        </div>
        
        <div class="cf-form-group">
            <label>Êtes-vous primo-accédant ?</label>
            <div class="cf-radio-group">
                <label class="cf-radio">
                    <input type="radio" name="cf-primo" value="non" checked>
                    <span>Non</span>
                </label>
                <label class="cf-radio">
                    <input type="radio" name="cf-primo" value="oui">
                    <span>Oui (résidence principale)</span>
                </label>
            </div>
            <div class="cf-primo-info" style="display:none;">
                <p class="cf-info-message">
                    ✅ En tant que primo-accédant, vous êtes exempté de la hausse 2025 des droits de mutation.
                </p>
            </div>
        </div>
        
        <div class="cf-result-group">
            <label>Frais de notaire estimés</label>
            <div class="cf-result" id="cf-frais-notaire">0 €</div>
        </div>
        
        <div class="cf-result-group">
            <label>Montant maximum de votre investissement immobilier</label>
            <div class="cf-result" id="cf-montant-max">0 €</div>
        </div>
        
        <button type="button" class="cf-calculate-btn" id="cf-calculate">
            Calculer
        </button>
        
        <div class="cf-details" id="cf-details" style="display:none;">
            <h4>Détail des frais de notaire</h4>
            <table class="cf-details-table">
                <tr>
                    <td>Droits de mutation (DMTO) :</td>
                    <td id="cf-droits-mutation">0 €</td>
                </tr>
                <tr>
                    <td>Émoluments du notaire :</td>
                    <td id="cf-emoluments">0 €</td>
                </tr>
                <tr>
                    <td>TVA sur émoluments :</td>
                    <td id="cf-tva">0 €</td>
                </tr>
                <tr>
                    <td>Contribution sécurité immobilière :</td>
                    <td id="cf-csi">0 €</td>
                </tr>
                <tr>
                    <td>Frais divers et débours :</td>
                    <td id="cf-frais-divers">0 €</td>
                </tr>
                <tr class="cf-total-row">
                    <td><strong>Total frais de notaire :</strong></td>
                    <td><strong id="cf-total-notaire">0 €</strong></td>
                </tr>
            </table>
            
            <div class="cf-taux-info" id="cf-taux-info">
                <p><strong>Information sur les taux appliqués :</strong></p>
                <ul>
                    <li>Taux DMTO département : <span id="cf-info-taux-dept">-</span></li>
                    <li>Statut primo-accédant : <span id="cf-info-primo">-</span></li>
                    <li>Taux total DMTO : <span id="cf-info-taux-total">-</span></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Script spécifique pour ce calculateur
(function() {
    // Attacher l'événement au bouton calculer
    document.getElementById('cf-calculate').addEventListener('click', function() {
        calculateFraisAchat();
    });
    
    // Fonction de calcul
    function calculateFraisAchat() {
        const budget = parseFloat(document.getElementById('cf-budget').value) || 0;
        const departement = document.getElementById('cf-departement').value;
        const typeAchat = document.querySelector('input[name="cf-type"]:checked').value;
        const isPrimo = document.querySelector('input[name="cf-primo"]:checked').value;
        
        if (budget <= 0) {
            alert('Veuillez entrer un budget valide');
            return;
        }
        
        if (!departement) {
            alert('Veuillez sélectionner un département');
            return;
        }
        
        // Appel AJAX
        jQuery.ajax({
            url: cf_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cf_calculate',
                nonce: cf_ajax.nonce,
                calculator_type: 'frais-achat',
                data: {
                    budget: budget,
                    departement: departement,
                    type: typeAchat,
                    primo: isPrimo
                }
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response.data);
                } else {
                    alert('Erreur : ' + (response.data || 'Erreur inconnue'));
                }
            },
            error: function() {
                alert('Erreur lors du calcul');
            }
        });
    }
    
    // Afficher les résultats
    function displayResults(data) {
        const formatNumber = (num) => {
            return new Intl.NumberFormat('fr-FR', {
                style: 'decimal',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(Math.round(num));
        };
        
        // Résultats principaux
        document.getElementById('cf-frais-notaire').textContent = formatNumber(data.frais_notaire) + ' €';
        document.getElementById('cf-montant-max').textContent = formatNumber(data.montant_max) + ' €';
        
        // Détails
        if (data.details) {
            document.getElementById('cf-droits-mutation').textContent = formatNumber(data.details.droits_mutation) + ' €';
            document.getElementById('cf-emoluments').textContent = formatNumber(data.details.emoluments) + ' €';
            document.getElementById('cf-tva').textContent = formatNumber(data.details.tva) + ' €';
            document.getElementById('cf-csi').textContent = formatNumber(data.details.csi) + ' €';
            document.getElementById('cf-frais-divers').textContent = formatNumber(data.details.frais_divers) + ' €';
            document.getElementById('cf-total-notaire').textContent = formatNumber(data.frais_notaire) + ' €';
            
            // Informations sur les taux
            if (data.info_taux) {
                document.getElementById('cf-info-taux-dept').textContent = data.info_taux.taux_dept;
                document.getElementById('cf-info-primo').textContent = data.info_taux.primo;
                document.getElementById('cf-info-taux-total').textContent = data.info_taux.taux_total;
            }
            
            document.getElementById('cf-details').style.display = 'block';
        }
    }
    
    // Gestion du changement de département
    document.getElementById('cf-departement').addEventListener('change', function() {
        const code = this.value;
        const deptInfo = cf_ajax.departements[code];
        const infoElement = document.getElementById('cf-dept-info');
        
        if (deptInfo) {
            let message = `Taux de base : ${deptInfo.taux_base.toFixed(2)}%`;
            
            if (deptInfo.hausse_2025) {
                message += ' | ⚠️ Hausse 2025 : +0,50% (sauf primo-accédants)';
                infoElement.classList.add('cf-hausse-warning');
            } else {
                infoElement.classList.remove('cf-hausse-warning');
            }
            
            infoElement.querySelector('.cf-info-text').textContent = message;
            infoElement.style.display = 'block';
        } else {
            infoElement.style.display = 'none';
        }
    });
    
    // Gestion primo-accédant
    document.querySelectorAll('input[name="cf-primo"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            const primoInfo = document.querySelector('.cf-primo-info');
            if (this.value === 'oui') {
                primoInfo.style.display = 'block';
            } else {
                primoInfo.style.display = 'none';
            }
        });
    });
})();
</script>