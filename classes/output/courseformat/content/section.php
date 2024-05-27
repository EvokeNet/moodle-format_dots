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

        $data->progress = $this->get_section_progress($format, $output);

        return $data;
    }

    protected function get_section_progress($format, $output) {
        $cmsummary = new $this->cmsummaryclass($format, $this->section);

        $cmsummary = $cmsummary->export_for_template($output);

        if (!isset($cmsummary->total) || $cmsummary->total === 0) {
            return [
                'modulescount' => isset($cmsummary->mods) ? $this->get_total_activities($cmsummary->mods) : 0,
                'progress' => 0
            ];
        }

        $progress = (int) ($cmsummary->complete * 100 / $cmsummary->total);

        return [
            'modulescount' => $this->get_total_activities($cmsummary->mods),
            'progress' => $progress,
            'progresscomplete' => $progress == 100,
            'animationleft' => $progress > 50,
            'animationright' => $progress <= 50,
        ];
    }

    private function get_total_activities($mods) {
        $total = 0;

        foreach ($mods as $mod) {
            $total += $mod['count'];
        }

        return $total;
    }
}
