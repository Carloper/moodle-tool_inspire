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
 * Inspire tool manager
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_inspire;

defined('MOODLE_INTERNAL') || die();

/**
 * Inspire tool site manager.
 *
 * @package   tool_inspire
 * @copyright 2016 David Monllao {@link http://www.davidmonllao.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model {

    const ANALYSE_OK = 0;
    const ANALYSE_GENERAL_ERROR = 1;
    const ANALYSE_INPROGRESS = 2;
    const ANALYSE_REJECTED_RANGE_PROCESSOR = 3;
    const ANALYSABLE_STATUS_INVALID_FOR_RANGEPROCESSORS = 4;
    const ANALYSABLE_STATUS_INVALID_FOR_TARGET = 5;

    protected $model = null;

    public function __construct($model) {
        $this->model = $model;
    }

    protected function get_target() {
        $classname = $this->model->target;
        return new $classname();
    }

    protected function get_indicators() {

        $indicators = [];

        // TODO Read the model indicators instead of read all indicators in the folder.
        $classes = \core_component::get_component_classes_in_namespace('tool_inspire', 'local\\indicator');
        foreach ($classes as $fullclassname => $classpath) {

            // Discard abstract classes and others.
            if (is_subclass_of($fullclassname, 'tool_inspire\local\indicator\base')) {
                if ((new \ReflectionClass($fullclassname))->isInstantiable()) {
                    $indicators[$fullclassname] = new $fullclassname();
                }
            }
        }

        return $indicators;
    }

    public function get_analyser($target, $indicators, $rangeprocessors) {
        // TODO Select it from any component.
        $classname = $target->get_analyser_class();

        // TODO Check class exists.
        return new $classname($this->model->id, $target, $indicators, $rangeprocessors);
    }

    /**
     * Get all available range processors.
     *
     * @return \tool_inspire\range_processor\base[]
     */
    protected function get_range_processors() {

        // TODO: It should be able to search range processors in other plugins.
        $classes = \core_component::get_component_classes_in_namespace('tool_inspire', 'local\\range_processor');

        $rangeprocessors = [];
        foreach ($classes as $fullclassname => $classpath) {
            if (self::is_a_valid_range_processor($fullclassname)) {
               $instance = new $fullclassname();
               $rangeprocessors[$instance->get_codename()] = $instance;
            }
        }

        return $rangeprocessors;
    }

    /**
     * is_a_valid_range_processor
     *
     * @param string $fullclassname
     * @return bool
     */
    protected static function is_a_valid_range_processor($fullclassname) {
        if (is_subclass_of($fullclassname, '\tool_inspire\local\range_processor\base')) {
            if ((new \ReflectionClass($fullclassname))->isInstantiable()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Builds the model dataset.
     *
     * @param  array   $options
     * @return array Status codes and generated files
     */
    public function build_dataset($options = array()) {

        $target = $this->get_target();
        $indicators = $this->get_indicators();
        $rangeprocessors = $this->get_range_processors();

        if (empty($target)) {
            throw new \moodle_exception('errornotarget', 'tool_inspire');
        }

        if (empty($indicators)) {
            throw new \moodle_exception('errornoindicators', 'tool_inspire');
        }

        if (empty($rangeprocessors)) {
            throw new \moodle_exception('errornorangeprocessors', 'tool_inspire');
        }

        $analyser = $this->get_analyser($target, $indicators, $rangeprocessors);
        return $analyser->analyse($options);
    }

    /**
     * Evaluates the model.
     *
     * @return void
     */
    public function evaluate() {

        $return = array();

        foreach ($this->get_range_processors() as $rangeprocessor) {

            $dataset = \tool_inspire\dataset_manager::get_range_file($this->model->id, $rangeprocessor->get_codename());
            if (!$dataset) {

                $results = new \stdClass();
                $results->errors = array('No dataset found');
                $return[$rangeprocessor->get_codename()] = array(
                    'status' => self::ANALYSE_GENERAL_ERROR,
                    'results' => $results
                );
                continue;
            }

            $outputdir = $this->get_output_dir($rangeprocessor->get_codename());

            $predict = $this->get_predictions_processor();

            // From moodle filesystem to the file system.
            // TODO This is not ideal, but it seems that there is no read access to moodle filesystem files.
            $dir = make_request_directory();
            $filepath = $dataset->copy_content_to_temp($dir);

            // Evaluate the dataset.
            $return[$rangeprocessor->get_codename()] = array(
                'status' => self::ANALYSE_OK,
                'results' => $predict->evaluate_dataset($filepath, $outputdir)
            );
        }

        return $return;
    }

    protected function get_predictions_processor() {
        // TODO Select it based on a config setting.
        return new \predict_python\processor();
        //return new \predict_php\processor();
    }

    protected function get_output_dir($subdir = false) {
        global $CFG;

        $outputdir = get_config('tool_inspire', 'modeloutputdir');
        if (empty($outputdir)) {
            // Apply default value.
            $outputdir = rtrim($CFG->dataroot, '/') . DIRECTORY_SEPARATOR . 'models';
        }

        $outputdir = $outputdir . DIRECTORY_SEPARATOR . $this->model->id;

        if ($subdir) {
            $outputdir = $outputdir . DIRECTORY_SEPARATOR . $subdir;
        }

        if (!is_dir($outputdir)) {
            mkdir($outputdir, $CFG->directorypermissions, true);
        }

        return $outputdir;
    }
}
