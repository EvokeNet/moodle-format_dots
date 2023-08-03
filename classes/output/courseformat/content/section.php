<?php

namespace format_dots\output\courseformat\content;

use core_courseformat\base as course_format;
use core_courseformat\output\local\content\section as section_base;
use stdClass;
use section_info;
/**
 * Base class to render a course section.
 *
 * @package   format_dots
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section extends section_base {

    /** @var course_format the course format */
    protected $format;

    public function export_for_template(\renderer_base $output): stdClass {
        $format = $this->format;

        $data = parent::export_for_template($output);

        if (!$this->format->get_section_number()) {
            $addsectionclass = $format->get_output_classname('content\\addsection');
            $addsection = new $addsectionclass($format);
            $data->numsections = $addsection->export_for_template($output);
            $data->insertafter = true;

            $data->subsectionurl = new \moodle_url('/course/view.php', [
                'id' => $format->get_course()->id,
                'addchildsection' => $data->num
            ]);
        }

        $data->sectionformatoptions = $format->get_format_options($this->section);

        return $data;
    }
}
