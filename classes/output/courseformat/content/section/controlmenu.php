<?php

namespace format_dots\output\courseformat\content\section;

use context_course;
use core_courseformat\output\local\content\section\controlmenu as controlmenu_base;

/**
 * Base class to render a course section menu.
 *
 * @package   format_dots
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controlmenu extends controlmenu_base {

    /** @var course_format the course format class */
    protected $format;

    /** @var section_info the course section class */
    protected $section;

    /**
     * Generate the edit control items of a section.
     *
     * This method must remain public until the final deprecation of section_edit_control_items.
     *
     * @return array of edit control items
     */
    public function section_control_items() {

        $format = $this->format;
        $section = $this->section;
        $course = $format->get_course();
        $sectionreturn = $format->get_section_number();

        $coursecontext = context_course::instance($course->id);

        if ($sectionreturn) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = [];
        if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = [
                    'url' => $url,
                    'icon' => 'i/marked',
                    'name' => $highlightoff,
                    'pixattr' => ['class' => ''],
                    'attr' => [
                        'class' => 'editing_highlight',
                        'data-action' => 'removemarker'
                    ],
                ];
            } else {
                $url->param('marker', $section->section);
                $highlight = get_string('highlight');
                $controls['highlight'] = [
                    'url' => $url,
                    'icon' => 'i/marker',
                    'name' => $highlight,
                    'pixattr' => ['class' => ''],
                    'attr' => [
                        'class' => 'editing_highlight',
                        'data-action' => 'setmarker'
                    ],
                ];
            }
        }

        $parentcontrols = parent::section_control_items();

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = [];
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }
}
