<?php

namespace Plexxer;

class EntityManager {
	private $api;
	private $queue = [
		'persist' => [],
		'delete' => []
	];

	public function __construct(Api $api) {
		$this->api = $api;
	}

	/**
	 * Add an entity to the delete queue
	 *
	 * @param Entity $entity
	 */
	public function delete(Entity $entity) {
		$this->queue['delete'][] = $entity;

		return $this;
	}

	/**
	 * Add an entity to the save queue
	 *
	 * @param Entity $entity
	 */
	public function persist(Entity &$entity) {
		$this->queue['persist'][] = $entity;

		return $this;
	}

	/**
	 * Save an entity and its relations to the database
	 *
	 * @param $entity
	 *
	 * @return array|Entity
	 */
	public function saveEntity(&$entity) {
		$failedDocuments = [];

		$relatedChildsReferences = [];

		/** @var Entity $entity */
		$updateArray = [];
		if ($entity->getId() === null) {
			foreach($entity->mappedData as $field => $value) {
				// If relation
				if (array_key_exists($field, $entity->mappedRelations)) {
					$this->saveRelatedEntity($entity, $field);
				}

				$updateArray[$field] = $entity->get($field);
			}

			$entity->_clearChangedFields();

			// Update entity
			$updateArray = $this->_translateUpdateQuery($updateArray);
			$response = $this->api->create($entity->_getEntityName(), $updateArray);

			if (empty($response['success']) || empty($response['createdDocuments'])) {
				$entity->_setValidationErrors($this->getResponseError($response));
				$failedDocuments[] = $entity;
			} else {
				$entity->set('id', $response['createdDocuments'][0]['id']);

				foreach($relatedChildsReferences as $relation => &$obj) {
					$obj->set($entity->_getEntityName(), $entity->getId());
				}

				$entity->_clearChangedFields();
			}
		} else {
			foreach($entity->changedFields as $field) {
				// If relation
				if (array_key_exists($field, $entity->mappedRelations)) {
					$this->saveRelatedEntity($entity, $field);
				}

				unset($updateArray[$field]);

				$updateArray[$field] = $entity->get($field);
			}

			$entity->_clearChangedFields();

			if (!empty($updateArray)) {
				// Update entity
				$updateArray = $this->_translateUpdateQuery($updateArray);
				$response = $this->api->update($entity->_getEntityName(), ['id' => $entity->getId()], $updateArray);
				if (empty($response['success'])) {
					$failedDocuments[] = $entity;
				}
			}
		}

		if (empty($failedDocuments))
			return $entity;

		return $failedDocuments;
	}

	/**
	 * Flush the entire queue, executing and finalizing all queued entities. Returns true on success, or an array on failure containing error messages.
	 *
	 * @return array|bool
	 */
	public function flush() {
		$failedDocuments = [];

		foreach($this->queue['persist'] as &$entity) {
			$entity = $this->saveEntity($entity);

			if (is_array($entity))
				$failedDocuments[] = $entity;
		}


		foreach($this->queue['delete'] as $entity) {
			$response = $this->deleteEntity($entity);

			if (is_array($response))
				$failedDocuments[] = $response;
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

	/**
	 * Get a specific entity repository: getRepository(Entity::class);
	 *
	 * @param $entity
	 *
	 * @return Repository
	 */
	public function getRepository($entity) {
		return new Repository($entity, $this->api);
	}

	private function getResponseError($response) {
		if (isset($response['failedDocuments'])) {
			return $response['failedDocuments'][0]['validationErrors'];
		}

		if (isset($response['message'])) {
			return $response['message'];
		}

		return false;
	}

	private function deleteEntity($entity) {
		/** @var Entity $entity */
		$failedDocuments = [];

		if (!empty($entity->getId())) {
			$response = $this->api->delete($entity->_getEntityName(), ['id' => $entity->getId()]);

			if (empty($response['success']) || empty($response['deleted'])) {
				$failedDocuments[] = $entity;
			}
		}

		if (empty($failedDocuments))
			return true;

		return $failedDocuments;
	}

	private function saveRelatedEntity(&$entity, $field) {
		$value = $entity->mappedData[$field];

		if ($entity->mappedRelations[$field] == 'one') {
			if (is_object($value)) {
				/** @var $value Entity */
				if ($value->getId() === null) {
					$response = $this->saveEntity($value);

					if (is_object($response)) {
						$relatedChildsReferences[] = $value;
					} elseif (is_array($response)) {
						$validationMessage = '';
						foreach($response as $validationError) {
							$errors = $validationError->_getValidationErrors();
							$validationMessage .= implode(", ", array_keys($errors));
						}

						throw new \Exception('Could not save a relation due to validation errors ('.$validationMessage.') for relation '.$field.'.');
					}
				}
			}
		} elseif (is_array($entity->mappedData[$field])) {
			foreach($entity->mappedData[$field] as $key => $obj) {
				/** @var $obj Entity */
				if (!is_scalar($obj) && is_object($obj)) {
					if ($obj->getId() === null) {
						// This relation is not yet saved, so save it my son
						$response = $this->saveEntity($obj);

						$relatedChildsReferences[] = $obj;

						if (is_object($response)) {
							// Everything went OK
						} else {
							throw new \Exception('Could not save a many relation due to validation errors ('.print_r($response, true).').');
						}
					}
				}
			}
		}
	}

	/**
	 * Translates all objects back into ID's for relational values since an entity is never saved into the database
	 *
	 * @param array $updateArray the array that needs translating
	 *
	 * @return array the updated array
	 */
	private function _translateUpdateQuery($updateArray) {
		foreach($updateArray as $key => $value) {
			if (is_object($value)) {
				/** @var Entity $value */
				// Then it's an entity
				$updateArray[$key] = $value->getId();
			} elseif (is_array($value) && isset($value[0])) {
				// The it's a many probably
				$updateArray[$key] = $this->_translateUpdateQuery($value);
			}
		}

		return $updateArray;
	}
}
