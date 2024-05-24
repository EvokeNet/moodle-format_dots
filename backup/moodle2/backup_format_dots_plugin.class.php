<?php

/**
 * Specialised backup for format_dots
 *
 * Ensure that photo background images are included in course backups.
 *
 * @package     format_dots
 * @category    backup
 * @copyright   2023 World Bank Group <https://worldbank.org>
 * @author      Willian Mano <willianmanoaraujo@gmail.com>
 */
class backup_format_dots_plugin extends backup_format_plugin {
    /**
     * Defines the backup structure for format_dots
     *
     * @return backup_plugin_element
     * @throws base_element_struct_exception
     */
    protected function define_section_plugin_structure(): backup_plugin_element {
        $parent = $this->get_plugin_element(null, $this->get_format_condition(), 'dots');

        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Create a nested element under each backed up section, this is just a dummy container.
        $imageswrapper = new backup_nested_element(
            'sectionimage',
            [ 'id' ],
            [ 'contenthash', 'pathnamehash', 'filename', 'mimetype' ]
        );
        $imageswrapper->set_source_table(
            'files',
            [
                'itemid' => backup::VAR_SECTIONID,
                'component' => backup_helper::is_sqlparam('format_dots'),
                'filearea' => backup_helper::is_sqlparam('sectionimage'),
            ]);

        // Annotate files in the format_dots/image filearea for this course's context ID
        // The itemid doesn't get mapped to the new section id, if it changes.
        $imageswrapper->annotate_files(
            'format_dots',
            'sectionimage',
            null
        );

        $pluginwrapper->add_child($imageswrapper);

        $parent->add_child($pluginwrapper);

        return $parent;
    }
}
