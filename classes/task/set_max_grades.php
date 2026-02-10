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

namespace local_setmaxgrades\task;

defined('MOODLE_INTERNAL') || die();

class set_max_grades extends \core\task\adhoc_task {

    public function get_name(): string {
        return get_string('pluginname', 'local_setmaxgrades');
    }

    /**
     * Создает задачу с данными CSV‑файла.
     */
    public static function instance(int $userid, string $csvcontent): self {
        $task = new self();
        $task->set_custom_data((object)[
            'csvcontent' => $csvcontent
        ]);
        $task->set_userid($userid);
        return $task;
    }

    public function execute(): bool {
        global $DB;

        $data = $this->get_custom_data();
        $csvcontent = $data->csvcontent ?? '';

        $updatedcount = 0;
        // Удаляем BOM (Byte Order Mark) если он есть
        $csvcontent = ltrim($csvcontent, "\xEF\xBB\xBF"); // UTF-8 BOM
        $csvcontent = ltrim($csvcontent, "\xFE\xFF");    // UTF-16 BE BOM
        $csvcontent = ltrim($csvcontent, "\xFF\xFE");    // UTF-16 LE BOM
        $csvcontent = ltrim($csvcontent, "\x00\x00\xFE\xFF"); // UTF-32 BE BOM
        $csvcontent = ltrim($csvcontent, "\xFF\xFE\x00\x00"); // UTF-32 LE BOM

// Удаляем невидимые символы BOM (U+FEFF)
        $csvcontent = trim($csvcontent, "\xEF\xBB\xBF");
        $csvcontent = trim($csvcontent, "\u{FEFF}"); // Для PHP 7+

// Разбиваем на строки
        $lines = preg_split('/\r\n|\n|\r/', $csvcontent);

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            $row = str_getcsv($line, ';');
            if (count($row) !== 2) {
                continue;
            }

            [$course_module_id, $grademax] = $row;
            $course_module_id = trim($course_module_id);
            mtrace('$course_module_id = '.$course_module_id.' grademax = '.$grademax);

            if ($course_module_id <= 0 || $grademax <= 0) {
                continue;
            }

            $sql = "
                SELECT gi.*
                FROM {course_modules} cm
                JOIN {grade_items} gi ON gi.iteminstance = cm.id 
                WHERE cm.id = :cmid
                AND gi.itemtype = 'mod' 
                AND gi.courseid = cm.course
            ";

            $item = $DB->get_record_sql($sql, ['cmid' => $course_module_id]);

            if (!$item) {
                mtrace('item - '.json_encode($item));
                mtrace("Нет такого course_modules.id = {$course_module_id}");
                continue;
            }

            if ($item->grademax == $grademax) {
                mtrace("Grade item {$item->id} already has grademax = {$grademax}");
                continue;
            }

            $item->grademax = $grademax;
            $item->timemodified = time();
            $DB->update_record('grade_items', $item);
            $updatedcount++;

            mtrace("Updated grade_item.id={$item->id} (cmid={$course_module_id}) grademax={$grademax}");
        }

        mtrace("=== Set max grades task completed. Updated: {$updatedcount} items. ===");
        \core_php_time_limit::raise();

        return true;
    }
}
