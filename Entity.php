<?php

namespace Plexxer;

class Entity {
	const sEntityName = null;

	public $mappedData;
	public $mappedRelations;
	public $changedFields = [];
	private $validationErrors = [];

	public function __construct($defaultValues = []) {
		foreach($defaultValues as $field => $value) {
			if (array_key_exists($field, $this->mappedData)) {
				$this->mappedData[$field] = $value;
			}
		}

		$this->resolveRelations();
	}

	/**
	 * Get a single field value from the entity.
	 *
	 * @param string $field
	 * @param mixed $defaultValue
	 *
	 * @return mixed
	 */
	public function get($field, $defaultValue = null) {
		return array_key_exists($field, $this->mappedData) ? $this->mappedData[$field] : $defaultValue;
	}

	/**
	 * Return the entire document as an array
	 *
	 * @return array the document
	 */
	public function toArray($fields = []) {
		$returnArray = [];

		foreach($this->mappedData as $field => $value) {
			if (!empty($fields) && !in_array($field, $fields))
				continue;

			if (array_key_exists($field, $this->mappedRelations)) {
				if ($this->mappedRelations[$field] == 'many') {
					foreach($value as $obj) {
						$returnArray[$field][] = is_scalar($value) ? $value : $obj->toArray();
					}
				} else {
					$returnArray[$field] = is_scalar($value) ? $value : $value->toArray();
				}
			} else {
				$returnArray[$field] = $value;
			}
		}

		return $returnArray;
	}

	/**
	 * @param string name of the field
	 * @param mixed value of the field
	 * @return bool true if field was set, false if field does not belong to this entity
	 */
	public function set($field, $value) {
		if (array_key_exists($field, $this->mappedData)) {
			$this->changedFields[] = $field;
			$this->mappedData[$field] = $value;
			return true;
		}

		return false;
	}

	public function getId() {
		return $this->get('id');
	}

	public function getCreated() {
		return $this->get('created');
	}

	public function getUpdated() {
		return $this->get('updated');
	}

	/**
	 * Accepts an array with key => values and overwrites all known fields in this document with the set values
	 *
	 * @param array an array containing keys and values belonging to this entity
	 * @return bool true
	 */
	public function fromArray($values) {
		foreach($values as $field => $value) {
			$this->changedFields[] = $field;
			if (array_key_exists($field, $this->mappedData)) {
				$this->mappedData[$field] = $value;
			}
		}

		return true;
	}

	/**
	 * Clones the current document and returns the copy instance
	 *
	 * @return Entity copy
	 */
	public function clone() {
		$clonedData = $this->mappedData;
		$clonedData['id'] = null;
		unset($clonedData['created']);
		unset($clonedData['updated']);

		$className = get_class($this);
		return new $className($clonedData);
	}

	final public function _setValidationErrors($errors) {
		$this->validationErrors = $errors;
	}

	final public function _getValidationErrors() {
		return $this->validationErrors;
	}

	final public function _getChangedFields() {
		return $this->changedFields;
	}

	final public function _clearChangedFields() {
		$this->changedFields = [];
	}

	final public function _getEntityName() {
		return static::sEntityName;
	}

	final public function _reset() {
		foreach($this->mappedData as $key => $value) {
			$this->mappedData[$key] = null;
		}

		$this->_clearChangedFields();
	}

	function _setValue($field, $value) {
		$this->changedFields[] = $field;
		$this->mappedData[$field] = $value;

		return $this;
	}

	public function resolveRelations() {
		if (!empty($this->mappedRelations)) {
			// Get the class namespace
			$classNamespace = explode('\\', get_class($this));
			array_pop($classNamespace);
			$classNamespace = implode('\\', $classNamespace);

			foreach($this->mappedRelations as $relation => $type) {
				$relationClass = $classNamespace.'\\'.$relation;
				$relationValue = $this->get($relation);

				$newValue = null;

				if ($type == 'many') {
					$newValue = [];
					foreach($relationValue as $relationData) {
						if (!is_scalar($relationData)) {
							$object = new $relationClass($relationData);

							$newValue[] = $object;
						}
					}
				} else {
					if (!is_scalar($relationValue)) {
						$object = new $relationClass($relationValue);

						$newValue = $object;
					} else {
						$newValue = $relationValue;
					}
				}

				$this->set($relation, $newValue);
			}
		}
	}
}