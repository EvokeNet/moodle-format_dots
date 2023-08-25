<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot. '/course/format/lib.php');

use core\output\inplace_editable;

/**
 * Main class for the Topics course format.
 *
 * @package    format_dots
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_dots extends core_courseformat\base {

    /**
     * Returns true if this course format uses sections.
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    public function uses_course_index() {
        return false;
    }

    public function uses_indentation(): bool {
        return false;
    }

    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_dots');
        } else {
            // Use course_format::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * Use section name is specified by user. Otherwise use default ("Topic #").
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                ['context' => context_course::instance($this->courseid)]);
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * The URL to use for the specified course (with section).
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = []) {
        global $CFG;
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);

        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                if ($sr) {
                    $sectionno = $sr;
                }
            }

            if ($sectionno) {
                $url->param('section', $sectionno);
            } else {
                if (empty($CFG->linkcoursesections) && !empty($options['navigation'])) {
                    return null;
                }
                $url->set_anchor('section-'.$sectionno);
            }
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format.
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    public function supports_components() {
        return true;
    }

    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() {
        return false;
    }


    /**
     * Loads all of the course sections into the navigation.
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     * @return void
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        // If section is specified in course/view.php, make sure it is expanded in navigation.
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // Check if there are callbacks to extend course navigation.
        parent::extend_course_navigation($navigation, $node);

        // We want to remove the general section if it is empty.
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
        if (!isset($sections[0])) {
            // The general section is empty to find the navigation node for it we need to get its ID.
            $section = $modinfo->get_section_info(0);
            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
            if ($generalsection) {
                // We found the node - now remove it.
                $generalsection->remove();
            }
        }
    }

    /**
     * Custom action after section has been moved in AJAX mode.
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        global $PAGE;
        $titles = [];
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return ['sectiontitles' => $titles, 'action' => 'move'];
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course.
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return [
            BLOCK_POS_LEFT => [],
            BLOCK_POS_RIGHT => [],
        ];
    }

    /**
     * Definitions of the additional options that this course format uses for course.
     *
     * Topics format uses the following options:
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = [
                'hiddensections' => [
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ],
                'portfolios' => [
                    'default' => '',
                    'type' => PARAM_URL,
                ],
                'evokation' => [
                    'default' => '',
                    'type' => PARAM_URL,
                ],
            ];
        }
        if ($foreditform) {
            $courseformatoptionsedit = [
                'hiddensections' => [
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible')
                        ],
                    ],
                ],
                'portfolios' => [
                    'label' => new lang_string('portfolios', 'format_dots'),
                    'help' => 'portfolios',
                    'help_component' => 'format_dots',
                    'element_type' => 'text',
                ],
                'evokation' => [
                    'label' => new lang_string('evokation', 'format_dots'),
                    'help' => 'evokation',
                    'help_component' => 'format_dots',
                    'element_type' => 'text',
                ],
            ];
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@link course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE, $PAGE;

        $elements = parent::create_edit_form_elements($mform, $forsection);

        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            // Add "numsections" element to the create course form - it will force new course to be prepopulated
            // with empty sections.
            // The "Number of sections" option is no longer available when editing course, instead teachers should
            // delete and add sections when needed.
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);
        }

        if ($forsection && $mform->elementExists('sectionimage')) {
            $sectionid = $PAGE->url->get_param('id');
            $filearea = 'sectionimage' . $sectionid;
            $file = format_dots_get_file($filearea, $this->course);
            if($file) {
                $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename(), false);
                $img = html_writer::img($url, $file->get_filename(),array('style' => 'width: 50%;'));
                $newitems = array();
                $newitems[] = $mform->createElement('static', 'currentsectionimage', get_string('sectionimageimagecurrent', 'format_dots'), $img);
                $newitems[] = $mform->createElement('checkbox', 'deletesectionimage', get_string('sectionimageimagedelete', 'format_dots'));
                array_splice($mform->_elements, 7, 0, $newitems);
            }
        }

        return $elements;
    }

    /**
     * Updates format options for a course.
     *
     * In case if course format was changed to 'dots', we try to copy options
     * 'hiddensections' from the previous format.
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;
        if ($oldcourse !== null) {
            $oldcourse = (array)$oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    }
                }
            }
        }
        return $this->update_format_options($data);
    }

    /**
     * Handle the sections form before saving data and files
     *
     * @param array $data
     * @return bool
     * @throws coding_exception
     */
    public function update_section_format_options($data) {
        global $PAGE, $USER;

        $courseid = $PAGE->course->id;
        $sectionid = $data['id'];
        $fs = get_file_storage();
        $filearea = 'sectionimage' . $sectionid;
        $context = \context_course::instance($courseid);
        $usercontext = \context_user::instance($USER->id);

        // Must delete actual background image.
        if (array_key_exists('deletesectionimage', $data) && $data['deletesectionimage'] == 1) {
            $fs->delete_area_files($context->id, 'format_dots', $filearea);
        }

        $sectionimage = file_get_submitted_draft_itemid('sectionimage');
        // Only updates if some file is uploaded.
        if ($fareafiles = file_get_all_files_in_draftarea($sectionimage)){
            file_save_draft_area_files($sectionimage, $context->id, 'format_dots', $filearea, 0);
        }

        // Clean-up user draft area after saving files.
        $fs->delete_area_files($usercontext->id, 'user', 'draft', $sectionimage);

        return parent::update_section_format_options($data);
    }

    /**
     * Whether this format allows to delete sections.
     *
     * Do not call this function directly, instead use {@link course_can_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section) {
        return true;
    }

    /**
     * Prepares the templateable object to display section name.
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return inplace_editable
     */
    public function inplace_editable_render_section_name($section, $linkifneeded = true,
            $editable = null, $edithint = null, $editlabel = null) {
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_dots');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_dots', $title);
        }
        return parent::inplace_editable_render_section_name($section, $linkifneeded, $editable, $edithint, $editlabel);
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    /**
     * Callback used in WS core_course_edit_section when teacher performs an AJAX action on a section (show/hide).
     *
     * Access to the course is already validated in the WS but the callback has to make sure
     * that particular action is allowed by checking capabilities
     *
     * Course formats should register.
     *
     * @param section_info|stdClass $section
     * @param string $action
     * @param int $sr
     * @return null|array any data for the Javascript post-processor (must be json-encodeable)
     */
    public function section_action($section, $action, $sr) {
        global $PAGE;

        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            // Format 'dots' allows to set and remove markers in addition to common section actions.
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_dots');

        if (!($section instanceof section_info)) {
            $modinfo = course_modinfo::instance($this->courseid);
            $section = $modinfo->get_section_info($section->section);
        }
        $elementclass = $this->get_output_classname('content\\section\\availability');
        $availability = new $elementclass($this, $section);

        $rv['section_availability'] = $renderer->render($availability);
        return $rv;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of configuration settings
     * @since Moodle 3.5
     */
    public function get_config_for_external() {
        // Return everything (nothing to hide).
        $formatoptions = $this->get_format_options();

        return $formatoptions;
    }

    /**
     * Returns true if we are on /course/view.php page
     *
     * @return bool
     * @throws moodle_exception
     */
    public function on_course_view_page() {
        global $PAGE;

        return ($PAGE->has_set_url() &&
            $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)
        );
    }

    /**
     * Allows course format to execute code on moodle_page::set_course()
     *
     * format_dots processes additional attributes in the view course URL
     * to manipulate sections and redirect to course view page
     *
     * @param moodle_page $page instance of page calling set_course
     * @throws coding_exception
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     */
    public function page_set_course(moodle_page $page) {
        global $PAGE;

        if ($PAGE != $page) {
            return;
        }

        $url = new \moodle_url(course_get_url($this->courseid));
        if ($this->on_course_view_page()) {
            $context = context_course::instance($this->courseid);

            // If requested, create new section and redirect to course view page.
            $addchildsection = optional_param('addchildsection', null, PARAM_INT);
            if ($addchildsection !== null && has_capability('moodle/course:update', $context)) {
                $this->create_new_section($addchildsection);

                redirect($url);
            }

            $section = optional_param('section', null, PARAM_INT);
            if ($section !== null) {
                $activities = $this->get_section_activities($section);

                if (empty($activities)) {
                    redirect($url, 'There is no available activities in this section.');
                }

                $lastactivityurl = $this->get_last_visited_or_first_activity_url($activities);

                if ($lastactivityurl) {
                    redirect($lastactivityurl);
                }

                redirect($url, 'There is no available activities in this section.');
            }
        }
    }

    private function get_section_activities($section) {
        $modinfo = get_fast_modinfo($this->get_course());

        $section = $modinfo->get_section_info($section);

        if (empty($modinfo->sections[$section->section])) {
            return [];
        }

        $mods = [];
        foreach ($modinfo->sections[$section->section] as $modnumber) {
            $mod = $modinfo->cms[$modnumber];

            // Only activities visible for user.
            if ($mod->uservisible) {
                $mods[$modnumber] = $mod;
            }
        }

        return $mods;
    }

    private function get_last_visited_or_first_activity_url(array $activities) {
        global $DB, $USER;

        $first = current($activities);

        if (count($activities) == 1) {
            return new moodle_url("/mod/{$first->modname}/view.php", ['id' => $first->id]);
        }

        $ids = array_map(function ($item) { return $item->id; }, $activities);

        list($insql, $inparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);

        $sql = "SELECT id, objecttable, contextinstanceid
                FROM {logstore_standard_log}
                WHERE objecttable <> 'course_modules'
                  AND target = :target
                  AND userid = :userid
                  AND courseid = :courseid
                  AND contextlevel = 70
                  AND contextinstanceid {$insql}
                ORDER BY id DESC LIMIT 10";

        $params = array_merge(['target' => 'course_module', 'userid' => $USER->id, 'courseid' => $this->courseid], $inparams);

        $lastvitedmodules = $DB->get_records_sql($sql, $params);

        if (!$lastvitedmodules) {
            return new moodle_url("/mod/{$first->modname}/view.php", ['id' => $first->id]);
        }

        foreach ($lastvitedmodules as $cm) {
            $coursemodule = $activities[$cm->contextinstanceid];

            if ($coursemodule && ($coursemodule->deletioninprogress == 0 AND $coursemodule->visible == 1)) {
                return new moodle_url("/mod/{$coursemodule->modname}/view.php", ['id' => $coursemodule->id]);
            }
        }

        return false;
    }

    /**
     * Create a new section under given parent
     *
     * @param int|section_info $parent parent section
     * @param null|int|section_info $before
     * @return int
     * @throws dml_exception
     * @throws dml_transaction_exception
     * @throws moodle_exception
     * @throws moodle_exception
     */
    public function create_new_section($parent = 0) {
        global $DB;

        $sections = get_fast_modinfo($this->courseid)->get_section_info_all();

        $sectionnums = array_keys($sections);

        $sectionnum = array_pop($sectionnums) + 1;

        course_create_sections_if_missing($this->courseid, $sectionnum);

        $section = $this->get_section($sectionnum);

        $data = new \stdClass();
        $data->courseid = $this->courseid;
        $data->format = 'dots';
        $data->sectionid = $section->id;
        $data->name = 'parent';
        $data->value = $parent;

        $DB->insert_record('course_format_options', $data);

        return $sectionnum;
    }

    /**
     * Definitions of the additional options that this course format uses for section
     *
     * See {@link format_base::course_format_options()} for return array definition.
     *
     * Additionally section format options may have property 'cache' set to true
     * if this option needs to be cached in {@link get_fast_modinfo()}. The 'cache' property
     * is recommended to be set only for fields used in {@link format_base::get_section_name()},
     * {@link format_base::extend_course_navigation()} and {@link format_base::get_view_url()}
     *
     * For better performance cached options are recommended to have 'cachedefault' property
     * Unlike 'default', 'cachedefault' should be static and not access get_config().
     *
     * Regardless of value of 'cache' all options are accessed in the code as
     * $sectioninfo->OPTIONNAME
     * where $sectioninfo is instance of section_info, returned by
     * get_fast_modinfo($course)->get_section_info($sectionnum)
     * or get_fast_modinfo($course)->get_section_info_all()
     *
     * All format options for particular section are returned by calling:
     * $this->get_format_options($section);
     *
     * @param bool $foreditform
     * @return array
     * @throws coding_exception
     */
    public function section_format_options($foreditform = false) {
        return [
            'parent' => [
                'type' => PARAM_INT,
                'label' => '',
                'element_type' => 'hidden',
                'default' => -1,
                'cache' => true,
                'cachedefault' => -1,
            ],
            'sectioncolor' => [
                'type' => PARAM_TEXT,
                'label' => get_string('sectioncolor', 'format_dots'),
                'cache' => true,
                'cachedefault' => '#254054',
                'element_type' => 'select',
                'element_attributes' => [
                    [
                        '#254054' => get_string('gray', 'format_dots'),
                        '#3399E1' => get_string('blue', 'format_dots'),
                        '#1ECCCC' => get_string('navy', 'format_dots'),
                        '#9013FE' => get_string('purple', 'format_dots'),
                        '#FB656F' => get_string('coral', 'format_dots'),
                        '#ECE046' => get_string('yellow', 'format_dots'),
                        '#28C503' => get_string('green', 'format_dots'),
                        '#E831BE' => get_string('pink', 'format_dots')
                    ]
                ],
                'default' => '#254054',
            ],
            'sectionicon' => [
                'type' => PARAM_TEXT,
                'label' => get_string('sectionicon', 'format_dots'),
                'element_type' => 'text',
                'default' => 'fa-home',
                'cache' => true,
                'cachedefault' => 'fa-home',
            ],
            'sectionimage' => [
                'type' => PARAM_FILE,
                'label' => get_string('sectionimage', 'format_dots'),
                'element_type' => 'filemanager',
                'element_attributes' => [null,
                    [
                        'subdirs' => false,
                        'maxfiles' => 1,
                        'accepted_types' => ['.jpg', '.png'],
                        'maxbytes' => 512000,
                        'areamaxbytes' => 512000
                    ]
                ],
            ],
        ];
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place.
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return inplace_editable
 */
function format_dots_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            [$itemid, 'dots'], MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

/**
 * Serve files for the plugin
 *
 * @param $course
 * @param $cm
 * @param $context
 * @param $filearea
 * @param $args
 * @param $forcedownload
 * @param array $options
 * @throws coding_exception
 */
function format_dots_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    // Default params.
    $itemid = $args[0];
    $filter = 0;
    $forcedownload = true;

    if (array_key_exists('filter', $options)) {
        $filter = $options['filter'];
    }

    // Recover file and stored_file objects.
    $file = format_dots_get_file($filearea, $course);

    if (is_null($file)) {
        send_file_not_found();
    }

    $fs = get_file_storage();
    $storedfile = $fs->get_file_by_hash($file->get_pathnamehash());

    if (!$storedfile) {
        send_file_not_found();
    }

    if (strpos($filearea, 'sectionimage') !== false) {
        send_stored_file($storedfile, 86400, $filter, $forcedownload, $options);
    }
}

/**
 * Get a course related file
 *
 * @param $filearea
 * @param $course
 * @return bool|stored_file File object or false if file not exists
 * @throws coding_exception
 */
function format_dots_get_file($filearea, $course) {
    global $CFG, $DB;

    require_once($CFG->libdir. '/filestorage/file_storage.php');
    require_once($CFG->dirroot. '/course/lib.php');
    $fs = get_file_storage();
    $context = context_course::instance($course->id);
    $files = $fs->get_area_files($context->id, 'format_dots', $filearea, 0, 'filename', false);
    if (count($files)) {
        foreach ($files as $entry) {
            $file = $fs->get_file($context->id, 'format_dots', $filearea, 0, $entry->get_filepath(), $entry->get_filename());
            return $file;
        }
    }
    return false;
}