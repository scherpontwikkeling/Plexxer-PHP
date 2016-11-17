<?php

namespace Plexxer;

class EntityManager {
	private $api;
	private $queue = [
		'persist' => [],
		'delete' => []
	];

	function __construct(Api $api) {
		$this->api = $api;
	}

	function delete(Entity $entity) {
		$this->queue['delete'][] = $entity;
	}

	function persist(Entity $entity) {
		$this->queue['persist'][] = $entity;
	}

	function flush() {
		$failedDocuments = [];

		foreach($this->queue['persist'] as $entity) {
			/** @var \Plexxer\Entity $entity */
			$updateArray = [];
			if ($entity->getId() === null) {
				foreach($entity->mappedData as $field => $value) {
					$updateArray[$field] = $entity->get($field);
				}

				$entity->_clearChangedFields();

				// New entity
				$response = $this->api->create($entity->_getEntityName(), $updateArray);

				if (empty($response['success']) || empty($response['createdDocuments'])) {
					$entity->_setValidationErrors($response['failedDocuments'][0]['validationErrors']);
					$failedDocuments[] = $entity;
				} else {
					$entity->set('id', $response['createdDocuments'][0]['id']);
					$entity->_clearChangedFields();
				}
			} else {
				foreach($entity->changedFields as $field) {
					$updateArray[$field] = $entity->get($field);
				}

				$entity->_clearChangedFields();
				// Update entity
				$response = $this->api->update($entity->_getEntityName(), ['id' => $entity->getId()], $updateArray);
				if (empty($response['success'])) {
					$failedDocuments[] = $entity;
				}
			}
		}


		foreach($this->queue['delete'] as $entity) {
			if (!empty($entity->getId())) {
				$response = $this->api->delete($entity->_getEntityName(), ['id' => $entity->getId()]);

				if (empty($response['success']) || empty($response['deleted'])) {
					$failedDocuments[] = $entity;
				}
			}
		}

		// Flush the queue
		$this->queue = [
			'persist' => [],
			'delete' => []
		];

		if (empty($failedDocuments))
			return true;

		return $failedDocuments;
	}

	public function getRepository($entity) {
		return new Repository($entity, $this->api);
	}
}
