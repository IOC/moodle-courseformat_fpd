<?php
/**
 * @package format_fpd
 * @copyright 2013 Institut Obert de Catalunya
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author Albert Gasset <albert@ioc.cat>
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/renderer.php');

class format_fpd_renderer extends format_section_renderer_base {

    public function print_page($course, $options, $cmblog, $controller, $groupid) {
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();

        echo $this->course_activity_clipboard($course, 0);

        echo $this->start_section_list();

        echo $this->print_general_section($course);

        echo $this->end_section_list();

        if ($cmblog) {
            $this->print_blog($options, $cmblog, $controller);
        }

        if ($controller) {
            $this->print_quadern($controller, $groupid);
        }
    }

    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'topics'));
    }

    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    protected function page_title() {
        return get_string('topicoutline');
    }

    private function post_rating($post) {
        $ratings = isset($post->ratings) ? $post->ratings : array();
        $rating = $ratings ? round(2 * array_sum($ratings) / count($ratings)) * 0.5 : 0;
        $output = html_writer::start_div('format-fpd-post-rating');
        for ($i = 1; $i <= $rating; $i++) {
            $output .= html_writer::div('', 'format-fpd-star format-fpd-star-full');
        }
        if ($i == $rating + 0.5) {
            $output .= html_writer::div('', 'format-fpd-star format-fpd-star-half');
        }
        $output .= html_writer::end_div();
        return $output;
    }

    private function print_posts($oublog, $posts, $heading) {
        echo html_writer::start_div('format-fpd-posts');
        echo html_writer::div($heading, 'format-fpd-posts-heading');
        foreach ($posts as $post) {
            $url = new moodle_url(
                '/mod/oublog/viewpost.php', array('post' => $post->id));
            $link = $this->output->action_link(
                $url, format_string($post->title));
            $rating = $oublog->allowratings ? $this->post_rating($post) : '';
            $date = userdate(
                $post->timeposted, get_string('strftimerecent'));
            $title = $link . $rating;
            $author = fullname($post) . ', ' . $date;
                    
            echo html_writer::start_div('format-fpd-post');
            echo html_writer::span($title, 'format-fpd-post-title');
            echo html_writer::span($author, 'format-fpd-post-author');
            echo html_writer::end_div('format-fpd-post');
        }
        echo html_writer::end_div();
    }

    private function print_blog($options, $cmblog, $controller) {
        global $DB;

        $context = context_module::instance($cmblog->id);
        $oublog = $DB->get_record(
            'oublog', array('id' => $cmblog->instance), '*', MUST_EXIST);

        echo html_writer::start_div('format-fpd-blog clearfix');

        $name = format_string(
            $cmblog->name, true, array('context' => $context));

        $link = $this->output->action_link($cmblog->get_url(), $name);

        echo $this->output->heading($link, 3, 'format-fpd-title');

        if ($oublog->readtracking and $options['blognumunread'] > 0
            and $controller and $controller->es_professor()) {
            list($posts, $cnt) = oublog_get_posts(
                $oublog, $context, 0, $cmblog, 0, -1, null, '', false,
                false, true, $options['blognumunread']);
            if ($posts) {
                $this->print_posts($oublog, $posts, 'Missatges no llegits');
            }
        }

        if ($options['blognumrecent'] > 0) {
            list($posts, $cnt) = oublog_get_posts(
                $oublog, $context, 0, $cmblog, 0, -1, null, '', false,
                false, false, $options['blognumrecent']);
            if ($posts) {
                $this->print_posts($oublog, $posts, 'Missatges recents');
            }
        }

        if ($oublog->allowratings and $options['blognumtoprated'] > 0) {
            list($posts, $cnt) = oublog_get_posts(
                $oublog, $context, 0, $cmblog, 0, -1, null, '', false,
                true, false, $options['blognumtoprated']);
            if ($posts) {
                $this->print_posts(
                    $oublog, $posts, 'Missatges més ben valorats');
            }
        }

        echo html_writer::end_div();
    }

    private function print_general_section($course) {
        global $PAGE;

        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info(0, MUST_EXIST);

        if ($section->summary or !empty($modinfo->sections[0]) or
            $PAGE->user_is_editing()) {

            echo $this->section_header($section, $course, false, 0);
            echo $this->courserenderer->course_section_cm_list($course, $section, 0);
            echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
            echo $this->section_footer();
        }
    }

    private function print_quadern($controller, $groupid) {
        echo html_writer::start_div('format-fpd-quadern');

        $name = format_string(
            $controller->cm->name, true,
            array('context' => $controller->context));

        if ($controller->es_alumne()) {
            $url = $controller->url_alumne('veure_alumne');
        } else {
            $url = $controller->url();
        }

        $link = $this->output->action_link($url, $name);
        echo $this->output->heading($link, 3, 'format-fpd-title');

        if ($controller->es_alumne()) {
            $accions = $controller->accions_pendents();
            if ($accions) {
                echo $controller->output->accions_pendents('alumne', $accions);
            }
        } else {
            list($alumnes, $users, $groups) =
                $controller->index_alumnes($groupid);
            if ($alumnes) {
                echo $controller->output->index_alumnes(
                    $alumnes, $users, $groups, $groupid);
            }
        }
        echo html_writer::end_div();
    }
}