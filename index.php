<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Provides the classic (before Moodle 4.3) browsing users mode.
 *
 * @package tool_browse_users_classic
 * @copyright 2025 Andrei Bautu <abautu@gmail.com>
 * @copyright 2022 based on work by Moodle Pty Ltd <support@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The lines below are 99.99% copied from admin/user.php (Moodle 4.2). The few (non-functional) changes allow it to work
 * in a different location and fix incompatibilities with Moodle coding standards.
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/user/filters/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

$delete       = optional_param('delete', 0, PARAM_INT);
$confirm      = optional_param('confirm', '', PARAM_ALPHANUM);   // The md5 confirmation hash.
$confirmuser  = optional_param('confirmuser', 0, PARAM_INT);
$sort         = optional_param('sort', 'name', PARAM_ALPHANUMEXT);
$dir          = optional_param('dir', 'ASC', PARAM_ALPHA);
$page         = optional_param('page', 0, PARAM_INT);
$perpage      = optional_param('perpage', 30, PARAM_INT);        // How many per page?
$ru           = optional_param('ru', '2', PARAM_INT);            // Show remote users?
$lu           = optional_param('lu', '2', PARAM_INT);            // Show local users?
$acl          = optional_param('acl', '0', PARAM_INT);           // Id of user to tweak mnet ACL (requires $access).
$suspend      = optional_param('suspend', 0, PARAM_INT);
$unsuspend    = optional_param('unsuspend', 0, PARAM_INT);
$unlock       = optional_param('unlock', 0, PARAM_INT);
$resendemail  = optional_param('resendemail', 0, PARAM_INT);

admin_externalpage_setup('editusers');

$sitecontext = context_system::instance();
$site = get_site();

if (!has_capability('moodle/user:update', $sitecontext) && !has_capability('moodle/user:delete', $sitecontext)) {
    throw new \moodle_exception('nopermissions', 'error', '', 'edit/delete users');
}

$stredit   = get_string('edit');
$strdelete = get_string('delete');
$strdeletecheck = get_string('deletecheck');
$strshowallusers = get_string('showallusers');
$strsuspend = get_string('suspenduser', 'admin');
$strunsuspend = get_string('unsuspenduser', 'admin');
$strunlock = get_string('unlockaccount', 'admin');
$strconfirm = get_string('confirm');
$strresendemail = get_string('resendemail');

$returnurl = new moodle_url(
    '/admin/tool/browse_users_classic/index.php',
    ['sort' => $sort, 'dir' => $dir, 'perpage' => $perpage, 'page' => $page]
);

$PAGE->set_primary_active_tab('siteadminnode');
$PAGE->navbar->add(get_string('userlist', 'admin'), $PAGE->url);

// The $user variable is also used outside of these if statements.
$user = null;
if ($confirmuser && confirm_sesskey()) {
    require_capability('moodle/user:update', $sitecontext);
    if (!$user = $DB->get_record('user', ['id' => $confirmuser, 'mnethostid' => $CFG->mnet_localhost_id])) {
        throw new \moodle_exception('nousers');
    }

    $auth = get_auth_plugin($user->auth);

    $result = $auth->user_confirm($user->username, $user->secret);

    if ($result == AUTH_CONFIRM_OK || $result == AUTH_CONFIRM_ALREADY) {
        redirect($returnurl);
    } else {
        echo $OUTPUT->header();
        redirect($returnurl, get_string('usernotconfirmed', '', fullname($user, true)));
    }
} else if ($resendemail && confirm_sesskey()) {
    if (!$user = $DB->get_record('user', ['id' => $resendemail, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0])) {
        throw new \moodle_exception('nousers');
    }

    // Prevent spamming users who are already confirmed.
    if ($user->confirmed) {
        throw new \moodle_exception('alreadyconfirmed', 'moodle');
    }

    $returnmsg = get_string('emailconfirmsentsuccess');
    $messagetype = \core\output\notification::NOTIFY_SUCCESS;
    if (!send_confirmation_email($user)) {
        $returnmsg = get_string('emailconfirmsentfailure');
        $messagetype = \core\output\notification::NOTIFY_ERROR;
    }

    redirect($returnurl, $returnmsg, null, $messagetype);
} else if ($delete && confirm_sesskey()) {              // Delete a selected user, after confirmation.
    require_capability('moodle/user:delete', $sitecontext);

    $user = $DB->get_record('user', ['id' => $delete, 'mnethostid' => $CFG->mnet_localhost_id], '*', MUST_EXIST);

    if ($user->deleted) {
        throw new \moodle_exception('usernotdeleteddeleted', 'error');
    }
    if (is_siteadmin($user->id)) {
        throw new \moodle_exception('useradminodelete', 'error');
    }

    if ($confirm != md5($delete)) {
        echo $OUTPUT->header();
        $fullname = fullname($user, true);
        echo $OUTPUT->heading(get_string('deleteuser', 'admin'));

        $optionsyes = ['delete' => $delete, 'confirm' => md5($delete), 'sesskey' => sesskey()];
        $deleteurl = new moodle_url($returnurl, $optionsyes);
        $deletebutton = new single_button($deleteurl, get_string('delete'), 'post');

        echo $OUTPUT->confirm(get_string('deletecheckfull', '', "'$fullname'"), $deletebutton, $returnurl);
        echo $OUTPUT->footer();
        die;
    } else if (data_submitted()) {
        if (delete_user($user)) {
            \core\session\manager::gc(); // Remove stale sessions.
            redirect($returnurl);
        } else {
            \core\session\manager::gc(); // Remove stale sessions.
            echo $OUTPUT->header();
            echo $OUTPUT->notification($returnurl, get_string('deletednot', '', fullname($user, true)));
        }
    }
} else if ($acl && confirm_sesskey()) {
    if (!has_capability('moodle/user:update', $sitecontext)) {
        throw new \moodle_exception('nopermissions', 'error', '', 'modify the NMET access control list');
    }
    if (!$user = $DB->get_record('user', ['id' => $acl])) {
        throw new \moodle_exception('nousers', 'error');
    }
    if (!is_mnet_remote_user($user)) {
        throw new \moodle_exception('usermustbemnet', 'error');
    }
    $accessctrl = strtolower(required_param('accessctrl', PARAM_ALPHA));
    if ($accessctrl != 'allow' && $accessctrl != 'deny') {
        throw new \moodle_exception('invalidaccessparameter', 'error');
    }
    $aclrecord = $DB->get_record('mnet_sso_access_control', ['username' => $user->username, 'mnet_host_id' => $user->mnethostid]);
    if (empty($aclrecord)) {
        $aclrecord = new stdClass();
        $aclrecord->mnet_host_id = $user->mnethostid;
        $aclrecord->username = $user->username;
        $aclrecord->accessctrl = $accessctrl;
        $DB->insert_record('mnet_sso_access_control', $aclrecord);
    } else {
        $aclrecord->accessctrl = $accessctrl;
        $DB->update_record('mnet_sso_access_control', $aclrecord);
    }
    $mnethosts = $DB->get_records('mnet_host', null, 'id', 'id,wwwroot,name');
    redirect($returnurl);
} else if ($suspend && confirm_sesskey()) {
    require_capability('moodle/user:update', $sitecontext);

    if ($user = $DB->get_record('user', ['id' => $suspend, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0])) {
        if (!is_siteadmin($user) && $USER->id != $user->id && $user->suspended != 1) {
            $user->suspended = 1;
            // Force logout.
            \core\session\manager::kill_user_sessions($user->id);
            user_update_user($user, false);
        }
    }
    redirect($returnurl);
} else if ($unsuspend && confirm_sesskey()) {
    require_capability('moodle/user:update', $sitecontext);

    if ($user = $DB->get_record('user', ['id' => $unsuspend, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0])) {
        if ($user->suspended != 0) {
            $user->suspended = 0;
            user_update_user($user, false);
        }
    }
    redirect($returnurl);
} else if ($unlock && confirm_sesskey()) {
    require_capability('moodle/user:update', $sitecontext);

    if ($user = $DB->get_record('user', ['id' => $unlock, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0])) {
        login_unlock_account($user);
    }
    redirect($returnurl);
}

// Create the user filter form.
$ufiltering = new user_filtering();
echo $OUTPUT->header();

// Carry on with the user listing.
$context = context_system::instance();
// These columns are always shown in the users list.
$requiredcolumns = ['city', 'country', 'lastaccess'];
// Extra columns containing the extra user fields, excluding the required columns (city and country, to be specific).
$userfields = \core_user\fields::for_identity($context, true)->excluding(...$requiredcolumns);
$extracolumns = $userfields->get_required_fields();
// Get all user name fields as an array, but with firstname and lastname first.
$allusernamefields = \core_user\fields::get_name_fields(true);
$columns = array_merge($allusernamefields, $extracolumns, $requiredcolumns);

foreach ($columns as $column) {
    $string[$column] = \core_user\fields::get_display_name($column);
    if ($sort != $column) {
        $columnicon = "";
        if ($column == "lastaccess") {
            $columndir = "DESC";
        } else {
            $columndir = "ASC";
        }
    } else {
        $columndir = $dir == "ASC" ? "DESC" : "ASC";
        if ($column == "lastaccess") {
            $columnicon = ($dir == "ASC") ? "sort_desc" : "sort_asc";
        } else {
            $columnicon = ($dir == "ASC") ? "sort_asc" : "sort_desc";
        }
        $columnicon = $OUTPUT->pix_icon(
            't/' . $columnicon,
            get_string(strtolower($columndir)),
            'core',
            ['class' => 'iconsort']
        );
    }
    $$column = "<a href=\"user.php?sort=$column&amp;dir=$columndir\">" . $string[$column] . "</a>$columnicon";
}

// We need to check that alternativefullnameformat is not set to '' or language.
// We don't need to check the fullnamedisplay setting here as the fullname function call further down has
// the override parameter set to true.
$fullnamesetting = $CFG->alternativefullnameformat;
// If we are using language or it is empty, then retrieve the default user names of just 'firstname' and 'lastname'.
if ($fullnamesetting == 'language' || empty($fullnamesetting)) {
    // Set $a variables to return 'firstname' and 'lastname'.
    $a = new stdClass();
    $a->firstname = 'firstname';
    $a->lastname = 'lastname';
    // Getting the fullname display will ensure that the order in the language file is maintained.
    $fullnamesetting = get_string('fullnamedisplay', null, $a);
}

// Order in string will ensure that the name columns are in the correct order.
$usernames = order_in_string($allusernamefields, $fullnamesetting);
$fullnamedisplay = [];
foreach ($usernames as $name) {
    // Use the link from $$column for sorting on the user's name.
    $fullnamedisplay[] = ${$name};
}
// All of the names are in one column. Put them into a string and separate them with a /.
$fullnamedisplay = implode(' / ', $fullnamedisplay);
// If $sort = name then it is the default for the setting and we should use the first name to sort by.
if ($sort == "name") {
    // Use the first item in the array.
    $sort = reset($usernames);
}

[$extrasql, $params] = $ufiltering->get_sql_filter();
$users = get_users_listing(
    $sort,
    $dir,
    $page * $perpage,
    $perpage,
    '',
    '',
    '',
    $extrasql,
    $params,
    $context
);
$usercount = get_users(false);
$usersearchcount = get_users(false, '', false, null, "", '', '', '', '', '*', $extrasql, $params);

if ($extrasql !== '') {
    echo $OUTPUT->heading("$usersearchcount / $usercount " . get_string('users'));
    $usercount = $usersearchcount;
} else {
    echo $OUTPUT->heading("$usercount " . get_string('users'));
}

$strall = get_string('all');

$baseurl = new moodle_url('/admin/tool/browse_users_classic/index.php', ['sort' => $sort, 'dir' => $dir, 'perpage' => $perpage]);
echo $OUTPUT->paging_bar($usercount, $page, $perpage, $baseurl);

flush();

$editlinkquery = [];
$viewprofileafteredit = get_config('tool_browse_users_classic', 'viewprofileafteredit');
if ($viewprofileafteredit) {
    $editlinkquery['returnto'] = 'profile';
}

$editprofiletarget = get_config('tool_browse_users_classic', 'editprofiletarget');
$editlinkattributes = [];
if ($editprofiletarget) {
    $editlinkattributes['target'] = $editprofiletarget;
}

if (!$users) {
    $match = [];
    echo $OUTPUT->heading(get_string('nousersfound'));

    $table = null;
} else {
    $countries = get_string_manager()->get_list_of_countries(true);
    if (empty($mnethosts)) {
        $mnethosts = $DB->get_records('mnet_host', null, 'id', 'id,wwwroot,name');
    }

    foreach ($users as $key => $user) {
        if (isset($countries[$user->country])) {
            $users[$key]->country = $countries[$user->country];
        }
    }
    if ($sort == "country") {
        // Need to resort by full country name, not code.
        foreach ($users as $user) {
            $susers[$user->id] = $user->country;
        }
        // Sort by country name, according to $dir.
        if ($dir === 'DESC') {
            arsort($susers);
        } else {
            asort($susers);
        }
        foreach ($susers as $key => $value) {
            $nusers[] = $users[$key];
        }
        $users = $nusers;
    }

    $table = new html_table();
    $table->head = [];
    $table->colclasses = [];
    $table->head[] = $fullnamedisplay;
    $table->attributes['class'] = 'admintable generaltable table-sm';
    foreach ($extracolumns as $field) {
        $table->head[] = ${$field};
    }
    $table->head[] = $city;
    $table->head[] = $country;
    $table->head[] = $lastaccess;
    $table->head[] = get_string('edit');
    $table->colclasses[] = 'centeralign';
    $table->head[] = "";
    $table->colclasses[] = 'centeralign';

    $table->id = "users";

    foreach ($users as $user) {
        $buttons = [];
        $lastcolumn = '';

        // Delete button.
        if (has_capability('moodle/user:delete', $sitecontext)) {
            // No deleting of self, mnet accounts or admins allowed.
            if (!(is_mnet_remote_user($user) || $user->id == $USER->id || is_siteadmin($user))) {
                $url = new moodle_url($returnurl, ['delete' => $user->id, 'sesskey' => sesskey()]);
                $buttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/delete', $strdelete));
            }
        }

        // Suspend button.
        if (has_capability('moodle/user:update', $sitecontext)) {
            if (is_mnet_remote_user($user)) {
                // Mnet users have special access control, they can not be deleted the standard way or suspended.
                $accessctrl = 'allow';
                $acl = $DB->get_record(
                    'mnet_sso_access_control',
                    ['username' => $user->username, 'mnet_host_id' => $user->mnethostid]
                );
                if ($acl) {
                    $accessctrl = $acl->accessctrl;
                }
                $changeaccessto = ($accessctrl == 'deny' ? 'allow' : 'deny');
                $buttons[] = " (<a href=\"?acl={$user->id}&amp;accessctrl=$changeaccessto&amp;sesskey=" . sesskey() . "\">"
                    . get_string($changeaccessto, 'mnet') . " access</a>)";
            } else {
                if ($user->suspended) {
                    $url = new moodle_url($returnurl, ['unsuspend' => $user->id, 'sesskey' => sesskey()]);
                    $buttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/show', $strunsuspend));
                } else {
                    // No suspending of admins or self!
                    if (!($user->id == $USER->id || is_siteadmin($user))) {
                        $url = new moodle_url($returnurl, ['suspend' => $user->id, 'sesskey' => sesskey()]);
                        $buttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/hide', $strsuspend));
                    }
                }

                if (login_is_lockedout($user)) {
                    $url = new moodle_url($returnurl, ['unlock' => $user->id, 'sesskey' => sesskey()]);
                    $buttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/unlock', $strunlock));
                }
            }
        }

        // Edit button.
        if (has_capability('moodle/user:update', $sitecontext)) {
            // Prevent editing of admins by non-admins.
            if (is_siteadmin($USER) || !is_siteadmin($user)) {
                $url = new moodle_url('/user/editadvanced.php', ['id' => $user->id, 'course' => $site->id] + $editlinkquery);
                $buttons[] = html_writer::link($url, $OUTPUT->pix_icon('t/edit', $stredit), $editlinkattributes);
            }
        }

        // The last column - confirm or mnet info.
        if (is_mnet_remote_user($user)) {
            // All mnet users are confirmed, let's print just the name of the host there.
            if (isset($mnethosts[$user->mnethostid])) {
                $lastcolumn = get_string($accessctrl, 'mnet') . ': ' . $mnethosts[$user->mnethostid]->name;
            } else {
                $lastcolumn = get_string($accessctrl, 'mnet');
            }
        } else if ($user->confirmed == 0) {
            if (has_capability('moodle/user:update', $sitecontext)) {
                $lastcolumn = html_writer::link(
                    new moodle_url($returnurl, ['confirmuser' => $user->id, 'sesskey' => sesskey()]),
                    $strconfirm
                );
            } else {
                $lastcolumn = "<span class=\"dimmed_text\">" . get_string('confirm') . "</span>";
            }

            $lastcolumn .= ' | ' . html_writer::link(
                new moodle_url($returnurl, ['resendemail' => $user->id, 'sesskey' => sesskey()]),
                $strresendemail
            );
        }

        if ($user->lastaccess) {
            $strlastaccess = format_time(time() - $user->lastaccess);
        } else {
            $strlastaccess = get_string('never');
        }
        $fullname = fullname($user, true);

        $row = [];
        $row[] = "<a href=\"../user/view.php?id=$user->id&amp;course=$site->id\">$fullname</a>";
        foreach ($extracolumns as $field) {
            $row[] = s($user->{$field});
        }
        $row[] = $user->city;
        $row[] = $user->country;
        $row[] = $strlastaccess;
        if ($user->suspended) {
            foreach ($row as $k => $v) {
                $row[$k] = html_writer::tag('span', $v, ['class' => 'usersuspended']);
            }
        }
        $row[] = implode(' ', $buttons);
        $row[] = $lastcolumn;
        $table->data[] = $row;
    }
}

// Add filters.
$ufiltering->display_add();
$ufiltering->display_active();

if (!empty($table)) {
    echo html_writer::start_tag('div', ['class' => 'no-overflow']);
    echo html_writer::table($table);
    echo html_writer::end_tag('div');
    echo $OUTPUT->paging_bar($usercount, $page, $perpage, $baseurl);
}
if (has_capability('moodle/user:create', $sitecontext)) {
    $url = new moodle_url('/user/editadvanced.php', ['id' => -1] + $editlinkquery);
    echo $OUTPUT->single_button($url, get_string('addnewuser'), 'get', $editlinkattributes);
}

echo $OUTPUT->footer();
