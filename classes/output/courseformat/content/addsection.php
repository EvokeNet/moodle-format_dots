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
 * Contains the default section course format output class.
 *
 * @package   core_courseformat
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_dots\output\courseformat\content;

use core\output\named_templatable;
use core_courseformat\base as course_format;
use core_courseformat\output\local\courseformat_named_templatable;
use moodle_url;
use renderable;
use stdClass;

/**
 * Base class to render a course add section buttons.
 *
 * @package   core_courseformat
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addsection extends \core_courseformat\output\local\content\addsection {

    public function get_template_name(\renderer_base $renderer): string {
        return 'format_dots/local/content/addsection';
    }

    /**
     * Get the add section button data.
     *
     * Current course format does not have 'numsections' option but it has multiple sections suppport.
     * Display the "Add section" link that will insert a section in the end.
     * Note to course format developers: inserting sections in the other positions should check both
     * capabilities 'moodle/course:update' and 'moodle/course:movesections'.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @param int $lastsection the last section number
     * @param int $maxsections the maximum number of sections
     * @return stdClass data context for a mustache template
     */
    protected function get_add_section_data(\renderer_base $output, int $lastsection, int $maxsections): stdClass {
        $format = $this->format;
        $course = $format->get_course();
        $data = new stdClass();

        if (get_string_manager()->string_exists('addsections', 'format_' . $course->format)) {
            $addstring = get_string('addsections', 'format_' . $course->format);
        } else {
            $addstring = get_string('addsections');
        }

        $params = ['courseid' => $course->id, 'insertsection' => 0, 'sesskey' => sesskey()];

        $singlesection = $this->format->get_section_number();
        if ($singlesection) {
            $params['sectionreturn'] = $singlesection;
        }

        $data->addsections = (object) [
            'url' => new moodle_url('/course/changenumsections.php', $params),
            'title' => $addstring,
            'newsection' => $maxsections - $lastsection,
        ];

        return $data;
    }
}
