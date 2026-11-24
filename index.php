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

/**
 * Balanced groups: choose people and data, run the external solver, preview, create.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/group/lib.php');

use local_balancedgroups\local\api_client;
use local_balancedgroups\local\balance_report;
use local_balancedgroups\local\csv_source;
use local_balancedgroups\local\group_creator;
use local_balancedgroups\local\request_builder;
use local_balancedgroups\local\source;
use local_balancedgroups\local\wizard;

$courseid = required_param('id', PARAM_INT);
$step = optional_param('step', 'setup', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/balancedgroups:use', $context);

$baseurl = new moodle_url('/local/balancedgroups/index.php', ['id' => $course->id]);
$PAGE->set_url(new moodle_url($baseurl, ['step' => $step]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'local_balancedgroups') . ': ' .
    format_string($course->shortname, true, ['context' => $context]));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
$PAGE->navbar->add(get_string('pluginname', 'local_balancedgroups'), $baseurl);

$output = $PAGE->get_renderer('core');

if (!get_config('local_balancedgroups', 'enabled')) {
    echo $output->header();
    echo $output->heading(get_string('pluginname', 'local_balancedgroups'));
    echo $output->notification(get_string('notenabled', 'local_balancedgroups'), 'info');
    if (has_capability('moodle/site:config', context_system::instance())) {
        echo html_writer::div(html_writer::link(
            new moodle_url('/admin/settings.php', ['section' => 'local_balancedgroups']),
            get_string('gotosettings', 'local_balancedgroups')
        ));
    }
    echo $output->footer();
    exit;
}

$cache = cache::make('local_balancedgroups', 'wizard');
$cachekey = 'c' . $course->id;
$state = $cache->get($cachekey) ?: [];
$source = new source($course);

if ($action === 'reset') {
    require_sesskey();
    $cache->delete($cachekey);
    redirect($baseurl);
}

// Step 3: preview, run again, create.
if ($step === 'preview') {
    if (empty($state['result'])) {
        redirect(new moodle_url($baseurl, ['step' => empty($state['details']) ? 'setup' : 'details']));
    }
    $error = null;
    if ($action === 'rerun') {
        require_sesskey();
        $error = wizard::run($course, $source, $state);
        if ($error === null) {
            $cache->set($cachekey, $state);
            redirect(
                new moodle_url($baseurl, ['step' => 'preview']),
                get_string('rerundone', 'local_balancedgroups'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }
    $result = $state['result'];
    $details = $state['details'];
    $groupnames = group_creator::names($details['namingscheme'], count($result['groups']));
    $problems = group_creator::check($course->id, $details['groupingname'], $groupnames);
    $canmanage = has_capability('moodle/course:managegroups', $context);

    if ($action === 'create') {
        require_sesskey();
        require_capability('moodle/course:managegroups', $context);
        if (!$problems) {
            try {
                $groupingid = group_creator::create($course, $details['groupingname'], $groupnames, $result['groups']);
            } catch (moodle_exception $e) {
                $groupingid = null;
                $error = $e->getMessage();
            }
        }
        if (!empty($groupingid)) {
            $cache->delete($cachekey);
            redirect(
                new moodle_url('/group/overview.php', ['id' => $course->id, 'grouping' => $groupingid]),
                get_string('created', 'local_balancedgroups', (object)['groups' => count($groupnames),
                'grouping' => s($details['groupingname'])]),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }

    $sizes = array_map('count', $result['groups']);
    $groups = [];
    foreach ($result['groups'] as $i => $members) {
        $groups[] = ['name' => $groupnames[$i], 'size' => count($members),
            'members' => array_map(static fn($u) => ['name' => $result['names'][$u] ?? ''], $members)];
    }
    $summary = get_string('summary', 'local_balancedgroups', (object)[
        'people' => array_sum($sizes), 'groups' => count($sizes),
        'sizes' => min($sizes) === max($sizes) ? min($sizes) : min($sizes) . '–' . max($sizes)]);
    $ctx = [
        'steps' => wizard::steps('preview'),
        'summary' => $summary,
        'meaning' => $result['meaning'],
        'warnings' => array_map(static fn($w) => ['text' => $w], $result['warnings']),
        'notes' => array_map(static fn($w) => ['text' => $w], $result['notes']),
        'hasmessages' => !empty($result['warnings']) || !empty($result['notes']),
        'groups' => $groups,
        'fields' => balance_report::build(
            $result['groups'],
            $groupnames,
            $result['fields'],
            $result['values'],
            $result['balance']
        ),
        'createlabel' => get_string('creategroups', 'local_balancedgroups', $details['groupingname']),
        'problems' => array_map(static fn($p) => ['text' => $p], $problems),
        'hasproblems' => !empty($problems),
        'cancreate' => $canmanage && !$problems,
        'nocapability' => !$canmanage,
        'actionurl' => (new moodle_url($baseurl, ['step' => 'preview']))->out(false),
        'sesskey' => sesskey(),
        'detailsurl' => (new moodle_url($baseurl, ['step' => 'details']))->out(false),
        'reseturl' => (new moodle_url($baseurl))->out(false),
        'courseid' => $course->id,
    ];
    echo $output->header();
    echo $output->heading(get_string('pluginname', 'local_balancedgroups'));
    if ($error !== null) {
        echo $output->notification($error, 'error');
    }
    echo $output->render_from_template('local_balancedgroups/preview', $ctx);
    echo $output->footer();
    exit;
}

// Step 2: priorities, CSV columns, rules, sizes and names.
if ($step === 'details') {
    if (empty($state['setup'])) {
        redirect($baseurl);
    }
    $setup = $state['setup'];
    $users = $source->users((int)$setup['roleid'], (int)$setup['groupid']);
    $fields = wizard::chosen_fields($source, $setup);

    $csvdata = null;
    $csv = $state['csv'] ?? null;
    if ($csv) {
        $matchoptions = [];
        foreach (csv_source::match_columns($csv['headers']) as $col => $userfield) {
            $m = csv_source::match($csv, $col, $userfield, $users);
            $matchoptions[$col] = get_string('csvmatchoption', 'local_balancedgroups', (object)[
                'column' => $csv['headers'][$col], 'matched' => count($m['rows']), 'total' => count($users)]);
        }
        $skip = array_keys($matchoptions);
        $togcol = csv_source::find_column($csv['headers'], csv_source::TOGETHER_ALIASES);
        $apacol = csv_source::find_column($csv['headers'], csv_source::APART_ALIASES);
        foreach ([$togcol, $apacol] as $c) {
            if ($c !== null) {
                $skip[] = $c;
            }
        }
        $columns = [];
        foreach ($csv['headers'] as $col => $header) {
            if (!in_array($col, $skip, true)) {
                $columns[$col] = ['label' => $header, 'numeric' => csv_source::is_numeric_column($csv['rows'], $col)];
            }
        }
        $csvdata = ['matchoptions' => $matchoptions, 'columns' => $columns,
            'hascodes' => $togcol !== null || $apacol !== null];
    }

    $names = [];
    if (!empty($setup['usecodes'])) {
        foreach ($users as $userid => $user) {
            $names[$userid] = fullname($user);
        }
    }
    $apiurl = get_config('local_balancedgroups', 'apiurl') ?: api_client::DEFAULT_URL;
    $host = parse_url($apiurl, PHP_URL_HOST) ?: $apiurl;
    $disclosure = $output->render_from_template('local_balancedgroups/disclosure', [
        'title' => get_string('disclosuretitle', 'local_balancedgroups', $host),
        'privacyurl' => $host === 'group-maker.kindoflost.com' ? 'https://group-maker.kindoflost.com/privacy' : null,
    ]);

    $form = new \local_balancedgroups\form\details_form(new moodle_url($baseurl, ['step' => 'details']), [
        'fields' => $fields, 'csv' => $csvdata, 'users' => $names, 'usercount' => count($users),
        'disclosure' => $disclosure,
    ]);

    // Defaults from earlier choices.
    if (!empty($state['details'])) {
        $d = $state['details'];
        $defaults = ['sizemode' => $d['sizemode'], 'numgroups' => $d['numgroups'] ?: '',
            'minsize' => $d['minsize'] ?: '', 'maxsize' => $d['maxsize'] ?: '',
            'namingscheme' => $d['namingscheme'], 'groupingname' => $d['groupingname'],
            'codesource' => $d['codesource'] ?? 'typed', 'csvmatch' => $d['csvmatch'] ?? null];
        foreach ($d['prio'] as $k => $v) {
            $defaults['prio_' . $k] = $v;
            $defaults['kind_' . $k] = $d['kind'][$k] ?? null;
        }
        foreach (($d['csvprio'] ?? []) as $k => $v) {
            $defaults['csvprio_' . $k] = $v;
            $defaults['csvkind_' . $k] = $d['csvkind'][$k] ?? null;
        }
        foreach (($d['codes'] ?? []) as $userid => $c) {
            $defaults['tog_' . $userid] = $c['t'];
            $defaults['apa_' . $userid] = $c['a'];
        }
        $form->set_data(array_filter($defaults, static fn($v) => $v !== null));
    }

    $error = null;
    if ($form->is_cancelled()) {
        $cache->delete($cachekey);
        redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
    } else if ($data = $form->get_data()) {
        $data = (array)$data;
        $d = ['prio' => [], 'kind' => [], 'csvprio' => [], 'csvkind' => [], 'codes' => [],
            'sizemode' => $data['sizemode'] ?? 'groups', 'numgroups' => (int)($data['numgroups'] ?? 0),
            'minsize' => (int)($data['minsize'] ?? 0), 'maxsize' => (int)($data['maxsize'] ?? 0),
            'namingscheme' => trim($data['namingscheme'] ?? ''), 'groupingname' => trim($data['groupingname'] ?? ''),
            'codesource' => $data['codesource'] ?? 'typed', 'csvmatch' => isset($data['csvmatch']) ? (int)$data['csvmatch'] : null];
        foreach ($fields as $field) {
            $k = $field['key'];
            $d['prio'][$k] = (int)($data['prio_' . $k] ?? 1);
            $kind = $data['kind_' . $k] ?? $field['kind'];
            $d['kind'][$k] = $kind === request_builder::KIND_NUMBER ? $kind : request_builder::KIND_CATEGORY;
        }
        foreach (($csvdata['columns'] ?? []) as $col => $unused) {
            $d['csvprio'][$col] = (int)($data['csvprio_' . $col] ?? 0);
            $kind = $data['csvkind_' . $col] ?? request_builder::KIND_CATEGORY;
            $d['csvkind'][$col] = $kind === request_builder::KIND_NUMBER ? $kind : request_builder::KIND_CATEGORY;
        }
        foreach ($names as $userid => $unused) {
            $t = trim($data['tog_' . $userid] ?? '');
            $a = trim($data['apa_' . $userid] ?? '');
            if ($t !== '' || $a !== '') {
                $d['codes'][$userid] = ['t' => $t, 'a' => $a];
            }
        }
        $state['details'] = $d;
        $cache->set($cachekey, $state);
        if (!empty($data['backbutton'])) {
            redirect($baseurl);
        }
        $error = wizard::run($course, $source, $state);
        if ($error === null) {
            $cache->set($cachekey, $state);
            redirect(new moodle_url($baseurl, ['step' => 'preview']));
        }
    }

    echo $output->header();
    echo $output->heading(get_string('pluginname', 'local_balancedgroups'));
    echo wizard::steps('details');
    if ($error !== null) {
        echo $output->notification($error, 'error');
    }
    $form->display();
    echo $output->footer();
    exit;
}

// Step 1: who and which data.
$hascsv = '';
if (!empty($state['csv'])) {
    $hascsv = get_string('csvsummary', 'local_balancedgroups', (object)['rows' => count($state['csv']['rows']),
        'columns' => count($state['csv']['headers'])]);
}
$form = new \local_balancedgroups\form\setup_form(
    new moodle_url($baseurl, ['step' => 'setup']),
    ['source' => $source, 'hascsv' => $hascsv]
);
if (!empty($state['setup'])) {
    $defaults = ['roleid' => $state['setup']['roleid'], 'groupid' => $state['setup']['groupid'],
        'usecodes' => $state['setup']['usecodes']];
    foreach ($state['setup']['fields'] as $k => $v) {
        $defaults['field_' . $k] = $v;
    }
    $form->set_data($defaults);
}
if ($form->is_cancelled()) {
    $cache->delete($cachekey);
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
} else if ($data = $form->get_data()) {
    $data = (array)$data;
    $setup = ['roleid' => (int)$data['roleid'], 'groupid' => (int)$data['groupid'],
        'usecodes' => !empty($data['usecodes']), 'fields' => []];
    foreach ($source->fields() as $key => $unused) {
        if (!empty($data['field_' . $key])) {
            $setup['fields'][$key] = 1;
        }
    }
    $state['setup'] = $setup;
    if ($form->parsedcsv !== null) {
        $state['csv'] = $form->parsedcsv;
        unset($state['details']['csvprio'], $state['details']['csvkind'], $state['details']['csvmatch']);
    } else if (!empty($data['removecsv'])) {
        unset($state['csv']);
    }
    unset($state['result']);
    $cache->set($cachekey, $state);
    redirect(new moodle_url($baseurl, ['step' => 'details']));
}

echo $output->header();
echo $output->heading(get_string('pluginname', 'local_balancedgroups'));
echo wizard::steps('setup');
echo html_writer::div(get_string('intro', 'local_balancedgroups'), 'mb-3');
if (!empty($state)) {
    echo html_writer::div($output->single_button(
        new moodle_url($baseurl, ['action' => 'reset']),
        get_string('startover', 'local_balancedgroups'),
        'post'
    ), 'mb-3');
}
$form->display();
echo $output->footer();
