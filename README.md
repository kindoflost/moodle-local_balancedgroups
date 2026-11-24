# Balanced groups (local_balancedgroups)

Split the people of a Moodle course into groups that are **balanced** on the data you choose:
profile fields, custom profile fields, gradebook items (including the course total) and columns
from a CSV file. Each group mirrors the whole list: categories (such as country or reading level)
are spread proportionally and numbers (such as grades) get equal group averages. Together/apart
rules ("keep these two together", "keep these three apart") are hard rules that are never broken.

The groups are computed by the free **Balanced Group Maker** service
(<https://group-maker.kindoflost.com>), which runs a mixed-integer optimization solver. The plugin
sends it anonymised data only (see [External service and privacy](#external-service-and-privacy)).
Nothing is created in Moodle until the teacher has seen a preview and pressed **Create**.

## Requirements

- Moodle 4.5 LTS to 5.3 (tested on 4.5 with PostgreSQL and MariaDB, and on 5.3 with PostgreSQL)
- PHP 8.1 or later
- Outgoing HTTPS from the Moodle server to `group-maker.kindoflost.com` (port 443)

## Installation

1. Copy this folder to `local/balancedgroups` in your Moodle code (on Moodle 5.1 and later:
   `public/local/balancedgroups`), or install the ZIP from *Site administration > Plugins > Install plugins*.
2. Visit *Site administration > Notifications* to finish the installation.
3. Read the disclosure at *Site administration > Plugins > Local plugins > Balanced groups* and,
   if your institution allows it, tick **Enable sending data to the external service**. The plugin
   does nothing until an administrator enables it.

## Using it

In a course, open **More > Balanced groups** (needs `local/balancedgroups:use`; given to editing
teachers and managers by default).

1. **People and data** - choose the role (students by default), optionally limit to an existing
   group, tick the fields to balance, optionally upload a CSV file, and tick *Use together/apart
   rules* if needed.
2. **Priorities, rules and sizes** - give each field a priority (1 = most important; each step
   counts four times less; ties allowed) and say whether to balance it as categories or numbers;
   pick the CSV match column and CSV columns to use; type together/apart codes per person (or take
   them from the CSV); choose a number of groups or a minimum/maximum size; set the group naming
   scheme (core style: `Group @` gives Group A, B, C...; `Group #` gives Group 1, 2, 3...) and the
   grouping name. Press **Make groups**.
3. **Preview and create** - see the groups and a balance table per field, computed in Moodle from
   your real values. **Run again** asks for another equally balanced arrangement (people are sent in
   a new random order with new random codes). **Create** makes the grouping and its groups with the
   core groups API (needs `moodle/course:managegroups`) and logs a
   `\local_balancedgroups\event\groups_created` event.

### CSV file

UTF-8 (or another encoding chosen on the form), comma, semicolon, colon or tab separated, with a
header row. It needs a column named `username`, `email` or `idnumber` to match students. Other
columns can be balanced. Columns named `must be together` and `must be apart` (or `together`,
`apart`, `keep together`, `keep apart`) can supply the rules. Several codes in one cell are
separated by commas. Example:

```
email,reading level,must be together,must be apart
ana@example.com,3,1,
bo@example.com,5,1,A
cy@example.com,2,,A
```

The CSV is parsed once and kept only in the teacher's session cache while the wizard is open.

### Limits

These are the service's limits: 300 people, 60 groups and 15 balancing fields per run. The service
allows 10 runs per minute per Moodle site. A run normally answers in 5-20 seconds; when the
service is busy it can queue a request for up to 45 seconds, so the default timeout is 90 seconds.

## Capabilities

| Capability | Default | Purpose |
|---|---|---|
| `local/balancedgroups:use` | editing teacher, manager | Open the page and send the anonymised data to the service (marked RISK_PERSONAL) |
| `moodle/course:managegroups` (core) | editing teacher, manager | Create the grouping and groups |
| `moodle/grade:viewall` (core) | editing teacher, teacher, manager | Needed to see gradebook items as fields |

Custom profile fields are offered only when the user may see them (visible to everyone; visible to
teachers with `moodle/site:viewuseridentity`; otherwise `moodle/user:viewalldetails`). City and
country respect *Hide user fields* unless the user has `moodle/course:viewhiddenuserfields`;
institution and department are offered only when they are shown identity fields for the user.

## Settings

| Setting | Default | Notes |
|---|---|---|
| Enable sending data to the external service | Off | Nothing can be sent until an administrator turns it on |
| API URL | `https://group-maker.kindoflost.com/addon/groups` | Change only for a self-hosted copy |
| Timeout (seconds) | 90 | Values under 75 can cut off answers that were queued |

If your site uses *cURL blocked hosts list* / *cURL allowed ports list*, `group-maker.kindoflost.com`
must not be blocked and port 443 must be allowed.

## External service and privacy

This plugin sends data to an external service: **Balanced Group Maker**
(<https://group-maker.kindoflost.com>), run by Mario of Kind of Lost (kindoflost.com,
<mario@kindoflost.com>) and hosted on Render (render.com) in the United States.

**What is sent**, only when a teacher presses *Make groups* or *Run again*, over HTTPS, as one
JSON request to `POST /addon/groups`:

- a random code for each person (for example `p3f9a01c2b7d4`), created anew for every request;
- for each field chosen for balancing, the field as `f1`, `f2`, ... with its priority;
- the values of those fields: numbers (such as grades) as they are, text values replaced by codes
  `c1`, `c2`, ... (per field);
- together/apart rules, with the teacher's codes replaced by `T1`, `T2`, ... and `A1`, `A2`, ...;
- the number of groups or the minimum/maximum group size;
- an HTTP header `X-Addon-User` holding a SHA-256 hash of the site's URL, used by the service only
  for rate limiting, and `X-Client: moodle-local_balancedgroups/<version>`.

**What is not sent**: names, email addresses, usernames, Moodle user ids, ID numbers, field names,
real category values (such as city names), group names or course information. The table that maps
the random codes back to people stays in the teacher's Moodle session. Note that grades and other
values can still be personal data even without names; check your institution's rules before
enabling the plugin.

**What the service does with it** (from its privacy policy,
<https://group-maker.kindoflost.com/privacy>, last updated November 11, 2026):

- The data is used only to compute the groups. It is held in the server's memory for the length of
  that one request and then discarded. It is not written to a database or permanent disk, not
  logged, not backed up, not sold, and not shared with any third party.
- No AI or machine-learning model is trained on it or used to make the groups; they are computed by
  a mathematical optimization solver. Each solve runs in its own short-lived process.
- For each run the service records one anonymous row: date and time, where the run came from, the
  country of the request, the number of people and groups, how long the solve took and whether it
  succeeded. No names, no content, no email address and no IP address are stored. The calling
  address or key is held in memory for a few minutes for rate limiting only.
- Everything travels over HTTPS (TLS). Only the developer can access the server.
- Contact for questions or suspected exposure: <mario@kindoflost.com>.

**What the plugin stores in Moodle**: nothing of its own. The wizard's choices, the parsed CSV and
the last preview are kept in the user's session cache (MUC session store) and removed when the
groups are created, when the teacher presses *Start over*, or when the session ends. The groups,
grouping and memberships it creates belong to the core groups subsystem; the event is stored by the
site's log stores. The plugin implements the Moodle Privacy API and declares the external location
(`add_external_location_link`).

## Support and issues

Report problems at <https://github.com/kindoflost/moodle-local_balancedgroups/issues>.

## License

2026 Kind of Lost <mario@kindoflost.com>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU
General Public License as published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not,
see <https://www.gnu.org/licenses/>.
