<?php
/**
 * @package   Gantry 5 Theme
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2015 RocketTheme, LLC
 * @license   GNU/GPLv2 and later
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') or die;

// Note: This file must be PHP 5.2 compatible.

// Bootstrap Gantry framework or fail gracefully.
$gantry = include_once dirname(__FILE__) . '/includes/gantry.php';

// Check min. required version of Gantry 5
$requiredGantryVersion = '5.2.0';

if (!$gantry->isCompatible($requiredGantryVersion)) {
    $current_theme = wp_get_theme();
    $error = sprintf(__('Please upgrade Gantry 5 Framework to v%s (or later) before using %s theme!', 'g5_hydrogen'), strtoupper($requiredGantryVersion), $current_theme->get('Name'));

    if(is_admin()) {
        add_action('admin_notices', function () use ($error) {
            echo '<div class="error"><p>' . $error . '</p></div>';
        });
    } else {
        wp_die($error);
    }
}

/** @var \Gantry\Framework\Theme $theme */
$theme = $gantry['theme'];

// Theme helper files that can contain useful methods or filters
$helpers = array(
    'includes/helper.php', // General helper file
);

foreach ($helpers as $file) {
    if (!$filepath = locate_template($file)) {
        trigger_error(sprintf(__('Error locating %s for inclusion', 'g5_hydrogen'), $file), E_USER_ERROR);
    }

    require $filepath;
}
