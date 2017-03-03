<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prediction_action {

    /**
     * @var \action_menu_link
     */
    protected $actionlink = null;

    /**
     * __construct
     *
     * @param string $actionname
     * @param \tool_inspire\prediction $prediction
     * @param \moodle_url $actionurl
     * @param \pix_icon $icon
     * @param string $text
     * @param bool $primary
     * @return void
     */
    public function __construct($actionname, \tool_inspire\prediction $prediction, \moodle_url $actionurl, \pix_icon $icon, $text, $primary = false) {

        // We want to track how effective are our suggested actions, we pass users through a script that will log these actions.
        $params = array('action' => $actionname, 'predictionid' => $prediction->get_prediction_data()->id, 'forwardurl' => $actionurl->out(false));
        $url = new \moodle_url('/admin/tool/inspire/action.php', $params);

        if ($primary === false) {
            $this->actionlink = new \action_menu_link_secondary($url, $icon, $text);
        } else {
            $this->actionlink = new \action_menu_link_primary($url, $icon, $text);
        }
    }

    /**
     * @return \action_menu_link
     */
    public function get_action_link() {
        return $this->actionlink;
    }
}
