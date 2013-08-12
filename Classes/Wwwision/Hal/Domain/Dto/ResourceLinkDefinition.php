<?php
namespace Wwwision\Hal\Domain\Dto;

/*                                                                        *
 * This script belongs to the Flow package "Wwwision.Hal".                *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;

/**
 */
class ResourceLinkDefinition {

	/**
	 * @var string
	 */
	protected $rel;

	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var ResourceDefinition
	 */
	protected $resourceDefinition = NULL;

	/**
	 * @var array
	 */
	protected $routeValues = NULL;

	/**
	 * String to append to the URI that is created with this link definition
	 * FIXME This is currently needed because exceeding routeParts are url encoded and we need s.th. like "?foo={bar}"
	 *
	 * @var string
	 */
	protected $appendQueryString;

	/**
	 * @var boolean
	 */
	protected $absolute = FALSE;

	/**
	 * @var boolean
	 */
	protected $templated = FALSE;

	/**
	 * @param string $rel
	 */
	public function __construct($rel) {
		$this->rel = $rel;
	}

	/**
	 * @return string
	 */
	public function getRel() {
		return $this->rel;
	}

	/**
	 * @param string $name
	 * @return void
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param string $title
	 * @return void
	 */
	public function setTitle($title) {
		$this->title = $title;
	}

	/**
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * @param ResourceDefinition $resourceDefinition
	 * @return void
	 */
	public function setResourceDefinition(ResourceDefinition $resourceDefinition) {
		$this->resourceDefinition = $resourceDefinition;
	}

	/**
	 * @return ResourceDefinition
	 */
	public function getResourceDefinition() {
		return $this->resourceDefinition;
	}

	/**
	 * @return boolean
	 */
	public function hasResourceDefinition() {
		return $this->resourceDefinition !== NULL;
	}

	/**
	 * @param array $routeValues
	 * @return void
	 */
	public function setRouteValues(array $routeValues) {
		$this->routeValues = $routeValues;
	}

	/**
	 * @return array
	 */
	public function getRouteValues() {
		return $this->routeValues;
	}

	/**
	 * @return boolean
	 */
	public function hasRouteValues() {
		return $this->routeValues !== NULL;
	}

	/**
	 * @param boolean $absolute
	 * @return void
	 */
	public function setAbsolute($absolute) {
		$this->absolute = $absolute;
	}

	/**
	 * @return boolean
	 */
	public function isAbsolute() {
		return $this->absolute;
	}

	/**
	 * @param boolean $templated
	 * @return void
	 */
	public function setTemplated($templated) {
		$this->templated = $templated;
	}

	/**
	 * @return boolean
	 */
	public function isTemplated() {
		return $this->templated;
	}

	/**
	 * @param string $append
	 * @return void
	 */
	public function setAppendQueryString($append) {
		$this->appendQueryString = $append;
	}

	/**
	 * @return string
	 */
	public function getAppendQueryString() {
		return $this->appendQueryString;
	}

	/**
	 * @return boolean
	 */
	public function hasAppendQueryString() {
		return $this->appendQueryString !== NULL && strlen($this->appendQueryString) > 0;
	}

}
?>