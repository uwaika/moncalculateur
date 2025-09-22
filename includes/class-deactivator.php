<?php
/**
 * Classe exécutée lors de la désactivation du plugin
 */

class CF_Deactivator {
    
    /**
     * Actions à effectuer lors de la désactivation
     */
    public static function deactivate() {
        // Nettoyer les transients
        self::clear_transients();
        
        // Flush les règles de réécriture
        flush_rewrite_rules();
    }
    
    /**
     * Nettoyer les transients
     */
    private static function clear_transients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_cf_%' 
            OR option_name LIKE '_transient_timeout_cf_%'"
        );
    }
}