<?php
/**
 * Classe exécutée lors de l'activation du plugin
 */

class CF_Activator {
    
    /**
     * Actions à effectuer lors de l'activation
     */
    public static function activate() {
        // Créer les tables
        $database = new CF_Database();
        $database->create_tables();
        
        // Créer les dossiers nécessaires s'ils n'existent pas
        self::create_directories();
        
        // Définir les capacités utilisateur
        self::add_capabilities();
        
        // Flush les règles de réécriture
        flush_rewrite_rules();
    }
    
    /**
     * Créer les répertoires nécessaires
     */
    private static function create_directories() {
        $directories = array(
            CF_PLUGIN_PATH . 'calculateurs',
            CF_PLUGIN_PATH . 'data',
            CF_PLUGIN_PATH . 'assets',
            CF_PLUGIN_PATH . 'assets/css',
            CF_PLUGIN_PATH . 'assets/js',
            CF_PLUGIN_PATH . 'includes'
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }
    
    /**
     * Ajouter les capacités nécessaires
     */
    private static function add_capabilities() {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_calculateurs');
        }
    }
}