<?php

namespace format_dots\output\courseformat;

use core_courseformat\output\local\content as content_base;
use course_modinfo;
use tool_brickfield\local\areas\mod_choice\option;

;

/**
 * Base class to render a course content.
 *
 * @package   format_dots
 * @copyright 2020 Ferran Recio <ferran@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends content_base {

    /**
     * @var bool Topic format has add section after each topic.
     *
     * The responsible for the buttons is core_courseformat\output\local\content\section.
     */
    protected $hasaddsection = true;

    /**
     * Export this data so it can be used as the context for a mustache template (core/inplace_editable).
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output) {
        global $PAGE;

        $format = $this->format;

        // Most formats uses section 0 as a separate section so we remove from the list.
        $sections = $this->export_sections($output);

        $courseformatoptions = course_get_format($format->get_course())->get_format_options();

        $data = (object)[
            'title' => $format->page_title(), // This method should be in the course_format class.
            'sections' => $sections,
            'format' => $format->get_format(),
            'sectionreturn' => 0,
            'editingmode' => $PAGE->user_is_editing(),
            'portfolios' => $courseformatoptions['portfolios'] ?? false,
            'evokation' => $courseformatoptions['evokation'] ?? false,
        ];

        if ($this->hasaddsection) {
            $addsection = new $this->addsectionclass($format);
            $data->numsections = $addsection->export_for_template($output);
        }

        return $data;
    }

    /**
     * Export sections array data.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return array data context for a mustache template
     */
    protected function export_sections(\renderer_base $output): array {

        $format = $this->format;
        $course = $format->get_course();
        $modinfo = $this->format->get_modinfo();

        // Generate section list.
        $sections = [];
        $stealthsections = [];
        $numsections = $format->get_last_section_number();
        foreach ($this->get_sections_to_display($modinfo) as $sectionnum => $thissection) {
            // The course/view.php check the section existence but the output can be called
            // from other parts so we need to check it.
            if (!$thissection) {
                throw new \moodle_exception('unknowncoursesection', 'error', course_get_url($course),
                    format_string($course->fullname));
            }

            $section = new $this->sectionclass($format, $thissection);

            if ($sectionnum > $numsections) {
                // Activities inside this section are 'orphaned', this section will be printed as 'stealth' below.
                if (!empty($modinfo->sections[$sectionnum])) {
                    $stealthsections[] = $section->export_for_template($output);
                }
                continue;
            }

            if (!$format->is_section_visible($thissection)) {
                continue;
            }

            $sections[] = $section->export_for_template($output);
        }

        if (!empty($stealthsections)) {
            $sections = array_merge($sections, $stealthsections);
        }

        foreach ($sections as $key => $section) {
            $section->url = new \moodle_url('/course/view.php', ['id' => $course->id, 'section' => $section->num]);
            $section->icon = $section->sectionformatoptions['sectionicon'];
            $section->color = $section->sectionformatoptions['sectioncolor'];
            $section->title = $section->header->title;
            $section->image = $this->get_section_image($course, $section->id);
            $section->hasimage = $section->image !== false;

            $section->ischildren = false;
            $section->haschildren = false;

            if (isset($section->sectionformatoptions['parent']) && $section->sectionformatoptions['parent'] >= 0) {
                $section->ischildren = true;
                $sections[$section->sectionformatoptions['parent']]->children[] = $section;

                $sections[$section->sectionformatoptions['parent']]->haschildren = true;

                unset($sections[$key]);
            }
        }

        return array_values($sections);
    }

    /**
     * Recover background url to section
     *
     * @param $section
     * @return string
     * @throws \dml_exception
     * @throws \coding_exception
     */
    protected function get_section_image($course, $sectionid) {
        $file = format_dots_get_file('sectionimage' . $sectionid, $course);

        if (!$file) {
            return false;
        }

        return \moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename());
    }

    /**
     * Return an array of sections to display.
     *
     * This method is used to differentiate between display a specific section
     * or a list of them.
     *
     * @param course_modinfo $modinfo the current course modinfo object
     * @return section_info[] an array of section_info to display
     */
    private function get_sections_to_display(course_modinfo $modinfo): array {
        $singlesection = $this->format->get_section_number();

        if ($singlesection) {
            return [
                $modinfo->get_section_info($singlesection),
            ];
        }

        $section = optional_param('section', null, PARAM_INT);

        if ($singlesection === 0 && $section === 0) {
            return [
                $modinfo->get_section_info(0),
            ];
        }

        return $modinfo->get_section_info_all();
    }

    public function get_template_name(\renderer_base $renderer): string {
        return 'format_dots/local/content';
    }
}
