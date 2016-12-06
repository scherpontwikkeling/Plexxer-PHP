<?php

namespace Plexxer;

class Repository {
	/** @var Entity $sEntityClass  */
	private $sEntityClass = null;

	private $sEntity;

	/** @var Api $oPLEXXER */
	private $oPLEXXER;

	public function __construct($entity, &$oPLEXXER) {
		$this->sEntity = constant($entity.'::sEntityName');
		$this->sEntityClass = $entity;
		$this->oPLEXXER = $oPLEXXER;
	}

	/**
	 * @param array $data
	 * @param array $query
	 *
	 * @return array Full of Entity objects :)
	 */
	public function read($data = [], $query = []) {
		$result = $this->oPLEXXER->read($this->sEntity, $data ?? [], $query ?? []);

		$returnValue = [];

		if (sizeof($result['documents']) > 0) {
			foreach($result['documents'] as $document) {
				$object = new $this->sEntityClass($document);
				$returnValue[] = $object;
			}
		}

		return $returnValue;
	}

	/**
	 * @param array $data
	 * @param array $query
	 *
	 * @return Entity|bool
	 */
	public function readOne(array $data = [], array $query = []) {
		$query['limit'] = 1;
		$result = $this->oPLEXXER->read($this->sEntity, $data ?? [], $query ?? []);

		$this->validateResponse($result);

		if (sizeof($result['documents']) > 0) {
			$object = new $this->sEntityClass(array_shift($result['documents']));
			return $object;
		}

		return false;
	}

	/**
	 * Overwrites the local mappedData and refreshes an object from the database
	 *
	 * @param Entity $entity
	 *
	 * @return bool
	 */
	public function rollback(Entity &$entity) {
		/** @var Entity $entity */
		if ($entity->get('id') !== null) {
			$data = $this->oPLEXXER->read($entity->_getEntityName(), ['id' => $entity->get('id')]);

			if ($data['success'] === true) {
				$entity->_clearChangedFields();

				$document = array_shift($data['documents']);
				foreach($document as $field => $value)
					$entity->set($field, $value);

				return true;
			}
		}

		return false;
	}

	private function validateResponse($response) {
		if (isset($response['success']) && $response['success'] === false) {
			$message = 'Generic error';

			if (isset($response['message']))
				$message = $response['message'];

			throw new \Exception('Database error: '.$message);
		}
	}
}