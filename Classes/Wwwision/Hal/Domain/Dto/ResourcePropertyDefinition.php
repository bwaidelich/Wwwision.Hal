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
class ResourcePropertyDefinition {

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var string
	 */
	protected $description;

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var mixed
	 */
	protected $staticValue = NULL;

	/**
	 * @param string $name
	 */
	public function __construct($name) {
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param string $description
	 * @return void
	 */
	public function setDescription($description) {
		$this->description = $description;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @param string $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param mixed $staticValue
	 * @return void
	 */
	public function setStaticValue($staticValue) {
		$this->staticValue = $staticValue;
	}

	/**
	 * @return boolean
	 */
	public function hasStaticValue() {
		return $this->staticValue !== NULL;
	}

	/**
	 * @return mixed
	 */
	public function getStaticValue() {
		return $this->staticValue;
	}

}
?>