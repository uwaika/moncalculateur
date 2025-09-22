<?php
/**
 * Plugin Name: Calculateurs Financiers Pro
 * Description: Suite de calculateurs financiers modulaires et personnalisables
 * Version: 3.0.0
 * Author: Votre Nom
 * Text Domain: calculateurs-financiers
 */

// Sécurité : Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Définir les constantes du plugin
define('CF_VERSION', '3.0.0');
define('CF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CF_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CF_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Chargement automatique des classes
spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'CF_') === 0) {
        $class_file = str_replace('_', '-', strtolower($class_name)) . '.php';
        $class_path = CF_PLUGIN_PATH . 'includes/class-' . str_replace('cf-', '', $class_file);
        
        if (file_exists($class_path)) {
            require_once $class_path;
        }
    }
});

// Fonction d'activation
function cf_activate() {
    require_once CF_PLUGIN_PATH . 'includes/class-activator.php';
    CF_Activator::activate();
}
register_activation_hook(__FILE__, 'cf_activate');

// Fonction de désactivation
function cf_deactivate() {
    require_once CF_PLUGIN_PATH . 'includes/class-deactivator.php';
    CF_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'cf_deactivate');

// Initialiser le plugin
function cf_init() {
    require_once CF_PLUGIN_PATH . 'includes/class-plugin-core.php';
    $plugin = new CF_Plugin_Core();
    $plugin->run();
}
add_action('plugins_loaded', 'cf_init');