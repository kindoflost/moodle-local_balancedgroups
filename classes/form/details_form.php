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

use local_balancedgroups\local\request_builder;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Step 2: priorities, CSV columns, together/apart rules, group sizes and names.
 *
 * Custom data: 'fields' (chosen Moodle fields), 'csv' (null or ['matchoptions', 'columns', 'hascodes']),
 * 'users' (user id => full name, only when rules are used), 'usercount', 'disclosure' (HTML).
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class details_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $fields = $this->_customdata['fields'];
        $csv = $this->_customdata['csv'];
        $users = $this->_customdata['users'];

        $prios = [];
        for ($i = 1; $i <= request_builder::MAX_FIELDS; $i++) {
            $prios[$i] = $i;
        }
        $prios[1] = get_string('prioritytop', 'local_balancedgroups');
        $kinds = [
            request_builder::KIND_CATEGORY => get_string('kindcategory', 'local_balancedgroups'),
            request_builder::KIND_NUMBER => get_string('kindnumber', 'local_balancedgroups'),
        ];

        $mform->addElement('header', 'prioheader', get_string('prioheader', 'local_balancedgroups'));
        $mform->setExpanded('prioheader');
        $mform->addElement('static', 'priointro', '', get_string('priointro', 'local_balancedgroups'));
        $n = 0;
        foreach ($fields as $field) {
            $n++;
            $key = $field['key'];
            $mform->addGroup([
                $mform->createElement('select', 'prio_' . $key, get_string('priority', 'local_balancedgroups'), $prios),
                $mform->createElement('select', 'kind_' . $key, get_string('balanceas', 'local_balancedgroups'), $kinds),
            ], 'grp_' . $key, s($field['label']), ' ', false);
            $mform->setDefault('prio_' . $key, min($n, request_builder::MAX_FIELDS));
            $mform->setDefault('kind_' . $key, $field['kind']);
        }

        if ($csv) {
            $mform->addElement('header', 'csvcolsheader', get_string('csvcolsheader', 'local_balancedgroups'));
            $mform->setExpanded('csvcolsheader');
            $mform->addElement('select', 'csvmatch', get_string('csvmatch', 'local_balancedgroups'), $csv['matchoptions']);
            $mform->addHelpButton('csvmatch', 'csvmatch', 'local_balancedgroups');
            $csvprios = [0 => get_string('notused', 'local_balancedgroups')] + $prios;
            foreach ($csv['columns'] as $i => $col) {
                $mform->addGroup([
                    $mform->createElement('select', 'csvprio_' . $i, get_string('priority', 'local_balancedgroups'), $csvprios),
                    $mform->createElement('select', 'csvkind_' . $i, get_string('balanceas', 'local_balancedgroups'), $kinds),
                ], 'csvgrp_' . $i, s($col['label']), ' ', false);
                $mform->setDefault(
                    'csvkind_' . $i,
                    $col['numeric'] ? request_builder::KIND_NUMBER : request_builder::KIND_CATEGORY
                );
                $mform->disabledIf('csvkind_' . $i, 'csvprio_' . $i, 'eq', 0);
            }
            if (!$csv['columns']) {
                $mform->addElement('static', 'csvnocols', '', get_string('csvnocolumns', 'local_balancedgroups'));
            }
        }

        if ($users) {
            $mform->addElement('header', 'codesheader', get_string('rulesheader', 'local_balancedgroups'));
            $mform->setExpanded('codesheader');
            $mform->addElement('static', 'codesintro', '', get_string('codesintro', 'local_balancedgroups'));
            if ($csv && $csv['hascodes']) {
                $mform->addElement('select', 'codesource', get_string('codesource', 'local_balancedgroups'), [
                    'typed' => get_string('codesourcetyped', 'local_balancedgroups'),
                    'csv' => get_string('codesourcecsv', 'local_balancedgroups'),
                ]);
                $mform->setDefault('codesource', 'csv');
            } else {
                $mform->addElement('hidden', 'codesource', 'typed');
            }
            $mform->setType('codesource', PARAM_ALPHA);
            $togetherlabel = get_string('together', 'local_balancedgroups');
            $apartlabel = get_string('apart', 'local_balancedgroups');
            foreach ($users as $userid => $name) {
                $mform->addGroup([
                    $mform->createElement(
                        'text',
                        'tog_' . $userid,
                        $togetherlabel,
                        ['size' => 8, 'placeholder' => $togetherlabel]
                    ),
                    $mform->createElement(
                        'text',
                        'apa_' . $userid,
                        $apartlabel,
                        ['size' => 8, 'placeholder' => $apartlabel]
                    ),
                ], 'codes_' . $userid, s($name), ' ', false);
                $mform->setType('tog_' . $userid, PARAM_TEXT);
                $mform->setType('apa_' . $userid, PARAM_TEXT);
                $mform->disabledIf('codes_' . $userid, 'codesource', 'eq', 'csv');
            }
        }

        $mform->addElement('header', 'sizeheader', get_string('sizeheader', 'local_balancedgroups'));
        $mform->setExpanded('sizeheader');
        $mform->addElement(
            'static',
            'peoplecount',
            get_string('peopletogroup', 'local_balancedgroups'),
            (int)$this->_customdata['usercount']
        );
        $mform->addElement(
            'radio',
            'sizemode',
            get_string('sizemode', 'local_balancedgroups'),
            get_string('sizemodegroups', 'local_balancedgroups'),
            'groups'
        );
        $mform->addElement('radio', 'sizemode', '', get_string('sizemoderange', 'local_balancedgroups'), 'range');
        $mform->setDefault('sizemode', 'groups');
        $mform->addElement('text', 'numgroups', get_string('numgroups', 'local_balancedgroups'), ['size' => 4]);
        $mform->setType('numgroups', PARAM_INT);
        $mform->hideIf('numgroups', 'sizemode', 'eq', 'range');
        $mform->addElement('text', 'minsize', get_string('minsize', 'local_balancedgroups'), ['size' => 4]);
        $mform->setType('minsize', PARAM_INT);
        $mform->hideIf('minsize', 'sizemode', 'eq', 'groups');
        $mform->addElement('text', 'maxsize', get_string('maxsize', 'local_balancedgroups'), ['size' => 4]);
        $mform->setType('maxsize', PARAM_INT);
        $mform->hideIf('maxsize', 'sizemode', 'eq', 'groups');
        $mform->addHelpButton('sizemode', 'sizemode', 'local_balancedgroups');

        $mform->addElement('text', 'namingscheme', get_string('namingscheme', 'group'));
        $mform->setType('namingscheme', PARAM_TEXT);
        $mform->setDefault('namingscheme', get_string('grouptemplate', 'group'));
        $mform->addHelpButton('namingscheme', 'namingscheme', 'group');
        $mform->addElement('text', 'groupingname', get_string('groupingname', 'group'), ['size' => 40]);
        $mform->setType('groupingname', PARAM_TEXT);
        $mform->setDefault('groupingname', get_string(
            'defaultgroupingname',
            'local_balancedgroups',
            userdate(time(), get_string('strftimedatetimeshort', 'langconfig'))
        ));

        $mform->addElement('html', $this->_customdata['disclosure']);

        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('makegroups', 'local_balancedgroups')),
            $mform->createElement('submit', 'backbutton', get_string('back')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttons, 'buttonar', '', ' ', false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Validation of sizes, names and the number of fields.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['backbutton'])) {
            return $errors;
        }
        $count = (int)$this->_customdata['usercount'];

        $used = count($this->_customdata['fields']);
        foreach ($data as $name => $value) {
            if (strpos($name, 'csvprio_') === 0 && (int)$value > 0) {
                $used++;
            }
        }
        if ($used < 1) {
            $errors['priointro'] = get_string('errornofields', 'local_balancedgroups');
        } else if ($used > request_builder::MAX_FIELDS) {
            $errors['priointro'] = get_string(
                'errortoomanyfields',
                'local_balancedgroups',
                (object)['count' => $used, 'max' => request_builder::MAX_FIELDS]
            );
        }

        if (($data['sizemode'] ?? 'groups') === 'groups') {
            $g = (int)($data['numgroups'] ?? 0);
            if ($g < 1) {
                $errors['numgroups'] = get_string('errornumgroups', 'local_balancedgroups');
            } else if ($g > request_builder::MAX_GROUPS) {
                $errors['numgroups'] = get_string(
                    'errortoomanygroups',
                    'local_balancedgroups',
                    (object)['count' => $g, 'max' => request_builder::MAX_GROUPS]
                );
            } else if ($g > $count) {
                $errors['numgroups'] = get_string('errormoregroupsthanpeople', 'local_balancedgroups', $count);
            }
        } else {
            $min = (int)($data['minsize'] ?? 0);
            $max = (int)($data['maxsize'] ?? 0);
            if ($min < 1 && $max < 1) {
                $errors['minsize'] = get_string('errornosize', 'local_balancedgroups');
            } else if ($min < 0 || $max < 0) {
                $errors['minsize'] = get_string('errornosize', 'local_balancedgroups');
            } else if ($min > 0 && $max > 0 && $min > $max) {
                $errors['maxsize'] = get_string('errorminmax', 'local_balancedgroups');
            }
        }

        $scheme = trim($data['namingscheme'] ?? '');
        if (substr_count($scheme, '@') + substr_count($scheme, '#') !== 1) {
            $errors['namingscheme'] = get_string('badnamingscheme', 'group');
        }
        if (trim($data['groupingname'] ?? '') === '') {
            $errors['groupingname'] = get_string('errorgroupingnameempty', 'local_balancedgroups');
        }
        return $errors;
    }
}
