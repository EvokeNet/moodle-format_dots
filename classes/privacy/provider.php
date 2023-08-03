<?php

namespace format_dots\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\null_provider;

/**
 * Privacy Subsystem for Topics course format implementing null_provider.
 *
 * @copyright  2018 Carlos Escobedo <carlos@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements null_provider {

    /**
     * Get the language string identifier with the component's language
     * file to explain why this plugin stores no data.
     *
     * @return  string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}