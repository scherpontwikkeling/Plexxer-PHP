<?php

namespace Plexxer;

class Entity {
	const sEntityName = null;

	public $mappedData;
	public $changedFields = [];
	private $validationErrors = [];

	public function __construct($defaultValues = []) {
		foreach($defaultValues as $field => $value) {
			if (array_key_exists($field, $this->mappedData)) {
				$this->mappedData[$field] = $value;
			}
		}
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
	 * getArray
	 *
	 * Return the entire document as an array
	 *
	 * @return (array) the document
	 */
	public function toArray($fields = []) {
		if (empty($fields))
			return $this->mappedData;
		else {
			$returnArray = [];

			foreach($fields as $field)
				if (array_key_exists($field, $this->mappedData))
					$returnArray[$field] = $this->mappedData[$field];

			return $returnArray;
		}
	}

	/**
	 * set
	 *
	 * Set a single field
	 *
	 * @param (string) name of the field
	 * @param (mixed) value of the field
	 * @return (bool) true if field was set, false if field does not belong to this entity
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
	 * fromArray
	 *
	 * Accepts an array with key => values and overwrites all known fields in this document with the set values
	 *
	 * @param (array) an array containing keys and values belonging to this entity
	 * @return (bool) true
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
	 * clone
	 *
	 * Clones the current document and returns the copy instance
	 *
	 * @return (mixed) copy on success, false on failure
	 */
	public function clone() {
		$clonedData = $this->mappedData;
		$clonedData['id'] = null;
		unset($clonedData['created']);
		unset($clonedData['updated']);

		$className = '\Plexxer\\'.$this->sEntity;
		return new $className($this->oPLEXXER, $clonedData);
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
}