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
 * Unit tests for evaluation, training and prediction.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllaó {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/test_indicator_max.php');
require_once(__DIR__ . '/fixtures/test_indicator_min.php');
require_once(__DIR__ . '/fixtures/test_indicator_fullname.php');
require_once(__DIR__ . '/fixtures/test_indicator_random.php');
require_once(__DIR__ . '/fixtures/test_target_shortname.php');

/**
 * Unit tests for evaluation, training and prediction.
 *
 * @package   tool_inspire
 * @copyright 2017 David Monllaó {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_inspire_prediction_testcase extends advanced_testcase {

    public function test_training_and_prediction() {
        $this->resetAfterTest(true);


    }

    /**
     * Basic test to check that prediction processors work as expected.
     *
     * @dataProvider provider_test_evaluation
     */
    public function test_evaluation($modelquality, $ncourses, $expected) {
        $this->resetAfterTest(true);

        if ($modelquality === 'perfect') {
            $modelobj = $this->add_perfect_model();
        } else if ($modelquality === 'random') {
            $modelobj = $this->add_random_model();
        } else {
            throw new \coding_exception('Only perfect and random accepted as $modelquality values');
        }


        // Generate training data.
        $params = array(
            'startdate' => mktime(0, 0, 0, 10, 24, 2015),
            'enddate' => mktime(0, 0, 0, 2, 24, 2016),
        );
        for ($i = 0; $i < $ncourses; $i++) {
            $name = 'a' . random_string(10);
            $params = array('shortname' => $name, 'fullname' => $name) + $params;
            $this->getDataGenerator()->create_course($params);
        }
        for ($i = 0; $i < $ncourses; $i++) {
            $name = 'b' . random_string(10);
            $params = array('shortname' => $name, 'fullname' => $name) + $params;
            $this->getDataGenerator()->create_course($params);
        }

        // We repeat the test for all enabled prediction processors.
        // TODO The "enabled" part is not implemented"
        $predictionprocessors = \tool_inspire\manager::get_all_prediction_processors();
        // TODO Uncomment.
        unset($predictionprocessors['\\predict_php\\processor']);

        foreach ($predictionprocessors as $classfullname => $predictionsprocessor) {

            set_config('predictionprocessor', $classfullname, 'tool_inspire');

            $this->model = new \tool_inspire\model($modelobj);
            $results = $this->model->evaluate();

            // We check that the returned status includes at least $expectedcode code.
            foreach ($results as $rangeprocessor => $result) {
                $message = 'The returned status code should include ' . $expected[$rangeprocessor] . ', ' .
                    $result->status . ' returned';
                $this->assertEquals($expected[$rangeprocessor], $result->status & $expected[$rangeprocessor], $message);
            }
        }

    }

    public function provider_test_evaluation() {

        return array(
            'bad-and-no-enough-data' => array(
                'modelquality' => 'random',
                'ncourses' => 10,
                'expectedresults' => array(
                    // The course duration is too much to be processed by in weekly basis.
                    'weekly' => \tool_inspire\model::NO_DATASET,
                    'weekly_accum' => \tool_inspire\model::NO_DATASET,
                    // 10 samples is not enough to process anything.
                    'no_range' => \tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA & \tool_inspire\model::EVALUATE_LOW_SCORE,
                    'single_range' => \tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA & \tool_inspire\model::EVALUATE_LOW_SCORE,
                    'quarters' => \tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA & \tool_inspire\model::EVALUATE_LOW_SCORE,
                    'quarters_accum' => \tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA & \tool_inspire\model::EVALUATE_LOW_SCORE,
                )
            ),
            'bad' => array(
                'modelquality' => 'random',
                'ncourses' => 50,
                'expectedresults' => array(
                    // The course duration is too much to be processed by in weekly basis.
                    'weekly' => \tool_inspire\model::NO_DATASET,
                    'weekly_accum' => \tool_inspire\model::NO_DATASET,
                    'no_range' => \tool_inspire\model::EVALUATE_LOW_SCORE,
                    'single_range' => \tool_inspire\model::EVALUATE_LOW_SCORE,
                    'quarters' => \tool_inspire\model::EVALUATE_LOW_SCORE,
                    'quarters_accum' => \tool_inspire\model::EVALUATE_LOW_SCORE,
                )
            ),

            'no-enough-data' => array(
                'modelquality' => 'perfect',
                'ncourses' => 10,
                'expectedresults' => array(
                    // The course duration is too much to be processed by in weekly basis.
                    'weekly' => \tool_inspire\model::NO_DATASET,
                    'weekly_accum' => \tool_inspire\model::NO_DATASET,
                    // 10 samples is not enough to process anything.
                    'no_range' => \tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA,
                    'single_range' => \tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA,
                    'quarters' => \tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA,
                    'quarters_accum' => \tool_inspire\model::EVALUATE_NOT_ENOUGH_DATA,
                )
            ),
            'good' => array(
                'modelquality' => 'perfect',
                'ncourses' => 50,
                'expectedresults' => array(
                    // The course duration is too much to be processed by in weekly basis.
                    'weekly' => \tool_inspire\model::NO_DATASET,
                    'weekly_accum' => \tool_inspire\model::NO_DATASET,
                    'no_range' => \tool_inspire\model::OK,
                    'single_range' => \tool_inspire\model::OK,
                    'quarters' => \tool_inspire\model::OK,
                    'quarters_accum' => \tool_inspire\model::OK,
                )
            )

        );
    }

    protected function add_random_model() {
        global $DB, $USER;

        $indicators = array('test_indicator_max', 'test_indicator_min', 'test_indicator_random');

        $modelobj = new stdClass();
        $modelobj->codename = 'testmodel';
        $modelobj->target = 'test_target_shortname';
        $modelobj->indicators = json_encode($indicators);
        $modelobj->predictionminscore = 0.8;
        $modelobj->timecreated = time();
        $modelobj->timemodified = time();
        $modelobj->usermodified = $USER->id;
        $id = $DB->insert_record('tool_inspire_models', $modelobj);

        // To load db defaults as well.
        return $DB->get_record('tool_inspire_models', array('id' => $id));
    }

    protected function add_perfect_model() {
        global $DB, $USER;

        $indicators = array('test_indicator_max', 'test_indicator_min', 'test_indicator_fullname');

        $modelobj = new stdClass();
        $modelobj->codename = 'testmodel';
        $modelobj->target = 'test_target_shortname';
        $modelobj->indicators = json_encode($indicators);
        $modelobj->predictionminscore = 0.8;
        $modelobj->timecreated = time();
        $modelobj->timemodified = time();
        $modelobj->usermodified = $USER->id;
        $id = $DB->insert_record('tool_inspire_models', $modelobj);

        // To load db defaults as well.
        return $DB->get_record('tool_inspire_models', array('id' => $id));
    }

}
