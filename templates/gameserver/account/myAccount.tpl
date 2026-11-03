<p>
	<?=$welcome?>
</p>

<?php if (isset ($setEmail)) { ?>
	<h2><?=$todo?></h2>
	<ul>
		<li>&raquo; <a href="javascript:void(0);" onclick="windowAction(this,'formAction=email');"><?=$setEmail?></a></li>
	</ul>
<?php } ?>

<h2><?=$this->getText ('account')?></h2>
<ul>
	<li>
		&raquo; <a href="javascript:void(0);" onclick="openWindow('Premium');"><strong><?=$this->getText ('premium')?></strong></a>
	</li>
	
	<?php if (isset ($changePassword)) { ?>
		<li>
			&raquo; <a href="javascript:void(0);" onclick="windowAction (this, {'action':'changePassword'});"><?=$this->getText ('changePass')?></a>
		</li>
	<?php } ?>
</ul>

<ul style="margin-top: 8px;">
	<li>
		&raquo; <a href="javascript:void(0);" onclick="openWindow('Vacation');"><?=$this->getText ('vacationMode')?></a>
	</li>
	<li>
		&raquo; <a href="javascript:void(0);" onclick="windowAction (this, {'action':'resetAccount'});"><?=$this->getText ('resetAccount')?></a>
	</li>
</ul>

<h2><?=$this->getText ('layout')?></h2>
<form onsubmit="return false;">
	<select name="layout" onchange="Game.layout.setPreference(this.value); submitForm(this.form, {'action':'layout'}); return false;">
		<?php foreach (array ('auto' => 'layoutAuto', 'desktop' => 'layoutDesktop', 'mobile' => 'layoutMobile') as $value => $key) { ?>
			<option value="<?=$value?>"<?php if ($layout === $value) { echo ' selected="selected"'; } ?>><?=$this->getText ($key)?></option>
		<?php } ?>
	</select>
</form>

<?php if (!empty ($push)) { ?>
	<h2><?=$this->getText ('push')?></h2>
	<div class="push-settings">
		<p class="push-intro"><?=$this->getText ('pushIntro')?></p>
		<p class="push-status" style="display: none;"
			data-on="<?=htmlspecialchars ($this->getText ('pushOn'))?>"
			data-off="<?=htmlspecialchars ($this->getText ('pushOff'))?>"
			data-blocked="<?=htmlspecialchars ($this->getText ('pushBlocked'))?>"
			data-unsupported="<?=htmlspecialchars ($this->getText ('pushUnsupported'))?>"
			data-ios="<?=htmlspecialchars ($this->getText ('pushIos'))?>"><?=$this->getText ('pushOff')?></p>
		<p>
			<button type="button" class="push-enable" style="display: none;" onclick="Game.push.enable(this); return false;"><?=$this->getText ('pushEnable')?></button>
			<button type="button" class="push-disable" style="display: none;" onclick="Game.push.disable(this); return false;"><?=$this->getText ('pushDisable')?></button>
		</p>

		<?php if (!empty ($pushCategories)) { ?>
			<form onsubmit="submitForm(this, {'action':'push'}); return false;">
				<p><?=$this->getText ('pushCategories')?></p>
				<ul>
					<?php foreach ($pushCategories as $category) { ?>
						<li>
							<label>
								<input type="checkbox" name="push_<?=htmlspecialchars ($category['id'])?>" value="1"<?php if ($category['enabled']) { echo ' checked="checked"'; } ?> />
								<?=htmlspecialchars ($category['label'])?>
							</label>
						</li>
					<?php } ?>
				</ul>
				<p><button type="submit"><?=$this->getText ('pushSave')?></button></p>
			</form>
		<?php } ?>
	</div>
<?php } ?>

<ul style="margin-top: 8px;">
	<li>
		&raquo; <a href="<?=ABSOLUTE_URL?>?logout=true"><?=$logout?></a>
	</li>
</ul>
