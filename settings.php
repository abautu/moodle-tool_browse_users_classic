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
 * Settings for plugin tool Browse users (classic mode)
 *
 * @package tool_browse_users_classic
 * @copyright 2025 Andrei Bautu <abautu@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (!$hassiteconfig) {
    return;
}
// Add 'Browse users (classic mode)' page to 'Accounts' section.
$browsepage = new admin_externalpage(
    'tool_browse_users_classic',
    get_string('pluginname', 'tool_browse_users_classic'),
    new moodle_url('/admin/tool/browse_users_classic/index.php'),
    'moodle/user:update'
);

$ADMIN->add('accounts', $browsepage, 'userbulk');

// Add a settings page.
$settings = new admin_settingpage('tool_browse_users_classic_settings', get_string('pluginname', 'tool_browse_users_classic'));
$ADMIN->add('tools', $settings);

// Add redirect setting.
$settings->add(new admin_setting_configcheckbox(
    'tool_browse_users_classic/viewprofileafteredit',
    get_string('viewprofileafteredit', 'tool_browse_users_classic'),
    get_string('viewprofileafteredit_desc', 'tool_browse_users_classic'),
    0
));

// Add redirect setting.
$settings->add(new admin_setting_configselect(
    'tool_browse_users_classic/editprofiletarget',
    get_string('editprofiletarget', 'tool_browse_users_classic'),
    get_string('editprofiletarget_desc', 'tool_browse_users_classic'),
    '',
    [
        '' => get_string('editprofiletarget_self', 'tool_browse_users_classic'),
        '_blank' => get_string('editprofiletarget_newtab', 'tool_browse_users_classic'),
        'profile' => get_string('editprofiletarget_same', 'tool_browse_users_classic'),
    ]
));
