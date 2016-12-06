<?php

namespace Plexxer;

class EntityIterator implements \Iterator {
	private $position = [];
	private $array = [];

	public function __construct($inputArray) {
		$this->position = 0;

		$this->array = $inputArray;

		return $this;
	}

	public function toArray() {
		return $this->entitiesToArray($this->array);
	}

	private function entitiesToArray($array) {
		foreach($array as $k => $v) {
			if (is_object($v) && method_exists($v, 'toArray')) {
				$array[$k] = $v->toArray();
			} elseif (is_array($v)) {
				$array[$k] = $this->entitiesToArray($v);
			} else {
				$array[$k] = $v;
			}
		}

		return $array;
	}

	public function rewind() {
		$this->position = 0;

		return reset($this->array);
	}

	public function current() {
		return $this->array[$this->position];
	}

	public function key() {
		return $this->position;
	}

	public function next() {
		$this->position++;

		return $this;
	}

	public function previous() {
		$this->position--;

		return $this;
	}

	public function valid() {
		return isset($this->array[$this->position]);
	}

}