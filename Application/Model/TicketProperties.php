<?php

	requires (
		'String'
	);

	class TicketProperties extends Model {
		
		const TABLE = 'tbl_ticket_property';
		
		public $primaryKey = ['ticket_id', 'name'];
		
		const CREATE_IF_NOT_EXISTS = true;
		
		const TYPE_WITH_VIRTUAL = 'VirtualProperties';
		const TYPE_WITH_VIRTUAL_MERGED = 'MergedProperties';
		
		public $belongsTo = [
			'Ticket' => [
				'foreign_key' => ['ticket_id']
			]
		];
		
		public static function findUniqueValues($property, $projectId) {
			return TicketProperties::findAll()
				->withoutDefaultScope()
				->distinct()
				->join(Ticket::TABLE, [
					'id = ' . self::TABLE . '.ticket_id',
					'project_id' => $projectId
				])
				->where(['name' => $property])
				->orderBy('value');
		}
		
		public static function buildSlug(Model $project, array $properties) {
			$parts = [
				$properties['Project.Slug']
			];

			if (isset($properties['Fahrplan.ID'])) {
				$parts[] = $properties['Fahrplan.ID'];
			}

			// add language if project has multiple languages
			if (count($project->Languages) > 0 && isset($properties['Record.Language'])) {
				$parts[] = $properties['Record.Language'];
			}

			// generate slug from ticket title (and ignore the one from the frab)
			 $parts[] = trim(preg_replace([
				'/[.:"\']/',
				'/[^a-zA-Z_\-0-9]/',
				'/_+/'
			],[
				'',
				'_',
				'_'
			], str_utf8_ascii_transliterate(
				$properties['Fahrplan.Title']
			)), '_');

			return implode('-', $parts);
		}
		
		public function defaultScope(Model_Resource $resource) {
			$resource
				->orderBy('name')
				->indexBy('name');
		}
	}
	
	class TicketPropertyAssocation {
		
		protected $_parent;
		protected $_type;
		
		public function __construct(Model $parent, $type = TicketProperties::TYPE_WITH_VIRTUAL) {
			$this->_parent = $parent;
			$this->_type = $type;
		}
		
		public function init(array $parent, Model_Resource $parentResource = null) {
			$resource = new TicketPropertyResource(
				new TicketProperties(),
				$this,
				$this->_parent
			);
			
			if ($this->isMerged()) {
				$include = [$parent['id']];
				
				if (!empty($parent['parent_id'])) {
					$include[] = $parent['parent_id'];
				}
				
				if ($parent['ticket_type'] === 'encoding') {
					$source = $this->_parent
						->Parent
						->Source;
					
					if ($source !== null) {
						$include[] = $source['id'];
					}
				}
			} else {
				$resource->where(['ticket_id' => $parent['id']]);
			}
			
			return $resource;
		}
		
		public function isMerged() {
			return $this->_type == TicketProperties::TYPE_WITH_VIRTUAL_MERGED;
		}
		
	}
	
	class TicketPropertyResource extends Model_Resource {
		
		protected $_association;
		protected $_parentTicket;
		
		public function __construct(
			Model $parentModel,
			TicketPropertyAssocation $association,
			Ticket $parentTicket
		) {
			parent::__construct($parentModel);
			
			$this->_association = $association;
			$this->_parentTicket = $parentTicket;
		}
		
		/*
			Virtual properties:
				
				Fahrplan.ID (via ticket.fahrplan_id)
				Fahrplan.Title (via ticket.title)
				
				Fahrplan.Date (via Fahrplan.DateTime) (legacy)
				Fahrplan.Start (via Fahrplan.DateTime) (legacy)
				
				Fahrplan.Person_list (via Fahrplan.Persons) (legacy)
			
			When merged we add these properties:
				
				Project.Slug (via project.slug)
				
				Encoding.Basename (via TicketProperties::buildSlug)
				
				EncodingProfile.Basename
				(via Encoding.Basename and EncodingProfile.Slug)
				EncodingProfile.Slug (via encoding_profile.slug)
				EncodingProfile.Extension (via encoding_profile.extension)
				EncodingProfile.MirrorFolder (via encoding_profile.mirror_folder)
		*/
		public function load() {
			if ($this->_entries !== null) {
				return $this;
			}
			
			parent::load();
			
			if ($this->_association->isMerged()) {
				$this->_mergeProperties();
			}
			
			$index = array_column($this->_entries, 'value', 'name');
			
			if (isset($index['Fahrplan.DateTime'])) {
				$date = new DateTime($index['Fahrplan.DateTime']);
				
				if (!isset($index['Fahrplan.Date'])) {
					$this->_entries[] = [
						'name' => 'Fahrplan.Date',
						'value' => $date->format('Y-m-d'),
						'virtual' => true
					];
				}
				
				if (!isset($index['Fahrplan.Start'])) {
					$this->_entries[] = [
						'name' => 'Fahrplan.Start',
						'value' => $date->format('H:i'),
						'virtual' => true
					];
				}
			}
			
			if (isset($index['Fahrplan.Persons']) and !isset($index['Fahrplan.Person_list'])) {
				$this->_entries[] = [
					'name' => 'Fahrplan.Person_list',
					'value' => $index['Fahrplan.Persons'],
					'virtual' => true
				];
			}
			
			if ($this->_parentTicket['ticket_type'] === 'meta' or $this->_association->isMerged()) {
				if (!isset($index['Fahrplan.ID'])) {
					$this->_entries[] = [
						'name' => 'Fahrplan.ID',
						'value' => $this->_parentTicket['fahrplan_id'],
						'virtual' => true
					];
				}
				
				if (!isset($index['Fahrplan.Title'])) {
					$this->_entries[] = [
						'name' => 'Fahrplan.Title',
						'value' => ($this->_parentTicket['ticket_type'] === 'meta' or !$this->_association->isMerged())?
							$this->_parentTicket['title'] :
							$this->_parentTicket->Parent['title'],
						'virtual' => true
					];
				}
			}
			
			if ($this->_association->isMerged()) {
				if (!isset($index['Project.Slug'])) {
					$this->_entries[] = [
						'name' => 'Project.Slug',
						'value' => $this->_parentTicket->Project['slug'],
						'virtual' => true
					];
				}
				
				if ($this->_parentTicket['ticket_type'] === 'encoding') {
					if (!isset($index['Encoding.Basename'])) {
						$basename = TicketProperties::buildSlug(
							$this->_parentTicket->Project,
							// TODO: better idea than rebuilding index?
							array_column($this->_entries, 'value', 'name')
						);
					
						$this->_entries[] = [
							'name' => 'Encoding.Basename',
							'value' => $basename,
							'virtual' => true
						];
					} else {
						$basename = $index['Encoding.Basename'];
					}
					
					$profile = $this->_parentTicket
						->EncodingProfileVersion
						->EncodingProfile;
					
					if ($profile === null) {
						throw new EntryNotFoundException();
					}
					
					if (!isset($index['EncodingProfile.Basename'])) {
						$this->_entries[] = [
							'name' => 'EncodingProfile.Basename',
							'value' => $basename . ((!empty($profile['slug']))?
								('_' . $profile['slug']) : ''),
							'virtual' => true
						];
					}
					
					if (!isset($index['EncodingProfile.Slug'])) {
						$this->_entries[] = [
							'name' => 'EncodingProfile.Slug',
							'value' => $profile['slug'],
							'virtual' => true
						];
					}
					
					if (!isset($index['EncodingProfile.Extension'])) {
						$this->_entries[] = [
							'name' => 'EncodingProfile.Extension',
							'value' => $profile['extension'],
							'virtual' => true
						];
					}
					
					if (!isset($index['EncodingProfile.MirrorFolder'])) {
						$this->_entries[] = [
							'name' => 'EncodingProfile.MirrorFolder',
							'value' => $profile['mirror_folder'],
							'virtual' => true
						];
					}
				}
			}
			
			if ($this->_parentResource->_query['orderBy'] === 'name') {
				usort($this->_entries, function($a, $b) {
					return strcmp($a['name'], $b['name']);
				});
			}
			
			return $this;
		}
		
		private function _mergeProperties() {
			$properties = $this->_parentTicket
				->Project
				->Properties
				->toArray();
			
			$properties = array_merge($properties, $this->_entries);
			
			$index = [];
			
			foreach ($properties as $i => $property) {
				$propertyIndex = [
					$i,
					// Set priority: current ticket, before parent ticket, other tickets (e.g. source) and project properties at last
					(isset($property['ticket_id']))?
						(($property['ticket_id'] === $this->_parentTicket['id'])?
							4 :
							(($property['ticket_id'] === $this->_parentTicket['parent_id'])?
								3 : 2)
						)
						: 1
				];
				
				// Property was already set
				if (isset($index[$property['name']])) {
					// Check if existing property has higher priority
					if ($index[$property['name']][1] > $propertyIndex[1]) {
						// Remove current property if priority is lower
						unset($properties[$i]);
						continue;
					}
					
					// Remove existing property
					unset($properties[$index[$property['name']][0]]);
				}
				
				$index[$property['name']] = $propertyIndex;
			}
			
			// Reset indices
			$this->_entries = array_values($properties);
		}
		
	}
	
?>