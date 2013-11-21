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
        // Codi basat en format_section_renderer_base::print_multiple_section_page
        // L'única diferència és en la secció 0.

        global $PAGE;

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        echo $this->start_section_list();

        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            if ($section == 0) {
                // Amaga els enllaços del blog i del quadern
                $modblog = $modinfo->get_cm($cmblog->id);
                $modquadern = $modinfo->get_cm($controller->cm->id);
                $blogvisible = $modblog->uservisible;
                $quadernvisible = $modquadern->uservisible;
                $modblog->uservisible = false;
                $modquadern->uservisible = false;

                // 0-section is displayed a little different then the others
                if ($thissection->summary or !empty($modinfo->sections[0]) or $PAGE->user_is_editing()) {
                    echo $this->section_header($thissection, $course, false, 0);
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                    echo $this->section_footer();
                }

                $modblog->uservisible = $blogvisible;
                $modquadern->uservisible = $quadernvisible;

                // Blog i quadern
                echo $this->end_section_list();
                if ($cmblog) {
                    $this->print_blog($course, $options, $cmblog, $controller);
                }
                if ($controller) {
                    $this->print_quadern($course, $controller, $groupid);
                }
                echo $this->start_section_list();

                continue;
            }
            if ($section > $course->numsections) {
                // activities inside this section are 'orphaned', this section will be printed as 'stealth' below
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but showavailability is turned on (and there is some available info text).
            $showsection = $thissection->uservisible ||
                    ($thissection->visible && !$thissection->available && $thissection->showavailability
                    && !empty($thissection->availableinfo));
            if (!$showsection) {
                // Hidden section message is overridden by 'unavailable' control
                // (showavailability option).
                if (!$course->hiddensections && $thissection->available) {
                    echo $this->section_hidden($section);
                }

                continue;
            }

            if (!$PAGE->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                echo $this->section_summary($thissection, $course, null);
            } else {
                echo $this->section_header($thissection, $course, false, 0);
                if ($thissection->uservisible) {
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, $section, 0);
                }
                echo $this->section_footer();
            }
        }

        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $course->numsections or empty($modinfo->sections[$section])) {
                    // this is not stealth section or it is empty
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();

            echo html_writer::start_tag('div', array('id' => 'changenumsections', 'class' => 'mdl-right'));

            // Increase number of sections.
            $straddsection = get_string('increasesections', 'moodle');
            $url = new moodle_url('/course/changenumsections.php',
                array('courseid' => $course->id,
                      'increase' => true,
                      'sesskey' => sesskey()));
            $icon = $this->output->pix_icon('t/switch_plus', $straddsection);
            echo html_writer::link($url, $icon.get_accesshide($straddsection), array('class' => 'increase-sections'));

            if ($course->numsections > 0) {
                // Reduce number of sections sections.
                $strremovesection = get_string('reducesections', 'moodle');
                $url = new moodle_url('/course/changenumsections.php',
                    array('courseid' => $course->id,
                          'increase' => false,
                          'sesskey' => sesskey()));
                $icon = $this->output->pix_icon('t/switch_minus', $strremovesection);
                echo html_writer::link($url, $icon.get_accesshide($strremovesection), array('class' => 'reduce-sections'));
            }

            echo html_writer::end_tag('div');
        } else {
            echo $this->end_section_list();
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

    private function print_posts($oublog, $posts, $url, $heading) {
        echo html_writer::start_div('format-fpd-posts');
        echo html_writer::start_div('format-fpd-posts-heading');
        echo html_writer::tag('a', $heading, array('href' => $url));
        echo html_writer::end_div();
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

    private function print_blog($course, $options, $cmblog, $controller) {
        global $DB;

        $modinfo = get_fast_modinfo($course);
        $mod = $modinfo->get_cm($cmblog->id);
        $context = context_module::instance($cmblog->id);
        $oublog = $DB->get_record(
            'oublog', array('id' => $cmblog->instance), '*', MUST_EXIST);

        echo html_writer::start_div('format-fpd-blog clearfix');

        $name = format_string(
            $cmblog->name, true, array('context' => $context));

        $link = $this->output->action_link($cmblog->get_url(), $name);

        if ($this->page->user_is_editing()) {
            $actions = course_get_cm_edit_actions($mod);
            $link .= ' '. $this->courserenderer->course_section_cm_edit_actions($actions);
        }

        echo $this->output->heading($link, 3, 'format-fpd-title');

        if ($cmblog->showdescription) {
            echo html_writer::div(
                format_module_intro('oublog', $oublog, $cmblog->id),
                'format-fpd-intro');
        }

        if ($oublog->readtracking and $options['blognumunread'] > 0
            and $controller and $controller->es_professor()) {
            list($posts, $cnt) = oublog_get_posts(
                $oublog, $context, 0, $cmblog, 0, -1, null, '', false,
                false, true, $options['blognumunread']);
            if ($posts) {
                $url = new moodle_url($cmblog->get_url());
                $url->params(array('individual' => 0, 'unread' => true));
                $this->print_posts($oublog, $posts, $url, 'Entrades no llegides');
            }
        }

        if ($options['blognumrecent'] > 0) {
            list($posts, $cnt) = oublog_get_posts(
                $oublog, $context, 0, $cmblog, 0, -1, null, '', false,
                false, false, $options['blognumrecent']);
            if ($posts) {
                $url = new moodle_url($cmblog->get_url());
                $url->params(array('individual' => 0));
                $this->print_posts($oublog, $posts, $url, 'Entrades recents');
            }
        }

        if ($oublog->allowratings and $options['blognumtoprated'] > 0) {
            list($posts, $cnt) = oublog_get_posts(
                $oublog, $context, 0, $cmblog, 0, -1, null, '', false,
                true, false, $options['blognumtoprated']);
            if ($posts) {
                $url = new moodle_url($cmblog->get_url());
                $url->params(array('individual' => 0, 'toprated' => true));
                $this->print_posts($oublog, $posts, $url, 'Entrades més ben valorades');
            }
        }

        echo html_writer::end_div();
    }

    private function print_quadern($course, $controller, $groupid) {
        $modinfo = get_fast_modinfo($course);
        $mod = $modinfo->get_cm($controller->cm->id);

        echo html_writer::start_div('format-fpd-quadern');

        if ($controller->es_alumne()) {
            $url = $controller->url_alumne('veure_alumne');
        } else {
            $url = $controller->url();
        }

        $name = format_string(
            $controller->cm->name, true,
            array('context' => $controller->context));

        $link = $this->output->action_link($url, $name);

        if ($this->page->user_is_editing()) {
            $actions = course_get_cm_edit_actions($mod);
            $link .= ' '. $this->courserenderer->course_section_cm_edit_actions($actions);
        }

        echo $this->output->heading($link, 3, 'format-fpd-title');

        if ($controller->cm->showdescription) {
            echo html_writer::div(
                format_module_intro('fpdquadern', $controller->quadern, $controller->cm->id),
                'format-fpd-intro');
        }

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