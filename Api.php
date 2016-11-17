<?php

namespace Plexxer;

class Api {
	private $apiBaseUrl = 'https://api.plexxer.com/';
	private $apiUrl;

	/** @var EntityManager $entityManager */
	private $entityManager;
	public $devMode = false;
	public $gzip = false;
	public $version = false;

	function __construct($apiKey, $apiToken) {
		$this->apiKey = $apiKey;
		$this->apiToken = $apiToken;

		if (empty($this->apiKey) || empty($this->apiToken)) {
			exit('Plexxer $apiKey and $authToken are required for the constructor.');
		}
	}

	/**
	 * @return EntityManager
	 */
	public function getEntityManager() {
		if ($this->entityManager === null) {
			$this->entityManager = new EntityManager($this);
		}

		return $this->entityManager;
	}

	function setVersion($v) {
		$this->version = $v;
	}

	function create($entity, $data) {
		$this->apiUrl = $this->apiBaseUrl.'create/'.$this->apiKey.'/'.$entity;

		return $this->request($data);
	}

	function read($entity, $data=array(), $query=array()) {

		$this->apiUrl = $this->apiBaseUrl.'read/'.$this->apiKey.'/'.$entity;

		if (!empty($query)) {
			$data['query'] = $query;
		}

		if (isset($query['gzip']) && $query['gzip'] === true) {
			$this->gzip = true;
		}

		if ($entity == 'app:file' && isset($query['format']) && $query['format'] == 'raw') {
			$this->request($data);
			return $this->rawResponse;
		}

		return $this->request($data);
	}

	function update($entity, $data, $set=array()) {
		$this->apiUrl = $this->apiBaseUrl.'update/'.$this->apiKey.'/'.$entity;

		if (!empty($set)) {
			$data[':set'] = $set;
		}

		return $this->request($data);
	}

	function delete($entity, $data) {
		$this->apiUrl = $this->apiBaseUrl.'delete/'.$this->apiKey.'/'.$entity;

		if (!empty($query)) {
			$data['query'] = $query;
		}

		return $this->request($data);
	}

	function request($data) {
		if ($this->apiUrl == null) {
			return false;
		}

		if ($this->devMode) {
			$this->request = curl_init($this->apiUrl.'?dev=1');
		} elseif ($this->version !== false) {
			$this->request = curl_init($this->apiUrl.'?v='.$this->version);
		} else {
			$this->request = curl_init($this->apiUrl);
		}

		curl_setopt_array($this->request, array(
			CURLOPT_HTTPHEADER => array(
				'X-Token: '.$this->apiToken
			),
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => array(
				'json' => json_encode($data)
			)
		));

		$this->rawResponse = curl_exec($this->request);
		$this->requestInfo = array(
			'size' => curl_getinfo($this->request, CURLINFO_SIZE_DOWNLOAD),
			'speed' => curl_getinfo($this->request, CURLINFO_SPEED_DOWNLOAD),
			'contenttype' => curl_getinfo($this->request, CURLINFO_CONTENT_TYPE),
			'length' => curl_getinfo($this->request, CURLINFO_CONTENT_LENGTH_DOWNLOAD)
		);

		if ($this->gzip === true) {
			// decode gzipped content
			$this->rawResponse = gzinflate($this->rawResponse);
		}

		$this->response = json_decode($this->rawResponse, true);

		$this->gzip = false;

		return $this->response;
	}

	function checkResponse($response) {
		if (is_array($response)) {
			if (isset($response['success'])) {
				if ($response['success'] === true) {
					return true;
				}
			}
		}

		return false;
	}
}
