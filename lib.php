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
            'blognumunread' => array(
                'label' => 'Nombre de missatges no llegits del bog',
                'default' => 10,
                'type' => PARAM_INT,
            ),
            'blognumrecent' => array(
                'label' => 'Nombre de missatges recents del blog',
                'default' => 10,
                'type' => PARAM_INT,
            ),
            'blognumtoprated' => array(
                'label' => 'Nombre de missatges més ben valorats del blog',
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
        global $USER;

        parent::extend_course_navigation($navigation, $node);

        $urlparams = null;       
        if ($this->es_alumne()) {
            $urlparams = array('individual' => $USER->id);
        }
        $this->add_navigation($node, $this->get_blog(), $urlparams);

        $urlparams = null;       
        if ($this->es_alumne()) {
            $urlparams = array(
                'accio' => 'veure_alumne',
                'alumne_id' => $USER->id,
            );
        }
        $this->add_navigation($node, $this->get_quadern(), $urlparams);
    }

    public function get_format_options($section = null) {
        $options = parent::get_format_options($section);
        if ($section === null) {
            $options['numsections'] = 0;
        }
        return $options;
    }

    public function get_blog() {
        global $CFG, $DB;

        if ($cm = $this->get_cm('oublog')) {
            return $cm;
        }

        require_once($CFG->dirroot . '/mod/oublog/lib.php');

        $modid = $DB->get_field(
            'modules', 'id', array('name' => 'oublog'), MUST_EXIST);

        $data = new object;
        $data->course = $this->courseid;
        $data->name = 'Blog';
        $data->grade = 0;
        $data->id = oublog_add_instance($data);

        return $this->add_cm($modid, $data->id);
    }

    public function get_quadern() {
        global $CFG, $DB;

        if ($cm = $this->get_cm('fpdquadern')) {
            return $cm;
        }

        require_once($CFG->dirroot . '/mod/fpdquadern/lib.php');

        $modid = $DB->get_field(
            'modules', 'id', array('name' => 'fpdquadern'), MUST_EXIST);

        $data = new object;
        $data->course = $this->courseid;
        $data->name = 'Quadern';
        $data->grade = 0;
        $data->id = fpdquadern_add_instance($data);

        return $this->add_cm($modid, $data->id);
    }

    public function get_section_name($section) {
        $sectionnum = is_object($section) ? $section->section : $section;

        switch($sectionnum) {
        case 0: 
            return get_string('section0name', 'format_topics');
        case 1:
            return "Configuració blog / quadern";
        default:
            return "Secció $sectionnum";
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
            if ($cm->sectionnum == 1) {
                return $cm;
            }
        }
    }

    private function add_cm($module, $instance) {
        $course = $this->get_course();

        $cm = new stdClass();
        $cm->course = $course->id;
        $cm->module = $module;
        $cm->instance = $instance;
        $cm->section = 1;
        $cm->id = add_course_module($cm);
        course_add_cm_to_section($course, $cm->id, $cm->section);

        get_fast_modinfo($course, 0, true);
        $modinfo = get_fast_modinfo($course, 0);

        return $modinfo->get_cm($cm->id);
    }

    private function add_navigation($node, $cm, array $urlparams=null) {
        if (!$cm->uservisible) {
            return;
        }

        $modulename = get_string('modulename', $cm->modname);
        if ($cm->icon) {
            $icon = new pix_icon($cm->icon, $modulename, $cm->iconcomponent);
        } else {
            $icon = new pix_icon('icon', $modulename, $cm->modname);
        }
        $context = context_module::instance($cm->id);
        $name = format_string($cm->name, true, array('context' => $context));
        $url = $cm->get_url();
        if ($urlparams) {
            $url->params($urlparams);
        }
        $action = new moodle_url($cm->get_url());

        $cmnode = $node->add(
            $name, $action, navigation_node::TYPE_ACTIVITY,
            null, $cm->id, $icon);
        
        if (global_navigation::module_extends_navigation($cm->modname)) {
            $cmnode->nodetype = navigation_node::NODETYPE_BRANCH;
        } else {
            $cmnode->nodetype = navigation_node::NODETYPE_LEAF;
        }
        $cmnode->hidden = !$cm->visible;
    }
}
