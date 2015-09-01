<?php
/**
 * HUBzero CMS
 *
 * Copyright 2005-2015 Purdue University. All rights reserved.
 *
 * This file is part of: The HUBzero(R) Platform for Scientific Collaboration
 *
 * The HUBzero(R) Platform for Scientific Collaboration (HUBzero) is free
 * software: you can redistribute it and/or modify it under the terms of
 * the GNU Lesser General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * HUBzero is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * HUBzero is a registered trademark of Purdue University.
 *
 * @package   hubzero-cms
 * @author    Sam Wilson <samwilson@purdue.edu>
 * @copyright Copyright 2005-2015 Purdue University. All rights reserved.
 * @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPLv3
 */

// No direct access.
defined('_HZEXEC_') or die();

Html::behavior('core');

$this->css()
     ->css('hubs')
     ->js('hubs');

// Set some ordering variables
$sortcol = $this->rows->orderBy;
$dir     = $this->rows->orderDir;
$newdir  = ($dir == 'asc') ? 'desc' : 'asc';
?>

<header id="content-header">
	<h2><?php echo $this->title; ?></h2>
	<div id="content-header-extra">
		<ul id="useroptions">
			<li class="last">
				<a class="add icon-add btn" href="<?php echo Route::url($this->base . '&task=new'); ?>">
					<?php echo Lang::txt('COM_TIME_HUBS_NEW'); ?>
				</a>
			</li>
		</ul>
	</div>
</header>

<div class="com_time_container">
	<?php $this->view('menu', 'shared')->display(); ?>
	<section class="com_time_content com_time_hubs">
		<div class="container">
			<?php if (count($this->getErrors()) > 0) : ?>
				<?php foreach ($this->getErrors() as $error) : ?>
				<p class="error"><?php echo $this->escape($error); ?></p>
				<?php endforeach; ?>
			<?php endif; ?>
			<div class="entries table">
				<div class="caption"><?php echo Lang::txt('COM_TIME_HUBS_CAPTION'); ?></div>
				<div class="thead">
					<div class="tr">
						<div class="th">
							<a <?php if ($sortcol == 'name') { echo ($dir == 'asc') ? 'class="sort_asc alph"' : 'class="sort_desc alph"'; } ?>
								href="<?php echo Route::url($this->base . '&orderby=name&orderdir=' . $newdir); ?>">
									<?php echo Lang::txt('COM_TIME_HUBS_NAME'); ?>
							</a>
						</div>
						<div class="th">
							<a <?php if ($sortcol == 'liaison') { echo ($dir == 'asc') ? 'class="sort_asc alph"' : 'class="sort_desc alph"'; } ?>
								href="<?php echo Route::url($this->base . '&orderby=liaison&orderdir=' . $newdir); ?>">
									<?php echo Lang::txt('COM_TIME_HUBS_LIAISON'); ?>
							</a>
						</div>
						<div class="th">
							<a <?php if ($sortcol == 'anniversary_date') { echo ($dir == 'asc') ? 'class="sort_asc num"' : 'class="sort_desc num"'; } ?>
								href="<?php echo Route::url($this->base . '&orderby=anniversary_date&orderdir=' . $newdir); ?>">
									<?php echo Lang::txt('COM_TIME_HUBS_ANNIVERSARY_DATE'); ?>
							</a>
						</div>
						<div class="th">
							<a <?php if ($sortcol == 'support_level') { echo ($dir == 'asc') ? 'class="sort_asc alph"' : 'class="sort_desc alph"'; } ?>
								href="<?php echo Route::url($this->base . '&orderby=support_level&orderdir=' . $newdir); ?>">
									<?php echo Lang::txt('COM_TIME_HUBS_SUPPORT_LEVEL'); ?>
							</a>
						</div>
					</div>
				</div>
				<div class="tbody">
					<?php foreach ($this->rows as $hub) : ?>
					<div class="tr">
						<div class="td">
							<div class="small-label"><?php echo Lang::txt('COM_TIME_HUBS_NAME'); ?>:</div>
							<div class="small-content">
								<a class="view" id="<?php echo $hub->id; ?>" href="<?php echo Route::url($this->base . '&task=readonly&id=' . $hub->id); ?>">
									<?php echo $hub->name; ?>
								</a>
							</div>
						</div>
						<div class="td">
							<div class="small-label"><?php echo Lang::txt('COM_TIME_HUBS_LIAISON'); ?>:</div>
							<div class="small-content">
								<?php echo $hub->liaison; ?>
							</div>
						</div>
						<div class="td">
							<div class="small-label"><?php echo Lang::txt('COM_TIME_HUBS_ANNIVERSARY_DATE'); ?>:</div>
							<div class="small-content">
								<?php echo ($hub->anniversary_date != '0000-00-00') ? Date::of($hub->anniversary_date)->toLocal('m/d/y') : ''; ?>
							</div>
						</div>
						<div class="td">
							<div class="small-label"><?php echo Lang::txt('COM_TIME_HUBS_SUPPORT_LEVEL'); ?>:</div>
							<div class="small-content">
								<?php echo $hub->support_level; ?>
							</div>
						</div>
					</div>
					<?php endforeach; ?>
					<?php if (!$this->rows->count()) : ?>
						<div class="tr">
							<div class="td no_hubs"><?php echo Lang::txt('COM_TIME_HUBS_NONE_TO_DISPLAY'); ?></div>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<form action="<?php echo Route::url($this->base); ?>">
				<?php echo $this->rows->pagination; ?>
			</form>
		</div>
	</section>
</div>