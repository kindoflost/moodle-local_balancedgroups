<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_balancedgroups\form;

use local_balancedgroups\local\csv_source;
use local_balancedgroups\local\request_builder;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/csvlib.class.php');

/**
 * Step 1: who to group and which data to balance.
 *
 * Custom data: 'source' (local\source), 'hascsv' (string summary of a CSV already uploaded, or '').
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setup_form extends \moodleform {
    /** @var array|null CSV parsed during validation (['headers', 'rows']). */
    public ?array $parsedcsv = null;

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        /** @var \local_balancedgroups\local\source $source */
        $source = $this->_customdata['source'];

        $mform->addElement('header', 'whoheader', get_string('whoheader', 'local_balancedgroups'));
        $mform->addElement('select', 'roleid', get_string('selectfromrole', 'group'), $source->roles());
        $mform->setDefault('roleid', $source->default_roleid());
        $mform->addElement('select', 'groupid', get_string('limittogroup', 'local_balancedgroups'), $source->groups());
        $mform->addHelpButton('groupid', 'limittogroup', 'local_balancedgroups');

        $mform->addElement('header', 'fieldsheader', get_string('fieldsheader', 'local_balancedgroups'));
        $mform->setExpanded('fieldsheader');
        $mform->addElement('static', 'fieldsintro', '', get_string('fieldsintro', 'local_balancedgroups'));
        $fields = $source->fields();
        $lastsource = null;
        foreach ($fields as $key => $field) {
            if ($field['source'] !== $lastsource) {
                $mform->addElement(
                    'static',
                    'sourcelabel_' . $field['source'],
                    '',
                    \html_writer::tag('strong', get_string('source_' . $field['source'], 'local_balancedgroups'))
                );
                $lastsource = $field['source'];
            }
            $mform->addElement('advcheckbox', 'field_' . $key, '', $field['label']);
        }
        if (!$fields) {
            $mform->addElement('static', 'nofields', '', get_string('nofields', 'local_balancedgroups'));
        }

        $mform->addElement('header', 'csvheader', get_string('csvheader', 'local_balancedgroups'));
        $mform->addElement('static', 'csvintro', '', get_string('csvintro', 'local_balancedgroups'));
        if (!empty($this->_customdata['hascsv'])) {
            $mform->setExpanded('csvheader');
            $mform->addElement(
                'static',
                'csvcurrent',
                get_string('csvcurrent', 'local_balancedgroups'),
                s($this->_customdata['hascsv'])
            );
            $mform->addElement('advcheckbox', 'removecsv', '', get_string('csvremove', 'local_balancedgroups'));
        }
        $mform->addElement(
            'filepicker',
            'csvfile',
            get_string('csvfile', 'local_balancedgroups'),
            null,
            ['accepted_types' => ['.csv', '.txt'], 'maxbytes' => 2 * 1024 * 1024]
        );
        $mform->addElement(
            'select',
            'delimiter',
            get_string('csvdelimiter', 'local_balancedgroups'),
            \csv_import_reader::get_delimiter_list()
        );
        $mform->setDefault('delimiter', 'comma');
        $encodings = \core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('csvencoding', 'local_balancedgroups'), $encodings);
        $mform->setDefault('encoding', 'UTF-8');

        $mform->addElement('header', 'rulesheader', get_string('rulesheader', 'local_balancedgroups'));
        $mform->setExpanded('rulesheader');
        $mform->addElement(
            'advcheckbox',
            'usecodes',
            get_string('usecodes', 'local_balancedgroups'),
            get_string('usecodes_label', 'local_balancedgroups')
        );
        $mform->addHelpButton('usecodes', 'usecodes', 'local_balancedgroups');

        $this->add_action_buttons(true, get_string('next'));
    }

    /**
     * Validation: a usable set of people, at least something to balance, a readable CSV.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $USER;
        $errors = parent::validation($data, $files);
        /** @var \local_balancedgroups\local\source $source */
        $source = $this->_customdata['source'];

        $users = $source->users((int)$data['roleid'], (int)$data['groupid']);
        if (count($users) < 2) {
            $errors['roleid'] = get_string('errortoofewpeople', 'local_balancedgroups', count($users));
        } else if (count($users) > request_builder::MAX_PEOPLE) {
            $errors['roleid'] = get_string(
                'errortoomanypeople',
                'local_balancedgroups',
                (object)['count' => count($users), 'max' => request_builder::MAX_PEOPLE]
            );
        }

        $chosen = 0;
        foreach ($data as $name => $value) {
            if (strpos($name, 'field_') === 0 && $value) {
                $chosen++;
            }
        }
        if ($chosen > request_builder::MAX_FIELDS) {
            $errors['fieldsintro'] = get_string(
                'errortoomanyfields',
                'local_balancedgroups',
                (object)['count' => $chosen, 'max' => request_builder::MAX_FIELDS]
            );
        }

        $draftid = $data['csvfile'] ?? 0;
        $content = null;
        if ($draftid) {
            $fs = get_file_storage();
            $draftfiles = $fs->get_area_files(
                \context_user::instance($USER->id)->id,
                'user',
                'draft',
                $draftid,
                'id DESC',
                false
            );
            if ($draftfiles) {
                $content = reset($draftfiles)->get_content();
            }
        }
        if ($content !== null) {
            try {
                $csv = csv_source::parse($content, $data['encoding'], $data['delimiter']);
                if (!csv_source::match_columns($csv['headers'])) {
                    $errors['csvfile'] = get_string('errorcsvnomatch', 'local_balancedgroups');
                } else {
                    $this->parsedcsv = $csv;
                }
            } catch (\moodle_exception $e) {
                $errors['csvfile'] = $e->getMessage();
            }
        }
        $hascsv = $this->parsedcsv !== null || (!empty($this->_customdata['hascsv']) && empty($data['removecsv']));
        if (!$chosen && !$hascsv && !isset($errors['csvfile'])) {
            $errors['fieldsintro'] = get_string('errornofields', 'local_balancedgroups');
        }
        return $errors;
    }
}
