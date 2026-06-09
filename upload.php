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
 * Simple image upload endpoint for drag-drop / file browse.
 *
 * Accepts a multipart POST with a `file` field and `pageid`, validates
 * the upload, stores it via file_storage, and returns a JSON response
 * with the pluginfile URL.
 *
 * This is the simpler alternative to the draft-area external function
 * approach, designed for direct drag-drop uploads from the browser.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

use local_byblos\artefact;
use local_byblos\file_manager;
use local_byblos\page;

// Require login and sesskey.
require_login();
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

/**
 * Send a JSON error response and exit.
 *
 * @param string $message Error message.
 * @param int    $code    HTTP status code.
 * @return never
 */
function local_byblos_upload_error(string $message, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['error' => $message], JSON_THROW_ON_ERROR);
    die();
}

// Validate request method.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    local_byblos_upload_error('Only POST requests are accepted.', 405);
}

// Validate capability.
$contextsystem = context_system::instance();
require_capability('local/byblos:createpage', $contextsystem);

// Validate parameters.
$pageid = required_param('pageid', PARAM_INT);

// Validate the page exists and user owns it.
$page = page::get($pageid);
if (!$page) {
    local_byblos_upload_error('Page not found.', 404);
}
if ((int) $page->userid !== (int) $USER->id) {
    local_byblos_upload_error('You do not own this page.', 403);
}

// Validate file upload.
if (empty($_FILES['file'])) {
    local_byblos_upload_error('No file was uploaded.');
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    local_byblos_upload_error('Upload failed with error code ' . $file['error'] . '.');
}

// Check file size (10 MB max).
if ($file['size'] > file_manager::MAX_UPLOAD_BYTES) {
    local_byblos_upload_error('File exceeds maximum size of ' . display_size(file_manager::MAX_UPLOAD_BYTES) . '.');
}

// Validate MIME type (image/* only).
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimetype = $finfo->file($file['tmp_name']);

try {
    file_manager::validate_image_mime($mimetype);
} catch (\moodle_exception $e) {
    local_byblos_upload_error('Invalid file type. Only images are allowed (got: ' . $mimetype . ').');
}

// Store the file.
try {
    $storedfile = file_manager::save_from_path(
        $pageid,
        $USER->id,
        $file['tmp_name'],
        $file['name'],
        $mimetype,
    );
} catch (\Throwable $e) {
    local_byblos_upload_error('Failed to store file: ' . $e->getMessage(), 500);
}

// Build response.
$context = context_user::instance($USER->id);
$url = file_manager::get_image_url($context->id, $pageid, $storedfile->get_filename());

// Record an artefact row so the upload is reusable via the picker.
$artefacttype = str_starts_with($mimetype, 'image/') ? 'image' : 'file';

// Derive a friendly title from the original filename: drop extension,
// replace underscores/hyphens with spaces, collapse whitespace.
$rawname = $storedfile->get_filename();
$basename = pathinfo($rawname, PATHINFO_FILENAME);
$title = trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $basename)));
if ($title === '') {
    $title = $rawname;
}

$artefactid = 0;
try {
    $artefactid = artefact::create(
        (int) $USER->id,
        $artefacttype,
        $title,
        '',
        '',
        (int) $storedfile->get_id(),
        'upload:editor',
    );
} catch (\Throwable $e) {
    // Upload already succeeded; don't fail the whole request if the
    // artefact bookkeeping row couldn't be written. Log quietly.
    debugging(
        'local_byblos: failed to create artefact row after upload: ' . $e->getMessage(),
        DEBUG_DEVELOPER
    );
    $artefactid = 0;
}

echo json_encode([
    'url' => $url->out(false),
    'filename' => $storedfile->get_filename(),
    'artefactid' => (int) $artefactid,
], JSON_THROW_ON_ERROR);
