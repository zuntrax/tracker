<?php
	
	requires(
		'String',
		
		'/Model/Project',
		'/Model/Ticket',
		
		'/Model/EncodingProfile',
		'/Helper/EncodingProfile',
		
		'/Model/TicketState',
		'/Model/ProjectTicketState',
		
		'/Model/WorkerGroup'
	);
	
	class Controller_Projects extends Controller_Application {
		
		public $requireAuthorization = true;
		
		public function index() {			
			$this->projects = Project::findAll();
			return $this->render('projects/index');
		}
		
		public function settings() {
			$this->properties = $this->project->Properties;
			
			$this->duration = Ticket::getRecordingDurationByProject(
				$this->project['id']
			);
			$this->encodingProfileCount = $this->project
				->EncodingProfileVersion
				->count();
			
			return $this->render('projects/settings');
		}	
			
		public function properties() {
			$this->form();
			
			if ($this->form->wasSubmitted() and
				$this->project->save($this->form->getValues())) {
				$this->flash('Properties updated');
				return $this->redirect('projects', 'properties', $this->project);
			}
			
			$this->properties = $this->project->Properties;
			return $this->render('projects/settings/properties');
		}
		
		public function profiles() {
			// Encoding Profiles
			$this->profilesForm = $this->form();
			
			if ($this->profilesForm->wasSubmitted() and
				$this->project->save($this->profilesForm->getValues())) {
				$this->flashNow('Encoding profiles updated');
			}
			
			$this->versions = $this->project
				->EncodingProfileVersion
				->join(['EncodingProfile'])
				->orderBy(EncodingProfile::TABLE . '.name');
			$this->versions->fetchAll();
			
			$this->versionsLeft = EncodingProfileVersion::findAll()
				->join([
					'EncodingProfile' => [
						'select' => 'name'
					]
				])
				->select(
					'id, encoding_profile_id, revision, created, description'
				)
				->orderBy(EncodingProfile::TABLE . '.name, revision DESC');
			
			$versions = $this->versions->pluck('encoding_profile_id');
			
			if (!empty($versions)) {
				$this->versionsLeft->whereNot([
					'encoding_profile_id' => $versions
				]);
			}
			
			return $this->render('projects/settings/profiles');
		}
		
		public function states() {
			// States
			$this->stateForm = $this->form();
			
			$this->states = TicketState::findAll()
				->join(['ProjectTicketState' => [
					'where' => ['project_id' => $this->project['id']]
				]])
				->select('ticket_type, ticket_state, service_executable')
				->orderBy('ticket_type, sort');

			if ($this->stateForm->wasSubmitted() and
				$this->project->save($this->stateForm->getValues())) {
				// TODO: move to Model?
				Cache::invalidateNamespace(
					'project.' . $this->project['id'] . '.states'
				);
				$this->flashNow('States updated');
			}
			
			return $this->render('projects/settings/states');
		}
		
		public function worker() {
			// Worker Groups
			$this->workerGroupForm = $this->form();
			
			if ($this->workerGroupForm->wasSubmitted() and
				$this->project->save($this->workerGroupForm->getValues())) {
				$this->flashNow('Worker group assignment updated');
			}
			
			$this->workerGroups = WorkerGroup::findAll()
				->select('id, title');
			$this->workerGroupAssignment = $this->project
				->WorkerGroup
				->select(WorkerGroup::TABLE . '.id')
				->indexBy('id')
				->toArray();
			
			return $this->render('projects/settings/worker');
		}
		
		public function create() {
			$this->form();
			
			if ($this->form->wasSubmitted() and
				($project = Project::create($this->form->getValues()))) {
				ProjectTicketState::createAll($project['id']);
				
				$this->flash('Project created');
				return $this->redirect('projects', 'settings', [
					'project_slug' => $project['slug']
				]);
			}
			
			return $this->render('projects/edit');
		}

		public function edit(array $arguments) {
			$this->form();
			
			if ($this->form->wasSubmitted() and
				$this->project->save($this->form->getValues())) {
				$this->flash('Project updated');
				return $this->redirect('projects', 'settings', $this->project);
			}
			
			return $this->render('projects/edit');
		}
		
		/*
			Duplicate project, copy languages, associated encoding profiles,
			selected states and worker groups
		*/
		public function duplicate() {
			$project = $this->project->duplicate();
			
			// Copy associated entries
			
			$project['Languages'] = $this->project
				->Languages
				->toArray();
			
			$project['EncodingProfileVersion'] = $this->project
				->EncodingProfileVersion
				->except(['fields'])
				->select('id AS encoding_profile_version_id')
				->toArray();
			
			$project['States'] = $this->project
				->States
				->select('ticket_type, ticket_state, service_executable')
				->toArray();
			
			$project['WorkerGroup'] = $this->project
				->WorkerGroup
				->except(['fields'])
				->select('id AS worker_group_id')
				->toArray();
			
			// Ensure unique slug
			$i = 0;
			
			do {
				$i++;
				$slug = 'duplicate-' . (($i > 1)? ($i . '-') : '') .
					'of-' . $project['slug'];
			} while (Project::exists(['slug' => $slug]));
			
			$project['title'] = 'Duplicate ' . (($i > 1)? ($i . ' ') : '') .
				'of ' . $project['title'];
			$project['slug'] = $project['project_slug'] = $slug;
			
			// Copy properties
			
			$properties = $this->project
				->Properties
				->indexBy('name')
				->toArray();
			
			if (isset($properties['Meta.Acronym'])) {
				$properties['Meta.Acronym']['value'] = 'duplicate-' .
					(($i > 1)? ($i . '-') : '') . 'of-' .
					$properties['Meta.Acronym']['value'];
			}
			
			$project['properties'] = $properties;
			
			if (!$project->save()) {
				return $this->redirect('projects', 'settings', $this->project);
			}
			
			$this->flash('Project duplicated');
			return $this->redirect('projects', 'edit', $project);
		}
		
		public function delete(array $arguments) {
			$this->form();
			
			if ($this->form->wasSubmitted() and $this->project->destroy()) {
				$this->flash('Project deleted');
				return $this->redirect('projects', 'index');
			}
			
			return $this->render('projects/delete');
		}
		
	}
	
?>