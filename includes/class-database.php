<?php
/**
 * Classe de gestion de la base de données
 */

class CF_Database {
    
    private $wpdb;
    private $table_settings;
    private $table_stats;
    private $table_logs;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Définir les noms des tables
        $this->table_settings = $wpdb->prefix . 'cf_calculator_settings';
        $this->table_stats = $wpdb->prefix . 'cf_calculator_stats';
        $this->table_logs = $wpdb->prefix . 'cf_calculator_logs';
    }
    
    /**
     * Créer les tables nécessaires
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Table des paramètres
        $sql_settings = "CREATE TABLE IF NOT EXISTS {$this->table_settings} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            calculator_type varchar(50) NOT NULL,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY calculator_setting (calculator_type, setting_key)
        ) $charset_collate;";
        
        // Table des statistiques
        $sql_stats = "CREATE TABLE IF NOT EXISTS {$this->table_stats} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            calculator_id varchar(50) NOT NULL,
            stat_type varchar(50) NOT NULL,
            stat_value bigint(20) DEFAULT 0,
            date_recorded date NOT NULL,
            PRIMARY KEY (id),
            KEY calculator_date (calculator_id, date_recorded)
        ) $charset_collate;";
        
        // Table des logs (optionnel pour tracking détaillé)
        $sql_logs = "CREATE TABLE IF NOT EXISTS {$this->table_logs} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            calculator_id varchar(50) NOT NULL,
            action_type varchar(50) NOT NULL,
            input_data longtext,
            output_data longtext,
            ip_address varchar(100),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY calculator_action (calculator_id, action_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_settings);
        dbDelta($sql_stats);
        dbDelta($sql_logs);
    }
    
    /**
     * Obtenir les paramètres d'un calculateur
     */
    public function get_calculator_settings($calculator_id) {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT setting_key, setting_value 
                FROM {$this->table_settings} 
                WHERE calculator_type = %s",
                $calculator_id
            ),
            ARRAY_A
        );
        
        $settings = array();
        foreach ($results as $row) {
            $value = $row['setting_value'];
            
            // Essayer de décoder JSON si c'est du JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
            
            $settings[$row['setting_key']] = $value;
        }
        
        return $settings;
    }
    
    /**
     * Sauvegarder les paramètres d'un calculateur
     */
    public function save_calculator_settings($calculator_id, $settings) {
        foreach ($settings as $key => $value) {
            // Encoder en JSON si c'est un array ou objet
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            
            $this->wpdb->replace(
                $this->table_settings,
                array(
                    'calculator_type' => $calculator_id,
                    'setting_key' => $key,
                    'setting_value' => $value
                ),
                array('%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Incrémenter les statistiques
     */
    public function increment_calculator_stats($calculator_id, $stat_type = 'views') {
        $today = current_time('Y-m-d');
        
        // Vérifier si un enregistrement existe pour aujourd'hui
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_stats} 
                WHERE calculator_id = %s 
                AND stat_type = %s 
                AND date_recorded = %s",
                $calculator_id,
                $stat_type,
                $today
            )
        );
        
        if ($existing) {
            // Incrémenter l'existant
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->table_stats} 
                    SET stat_value = stat_value + 1 
                    WHERE id = %d",
                    $existing
                )
            );
        } else {
            // Créer un nouvel enregistrement
            $this->wpdb->insert(
                $this->table_stats,
                array(
                    'calculator_id' => $calculator_id,
                    'stat_type' => $stat_type,
                    'stat_value' => 1,
                    'date_recorded' => $today
                ),
                array('%s', '%s', '%d', '%s')
            );
        }
    }
    
    /**
     * Obtenir les statistiques d'un calculateur
     */
    public function get_calculator_stats($calculator_id) {
        $stats = array();
        
        // Total des vues
        $stats['views'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(stat_value) 
                FROM {$this->table_stats} 
                WHERE calculator_id = %s 
                AND stat_type = 'views'",
                $calculator_id
            )
        ) ?: 0;
        
        // Total des calculs
        $stats['calculations'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(stat_value) 
                FROM {$this->table_stats} 
                WHERE calculator_id = %s 
                AND stat_type = 'calculations'",
                $calculator_id
            )
        ) ?: 0;
        
        return $stats;
    }
    
    /**
     * Obtenir les statistiques sur une période
     */
    public function get_calculator_stats_period($calculator_id, $period = '7days') {
        $date_from = $this->get_date_from_period($period);
        
        $stats = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT 
                    SUM(CASE WHEN stat_type = 'views' THEN stat_value ELSE 0 END) as views,
                    SUM(CASE WHEN stat_type = 'calculations' THEN stat_value ELSE 0 END) as calculations,
                    MAX(date_recorded) as last_used
                FROM {$this->table_stats} 
                WHERE calculator_id = %s 
                AND date_recorded >= %s",
                $calculator_id,
                $date_from
            ),
            ARRAY_A
        );
        
        // Calculer la tendance
        $stats['trend'] = $this->calculate_trend($calculator_id, $period);
        
        return $stats;
    }
    
    /**
     * Obtenir le total des vues
     */
    public function get_total_views() {
        return $this->wpdb->get_var(
            "SELECT SUM(stat_value) 
            FROM {$this->table_stats} 
            WHERE stat_type = 'views'"
        ) ?: 0;
    }
    
    /**
     * Obtenir le total des calculs
     */
    public function get_total_calculations() {
        return $this->wpdb->get_var(
            "SELECT SUM(stat_value) 
            FROM {$this->table_stats} 
            WHERE stat_type = 'calculations'"
        ) ?: 0;
    }
    
    /**
     * Logger une action
     */
    public function log_action($calculator_id, $action_type, $input_data = null, $output_data = null) {
        $this->wpdb->insert(
            $this->table_logs,
            array(
                'calculator_id' => $calculator_id,
                'action_type' => $action_type,
                'input_data' => is_array($input_data) ? json_encode($input_data) : $input_data,
                'output_data' => is_array($output_data) ? json_encode($output_data) : $output_data,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Obtenir les valeurs moyennes
     */
    public function get_top_values($period = '30days') {
        $date_from = $this->get_date_from_period($period);
        
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT 
                    calculator_id,
                    AVG(CAST(JSON_EXTRACT(input_data, '$.budget') AS UNSIGNED)) as avg_budget,
                    COUNT(*) as count
                FROM {$this->table_logs}
                WHERE action_type = 'calculation'
                AND created_at >= %s
                AND input_data IS NOT NULL
                GROUP BY calculator_id",
                $date_from
            ),
            ARRAY_A
        );
        
        $values = array();
        foreach ($results as $row) {
            $values[$row['calculator_id']] = array(
                'avg_budget' => round($row['avg_budget']),
                'count' => $row['count']
            );
        }
        
        return $values;
    }
    
    /**
     * Calculer la date de début selon la période
     */
    private function get_date_from_period($period) {
        switch ($period) {
            case '7days':
                return date('Y-m-d', strtotime('-7 days'));
            case '30days':
                return date('Y-m-d', strtotime('-30 days'));
            case '3months':
                return date('Y-m-d', strtotime('-3 months'));
            case 'year':
                return date('Y-01-01');
            default:
                return '2000-01-01';
        }
    }
    
    /**
     * Calculer la tendance
     */
    private function calculate_trend($calculator_id, $period) {
        // Simplifier : comparer la dernière semaine avec la précédente
        $last_week = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(stat_value) 
                FROM {$this->table_stats} 
                WHERE calculator_id = %s 
                AND stat_type = 'views'
                AND date_recorded >= %s",
                $calculator_id,
                date('Y-m-d', strtotime('-7 days'))
            )
        ) ?: 0;
        
        $previous_week = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(stat_value) 
                FROM {$this->table_stats} 
                WHERE calculator_id = %s 
                AND stat_type = 'views'
                AND date_recorded >= %s
                AND date_recorded < %s",
                $calculator_id,
                date('Y-m-d', strtotime('-14 days')),
                date('Y-m-d', strtotime('-7 days'))
            )
        ) ?: 0;
        
        if ($last_week > $previous_week * 1.1) {
            return 'up';
        } elseif ($last_week < $previous_week * 0.9) {
            return 'down';
        }
        
        return 'stable';
    }
    
    /**
     * Réinitialiser les paramètres d'un calculateur
     */
    public function reset_calculator_settings($calculator_id) {
        $this->wpdb->delete(
            $this->table_settings,
            array('calculator_type' => $calculator_id),
            array('%s')
        );
    }
    
    /**
     * Nettoyer les vieux logs
     */
    public function cleanup_old_logs($days = 90) {
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_logs} 
                WHERE created_at < %s",
                date('Y-m-d', strtotime("-{$days} days"))
            )
        );
    }
}