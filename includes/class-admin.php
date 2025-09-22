<?php
/**
 * Classe de gestion de l'interface d'administration
 */

class CF_Admin {
    
    private $calculateurs;
    private $database;
    
    public function __construct($calculateurs) {
        $this->calculateurs = $calculateurs;
        $this->database = new CF_Database();
    }
    
    /**
     * Ajouter les menus d'administration
     */
    public function add_admin_menu() {
        // Menu principal avec dashboard
        add_menu_page(
            'Calculateurs Financiers',
            'Calculateurs',
            'manage_options',
            'cf-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-calculator',
            30
        );
        
        // Sous-menu Dashboard (remplace le menu principal)
        add_submenu_page(
            'cf-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'cf-dashboard',
            array($this, 'render_dashboard')
        );
        
        // Sous-menu pour chaque calculateur
        foreach ($this->calculateurs as $calc_id => $calc) {
            add_submenu_page(
                'cf-dashboard',
                $calc['name'],
                $calc['name'],
                'manage_options',
                'cf-calc-' . $calc_id,
                array($this, 'render_calculator_settings')
            );
        }
        
        // Sous-menu Shortcodes
        add_submenu_page(
            'cf-dashboard',
            'Shortcodes',
            'Shortcodes',
            'manage_options',
            'cf-shortcodes',
            array($this, 'render_shortcodes_page')
        );
        
        // Sous-menu Import/Export
        add_submenu_page(
            'cf-dashboard',
            'Import/Export',
            'Import/Export',
            'manage_options',
            'cf-import-export',
            array($this, 'render_import_export')
        );
        
        // Sous-menu Statistiques
        add_submenu_page(
            'cf-dashboard',
            'Statistiques',
            'Statistiques',
            'manage_options',
            'cf-stats',
            array($this, 'render_statistics')
        );
    }
    
    /**
     * Render du Dashboard principal
     */
    public function render_dashboard() {
        // Récupérer les stats globales
        $total_views = $this->database->get_total_views();
        $total_calculations = $this->database->get_total_calculations();
        $active_calculators = count($this->calculateurs);
        
        ?>
        <div class="wrap cf-admin-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-calculator"></span>
                Dashboard Calculateurs Financiers
            </h1>
            
            <!-- Cartes de statistiques -->
            <div class="cf-stats-cards">
                <div class="cf-stat-card">
                    <div class="cf-stat-icon">
                        <span class="dashicons dashicons-visibility"></span>
                    </div>
                    <div class="cf-stat-content">
                        <div class="cf-stat-number"><?php echo number_format($total_views); ?></div>
                        <div class="cf-stat-label">Vues totales</div>
                    </div>
                </div>
                
                <div class="cf-stat-card">
                    <div class="cf-stat-icon">
                        <span class="dashicons dashicons-chart-bar"></span>
                    </div>
                    <div class="cf-stat-content">
                        <div class="cf-stat-number"><?php echo number_format($total_calculations); ?></div>
                        <div class="cf-stat-label">Calculs effectués</div>
                    </div>
                </div>
                
                <div class="cf-stat-card">
                    <div class="cf-stat-icon">
                        <span class="dashicons dashicons-admin-plugins"></span>
                    </div>
                    <div class="cf-stat-content">
                        <div class="cf-stat-number"><?php echo $active_calculators; ?></div>
                        <div class="cf-stat-label">Calculateurs actifs</div>
                    </div>
                </div>
                
                <div class="cf-stat-card">
                    <div class="cf-stat-icon">
                        <span class="dashicons dashicons-performance"></span>
                    </div>
                    <div class="cf-stat-content">
                        <div class="cf-stat-number">
                            <?php 
                            echo $total_views > 0 ? 
                                number_format(($total_calculations / $total_views) * 100, 1) . '%' : 
                                '0%'; 
                            ?>
                        </div>
                        <div class="cf-stat-label">Taux de conversion</div>
                    </div>
                </div>
            </div>
            
            <!-- Grille des calculateurs -->
            <h2>Calculateurs disponibles</h2>
            <div class="cf-calculators-grid">
                <?php foreach ($this->calculateurs as $calc_id => $calc): 
                    $stats = $this->database->get_calculator_stats($calc_id);
                ?>
                <div class="cf-calculator-card">
                    <div class="cf-card-header">
                        <h3><?php echo esc_html($calc['name']); ?></h3>
                        <span class="cf-version">v<?php echo esc_html($calc['version']); ?></span>
                    </div>
                    
                    <div class="cf-card-body">
                        <p class="cf-card-description">
                            <?php echo esc_html($calc['description']); ?>
                        </p>
                        
                        <!-- Mini stats -->
                        <div class="cf-mini-stats">
                            <div class="cf-mini-stat">
                                <span class="dashicons dashicons-visibility"></span>
                                <span><?php echo number_format($stats['views'] ?? 0); ?> vues</span>
                            </div>
                            <div class="cf-mini-stat">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <span><?php echo number_format($stats['calculations'] ?? 0); ?> calculs</span>
                            </div>
                        </div>
                        
                        <!-- Shortcode avec bouton copier -->
                        <div class="cf-shortcode-display">
                            <input type="text" 
                                   readonly 
                                   value='[calculateur type="<?php echo esc_attr($calc['shortcode']); ?>"]'
                                   class="cf-shortcode-input"
                                   id="shortcode-<?php echo esc_attr($calc_id); ?>">
                            <button class="button cf-copy-btn" 
                                    data-target="shortcode-<?php echo esc_attr($calc_id); ?>">
                                <span class="dashicons dashicons-clipboard"></span>
                                Copier
                            </button>
                        </div>
                    </div>
                    
                    <div class="cf-card-footer">
                        <a href="<?php echo admin_url('admin.php?page=cf-calc-' . $calc_id); ?>" 
                           class="button button-primary">
                            <span class="dashicons dashicons-admin-settings"></span>
                            Configurer
                        </a>
                        <a href="#" 
                           class="button cf-preview-btn"
                           data-calculator="<?php echo esc_attr($calc_id); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                            Prévisualiser
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Actions rapides -->
            <div class="cf-quick-actions">
                <h2>Actions rapides</h2>
                <div class="cf-actions-grid">
                    <a href="<?php echo admin_url('admin.php?page=cf-shortcodes'); ?>" class="button button-large">
                        <span class="dashicons dashicons-editor-code"></span>
                        Voir tous les shortcodes
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=cf-import-export'); ?>" class="button button-large">
                        <span class="dashicons dashicons-migrate"></span>
                        Import/Export config
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=cf-stats'); ?>" class="button button-large">
                        <span class="dashicons dashicons-chart-area"></span>
                        Statistiques détaillées
                    </a>
                    <button class="button button-large cf-clear-cache">
                        <span class="dashicons dashicons-trash"></span>
                        Vider le cache
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Modal de prévisualisation -->
        <div id="cf-preview-modal" class="cf-modal" style="display: none;">
            <div class="cf-modal-content">
                <span class="cf-modal-close">&times;</span>
                <h2>Prévisualisation</h2>
                <div id="cf-preview-container"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Page des shortcodes
     */
    public function render_shortcodes_page() {
        ?>
        <div class="wrap cf-admin-wrap">
            <h1>
                <span class="dashicons dashicons-editor-code"></span>
                Shortcodes disponibles
            </h1>
            
            <div class="cf-notice cf-notice-info">
                <p>
                    <strong>Astuce :</strong> Copiez et collez ces shortcodes dans vos pages ou articles pour afficher les calculateurs.
                    Vous pouvez aussi personnaliser l'apparence avec les paramètres optionnels.
                </p>
            </div>
            
            <div class="cf-shortcodes-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="25%">Calculateur</th>
                            <th width="35%">Shortcode de base</th>
                            <th width="40%">Paramètres optionnels</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->calculateurs as $calc_id => $calc): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($calc['name']); ?></strong>
                                <br>
                                <span class="description"><?php echo esc_html($calc['description']); ?></span>
                            </td>
                            <td>
                                <div class="cf-shortcode-cell">
                                    <code class="cf-code-block">[calculateur type="<?php echo esc_attr($calc['shortcode']); ?>"]</code>
                                    <button class="button button-small cf-copy-inline" 
                                            data-text='[calculateur type="<?php echo esc_attr($calc['shortcode']); ?>"]'>
                                        Copier
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div class="cf-params-examples">
                                    <p><strong>Largeur personnalisée :</strong></p>
                                    <code>[calculateur type="<?php echo esc_attr($calc['shortcode']); ?>" width="80%"]</code>
                                    
                                    <p><strong>Alignement :</strong></p>
                                    <code>[calculateur type="<?php echo esc_attr($calc['shortcode']); ?>" align="left"]</code>
                                    
                                    <p><strong>Thème :</strong></p>
                                    <code>[calculateur type="<?php echo esc_attr($calc['shortcode']); ?>" theme="dark"]</code>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="cf-shortcode-help">
                <h2>Guide d'utilisation</h2>
                
                <div class="cf-help-grid">
                    <div class="cf-help-card">
                        <h3>Dans l'éditeur classique</h3>
                        <p>Collez simplement le shortcode dans votre contenu où vous souhaitez afficher le calculateur.</p>
                    </div>
                    
                    <div class="cf-help-card">
                        <h3>Dans Gutenberg</h3>
                        <p>Utilisez le bloc "Shortcode" et collez le code à l'intérieur.</p>
                    </div>
                    
                    <div class="cf-help-card">
                        <h3>Dans Elementor</h3>
                        <p>Utilisez le widget "Shortcode" et collez le code dans le champ prévu.</p>
                    </div>
                    
                    <div class="cf-help-card">
                        <h3>Dans un widget</h3>
                        <p>Ajoutez un widget "Texte" ou "HTML personnalisé" et collez le shortcode.</p>
                    </div>
                </div>
                
                <h3>Paramètres disponibles</h3>
                <table class="cf-params-table">
                    <tr>
                        <td><code>type</code></td>
                        <td>Identifiant du calculateur (obligatoire)</td>
                    </tr>
                    <tr>
                        <td><code>width</code></td>
                        <td>Largeur du calculateur (ex: "100%", "600px", "80%")</td>
                    </tr>
                    <tr>
                        <td><code>align</code></td>
                        <td>Alignement : "left", "center", "right"</td>
                    </tr>
                    <tr>
                        <td><code>theme</code></td>
                        <td>Thème visuel : "default", "dark", "minimal"</td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Page des paramètres d'un calculateur
     */
    public function render_calculator_settings() {
        $page = $_GET['page'] ?? '';
        $calc_id = str_replace('cf-calc-', '', $page);
        
        if (!isset($this->calculateurs[$calc_id])) {
            echo '<div class="error"><p>Calculateur non trouvé.</p></div>';
            return;
        }
        
        $calculator = $this->calculateurs[$calc_id];
        $settings = $this->database->get_calculator_settings($calc_id);
        
        // Traitement du formulaire
        if (isset($_POST['submit'])) {
            check_admin_referer('cf_save_settings_' . $calc_id);
            $this->save_calculator_settings($calc_id, $_POST['settings'] ?? array());
            echo '<div class="notice notice-success"><p>Paramètres sauvegardés avec succès !</p></div>';
            $settings = $this->database->get_calculator_settings($calc_id);
        }
        
        ?>
        <div class="wrap cf-admin-wrap">
            <h1>
                <?php echo esc_html($calculator['name']); ?>
                <span class="cf-version-badge">v<?php echo esc_html($calculator['version']); ?></span>
            </h1>
            
            <div class="cf-settings-container">
                <div class="cf-settings-main">
                    <form method="post" action="" class="cf-settings-form">
                        <?php wp_nonce_field('cf_save_settings_' . $calc_id); ?>
                        
                        <div class="cf-settings-section">
                            <h2>Paramètres du calculateur</h2>
                            
                            <?php 
                            // Générer les champs depuis la config
                            if (isset($calculator['parameters'])) {
                                foreach ($calculator['parameters'] as $param_key => $param) {
                                    if (!isset($param['editable']) || $param['editable'] !== true) {
                                        continue;
                                    }
                                    
                                    $current_value = $settings[$param_key] ?? $param['default'];
                                    ?>
                                    <div class="cf-field-group">
                                        <label for="<?php echo esc_attr($param_key); ?>">
                                            <?php echo esc_html($param['label']); ?>
                                            <?php if (isset($param['help'])): ?>
                                                <span class="cf-help-icon" title="<?php echo esc_attr($param['help']); ?>">?</span>
                                            <?php endif; ?>
                                        </label>
                                        
                                        <?php
                                        // Afficher le bon type de champ selon le type
                                        switch ($param['type']) {
                                            case 'float':
                                            case 'number':
                                                ?>
                                                <input type="number" 
                                                       step="<?php echo $param['type'] === 'float' ? '0.01' : '1'; ?>"
                                                       id="<?php echo esc_attr($param_key); ?>"
                                                       name="settings[<?php echo esc_attr($param_key); ?>]"
                                                       value="<?php echo esc_attr($current_value); ?>"
                                                       class="regular-text">
                                                <?php
                                                break;
                                                
                                            case 'text':
                                                ?>
                                                <input type="text"
                                                       id="<?php echo esc_attr($param_key); ?>"
                                                       name="settings[<?php echo esc_attr($param_key); ?>]"
                                                       value="<?php echo esc_attr($current_value); ?>"
                                                       class="regular-text">
                                                <?php
                                                break;
                                                
                                            case 'select':
                                                ?>
                                                <select id="<?php echo esc_attr($param_key); ?>"
                                                        name="settings[<?php echo esc_attr($param_key); ?>]">
                                                    <?php foreach ($param['options'] as $opt_value => $opt_label): ?>
                                                        <option value="<?php echo esc_attr($opt_value); ?>"
                                                                <?php selected($current_value, $opt_value); ?>>
                                                            <?php echo esc_html($opt_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php
                                                break;
                                                
                                            case 'array':
                                                // Pour les barèmes et tableaux
                                                if ($param_key === 'emoluments_bareme') {
                                                    $bareme = is_array($current_value) ? $current_value : json_decode($current_value, true);
                                                    ?>
                                                    <div class="cf-bareme-editor">
                                                        <?php foreach ($bareme as $i => $tranche): ?>
                                                            <div class="cf-bareme-row">
                                                                <input type="number" 
                                                                       step="0.001"
                                                                       name="settings[<?php echo esc_attr($param_key); ?>][<?php echo $i; ?>][taux]"
                                                                       value="<?php echo esc_attr($tranche['taux']); ?>"
                                                                       class="small-text"> %
                                                                <span class="cf-bareme-range">
                                                                    (<?php echo number_format($tranche['min']); ?>€ 
                                                                    <?php echo $tranche['max'] ? '- ' . number_format($tranche['max']) . '€' : 'et plus'; ?>)
                                                                </span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php
                                                }
                                                break;
                                        }
                                        ?>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        
                        <div class="cf-settings-actions">
                            <?php submit_button('Sauvegarder les paramètres', 'primary', 'submit', false); ?>
                            <button type="button" class="button cf-reset-settings" 
                                    data-calculator="<?php echo esc_attr($calc_id); ?>">
                                Réinitialiser aux valeurs par défaut
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="cf-settings-sidebar">
                    <!-- Shortcode -->
                    <div class="cf-sidebar-box">
                        <h3>Shortcode</h3>
                        <div class="cf-shortcode-display">
                            <input type="text" 
                                   readonly 
                                   value='[calculateur type="<?php echo esc_attr($calculator['shortcode']); ?>"]'
                                   class="cf-shortcode-input">
                            <button class="button cf-copy-btn">Copier</button>
                        </div>
                    </div>
                    
                    <!-- Statistiques -->
                    <div class="cf-sidebar-box">
                        <h3>Statistiques</h3>
                        <?php $stats = $this->database->get_calculator_stats($calc_id); ?>
                        <ul class="cf-stats-list">
                            <li>
                                <span class="cf-stat-label">Vues :</span>
                                <span class="cf-stat-value"><?php echo number_format($stats['views'] ?? 0); ?></span>
                            </li>
                            <li>
                                <span class="cf-stat-label">Calculs :</span>
                                <span class="cf-stat-value"><?php echo number_format($stats['calculations'] ?? 0); ?></span>
                            </li>
                            <li>
                                <span class="cf-stat-label">Taux conversion :</span>
                                <span class="cf-stat-value">
                                    <?php 
                                    $views = $stats['views'] ?? 0;
                                    $calcs = $stats['calculations'] ?? 0;
                                    echo $views > 0 ? number_format(($calcs / $views) * 100, 1) . '%' : '0%';
                                    ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Actions -->
                    <div class="cf-sidebar-box">
                        <h3>Actions</h3>
                        <button class="button button-secondary cf-preview-calc" 
                                data-calculator="<?php echo esc_attr($calc_id); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                            Prévisualiser
                        </button>
                        <button class="button button-secondary cf-export-config" 
                                data-calculator="<?php echo esc_attr($calc_id); ?>">
                            <span class="dashicons dashicons-download"></span>
                            Exporter config
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Page Import/Export
     */
    public function render_import_export() {
        ?>
        <div class="wrap cf-admin-wrap">
            <h1>Import / Export des configurations</h1>
            
            <div class="cf-import-export-container">
                <!-- Export -->
                <div class="cf-ie-section">
                    <h2>Export</h2>
                    <p>Exportez les configurations de vos calculateurs pour les sauvegarder ou les réutiliser.</p>
                    
                    <div class="cf-export-options">
                        <label>
                            <input type="radio" name="export_type" value="all" checked>
                            Tous les calculateurs
                        </label>
                        <label>
                            <input type="radio" name="export_type" value="selected">
                            Calculateurs sélectionnés
                        </label>
                    </div>
                    
                    <div class="cf-calculator-checkboxes" style="display: none;">
                        <?php foreach ($this->calculateurs as $calc_id => $calc): ?>
                            <label>
                                <input type="checkbox" name="export_calculators[]" value="<?php echo esc_attr($calc_id); ?>">
                                <?php echo esc_html($calc['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <button class="button button-primary cf-export-btn">
                        <span class="dashicons dashicons-download"></span>
                        Télécharger l'export
                    </button>
                </div>
                
                <!-- Import -->
                <div class="cf-ie-section">
                    <h2>Import</h2>
                    <p>Importez une configuration précédemment exportée.</p>
                    
                    <form method="post" enctype="multipart/form-data" class="cf-import-form">
                        <?php wp_nonce_field('cf_import_config'); ?>
                        
                        <input type="file" name="import_file" accept=".json" required>
                        
                        <div class="cf-import-options">
                            <label>
                                <input type="checkbox" name="backup_current" value="1" checked>
                                Sauvegarder la configuration actuelle avant l'import
                            </label>
                        </div>
                        
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-upload"></span>
                            Importer la configuration
                        </button>
                    </form>
                </div>
                
                <!-- Templates -->
                <div class="cf-ie-section">
                    <h2>Templates prédéfinis</h2>
                    <p>Chargez des configurations prédéfinies pour différents cas d'usage.</p>
                    
                    <div class="cf-templates-grid">
                        <div class="cf-template-card">
                            <h3>Configuration 2024</h3>
                            <p>Paramètres standards pour l'année 2024</p>
                            <button class="button cf-load-template" data-template="2024">Charger</button>
                        </div>
                        
                        <div class="cf-template-card">
                            <h3>Configuration 2025</h3>
                            <p>Inclut la hausse DMTO et les nouveaux taux</p>
                            <button class="button cf-load-template" data-template="2025">Charger</button>
                        </div>
                        
                        <div class="cf-template-card">
                            <h3>Mode démo</h3>
                            <p>Configuration avec des valeurs d'exemple</p>
                            <button class="button cf-load-template" data-template="demo">Charger</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Page des statistiques détaillées
     */
    public function render_statistics() {
        // Période sélectionnée
        $period = $_GET['period'] ?? '7days';
        
        ?>
        <div class="wrap cf-admin-wrap">
            <h1>Statistiques détaillées</h1>
            
            <!-- Sélecteur de période -->
            <div class="cf-stats-toolbar">
                <select id="cf-stats-period" class="cf-period-selector">
                    <option value="7days" <?php selected($period, '7days'); ?>>7 derniers jours</option>
                    <option value="30days" <?php selected($period, '30days'); ?>>30 derniers jours</option>
                    <option value="3months" <?php selected($period, '3months'); ?>>3 derniers mois</option>
                    <option value="year" <?php selected($period, 'year'); ?>>Cette année</option>
                    <option value="all" <?php selected($period, 'all'); ?>>Tout</option>
                </select>
                
                <button class="button cf-export-stats">
                    <span class="dashicons dashicons-download"></span>
                    Exporter CSV
                </button>
            </div>
            
            <!-- Graphiques -->
            <div class="cf-charts-container">
                <div class="cf-chart-box">
                    <h3>Évolution des vues et calculs</h3>
                    <canvas id="cf-evolution-chart"></canvas>
                </div>
                
                <div class="cf-chart-box">
                    <h3>Répartition par calculateur</h3>
                    <canvas id="cf-repartition-chart"></canvas>
                </div>
            </div>
            
            <!-- Tableau détaillé -->
            <h2>Détails par calculateur</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Calculateur</th>
                        <th>Vues</th>
                        <th>Calculs</th>
                        <th>Taux conversion</th>
                        <th>Dernière utilisation</th>
                        <th>Tendance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($this->calculateurs as $calc_id => $calc):
                        $stats = $this->database->get_calculator_stats_period($calc_id, $period);
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($calc['name']); ?></strong>
                        </td>
                        <td><?php echo number_format($stats['views'] ?? 0); ?></td>
                        <td><?php echo number_format($stats['calculations'] ?? 0); ?></td>
                        <td>
                            <?php 
                            $rate = ($stats['views'] ?? 0) > 0 ? 
                                ($stats['calculations'] ?? 0) / $stats['views'] * 100 : 0;
                            echo number_format($rate, 1) . '%';
                            ?>
                        </td>
                        <td><?php echo $stats['last_used'] ?? 'Jamais'; ?></td>
                        <td>
                            <?php 
                            $trend = $stats['trend'] ?? 'stable';
                            $icon = $trend === 'up' ? '↗' : ($trend === 'down' ? '↘' : '→');
                            $color = $trend === 'up' ? 'green' : ($trend === 'down' ? 'red' : 'gray');
                            ?>
                            <span style="color: <?php echo $color; ?>; font-size: 20px;">
                                <?php echo $icon; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Top des valeurs -->
            <h2>Valeurs les plus utilisées</h2>
            <div class="cf-top-values">
                <?php
                $top_values = $this->database->get_top_values($period);
                foreach ($top_values as $calc_id => $values):
                    if (!isset($this->calculateurs[$calc_id])) continue;
                ?>
                <div class="cf-top-values-box">
                    <h3><?php echo esc_html($this->calculateurs[$calc_id]['name']); ?></h3>
                    <table class="cf-values-table">
                        <tr>
                            <td>Budget moyen :</td>
                            <td><?php echo number_format($values['avg_budget'] ?? 0); ?> €</td>
                        </tr>
                        <tr>
                            <td>Budget médian :</td>
                            <td><?php echo number_format($values['median_budget'] ?? 0); ?> €</td>
                        </tr>
                        <tr>
                            <td>Département le plus sélectionné :</td>
                            <td><?php echo $values['top_department'] ?? 'N/A'; ?></td>
                        </tr>
                    </table>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Sauvegarder les paramètres d'un calculateur
     */
    private function save_calculator_settings($calc_id, $settings) {
        // Traiter les données selon le type
        foreach ($settings as $key => $value) {
            if (is_array($value) && isset($value[0]['taux'])) {
                // C'est un barème, on le convertit en JSON
                $settings[$key] = json_encode($value);
            }
        }
        
        $this->database->save_calculator_settings($calc_id, $settings);
    }
    
    /**
     * Charger les assets admin
     */
    public function enqueue_admin_assets($hook) {
        // Charger seulement sur nos pages
        if (strpos($hook, 'cf-') === false && strpos($hook, 'cf_') === false) {
            return;
        }
        
        // CSS Admin
        wp_enqueue_style(
            'cf-admin',
            CF_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CF_VERSION
        );
        
        // JS Admin
        wp_enqueue_script(
            'cf-admin',
            CF_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            CF_VERSION,
            true
        );
        
        // Chart.js pour les statistiques
        if ($hook === 'calculateurs_page_cf-stats' || $hook === 'toplevel_page_cf-dashboard') {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js',
                array(),
                '3.9.1',
                true
            );
        }
        
        // Localisation
        wp_localize_script('cf-admin', 'cf_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cf_admin_nonce'),
            'confirm_reset' => 'Êtes-vous sûr de vouloir réinitialiser aux valeurs par défaut ?',
            'confirm_import' => 'L\'import remplacera les paramètres actuels. Continuer ?',
            'copy_success' => 'Copié !',
            'copy_error' => 'Erreur lors de la copie'
        ));
    }
}