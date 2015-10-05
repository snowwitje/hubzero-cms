<?php
/**
 * @package		Joomla.Administrator
 * @subpackage	com_media
 * @copyright	Copyright (C) 2005 - 2014 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_HZEXEC_') or die();

$style = Request::getState('media.list.layout', 'layout', 'thumbs', 'word');
?>
<div id="submenu-box">
	<div class="submenu-box">
		<div class="submenu-pad">
			<ul id="submenu" class="media">
				<li><a href="#" id="thumbs" onclick="MediaManager.setViewType('thumbs')" class="<?php echo ($style == "thumbs") ? 'active' : '';?>">
				<?php echo Lang::txt('COM_MEDIA_THUMBNAIL_VIEW'); ?></a></li>
				<li><a href="#" id="details" onclick="MediaManager.setViewType('details')" class="<?php echo ($style == "details") ? 'active' : '';?>">
				<?php echo Lang::txt('COM_MEDIA_DETAIL_VIEW'); ?></a></li>
			</ul>
			<div class="clr"></div>
		</div>
	</div>
	<div class="clr"></div>
</div>