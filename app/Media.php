<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2017 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace Fisharebest\Webtrees;

use Fisharebest\Webtrees\Functions\FunctionsPrintFacts;
use League\Glide\Urls\UrlBuilderFactory;

/**
 * A GEDCOM media (OBJE) object.
 */
class Media extends GedcomRecord {
	const RECORD_TYPE = 'OBJE';
	const URL_PREFIX  = 'mediaviewer.php?mid=';

	/**
	 * Each object type may have its own special rules, and re-implement this function.
	 *
	 * @param int $access_level
	 *
	 * @return bool
	 */
	protected function canShowByType($access_level) {
		// Hide media objects if they are attached to private records
		$linked_ids = Database::prepare(
			"SELECT l_from FROM `##link` WHERE l_to = ? AND l_file = ?"
		)->execute([
			$this->xref, $this->tree->getTreeId(),
		])->fetchOneColumn();
		foreach ($linked_ids as $linked_id) {
			$linked_record = GedcomRecord::getInstance($linked_id, $this->tree);
			if ($linked_record && !$linked_record->canShow($access_level)) {
				return false;
			}
		}

		// ... otherwise apply default behaviour
		return parent::canShowByType($access_level);
	}

	/**
	 * Fetch data from the database
	 *
	 * @param string $xref
	 * @param int    $tree_id
	 *
	 * @return null|string
	 */
	protected static function fetchGedcomRecord($xref, $tree_id) {
		return Database::prepare(
			"SELECT m_gedcom FROM `##media` WHERE m_id = :xref AND m_file = :tree_id"
		)->execute([
			'xref'    => $xref,
			'tree_id' => $tree_id,
		])->fetchOne();
	}

	/**
	 * Get the media files for this media object
	 *
	 * @return MediaFile[]
	 */
	public function mediaFiles(): array {
		$media_files = [];

		foreach ($this->getFacts('FILE') as $fact) {
			$media_files[] = new MediaFile($fact->getGedcom(), $this);
		}

		return $media_files;
	}

	/**
	 * Get the first media file that contains an image.
	 *
	 * @return MediaFile|null
	 */
	public function firstImageFile() {
		foreach ($this->mediaFiles() as $media_file) {
			if ($media_file->isImage()) {
				return $media_file;
			}
		}

		return null;
	}

	/**
	 * Get the first note attached to this media object
	 *
	 * @return null|string
	 */
	public function getNote() {
		$note = $this->getFirstFact('NOTE');
		if ($note) {
			$text = $note->getValue();
			if (preg_match('/^@' . WT_REGEX_XREF . '@$/', $text)) {
				$text = $note->getTarget()->getNote();
			}

			return $text;
		} else {
			return '';
		}
	}

	/**
	 * Extract names from the GEDCOM record.
	 */
	public function extractNames() {
		$names = [];
		foreach ($this->mediaFiles() as $media_file) {
			$names[] = $media_file->title();
			$names[] = $media_file->filename();
		}
		$names = array_filter(array_unique($names));

		if (empty($names)) {
			$names[] = $this->getFallBackName();
		}

		foreach ($names as $name) {
			$this->addName(static::RECORD_TYPE, $name, null);
		}
	}

	/**
	 * This function should be redefined in derived classes to show any major
	 * identifying characteristics of this record.
	 *
	 * @return string
	 */
	public function formatListDetails() {
		ob_start();
		FunctionsPrintFacts::printMediaLinks('1 OBJE @' . $this->getXref() . '@', 1);

		return ob_get_clean();
	}
}
