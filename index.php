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
 * Page for setting max grades from CSV.
 *
 * @package    local_setmaxgrades
 * @copyright  2026 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

admin_externalpage_setup('local_setmaxgrades');

$PAGE->set_url(new moodle_url('/local/setmaxgrades/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_setmaxgrades'));
$PAGE->set_heading($SITE->fullname);

$mform = new class extends moodleform {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('filepicker', 'csvfile', get_string('csvfile', 'local_setmaxgrades'),
            null, ['maxbytes' => 1048576, 'accepted_types' => '.csv']);
        $mform->addRule('csvfile', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('processgrades', 'local_setmaxgrades'));
    }
};

$mform = new $mform();

$message = '';

if ($data = $mform->get_data()) {
    $fs = get_file_storage();
    $usercontext = context_user::instance($USER->id);
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data->csvfile, 'id', false);

    if (empty($files)) {
        $message = html_writer::tag('div', get_string('errorfile', 'local_setmaxgrades'), ['class' => 'alert alert-danger']);
        $message = html_writer::tag('div', json_encode($files), ['class' => 'alert alert-danger']);
    } else {
        $file = reset($files);
        $csvcontent = $file->get_content();

        $task = \local_setmaxgrades\task\set_max_grades::instance($USER->id, $csvcontent);
        \core\task\manager::queue_adhoc_task($task);

        $message = html_writer::tag('div',
            'CSV‑файл принят. Задача на обновление максимальных баллов поставлена в очередь.',
            ['class' => 'alert alert-success']);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_setmaxgrades'));

if (!empty($message)) {
    echo $message;
}

$mform->display();

echo $OUTPUT->footer();
