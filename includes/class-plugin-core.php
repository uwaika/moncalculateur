<?php
/**
 * Classe principale du plugin
 * Gère le chargement des modules et l'orchestration générale
 */

class CF_Plugin_Core {
    
    private $calculateurs = array();
    private $loader;
    private $admin;
    private $ajax_handler;
    private $database;
    private $version;
    
    public function __construct() {
        $this->version = CF_VERSION;
        $this->load_dependencies();
        $this->load_calculateurs();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
    
    /**
     * Charger les dépendances requises
     */
    private function load_dependencies() {
        // Loader pour gérer les hooks
        require_once CF_PLUGIN_PATH . 'includes/class-loader.php';
        $this->loader = new CF_Loader();
        
        // Gestionnaire de base de données
        require_once CF_PLUGIN_PATH . 'includes/class-database.php';
        $this->database = new CF_Database();
        
        // Interface d'administration
        require_once CF_PLUGIN_PATH . 'includes/class-admin.php';
        $this->admin = new CF_Admin($this->get_calculateurs_list());
        
        // Gestionnaire AJAX
        require_once CF_PLUGIN_PATH . 'includes/class-ajax-handler.php';
        $this->ajax_handler = new CF_Ajax_Handler();
    }
    
    /**
     * Charger automatiquement tous les calculateurs
     */
 
private function load_calculateurs() {
    $calculateurs_dir = CF_PLUGIN_PATH . 'calculateurs/';
    
    // Ignorer le dossier template
    $ignore = array('template-calculateur', '.', '..');
    
    // Scanner le dossier des calculateurs
    if (is_dir($calculateurs_dir)) {
        $dirs = scandir($calculateurs_dir);
        
        foreach ($dirs as $dir) {
            if (!in_array($dir, $ignore) && is_dir($calculateurs_dir . $dir)) {

                $config_file = $calculateurs_dir . $dir . '/config.json';
                
                if (file_exists($config_file)) {
                    // Lire et nettoyer le contenu
                    $content = file_get_contents($config_file);
                    // Supprimer le BOM si présent
                    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
                    
                    $config = json_decode($content, true);
                    
                    if ($config && isset($config['id'])) {
                        // Ajouter le chemin au config
                        $config['path'] = $calculateurs_dir . $dir . '/';
                        $config['url'] = CF_PLUGIN_URL . 'calculateurs/' . $dir . '/';
                        
                        // Stocker la configuration
                        $this->calculateurs[$config['id']] = $config;
                        
                        // Charger la classe du calculateur si elle existe
                        $calculator_file = $config['path'] . 'calculator.php';
                        if (file_exists($calculator_file)) {
                            require_once $calculator_file;
                        }
                    }
                }
            }
        }
    }
    
    // Si aucun calculateur trouvé, ajouter manuellement frais_achat
    if (empty($this->calculateurs)) {
        $this->calculateurs['frais_achat'] = array(
            'id' => 'frais_achat',
            'name' => 'Frais d\'achat immobilier',
            'shortcode' => 'frais_achat_immobilier',
            'version' => '2.0.0',
            'description' => 'Calcul des frais de notaire',
            'path' => $calculateurs_dir . 'frais-achat/',
            'url' => CF_PLUGIN_URL . 'calculateurs/frais-achat/',
            'calculator_class' => 'CF_Calculator_Frais_Achat',
            'parameters' => array()
        );
        
        // Charger la classe
        $calculator_file = $this->calculateurs['frais_achat']['path'] . 'calculator.php';
        if (file_exists($calculator_file)) {
            require_once $calculator_file;
        }
    }
}



    /**
     * Définir les hooks pour l'administration
     */
    private function define_admin_hooks() {
        // Menus d'administration
        $this->loader->add_action('admin_menu', $this->admin, 'add_admin_menu');
        
        // Scripts et styles admin
        $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_admin_assets');
        
        // AJAX handlers pour l'admin
        $this->loader->add_action('wp_ajax_cf_save_settings', $this->ajax_handler, 'save_settings');
        $this->loader->add_action('wp_ajax_cf_get_calculator_stats', $this->ajax_handler, 'get_calculator_stats');
        $this->loader->add_action('wp_ajax_cf_export_config', $this->ajax_handler, 'export_config');
        $this->loader->add_action('wp_ajax_cf_import_config', $this->ajax_handler, 'import_config');
        $this->loader->add_action('wp_ajax_cf_reset_settings', $this->ajax_handler, 'reset_settings');
    }
    
    /**
     * Définir les hooks pour le frontend
     */
    private function define_public_hooks() {
        // Shortcode principal
        add_shortcode('calculateur', array($this, 'render_calculator_shortcode'));
        
        // Scripts et styles frontend
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_public_assets');
        
        // AJAX handlers pour les calculs
        $this->loader->add_action('wp_ajax_cf_calculate', $this->ajax_handler, 'handle_calculation');
        $this->loader->add_action('wp_ajax_nopriv_cf_calculate', $this->ajax_handler, 'handle_calculation');
        
        // AJAX pour les données départements
        $this->loader->add_action('wp_ajax_cf_get_departement_data', $this->ajax_handler, 'get_departement_data');
        $this->loader->add_action('wp_ajax_nopriv_cf_get_departement_data', $this->ajax_handler, 'get_departement_data');
        
        // Hook pour tracker l'utilisation
        $this->loader->add_action('wp_ajax_cf_track_usage', $this->ajax_handler, 'track_usage');
        $this->loader->add_action('wp_ajax_nopriv_cf_track_usage', $this->ajax_handler, 'track_usage');
    }
    
    /**
     * Charger les assets publics
     */
    public function enqueue_public_assets() {
        // CSS global
        wp_enqueue_style(
            'cf-global',
            CF_PLUGIN_URL . 'assets/css/global.css',
            array(),
            $this->version
        );
        
        // JS global
        wp_enqueue_script(
            'cf-global',
            CF_PLUGIN_URL . 'assets/js/global.js',
            array('jquery'),
            $this->version,
            true
        );
        
        // Charger les données des départements
        $departements_file = CF_PLUGIN_PATH . 'data/departements.json';
        $departements = file_exists($departements_file) ? 
            json_decode(file_get_contents($departements_file), true) : array();
        
        // Localiser le script avec les données nécessaires
        wp_localize_script('cf-global', 'cf_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cf_calculator_nonce'),
            'plugin_url' => CF_PLUGIN_URL,
            'departements' => $departements,
            'calculateurs' => array_map(function($calc) {
                return array(
                    'id' => $calc['id'],
                    'name' => $calc['name'],
                    'shortcode' => '[calculateur type="' . $calc['shortcode'] . '"]'
                );
            }, $this->calculateurs)
        ));
        
        // Charger les assets spécifiques si on est sur une page avec shortcode
        global $post;
        if (is_a($post, 'WP_Post')) {
            foreach ($this->calculateurs as $calc_id => $calc) {
                if (has_shortcode($post->post_content, 'calculateur')) {
                    // Charger le CSS du calculateur si nécessaire
                    $calc_css = $calc['path'] . 'style.css';
                    if (file_exists($calc_css)) {
                        wp_enqueue_style(
                            'cf-calc-' . $calc_id,
                            $calc['url'] . 'style.css',
                            array('cf-global'),
                            $this->version
                        );
                    }
                    
                    // Charger le JS du calculateur si nécessaire
                    $calc_js = $calc['path'] . 'script.js';
                    if (file_exists($calc_js)) {
                        wp_enqueue_script(
                            'cf-calc-' . $calc_id,
                            $calc['url'] . 'script.js',
                            array('jquery', 'cf-global'),
                            $this->version,
                            true
                        );
                    }
                }
            }
        }
    }
    
    /**
     * Renderer pour le shortcode
     */
    public function render_calculator_shortcode($atts) {
    $atts = shortcode_atts(array(
        'type' => '',
        'theme' => 'default',
        'width' => '100%',
        'align' => 'center'
    ), $atts, 'calculateur');
    
    // Vérifier que le calculateur existe
    $calc_id = null;
    $calculator = null;
    foreach ($this->calculateurs as $id => $calc) {
        if ($calc['shortcode'] === $atts['type'] || $id === $atts['type']) {
            $calc_id = $id;  // Garder l'ID original pour les chemins de fichiers
            $calculator = $calc;
            break;
        }
    }
    
    if (!$calc_id || !$calculator) {
        return '<div class="cf-error">Calculateur non trouvé. Type demandé : ' . esc_html($atts['type']) . '</div>';
    }
    
    // Tracker l'utilisation
    $this->track_calculator_view($calc_id);
    
    // Charger le template
    $template_file = $calculator['path'] . 'template.php';
    if (!file_exists($template_file)) {
        return '<div class="cf-error">Template non trouvé pour ce calculateur.</div>';
    }
    
    // Charger les paramètres depuis la BDD
    $settings = $this->database->get_calculator_settings($calc_id);
    
    // Capturer le output
    ob_start();
    
    // Wrapper avec les attributs - UTILISER LE SHORTCODE pour data-calculator
    ?>
    <div class="cf-calculator-wrapper" 
         style="width: <?php echo esc_attr($atts['width']); ?>; 
                text-align: <?php echo esc_attr($atts['align']); ?>;"
         data-calculator="<?php echo esc_attr($calculator['shortcode']); ?>"
         data-theme="<?php echo esc_attr($atts['theme']); ?>">
        <?php
        // Inclure le template avec les paramètres
        $calc_config = $calculator;
        $calc_settings = $settings;
        include $template_file;
        ?>
    </div>
    <?php
    
    return ob_get_clean();
}
    
    /**
     * Tracker les vues d'un calculateur
     */
    private function track_calculator_view($calc_id) {
        $this->database->increment_calculator_stats($calc_id, 'views');
    }
    
    /**
     * Obtenir la liste des calculateurs
     */
    public function get_calculateurs_list() {
        return $this->calculateurs;
    }
    
    /**
     * Lancer le plugin
     */
    public function run() {
        $this->loader->run();
    }
}