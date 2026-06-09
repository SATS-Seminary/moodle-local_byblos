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

use context_user;
use moodle_url;
use stored_file;

/**
 * File manager — helper class for image upload / retrieval / deletion.
 *
 * All files are stored under component `local_byblos`, filearea `images`,
 * using the user's context ({@see context_user}) and itemid = pageid.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_manager {
    /** @var string Component name used in file_storage. */
    public const COMPONENT = 'local_byblos';

    /** @var string Filearea for page images. */
    public const FILEAREA_IMAGES = 'images';

    /** @var string Filearea for portfolio exports. */
    public const FILEAREA_EXPORTS = 'exports';

    /** @var string Filearea for reflection-section media (recorded audio/video). */
    public const FILEAREA_REFLECTION = 'reflection';

    /** @var string Filearea for artefact files and recorded media (itemid = artefact id). */
    public const FILEAREA_ARTEFACT = 'artefact';

    /** @var int Maximum upload size in bytes (10 MB). */
    public const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

    /** @var int Maximum reflection-media size in bytes (200 MB; recordings are large). */
    public const MAX_REFLECTION_BYTES = 200 * 1024 * 1024;

    /** @var string[] Allowed MIME type prefixes. */
    private const ALLOWED_MIME_PREFIXES = ['image/'];

    /**
     * Save an uploaded image from the draft area to permanent storage.
     *
     * Moves the file from the user's draft filearea (populated by the
     * file picker or direct upload) into the permanent `images` area
     * keyed by page ID.
     *
     * @param int $pageid       Portfolio page ID (used as itemid).
     * @param int $userid       Owner user ID.
     * @param int $draftitemid  Draft-area item ID containing the uploaded file.
     * @return stored_file|null The stored file, or null if nothing was found.
     * @throws \moodle_exception If the file is not a valid image type.
     */
    public static function save_uploaded_image(int $pageid, int $userid, int $draftitemid): ?stored_file {
        $context = context_user::instance($userid);
        $fs = get_file_storage();

        // Retrieve files from the draft area.
        $draftfiles = $fs->get_area_files(
            $context->id,
            'user',
            'draft',
            $draftitemid,
            'id DESC',
            false // Exclude directories.
        );

        if (empty($draftfiles)) {
            return null;
        }

        // Take the most recent file from the draft area.
        $draftfile = reset($draftfiles);

        // Validate MIME type.
        self::validate_image_mime($draftfile->get_mimetype());

        // Validate file size.
        if ($draftfile->get_filesize() > self::MAX_UPLOAD_BYTES) {
            throw new \moodle_exception('error:filetoobig', 'local_byblos', '', [
                'maxsize' => display_size(self::MAX_UPLOAD_BYTES),
            ]);
        }

        // Prepare the destination record.
        $filerecord = [
            'contextid' => $context->id,
            'component' => self::COMPONENT,
            'filearea'  => self::FILEAREA_IMAGES,
            'itemid'    => $pageid,
            'filepath'  => '/',
            'filename'  => $draftfile->get_filename(),
        ];

        // Delete any existing file with the same name to avoid collisions.
        $existing = $fs->get_file(
            $context->id,
            self::COMPONENT,
            self::FILEAREA_IMAGES,
            $pageid,
            '/',
            $draftfile->get_filename(),
        );
        if ($existing) {
            $existing->delete();
        }

        // Copy from draft to permanent storage.
        return $fs->create_file_from_storedfile($filerecord, $draftfile);
    }

    /**
     * Save an uploaded file directly (from $_FILES, not draft area).
     *
     * Used by the simple upload.php endpoint for drag-drop uploads.
     *
     * @param int    $pageid   Portfolio page ID.
     * @param int    $userid   Owner user ID.
     * @param string $filepath Temporary file path on disk.
     * @param string $filename Original filename.
     * @param string $mimetype MIME type of the file.
     * @return stored_file The stored file.
     * @throws \moodle_exception If validation fails.
     */
    public static function save_from_path(
        int $pageid,
        int $userid,
        string $filepath,
        string $filename,
        string $mimetype,
    ): stored_file {
        self::validate_image_mime($mimetype);

        $filesize = filesize($filepath);
        if ($filesize > self::MAX_UPLOAD_BYTES) {
            throw new \moodle_exception('error:filetoobig', 'local_byblos', '', [
                'maxsize' => display_size(self::MAX_UPLOAD_BYTES),
            ]);
        }

        $context = context_user::instance($userid);
        $fs = get_file_storage();

        // Clean the filename.
        $filename = clean_param($filename, PARAM_FILE);
        if (empty($filename)) {
            $filename = 'upload_' . time() . '.png';
        }

        // Delete any existing file with the same name.
        $existing = $fs->get_file(
            $context->id,
            self::COMPONENT,
            self::FILEAREA_IMAGES,
            $pageid,
            '/',
            $filename,
        );
        if ($existing) {
            $existing->delete();
        }

        $filerecord = [
            'contextid' => $context->id,
            'component' => self::COMPONENT,
            'filearea'  => self::FILEAREA_IMAGES,
            'itemid'    => $pageid,
            'filepath'  => '/',
            'filename'  => $filename,
        ];

        return $fs->create_file_from_pathname($filerecord, $filepath);
    }

    /**
     * Build the pluginfile.php URL for a stored image.
     *
     * @param int    $contextid User context ID.
     * @param int    $pageid    Page ID (itemid).
     * @param string $filename  Stored filename.
     * @return moodle_url The serving URL.
     */
    public static function get_image_url(int $contextid, int $pageid, string $filename): moodle_url {
        return moodle_url::make_pluginfile_url(
            $contextid,
            self::COMPONENT,
            self::FILEAREA_IMAGES,
            $pageid,
            '/',
            $filename,
        );
    }

    /**
     * Delete all images associated with a page.
     *
     * Call this when a page is deleted to clean up stored files.
     *
     * @param int $pageid Page ID.
     * @param int $userid Owner user ID.
     * @return void
     */
    public static function delete_page_images(int $pageid, int $userid): void {
        $context = context_user::instance($userid);
        $fs = get_file_storage();

        $fs->delete_area_files(
            $context->id,
            self::COMPONENT,
            self::FILEAREA_IMAGES,
            $pageid,
        );
    }

    /**
     * List all stored image files for a page.
     *
     * @param int $pageid Page ID.
     * @param int $userid Owner user ID.
     * @return stored_file[] Array of stored file objects.
     */
    public static function get_page_images(int $pageid, int $userid): array {
        $context = context_user::instance($userid);
        $fs = get_file_storage();

        return $fs->get_area_files(
            $context->id,
            self::COMPONENT,
            self::FILEAREA_IMAGES,
            $pageid,
            'filename ASC',
            false, // Exclude directories.
        );
    }

    /**
     * Validate that a MIME type is an allowed image type.
     *
     * @param string $mimetype The MIME type to check.
     * @return void
     * @throws \moodle_exception If the MIME type is not an image type.
     */
    public static function validate_image_mime(string $mimetype): void {
        foreach (self::ALLOWED_MIME_PREFIXES as $prefix) {
            if (str_starts_with($mimetype, $prefix)) {
                return;
            }
        }

        throw new \moodle_exception('error:invalidfiletype', 'local_byblos', '', $mimetype);
    }
}
