<?php
/**
 * Classe de calcul pour les frais d'achat immobilier
 */

class CF_Calculator_Frais_Achat {
    
    private $database;
    private $settings;
    private $departements;
    
    public function __construct($database) {
        $this->database = $database;
        $this->load_settings();
        $this->load_departements();
    }
    
    /**
     * Charger les paramètres depuis la BDD
     */
    private function load_settings() {
        $this->settings = $this->database->get_calculator_settings('frais-achat');
        
        // Valeurs par défaut si pas en BDD
        $defaults = array(
            'notaire_neuf_taux' => 2.5,
            'droits_mutation_base' => 4.50,
            'hausse_dmto_2025' => 0.50,
            'taxe_communale' => 1.20,
            'frais_assiette' => 2.37,
            'emoluments_bareme' => json_encode(array(
                array('min' => 0, 'max' => 6500, 'taux' => 3.945),
                array('min' => 6500, 'max' => 17000, 'taux' => 1.627),
                array('min' => 17000, 'max' => 60000, 'taux' => 1.085),
                array('min' => 60000, 'max' => null, 'taux' => 0.814)
            )),
            'frais_divers' => 800,
            'tva_emoluments' => 20,
            'contribution_securite' => 0.10
        );
        
        foreach ($defaults as $key => $value) {
            if (!isset($this->settings[$key])) {
                $this->settings[$key] = $value;
            }
        }
    }
    
    /**
     * Charger les données des départements
     */
    private function load_departements() {
        $departements_file = CF_PLUGIN_PATH . 'data/departements.json';
        
        if (file_exists($departements_file)) {
            $this->departements = json_decode(file_get_contents($departements_file), true);
        } else {
            // Départements par défaut si le fichier n'existe pas
            $this->departements = $this->get_default_departements();
        }
    }
    
    /**
     * Calculer les frais
     */
    public function calculate($data) {
        $budget = floatval($data['budget'] ?? 0);
        $type_achat = sanitize_text_field($data['type'] ?? 'ancien');
        $code_dept = sanitize_text_field($data['departement'] ?? '75');
        $is_primo = ($data['primo'] ?? 'non') === 'oui';
        
        if ($budget <= 0) {
            return array('error' => 'Budget invalide');
        }
        
        // Obtenir le taux DMTO du département
        $dept_info = $this->departements[$code_dept] ?? null;
        if (!$dept_info) {
            $dept_info = array(
                'taux_base' => floatval($this->settings['droits_mutation_base']),
                'hausse_2025' => false
            );
        }
        
        $taux_dept = $dept_info['taux_base'];
        
        // Appliquer la hausse 2025 si applicable
        if ($dept_info['hausse_2025'] && !$is_primo && $type_achat === 'ancien') {
            $taux_dept += floatval($this->settings['hausse_dmto_2025']);
        }
        
        if ($type_achat === 'neuf') {
            return $this->calculate_neuf($budget);
        } else {
            return $this->calculate_ancien($budget, $taux_dept, $is_primo, $dept_info);
        }
    }
    
    /**
     * Calcul pour le neuf
     */
    private function calculate_neuf($budget) {
        $taux_notaire = floatval($this->settings['notaire_neuf_taux']) / 100;
        $montant_max = $budget / (1 + $taux_notaire);
        $frais_notaire = $montant_max * $taux_notaire;
        
        // Détails approximatifs
        $details = array(
            'droits_mutation' => round($montant_max * 0.00715, 2),
            'emoluments' => round($frais_notaire * 0.7, 2),
            'tva' => round($frais_notaire * 0.14, 2),
            'csi' => round(max(15, $montant_max * floatval($this->settings['contribution_securite']) / 100), 2),
            'frais_divers' => floatval($this->settings['frais_divers'])
        );
        
        return array(
            'frais_notaire' => round($frais_notaire, 2),
            'montant_max' => round($montant_max, 2),
            'details' => $details,
            'info_taux' => array(
                'taux_dept' => 'N/A (neuf)',
                'primo' => 'Non applicable',
                'taux_total' => '0,715%'
            )
        );
    }
    
    /**
     * Calcul pour l'ancien
     */
    private function calculate_ancien($budget, $taux_dept, $is_primo, $dept_info) {
        // Calcul du taux total DMTO
        $taxe_communale = floatval($this->settings['taxe_communale']);
        $frais_assiette = floatval($this->settings['frais_assiette']) / 100;
        $taux_dmto_total = ($taux_dept * (1 + $frais_assiette)) + $taxe_communale;
        
        // Itération pour trouver le montant exact
        $montant_max = $budget;
        $frais_notaire_total = 0;
        
        // 10 itérations pour affiner le calcul
        for ($i = 0; $i < 10; $i++) {
            $montant_test = $montant_max - $frais_notaire_total;
            
            // Calcul des droits de mutation
            $droits_mutation = $montant_test * $taux_dmto_total / 100;
            
            // Calcul des émoluments
            $emoluments = $this->calculate_emoluments($montant_test);
            
            // TVA sur émoluments
            $tva = $emoluments * floatval($this->settings['tva_emoluments']) / 100;
            
            // Contribution de sécurité immobilière
            $csi = max(15, $montant_test * floatval($this->settings['contribution_securite']) / 100);
            
            // Frais divers
            $frais_divers = floatval($this->settings['frais_divers']);
            
            // Total des frais
            $frais_notaire_total = $droits_mutation + $emoluments + $tva + $csi + $frais_divers;
        }
        
        $montant_max = $budget - $frais_notaire_total;
        
        return array(
            'frais_notaire' => round($frais_notaire_total, 2),
            'montant_max' => round($montant_max, 2),
            'details' => array(
                'droits_mutation' => round($droits_mutation, 2),
                'emoluments' => round($emoluments, 2),
                'tva' => round($tva, 2),
                'csi' => round($csi, 2),
                'frais_divers' => round($frais_divers, 2)
            ),
            'info_taux' => array(
                'taux_dept' => number_format($taux_dept, 2) . '%' . 
                    ($dept_info['hausse_2025'] && !$is_primo ? ' (hausse 2025)' : ''),
                'primo' => $is_primo ? 'Oui (exempté)' : 'Non',
                'taux_total' => number_format($taux_dmto_total, 3) . '%'
            )
        );
    }
    
    /**
     * Calculer les émoluments selon le barème
     */
    private function calculate_emoluments($montant) {
        $bareme = json_decode($this->settings['emoluments_bareme'], true);
        if (!is_array($bareme)) {
            return $montant * 0.01; // Fallback 1%
        }
        
        $emoluments = 0;
        
        foreach ($bareme as $tranche) {
            if ($montant > $tranche['min']) {
                $montant_tranche = $tranche['max'] ? 
                    min($montant, $tranche['max']) - $tranche['min'] : 
                    $montant - $tranche['min'];
                
                $emoluments += $montant_tranche * $tranche['taux'] / 100;
            }
        }
        
        return $emoluments;
    }
    
    /**
     * Obtenir les départements par défaut
     */
    private function get_default_departements() {
        // Liste simplifiée pour le fallback
        return array(
            '01' => array('nom' => 'Ain', 'taux_base' => 4.50, 'hausse_2025' => false),
            '75' => array('nom' => 'Paris', 'taux_base' => 4.50, 'hausse_2025' => true),
            '69' => array('nom' => 'Rhône', 'taux_base' => 4.50, 'hausse_2025' => true),
            '13' => array('nom' => 'Bouches-du-Rhône', 'taux_base' => 4.50, 'hausse_2025' => true),
            '33' => array('nom' => 'Gironde', 'taux_base' => 4.50, 'hausse_2025' => true),
            '36' => array('nom' => 'Indre', 'taux_base' => 3.80, 'hausse_2025' => false),
            '56' => array('nom' => 'Morbihan', 'taux_base' => 3.80, 'hausse_2025' => false)
        );
    }
}