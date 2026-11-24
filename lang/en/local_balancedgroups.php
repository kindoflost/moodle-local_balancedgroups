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
 * English strings for local_balancedgroups.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apart'] = 'Must be apart';
$string['apiurl'] = 'API URL';
$string['apiurl_desc'] = 'Address of the Balanced Group Maker endpoint. Change it only if you run your own copy of the service.';
$string['average'] = 'Average';
$string['backtostart'] = 'Start again from step 1';
$string['balanceas'] = 'Balance as';
$string['balancedgroups:use'] = 'Make balanced groups with the external Balanced Group Maker service';
$string['balanceheading'] = 'How balanced the groups are';
$string['balanceintro'] = 'Computed here from your real data. For categories, each group should hold about the same share of each value as the whole list; for numbers, each group\'s average should be close to the overall average.';
$string['balancescore'] = 'Balance score {$a} / 100';
$string['cachedef_wizard'] = 'Choices and preview of the balanced groups wizard (current session only)';
$string['changesettings'] = 'Change priorities, rules or sizes';
$string['codesintro'] = 'People who share a code in "Must be together" always end up in the same group; people who share a code in "Must be apart" always end up in different groups. Codes can be any word or number, and several codes can be separated by commas. These are hard rules.';
$string['codesource'] = 'Take the codes from';
$string['codesourcecsv'] = 'The together/apart columns of the CSV file';
$string['codesourcetyped'] = 'What is typed below';
$string['countshare'] = '{$a->count} ({$a->share}%)';
$string['created'] = '{$a->groups} groups were created in the grouping "{$a->grouping}".';
$string['creategroups'] = 'Create these groups in the grouping "{$a}"';
$string['csvcolsheader'] = 'Columns from the CSV file';
$string['csvcurrent'] = 'Uploaded CSV file';
$string['csvdelimiter'] = 'CSV separator';
$string['csvencoding'] = 'Encoding';
$string['csvfile'] = 'CSV file';
$string['csvheader'] = 'Extra data from a CSV file (optional)';
$string['csvintro'] = 'A CSV file with a header row and one row per student. It must have a column called username, email or idnumber to match students; other columns can be balanced (for example a reading level or a survey answer). Columns called "must be together" and "must be apart" can supply the rules. The file is read once and kept only in your session.';
$string['csvmatch'] = 'Match students on';
$string['csvmatch_help'] = 'The CSV column used to find each student. Students without a matching row get blank values for the CSV columns, and blank values are simply left out of the balance for that column.';
$string['csvmatchoption'] = '{$a->column} ({$a->matched} of {$a->total} people found)';
$string['csvnocolumns'] = 'The CSV file has no other columns to balance.';
$string['csvremove'] = 'Remove the uploaded CSV file';
$string['csvsummary'] = '{$a->rows} rows, {$a->columns} columns';
$string['defaultgroupingname'] = 'Balanced groups {$a}';
$string['disclosurenotsent'] = 'Not sent: names, email addresses, usernames, user ids, field names or the real values of text fields. The answer comes back in the same request and nothing is created in Moodle until you press "Create" on the next page.';
$string['disclosureprivacy'] = 'Privacy policy of the Balanced Group Maker service';
$string['disclosuresent'] = 'Sent: a random code for each person (new on every run), the balancing fields as f1, f2..., text values replaced by codes c1, c2..., numbers such as grades as they are, together/apart rules as codes, the group sizes, and an anonymous key made from this site\'s address for rate limiting.';
$string['disclosuretitle'] = 'When you press "Make groups", data is sent to {$a}';
$string['enabled'] = 'Enable sending data to the external service';
$string['enabled_desc'] = 'Off by default. While this is off, teachers cannot use the tool. Turn it on only if your institution allows sending the anonymised data described above to the Balanced Group Maker service.';
$string['error400'] = 'The service did not accept the request.';
$string['error413'] = 'The class list is too large to send. Use fewer people or fewer fields.';
$string['error422'] = 'No grouping could be made with these settings. Relax a rule, change the group sizes or use fewer fields.';
$string['error429'] = 'Too many requests from this site in the last minute (the free service allows 10 per minute per site). Wait a minute and try again.';
$string['error503'] = 'The service is busy solving another grouping right now. Wait a few seconds and try again.';
$string['errorbadrequest'] = 'The request could not be prepared.';
$string['errorbadresponse'] = 'The service sent an answer this plugin cannot read. Nothing was changed. Try again later.';
$string['errorcannotcreate'] = 'The groups were not created: {$a}';
$string['errorconnect'] = 'The Balanced Group Maker service could not be reached ({$a}). Check that this server can make outgoing HTTPS requests to it, including Moodle\'s "cURL blocked hosts list" and "cURL allowed ports list" settings.';
$string['errorcsv'] = 'The CSV file could not be read: {$a}';
$string['errorcsvcolumns'] = 'The CSV file has more than {$a} columns.';
$string['errorcsvempty'] = 'The CSV file has a header row but no data rows.';
$string['errorcsvnomatch'] = 'The CSV file needs a column called username, email or idnumber to match students.';
$string['errorcsvrows'] = 'The CSV file has more than {$a} rows.';
$string['errorgroupexists'] = 'A group called "{$a}" already exists in this course.';
$string['errorgroupingexists'] = 'A grouping called "{$a}" already exists in this course.';
$string['errorgroupingnameempty'] = 'Give the grouping a name.';
$string['errorhttp'] = 'The service answered with HTTP status {$a}. Try again later.';
$string['errorminmax'] = 'The minimum size is larger than the maximum size.';
$string['errormoregroupsthanpeople'] = 'There are only {$a} people to group.';
$string['errornamesnotunique'] = 'The naming scheme gives two groups the same name.';
$string['errornofields'] = 'Choose at least one field to balance, or upload a CSV file with columns to balance.';
$string['errornosize'] = 'Give a minimum and/or a maximum group size.';
$string['errornotenrolled'] = '{$a} is no longer enrolled in the course. Run the grouping again.';
$string['errornotnumeric'] = '"{$a->field}" is set to balance as numbers, but {$a->person} has "{$a->value}". Balance it as categories instead, or fix the value.';
$string['errornumgroups'] = 'Give a number of groups of 1 or more.';
$string['errortimeout'] = 'The service did not answer within {$a} seconds. It may be busy; try again in a minute. The site administrator can raise the timeout in the plugin settings.';
$string['errortoofewpeople'] = 'At least 2 people are needed (found {$a}). Check the role and group choices.';
$string['errortoomanyfields'] = '{$a->count} fields are chosen; the limit is {$a->max}.';
$string['errortoomanygroups'] = '{$a->count} groups were asked for; the limit is {$a->max}.';
$string['errortoomanypeople'] = '{$a->count} people match these choices; the limit is {$a->max}. Limit the list to an existing group.';
$string['eventgroupscreated'] = 'Balanced groups created';
$string['fieldsheader'] = 'Data to balance';
$string['fieldsintro'] = 'Each group will mirror the whole list on every field you tick: categories (such as country) are spread proportionally, numbers (such as grades) get equal group averages. You set the priority of each field on the next page.';
$string['fixnames'] = 'Change the naming scheme or the grouping name under "Change priorities, rules or sizes".';
$string['gotosettings'] = 'Open the plugin settings';
$string['groupingdescription'] = 'Created by the Balanced groups tool.';
$string['intro'] = 'Split the people of this course into groups that are balanced on the data you choose, using the free Balanced Group Maker optimization service. You see a preview before anything is created.';
$string['kindcategory'] = 'Categories (spread proportionally)';
$string['kindnumber'] = 'Numbers (equal averages)';
$string['limittogroup'] = 'Only people in group';
$string['limittogroup_help'] = 'Use this to split one existing group (for example a class section) into smaller balanced groups.';
$string['makegroups'] = 'Make groups';
$string['maxsize'] = 'Maximum group size';
$string['minsize'] = 'Minimum group size';
$string['nofields'] = 'No fields are available to you. You can still upload a CSV file.';
$string['nolimitgroup'] = 'Any group';
$string['nomanagegroups'] = 'You can preview groupings, but creating groups needs the capability to manage groups in this course.';
$string['notbalanced'] = 'Nothing to balance';
$string['notenabled'] = 'The site administrator has not enabled the Balanced groups tool. It sends anonymised class data to an external service, so it is off until an administrator turns it on.';
$string['notused'] = 'Not used';
$string['numgroups'] = 'Number of groups';
$string['overall'] = 'Overall';
$string['peoplecounted'] = 'People counted';
$string['peopletogroup'] = 'People to group';
$string['pluginname'] = 'Balanced groups';
$string['prioheader'] = 'Priorities';
$string['priointro'] = 'Priority 1 is the most important. Each step down counts four times less, so a lower-priority field gives way before a higher one. Ties are allowed.';
$string['priority'] = 'Priority';
$string['prioritytop'] = '1 (most important)';
$string['privacy:metadata:balancedgroupmaker'] = 'To compute the groups, anonymised data about the selected people is sent to the Balanced Group Maker service (group-maker.kindoflost.com by default). The service holds it in memory for the length of the request only and does not store it.';
$string['privacy:metadata:balancedgroupmaker:fieldvalues'] = 'The values of the fields chosen for balancing: numbers (such as grades) as they are, text values replaced by codes. Field names are not sent.';
$string['privacy:metadata:balancedgroupmaker:persontoken'] = 'A random code for each person, created anew for every request. It is not the user id and cannot be linked back to the user outside this Moodle site.';
$string['privacy:metadata:balancedgroupmaker:rulecodes'] = 'Together/apart rules, with the teacher\'s codes replaced by neutral codes.';
$string['privacy:metadata:balancedgroupmaker:sitekey'] = 'A hash of this site\'s address, used by the service only to rate-limit requests per site.';
$string['privacy:metadata:core_group'] = 'The groups and group memberships this plugin creates are stored by the core groups subsystem.';
$string['proposedgroups'] = 'Proposed groups';
$string['rerun'] = 'Run again';
$string['rerundone'] = 'The groups were computed again.';
$string['rulesheader'] = 'Together and apart rules';
$string['settingsdisclosure'] = '<p>This plugin sends data to an external service, the free <a href="https://group-maker.kindoflost.com" target="_blank" rel="noopener">Balanced Group Maker</a> (group-maker.kindoflost.com, run by Kind of Lost and hosted on Render in the United States), which computes the groups with a mathematical optimization solver.</p><p><strong>Sent</strong> when a teacher presses "Make groups": a random code for each person (new on every request), the values of the fields chosen for balancing (numbers as they are, text values replaced by codes c1, c2...; field names replaced by f1, f2...), together/apart rules as neutral codes, the group sizes, and a hash of this site\'s address for rate limiting. <strong>Not sent</strong>: names, email addresses, usernames, user ids or any other identifier. The service states that it keeps the data in memory only for the request, does not store or share it and does not use it to train AI models; it logs one anonymous row per run (time, country of the request, number of people and groups, duration, success). See its <a href="https://group-maker.kindoflost.com/privacy" target="_blank" rel="noopener">privacy policy</a>.</p><p>Grades and profile fields can still be personal data even without names. Check that your institution allows this before enabling.</p>';
$string['sizeheader'] = 'Groups';
$string['sizemode'] = 'Group size';
$string['sizemode_help'] = 'With a number of groups, group sizes differ by at most one. With a minimum and/or maximum size, the service picks the number of groups whose sizes fit the range best.';
$string['sizemodegroups'] = 'Set the number of groups';
$string['sizemoderange'] = 'Set a minimum and/or maximum group size';
$string['solvernote'] = 'Solver:';
$string['source_customprofile'] = 'Custom profile fields';
$string['source_grade'] = 'Gradebook';
$string['source_profile'] = 'Profile';
$string['startover'] = 'Start over';
$string['stepdetails'] = 'Priorities, rules and sizes';
$string['steppreview'] = 'Preview and create';
$string['stepsetup'] = 'People and data';
$string['summary'] = '{$a->people} people in {$a->groups} groups of {$a->sizes}';
$string['timeout'] = 'Timeout (seconds)';
$string['timeout_desc'] = 'How long to wait for an answer. The service may queue a request for up to 45 seconds when busy and then solve it, so values under 75 can cut off answers that were on their way.';
$string['together'] = 'Must be together';
$string['usecodes'] = 'Together/apart rules';
$string['usecodes_help'] = 'Tick this to keep some people in the same group or in different groups. On the next page you type a code per person, or take the codes from the CSV file.';
$string['usecodes_label'] = 'Use together/apart rules';
$string['whoheader'] = 'People to group';
