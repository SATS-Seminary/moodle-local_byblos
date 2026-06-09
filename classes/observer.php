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

namespace local_byblos;

/**
 * Event observers for local_byblos.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Notify the recipient when a portfolio page is shared directly with them.
     *
     * Only direct user shares notify a person. Course, group and public shares
     * deliberately send nothing (a whole cohort does not want a ping each time).
     *
     * @param \local_byblos\event\page_shared $event The share event.
     * @return void
     */
    public static function page_shared(\local_byblos\event\page_shared $event): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/lib/messagelib.php');

        $other = $event->other ?? [];
        if (($other['sharetype'] ?? '') !== 'user') {
            return;
        }

        $targetid = (int) ($other['sharevalue'] ?? 0);
        $pageid   = (int) ($other['pageid'] ?? 0);
        if ($targetid <= 0 || $pageid <= 0) {
            return;
        }

        $page   = $DB->get_record('local_byblos_page', ['id' => $pageid]);
        $sharer = \core_user::get_user((int) $event->userid);
        $target = \core_user::get_user($targetid);
        if (!$page || !$sharer || !$target) {
            return;
        }

        $url = new \moodle_url('/local/byblos/page.php', ['id' => $pageid]);
        $a = (object) [
            'sharer' => fullname($sharer),
            'title'  => format_string($page->title),
        ];

        $message = new \core\message\message();
        $message->component         = 'local_byblos';
        $message->name              = 'pageshared';
        $message->userfrom          = $sharer;
        $message->userto            = $target;
        $message->subject           = get_string('message_pageshared_subject', 'local_byblos', $a);
        $message->fullmessage       = get_string('message_pageshared_body', 'local_byblos', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = '<p>' . get_string('message_pageshared_body', 'local_byblos', $a) . '</p>'
            . '<p><a href="' . $url->out(false) . '">' . s($a->title) . '</a></p>';
        $message->smallmessage      = get_string('message_pageshared_subject', 'local_byblos', $a);
        $message->notification      = 1;
        $message->contexturl        = $url->out(false);
        $message->contexturlname    = $a->title;

        message_send($message);
    }
}
