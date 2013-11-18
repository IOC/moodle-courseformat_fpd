<?php
/**
 * @package format_fpd
 * @copyright 2013 Institut Obert de Catalunya
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author Albert Gasset <albert@ioc.cat>
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/fpdquadern/locallib.php');
require_once($CFG->dirroot . '/mod/oublog/locallib.php');

$groupid = optional_param('group', 0, PARAM_INT);

$format = course_get_format($course);
$course = $format->get_course();
$options = $format->get_format_options();

course_create_sections_if_missing($course, range(0, $course->numsections));

$cmblog = $format->get_blog();
$cmquadern = $format->get_quadern();

if (!$cmquadern or !$cmquadern->uservisible) {
    $controller = null;
} elseif ($format->es_alumne()) {
    $controller = new mod_fpdquadern\alumne_controller($cmquadern, $USER->id);
} else {
    $controller = new mod_fpdquadern\quadern_controller($cmquadern);
}

$renderer = $PAGE->get_renderer('format_fpd');
$renderer->print_page($course, $options, $cmblog, $controller, $groupid);

// Include course format js module
$PAGE->requires->js('/course/format/topics/format.js');

