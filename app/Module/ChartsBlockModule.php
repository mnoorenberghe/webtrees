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
namespace Fisharebest\Webtrees\Module;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Bootstrap4;
use Fisharebest\Webtrees\Controller\HourglassController;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\Functions\FunctionsEdit;
use Fisharebest\Webtrees\Functions\FunctionsPrint;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\InteractiveTree\TreeView;
use Fisharebest\Webtrees\View;

/**
 * Class ChartsBlockModule
 */
class ChartsBlockModule extends AbstractModule implements ModuleBlockInterface {
	/** {@inheritdoc} */
	public function getTitle() {
		return /* I18N: Name of a module/block */ I18N::translate('Charts');
	}

	/** {@inheritdoc} */
	public function getDescription() {
		return /* I18N: Description of the “Charts” module */ I18N::translate('An alternative way to display charts.');
	}

	/**
	 * Generate the HTML content of this block.
	 *
	 * @param int      $block_id
	 * @param bool     $template
	 * @param string[] $cfg
	 *
	 * @return string
	 */
	public function getBlock($block_id, $template = true, $cfg = []): string {
		global $WT_TREE, $ctype, $controller;

		$PEDIGREE_ROOT_ID = $WT_TREE->getPreference('PEDIGREE_ROOT_ID');
		$gedcomid         = $WT_TREE->getUserPreference(Auth::user(), 'gedcomid');

		$type = $this->getBlockSetting($block_id, 'type', 'pedigree');
		$pid  = $this->getBlockSetting($block_id, 'pid', Auth::check() ? ($gedcomid ? $gedcomid : $PEDIGREE_ROOT_ID) : $PEDIGREE_ROOT_ID);

		foreach (['type', 'pid'] as $name) {
			if (array_key_exists($name, $cfg)) {
				$$name = $cfg[$name];
			}
		}

		$person = Individual::getInstance($pid, $WT_TREE);
		if (!$person) {
			$pid = $PEDIGREE_ROOT_ID;
			$this->setBlockSetting($block_id, 'pid', $pid);
			$person = Individual::getInstance($pid, $WT_TREE);
		}

		$title = $this->getTitle();

		if ($person) {
			$content = '';
			switch ($type) {
				case 'pedigree':
					$title           = I18N::translate('Pedigree of %s', $person->getFullName());
					$chartController = new HourglassController($person->getXref());
					$content .= '<table cellspacing="0" cellpadding="0" border="0"><tr>';
					$content .= '<td class="myCharts">';
					ob_start();
					FunctionsPrint::printPedigreePerson($person);
					$content .= ob_get_clean();
					$content .= '</td>';
					$content .= '<td>';
					ob_start();
					$chartController->printPersonPedigree($person, 1);
					$content .= ob_get_clean();
					$content .= '</td>';
					$content .= '</tr></table>';
					$content .= '<script>' . $chartController->setupJavascript() . '</script>';
					break;
				case 'descendants':
					$title           = I18N::translate('Descendants of %s', $person->getFullName());
					$chartController = new HourglassController($person->getXref());
					ob_start();
					$chartController->printDescendency($person, 1, false);
					$content .= ob_get_clean();
					$content .= '<script>' . $chartController->setupJavascript() . '</script>';
					break;
				case 'hourglass':
					$title           = I18N::translate('Hourglass chart of %s', $person->getFullName());
					$chartController = new HourglassController($person->getXref());
					$content .= '<table cellspacing="0" cellpadding="0" border="0"><tr>';
					$content .= '<td>';
					ob_start();
					$chartController->printDescendency($person, 1, false);
					$content .= ob_get_clean();
					$content .= '</td>';
					$content .= '<td>';
					ob_start();
					$chartController->printPersonPedigree($person, 1);
					$content .= ob_get_clean();
					$content .= '</td>';
					$content .= '</tr></table>';
					$content .= '<script>' . $chartController->setupJavascript() . '</script>';
					break;
				case 'treenav':
					$title = I18N::translate('Interactive tree of %s', $person->getFullName());
					$mod   = new InteractiveTreeModule(WT_MODULES_DIR . 'tree');
					$tv    = new TreeView;
					$content .= '<script>$("head").append(\'<link rel="stylesheet" href="' . $mod->css() . '" type="text/css" />\');</script>';
					$content .= '<script src="' . $mod->js() . '"></script>';
					list($html, $js) = $tv->drawViewport($person, 2);
					$content .= $html . '<script>' . $js . '</script>';
					break;
			}
		} else {
			$content = I18N::translate('You must select an individual and a chart type in the block preferences');
		}

		if ($template) {
			if ($ctype == 'gedcom' && Auth::isManager($WT_TREE) || $ctype == 'user' && Auth::check()) {
				$config_url = Html::url('block_edit.php', ['block_id' => $block_id, 'ged' => $WT_TREE->getName()]);
			} else {
				$config_url = '';
			}

			return View::make('blocks/template', [
				'block'      => str_replace('_', '-', $this->getName()),
				'id'         => $block_id,
				'config_url' => $config_url,
				'title'      => strip_tags($title),
				'content'    => $content,
			]);
		} else {
			return $content;
		}
	}

	/** {@inheritdoc} */
	public function loadAjax(): bool {
		return true;
	}

	/** {@inheritdoc} */
	public function isUserBlock(): bool {
		return true;
	}

	/** {@inheritdoc} */
	public function isGedcomBlock(): bool {
		return true;
	}

	/**
	 * An HTML form to edit block settings
	 *
	 * @param int $block_id
	 *
	 * @return void
	 */
	public function configureBlock($block_id): void {
		global $WT_TREE, $controller;

		$PEDIGREE_ROOT_ID = $WT_TREE->getPreference('PEDIGREE_ROOT_ID');
		$gedcomid         = $WT_TREE->getUserPreference(Auth::user(), 'gedcomid');

		if (Filter::postBool('save') && Filter::checkCsrf()) {
			$this->setBlockSetting($block_id, 'type', Filter::post('type', 'pedigree|descendants|hourglass|treenav', 'pedigree'));
			$this->setBlockSetting($block_id, 'pid', Filter::post('pid', WT_REGEX_XREF));
		}

		$type = $this->getBlockSetting($block_id, 'type', 'pedigree');
		$pid  = $this->getBlockSetting($block_id, 'pid', Auth::check() ? ($gedcomid ? $gedcomid : $PEDIGREE_ROOT_ID) : $PEDIGREE_ROOT_ID);

		$charts = [
			'pedigree'    => I18N::translate('Pedigree'),
			'descendants' => I18N::translate('Descendants'),
			'hourglass'   => I18N::translate('Hourglass chart'),
			'treenav'     => I18N::translate('Interactive tree'),
		];
		uasort($charts, 'Fisharebest\Webtrees\I18N::strcasecmp');

		?>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label" for="type">
				<?= I18N::translate('Chart type') ?>
			</label>
			<div class="col-sm-9">
				<?= Bootstrap4::select($charts, $type, ['id' => 'type', 'name' => 'type']) ?>
			</div>
		</div>
		<div class="form-group row">
			<label class="col-sm-3 col-form-label" for="pid">
				<label for="pid">
					<?= I18N::translate('Individual') ?>
				</label>
			</label>
			<div class="col-sm-9">
				<?= FunctionsEdit::formControlIndividual(Individual::getInstance($pid, $WT_TREE), ['id' => 'pid', 'name' => 'pid']) ?>
			</div>
		</div>
		<?php
	}
}
