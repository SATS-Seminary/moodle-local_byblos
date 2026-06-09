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
 * Abstract base class for artefact type plugins.
 *
 * Each artefact type (text, file, image, badge, course_completion,
 * blog_entry) extends this class and registers itself with the static
 * type registry. The registry is used at render time to dispatch to
 * the correct renderer.
 *
 * @package    local_byblos
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class artefact_type {
    /**
     * Internal registry of artefact types keyed by name.
     *
     * @var array<string, artefact_type>
     */
    private static array $registry = [];

    /**
     * Get the machine-readable type name (e.g. "text", "badge").
     *
     * @return string
     */
    abstract public function get_name(): string;

    /**
     * Get the human-readable display name.
     *
     * Should use get_string() for localisation.
     *
     * @return string
     */
    abstract public function get_display_name(): string;

    /**
     * Get the icon identifier for this type.
     *
     * Returns a Moodle pix icon name (e.g. "i/badge", "f/text").
     *
     * @return string
     */
    abstract public function get_icon(): string;

    /**
     * Render an artefact of this type to HTML.
     *
     * @param \stdClass $artefact The artefact record from the database.
     * @return string HTML output.
     */
    abstract public function render(\stdClass $artefact): string;

    /**
     * Register an artefact type instance in the global registry.
     *
     * @param artefact_type $type The type instance to register.
     * @return void
     */
    public static function register(artefact_type $type): void {
        self::$registry[$type->get_name()] = $type;
    }

    /**
     * Retrieve a registered artefact type by name.
     *
     * @param string $name Type name.
     * @return artefact_type|null The type instance, or null if not registered.
     */
    public static function get(string $name): ?artefact_type {
        self::ensure_loaded();
        return self::$registry[$name] ?? null;
    }

    /**
     * Get all registered artefact types.
     *
     * @return array<string, artefact_type> Keyed by type name.
     */
    public static function get_all(): array {
        self::ensure_loaded();
        return self::$registry;
    }

    /**
     * Ensure all built-in types are loaded and registered.
     *
     * This lazy-loads the type classes on first access so that
     * callers don't need to manually initialise the registry.
     *
     * @return void
     */
    private static function ensure_loaded(): void {
        if (!empty(self::$registry)) {
            return;
        }

        $builtins = [
            artefact_types\text::class,
            artefact_types\file::class,
            artefact_types\image::class,
            artefact_types\audio::class,
            artefact_types\video::class,
            artefact_types\link::class,
            artefact_types\embed::class,
            artefact_types\course_completion::class,
            artefact_types\badge::class,
            artefact_types\blog_entry::class,
        ];

        foreach ($builtins as $classname) {
            $instance = new $classname();
            self::register($instance);
        }
    }

    /**
     * The artefact types a user can create by hand, in display order.
     *
     * Excludes the auto-imported types (badge, course_completion, blog_entry),
     * which are created from Moodle data rather than authored in the form.
     *
     * @return string[]
     */
    public static function creatable_types(): array {
        return ['text', 'image', 'file', 'link', 'audio', 'video', 'embed'];
    }
}
