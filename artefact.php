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
 * View, create, or edit a single artefact.
 *
 * URL: /local/byblos/artefact.php?id=X (view)
 *      /local/byblos/artefact.php?action=edit&id=X (edit)
 *      /local/byblos/artefact.php?action=edit (create new)
 *
 * The create/edit form is a type-aware moodleform that self-posts here; the
 * fields shown adapt to the chosen artefact type.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_byblos\artefact as artefact_model;
use local_byblos\artefact_type;
use local_byblos\file_manager;
use local_byblos\form\artefact_form;

require_login();
$context = context_system::instance();
require_capability('local/byblos:use', $context);

$id     = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$PAGE->set_context($context);

// Create / edit mode. The moodleform self-posts back to this branch.
if ($action === 'edit') {
    global $USER, $CFG;
    require_once($CFG->libdir . '/filelib.php');
    require_capability('local/byblos:createpage', $context);

    $artefact = null;
    if ($id > 0) {
        $artefact = artefact_model::get($id);
        if (!$artefact || (int) $artefact->userid !== (int) $USER->id) {
            throw new moodle_exception('accessdenied', 'local_byblos');
        }
    }
    $isedit = ($id > 0);
    $usercontext = context_user::instance($USER->id);
    $itemid = $isedit ? $id : null;

    $pagetitle = get_string($isedit ? 'editartefact' : 'newartefact', 'local_byblos');
    $PAGE->set_url(new moodle_url('/local/byblos/artefact.php', ['action' => 'edit', 'id' => $id]));
    $PAGE->set_title($pagetitle);
    $PAGE->set_heading($pagetitle);

    $form = new artefact_form(new moodle_url('/local/byblos/artefact.php', ['action' => 'edit', 'id' => $id]));

    if ($form->is_cancelled()) {
        redirect($isedit
            ? new moodle_url('/local/byblos/artefact.php', ['id' => $id])
            : new moodle_url('/local/byblos/view.php', ['tab' => 'artefacts']));
    } else if ($data = $form->get_data()) {
        $type  = $data->type;
        $title = trim($data->title);
        $desc  = $data->description ?? '';

        // A row id is needed to key the files, so create new artefacts first.
        $artefactid = $isedit
            ? $id
            : artefact_model::create((int) $USER->id, $type, $title, $desc, '');

        $content = '';
        $fileid  = null;

        if (in_array($type, ['text', 'audio', 'video'], true)) {
            $content = file_save_draft_area_files(
                $data->content_editor['itemid'],
                $usercontext->id,
                'local_byblos',
                file_manager::FILEAREA_ARTEFACT,
                $artefactid,
                artefact_form::editor_options(),
                $data->content_editor['text']
            );
        } else if ($type === 'image' || $type === 'file') {
            $draftid = ($type === 'image') ? $data->imagefile : $data->attachment;
            $options = ($type === 'image') ? artefact_form::image_options() : artefact_form::file_options();
            file_save_draft_area_files(
                $draftid,
                $usercontext->id,
                'local_byblos',
                file_manager::FILEAREA_ARTEFACT,
                $artefactid,
                $options
            );
            $fs = get_file_storage();
            $files = $fs->get_area_files(
                $usercontext->id,
                'local_byblos',
                file_manager::FILEAREA_ARTEFACT,
                $artefactid,
                'filepath, filename',
                false
            );
            $stored = reset($files);
            $fileid = $stored ? (int) $stored->get_id() : null;
        } else if ($type === 'link' || $type === 'embed') {
            $content = $data->url ?? '';
        }

        artefact_model::update($artefactid, [
            'artefacttype' => $type,
            'title'        => $title,
            'description'  => $desc,
            'content'      => $content,
            'fileid'       => $fileid,
        ]);

        redirect(
            new moodle_url('/local/byblos/artefact.php', ['id' => $artefactid]),
            get_string('artefactsaved', 'local_byblos'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        // Display: seed the form with existing values and prepared draft areas.
        $toform = ['id' => $id];
        if ($artefact) {
            $toform['type']        = $artefact->artefacttype;
            $toform['title']       = $artefact->title;
            $toform['description'] = $artefact->description;
        }

        $iseditorcontent = $artefact
            && in_array($artefact->artefacttype, ['text', 'audio', 'video'], true);
        $editordraft = 0;
        $editortext = file_prepare_draft_area(
            $editordraft,
            $usercontext->id,
            'local_byblos',
            file_manager::FILEAREA_ARTEFACT,
            $itemid,
            artefact_form::editor_options(),
            $iseditorcontent ? ($artefact->content ?? '') : ''
        );
        $toform['content_editor'] = ['text' => $editortext, 'format' => FORMAT_HTML, 'itemid' => $editordraft];

        $imagedraft = 0;
        file_prepare_draft_area(
            $imagedraft,
            $usercontext->id,
            'local_byblos',
            file_manager::FILEAREA_ARTEFACT,
            $itemid,
            artefact_form::image_options()
        );
        $toform['imagefile'] = $imagedraft;

        $filedraft = 0;
        file_prepare_draft_area(
            $filedraft,
            $usercontext->id,
            'local_byblos',
            file_manager::FILEAREA_ARTEFACT,
            $itemid,
            artefact_form::file_options()
        );
        $toform['attachment'] = $filedraft;

        if ($artefact && in_array($artefact->artefacttype, ['link', 'embed'], true)) {
            $toform['url'] = $artefact->content;
        }

        $form->set_data($toform);

        echo $OUTPUT->header();
        $form->display();
        echo $OUTPUT->footer();
    }
    exit;
}

// View mode (default).
if ($id <= 0) {
    redirect(new moodle_url('/local/byblos/artefacts.php'));
}

$artefact = artefact_model::get($id);
if (!$artefact) {
    throw new moodle_exception('artefactnotfound', 'local_byblos');
}

$isowner = ((int) $artefact->userid === (int) $USER->id);

$PAGE->set_url(new moodle_url('/local/byblos/artefact.php', ['id' => $id]));
$PAGE->set_title(format_string($artefact->title));
$PAGE->set_heading(format_string($artefact->title));

$handler = artefact_type::get($artefact->artefacttype);

$data = [
    'artefact' => [
        'id'          => $artefact->id,
        'title'       => format_string($artefact->title, true, ['escape' => false]),
        'typelabel'   => $handler ? $handler->get_display_name() : $artefact->artefacttype,
        'description' => format_text($artefact->description ?? '', FORMAT_HTML),
        'timecreated' => userdate($artefact->timecreated),
    ],
    'rendered'  => $handler
        ? $handler->render($artefact)
        : format_text($artefact->content ?? '', FORMAT_HTML),
    'isowner'   => $isowner,
    'editurl'   => (new moodle_url('/local/byblos/artefact.php', ['id' => $id, 'action' => 'edit']))->out(false),
    'deleteurl' => (new moodle_url('/local/byblos/delete.php'))->out(false),
    'dashurl'   => (new moodle_url('/local/byblos/view.php', ['tab' => 'artefacts']))->out(false),
    'sesskey'   => sesskey(),
];

$PAGE->requires->js_call_amd('local_byblos/confirm', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_byblos/artefact_view', $data);
echo $OUTPUT->footer();
