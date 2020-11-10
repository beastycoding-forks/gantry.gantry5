<?php

/**
 * @package   Gantry5
 * @author    RocketTheme http://www.rockettheme.com
 * @copyright Copyright (C) 2007 - 2020 RocketTheme, LLC
 * @license   Dual License: MIT or GNU/GPLv2 and later
 *
 * http://opensource.org/licenses/MIT
 * http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Gantry Framework code that extends GPL code is considered GNU/GPLv2 and later
 */

namespace Gantry\Admin\Events;

use Gantry\Admin\Theme;
use Gantry\Framework\Gantry;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class AssigmentsEvent
 * @package Gantry\Admin\Events
 */
class InitThemeEvent extends Event
{
    /** @var Gantry */
    public $gantry;
    /** @var Theme */
    public $theme;
}