<?= $this->render('projects/settings/_header'); ?>

<?php echo $f = $form(); ?>
	<div class="project-settings-save">
		<fieldset>
			<?= $f->submit('Save changes'); ?>
		</fieldset>
	</div>
	<fieldset>
		<legend>Properties</legend>
		<?php echo $this->render('shared/form/properties', array(
			'f' => $f,
			'properties' => array(
				'for' => (!empty($project))? $project->Properties : null,
				'field' => 'properties',
				'description' => 'property',
				'key' => 'name',
				'value' => 'value'
			)
		)); ?>
	</fieldset>
	
	<fieldset>
		<legend>Languages</legend>
		<?php echo $this->render('shared/form/properties', array(
			'f' => $f,
			'properties' => array(
				'for' => (!empty($project))? $project->Languages : null,
				'field' => 'languages',
				'description' => 'language',
				'key' => 'language',
				'value' => 'description'
			)
		)); ?>
	</fieldset>
	
	<fieldset>
		<ul>
			<li>
				<?php echo $f->submit('Save changes') . ' or ';
				echo $this->linkTo('projects', 'settings', $project, 'discard changes', array('class' => 'reset')); ?>
			</li>
		</ul>
	</fieldset>
</form>