<?php
/**
 * Gestionnaire des requêtes AJAX
 */

class CF_Ajax_Handler {
    
    private $database;
    
    public function __construct() {
        $this->database = new CF_Database();
    }
    
    /**
     * Gérer les calculs
     */
    public function handle_calculation() {
        // Vérifier le nonce
        if (!check_ajax_referer('cf_calculator_nonce', 'nonce', false)) {
            wp_send_json_error('Sécurité : nonce invalide');
        }
        
        $calculator_type = sanitize_text_field($_POST['calculator_type'] ?? '');
        $data = $_POST['data'] ?? array();
        
        // Logger l'action
        $this->database->log_action($calculator_type, 'calculation', $data, null);
        
        // Incrémenter les stats
        $this->database->increment_calculator_stats($calculator_type, 'calculations');
        
        // Charger le calculateur approprié
        $calculator_file = CF_PLUGIN_PATH . 'calculateurs/' . $calculator_type . '/calculator.php';
$config_file = CF_PLUGIN_PATH . 'calculateurs/' . $calculator_type . '/config.json';

        
        if (!file_exists($calculator_file)) {
            wp_send_json_error('Calculateur non trouvé');
        }
        
     require_once $calculator_file;

// Le nom de la classe est dans la config
       if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true);
    $calculator_class = $config['calculator_class'] ?? '';
    
    if (class_exists($calculator_class)) {
        $calculator = new $calculator_class($this->database);
        $result = $calculator->calculate($data);
        
        // Logger le résultat
        $this->database->log_action($calculator_type, 'result', $data, $result);
        
        wp_send_json_success($result);
        return; // ← AJOUTEZ ÇA
    } else {
        wp_send_json_error('Classe calculateur non trouvée: ' . $calculator_class);
        return; // ← ET ÇA
    }
} else {
    wp_send_json_error('Fichier config non trouvé: ' . $config_file);
    return; // ← ET ÇA
}
    }
    
    /**
     * Obtenir les données des départements
     */
    public function get_departement_data() {
        check_ajax_referer('cf_calculator_nonce', 'nonce');
        
        $departement = sanitize_text_field($_POST['departement'] ?? '');
        
        $departements_file = CF_PLUGIN_PATH . 'data/departements.json';
        if (!file_exists($departements_file)) {
            wp_send_json_error('Données départements non trouvées');
        }
        
        $departements = json_decode(file_get_contents($departements_file), true);
        
        if (isset($departements[$departement])) {
            wp_send_json_success($departements[$departement]);
        }
        
        wp_send_json_error('Département non trouvé');
    }
    
    /**
     * Tracker l'utilisation
     */
    public function track_usage() {
        check_ajax_referer('cf_calculator_nonce', 'nonce');
        
        $calculator_id = sanitize_text_field($_POST['calculator_id'] ?? '');
        $action = sanitize_text_field($_POST['action_type'] ?? 'view');
        
        if ($calculator_id) {
            $this->database->increment_calculator_stats($calculator_id, $action);
            wp_send_json_success();
        }
        
        wp_send_json_error();
    }
    
    /**
     * Sauvegarder les paramètres (admin)
     */
    public function save_settings() {
        check_ajax_referer('cf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
        }
        
        $calculator_id = sanitize_text_field($_POST['calculator_id'] ?? '');
        $settings = $_POST['settings'] ?? array();
        
        if ($calculator_id && !empty($settings)) {
            $this->database->save_calculator_settings($calculator_id, $settings);
            wp_send_json_success('Paramètres sauvegardés');
        }
        
        wp_send_json_error('Données invalides');
    }
    
    /**
     * Obtenir les statistiques d'un calculateur
     */
    public function get_calculator_stats() {
        check_ajax_referer('cf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
        }
        
        $calculator_id = sanitize_text_field($_POST['calculator_id'] ?? '');
        $period = sanitize_text_field($_POST['period'] ?? '30days');
        
        if ($calculator_id) {
            $stats = $this->database->get_calculator_stats_period($calculator_id, $period);
            wp_send_json_success($stats);
        }
        
        wp_send_json_error();
    }
    
    /**
     * Exporter la configuration
     */
    public function export_config() {
        check_ajax_referer('cf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
        }
        
        $export_type = sanitize_text_field($_POST['export_type'] ?? 'all');
        $calculators = $_POST['calculators'] ?? array();
        
        $export_data = array(
            'version' => CF_VERSION,
            'exported_at' => current_time('mysql'),
            'calculators' => array()
        );
        
        if ($export_type === 'all') {
            // Exporter tous les calculateurs
            $calculateurs_dir = CF_PLUGIN_PATH . 'calculateurs/';
            $dirs = scandir($calculateurs_dir);
            
            foreach ($dirs as $dir) {
                if ($dir !== '.' && $dir !== '..' && $dir !== 'template-calculateur') {
                    $config_file = $calculateurs_dir . $dir . '/config.json';
                    if (file_exists($config_file)) {
                        $config = json_decode(file_get_contents($config_file), true);
                        $calc_id = $config['id'] ?? $dir;
                        
                        $export_data['calculators'][$calc_id] = array(
                            'config' => $config,
                            'settings' => $this->database->get_calculator_settings($calc_id)
                        );
                    }
                }
            }
        } else {
            // Exporter les calculateurs sélectionnés
            foreach ($calculators as $calc_id) {
                $calc_id = sanitize_text_field($calc_id);
                $config_file = CF_PLUGIN_PATH . 'calculateurs/' . $calc_id . '/config.json';
                
                if (file_exists($config_file)) {
                    $config = json_decode(file_get_contents($config_file), true);
                    
                    $export_data['calculators'][$calc_id] = array(
                        'config' => $config,
                        'settings' => $this->database->get_calculator_settings($calc_id)
                    );
                }
            }
        }
        
        wp_send_json_success(array(
            'filename' => 'cf-export-' . date('Y-m-d-His') . '.json',
            'data' => json_encode($export_data, JSON_PRETTY_PRINT)
        ));
    }
    
    /**
     * Importer la configuration
     */
    public function import_config() {
        check_ajax_referer('cf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
        }
        
        $import_data = json_decode(stripslashes($_POST['import_data'] ?? ''), true);
        
        if (!$import_data || !isset($import_data['calculators'])) {
            wp_send_json_error('Données d\'import invalides');
        }
        
        // Faire un backup si demandé
        if ($_POST['backup_current'] ?? false) {
            // Le backup est déjà fait via export_config
        }
        
        // Importer les configurations
        foreach ($import_data['calculators'] as $calc_id => $data) {
            if (isset($data['settings'])) {
                $this->database->save_calculator_settings($calc_id, $data['settings']);
            }
        }
        
        wp_send_json_success('Import réussi');
    }
    
    /**
     * Réinitialiser les paramètres
     */
    public function reset_settings() {
        check_ajax_referer('cf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
        }
        
        $calculator_id = sanitize_text_field($_POST['calculator_id'] ?? '');
        
        if ($calculator_id) {
            $this->database->reset_calculator_settings($calculator_id);
            wp_send_json_success('Paramètres réinitialisés');
        }
        
        wp_send_json_error();
    }
}