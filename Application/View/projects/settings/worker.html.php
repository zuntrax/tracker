<?php $this->title('Worker groups | '); ?>
<?= $this->render('projects/settings/_header'); ?>

<?= $f = $workerGroupForm(['disabled' => $project['read_only']]); ?>
	<div class="project-settings-save">
		<fieldset>
			<?= $f->submit('Save assignment'); ?>
		</fieldset>
	</div>
		
	<ul class="worker-groups clearfix">
		<?php foreach ($workerGroups as $index => $group): ?>
			<li>
				<?= $f->checkbox(
					'WorkerGroup[' . $index . '][worker_group_id]',
					$group['title'],
					isset($workerGroupAssignment[$group['id']]),
					['value' => $group['id']] +
						((isset($workerGroupAssignment[$group['id']]))?
							['data-association-destroy' => 'WorkerGroup[' . $index . '][_destroy]'] :
							[]),
					false
				); ?>
				
				<?php if (User::isAllowed('workers', 'queue')) {
					echo $this->linkTo('workers', 'queue', $group, 'Show queue');
				} ?>
			</li>
		<?php $f->register('WorkerGroup[' . $index . '][_destroy]');
		endforeach; ?>
	</ul>
</form>