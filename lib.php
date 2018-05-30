<?php
/**
 * @package format_fpd
 * @copyright 2013 Institut Obert de Catalunya
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author Albert Gasset <albert@ioc.cat>
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/lib.php');

class format_fpd extends format_base {

    public function ajax_section_move() {
        global $PAGE;
        $titles = array();
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return array('sectiontitles' => $titles, 'action' => 'move');
    }

    public function course_format_options($foreditform = false) {
        return array(
            'numsections' => array(
                'label' => new lang_string('numberweeks'),
                'default' => 0,
                'type' => PARAM_INT,
            ),
            'blognumunread' => array(
                'label' => "Nombre d'entrades no llegides del bog",
                'default' => 10,
                'type' => PARAM_INT,
            ),
            'blognumrecent' => array(
                'label' => "Nombre d'entrades recents del blog",
                'default' => 10,
                'type' => PARAM_INT,
            ),
            'blognumtoprated' => array(
                'label' => "Nombre d'entrades més ben valorades del blog",
                'default' => 10,
                'type' => PARAM_INT,
            ),
        );
    }

    public function es_alumne() {
        $cmquadern = $this->get_quadern();
        $context = context_module::instance($cmquadern->id);
        return has_capability('mod/fpdquadern:alumne', $context, null, false);
    }

    public function extend_course_navigation($navigation, navigation_node $node) {
        // Crea blog i quadern si no existeixen
        $this->get_blog();
        $this->get_quadern();

        parent::extend_course_navigation($navigation, $node);
    }

    public function get_blog() {
        global $CFG, $DB;

        if ($cm = $this->get_cm('oublog')) {
            return $cm;
        }

        require_once($CFG->dirroot . '/mod/oublog/lib.php');

        $data = new stdClass();
        $data->course = $this->courseid;
        $data->name = 'Blog';
        $data->grade = 0;
        $data->id = oublog_add_instance($data);

        return $this->add_cm('oublog', $data->id);
    }

    public function get_format_options($section = null) {
        $options = parent::get_format_options($section);
        if ($section === null) {
            $options['coursedisplay'] = COURSE_DISPLAY_SINGLEPAGE;
            $options['hiddensections'] = 1;
        }
        return $options;
    }

    public function get_quadern() {
        global $CFG, $DB;

        if ($cm = $this->get_cm('fpdquadern')) {
            return $cm;
        }

        require_once($CFG->dirroot . '/mod/fpdquadern/lib.php');

        $data = new stdClass();
        $data->course = $this->courseid;
        $data->name = 'Quadern';
        $data->grade = 0;
        $data->id = fpdquadern_add_instance($data);

        return $this->add_cm('fpdquadern', $data->id);
    }

    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                    array('context' => context_course::instance($this->courseid)));
        } else if ($section->section == 0) {
            return get_string('section0name', 'format_topics');
        } else {
            return get_string('topic').' '.$section->section;
        }
    }

    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        $ajaxsupport->testedbrowsers = array(
            'MSIE' => 6.0,
            'Gecko' => 20061111,
            'Safari' => 531,
            'Chrome' => 6.0,
        );
        return $ajaxsupport;
    }

    private function get_cm($modname)  {
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);

        foreach ($modinfo->get_instances_of($modname) as $cm) {
            if ($cm->sectionnum == 0) {
                return $cm;
            }
        }

        foreach ($modinfo->get_instances_of($modname) as $cm) {
            $sectioninfo = $modinfo->get_section_info(0, MUST_EXIST);
            moveto_module($cm, $sectioninfo);
            get_fast_modinfo($course, 0, true);
            return $this->get_cm($modname);
        }
    }

    private function add_cm($modname, $instance) {
        global $DB;

        $course = $this->get_course();
        $modid = $DB->get_field(
            'modules', 'id', array('name' => $modname), MUST_EXIST);

        $cm = new stdClass();
        $cm->course = $course->id;
        $cm->module = $modid;
        $cm->instance = $instance;
        $cm->section = 0;
        $cm->id = add_course_module($cm);
        course_add_cm_to_section($course, $cm->id, $cm->section);

        get_fast_modinfo($course, 0, true);
        $modinfo = get_fast_modinfo($course, 0);

        return $modinfo->get_cm($cm->id);
    }
}
