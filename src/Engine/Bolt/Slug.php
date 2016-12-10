<?php
declare(strict_types=1);
namespace Airship\Engine\Bolt;
use Airship\Engine\Database;

/**
 * Trait Slug
 *
 * Put common methods for slug generation here.
 *
 * @package Airship\Engine\Bolt
 */
trait Slug
{
    /**
     * Make a generic slug for most tables
     *
     * @param string $title What are we basing the URL off of?
     * @param string $table Which table to check for duplicates?
     * @param string $column Which column to check for duplicates?
     * @return string
     */
    protected function makeGenericSlug(
        string $title,
        string $table,
        string $column = 'slug'
    ): string {
        if (!($this->db instanceof Database)) {
            throw new \TypeError(
                \trk('errors.type.wrong_class', Database::class)
            );
        }
        $query = 'SELECT count(*) FROM ' .
                $this->db->escapeIdentifier($table) .
            ' WHERE ' .
                $this->db->escapeIdentifier($column) .
            ' = ?';

        $slug = $base_slug = \Airship\slugFromTitle($title);
        $i = 1;
        while ($this->db->cell($query, $slug) > 0) {
            $slug = $base_slug . '-' . ++$i;
        }
        return $slug;
    }
}
