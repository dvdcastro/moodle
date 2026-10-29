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

namespace core_backup;

use backup;
use backup_controller;
use restore_controller;
use restore_dbops;

/**
 * Tests for restoring question attempts.
 *
 * @package    core_backup
 * @category   test
 * @copyright  2026 Louisiana State University
 * @author     David Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_questions_attempt_data_trait::class)]
final class restore_question_attempts_test extends \advanced_testcase {
    /**
     * Load required libraries.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Restoring question attempts twice in the same PHP process (as the ad hoc task runner does
     * when it runs two asynchronous restores one after the other) must work both times.
     *
     * Each restore must recode the attempt data with its own mappings.
     */
    public function test_restore_question_attempts_twice_in_same_process(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $quiz = $generator->create_module('quiz', ['course' => $course->id, 'sumgrades' => 1]);

        // A multichoice question: its restorer recodes the '_order' step data with question_answer mappings.
        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(
            ['contextid' => \context_module::instance($quiz->cmid)->id]
        );
        $question = $questiongenerator->create_question('multichoice', 'one_of_four', ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);

        // One finished attempt.
        $this->setUser($student);
        $quizgenerator = $generator->get_plugin_generator('mod_quiz');
        $attempt = $quizgenerator->create_attempt($quiz->id, $student->id);
        $quizgenerator->submit_responses($attempt->id, [1 => 'One'], false, true);
        $this->setAdminUser();

        // Back up the course, with users.
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->execute_plan();
        $backupfile = $bc->get_results()['backup_destination'];
        $bc->destroy();

        // Restore it twice, into two new courses, in this same process.
        $newcourseids = [];
        foreach ([1, 2] as $i) {
            $tempdir = restore_controller::get_tempdir_name(SITEID, $USER->id);
            $backupfile->extract_to_pathname(
                get_file_packer('application/vnd.moodle.backup'),
                make_backup_temp_directory($tempdir)
            );
            $newcourseid = restore_dbops::create_new_course('Restored ' . $i, 'R' . $i, $course->category);
            $rc = new restore_controller(
                $tempdir,
                $newcourseid,
                backup::INTERACTIVE_NO,
                backup::MODE_GENERAL,
                $USER->id,
                backup::TARGET_NEW_COURSE
            );
            $this->assertTrue($rc->execute_precheck());
            $rc->execute_plan();
            $rc->destroy();
            $newcourseids[] = $newcourseid;
        }

        // Each restored attempt must point at the answers of the question restored in its own course.
        foreach ($newcourseids as $newcourseid) {
            $newquiz = $DB->get_record('quiz', ['course' => $newcourseid], '*', MUST_EXIST);
            $newattempt = $DB->get_record('quiz_attempts', ['quiz' => $newquiz->id], '*', MUST_EXIST);
            $quba = \question_engine::load_questions_usage_by_activity($newattempt->uniqueid);
            $qa = $quba->get_question_attempt(1);
            $this->assertNotEquals($question->id, $qa->get_question_id());

            $order = explode(',', $qa->get_step(0)->get_qt_var('_order'));
            $answerids = $DB->get_fieldset_select('question_answers', 'id', 'question = ?', [$qa->get_question_id()]);
            sort($order);
            sort($answerids);
            $this->assertCount(4, $answerids);
            $this->assertEquals($answerids, $order);
        }
    }
}
