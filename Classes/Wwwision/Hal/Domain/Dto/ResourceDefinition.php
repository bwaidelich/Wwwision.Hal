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
use TYPO3\Flow\Utility\Arrays;

/**
 */
class ResourceDefinition {

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var array
	 */
	protected $options;

	/**
	 * @var string
	 */
	protected $description;

	/**
	 * @var ResourceDefinition
	 */
	protected $aliasFor = NULL;

	/**
	 * @var ResourceDefinition
	 */
	protected $collectionOf = NULL;

	/**
	 * @var array<ResourcePropertyDefinition>
	 */
	protected $propertyDefinitions = array();

	/**
	 * @var array<ResourceLinkDefinition>
	 */
	protected $linkDefinitions = array();

	/**
	 * @var array<ResourceDefinition>
	 */
	protected $embeddedResourceDefinitions = array();

	/**
	 * @param string $name
	 * @param array $options
	 */
	public function __construct($name, array $options) {
		$this->name = $name;
		$this->options = $options;
	}

	/**
	 * @param ResourceDefinition $aliasFor
	 * @return void
	 */
	public function setAliasFor(ResourceDefinition $aliasFor) {
		$this->aliasFor = $aliasFor;
	}

	/**
	 * @return ResourceDefinition
	 */
	public function getAliasFor() {
		return $this->aliasFor;
	}

	/**
	 * @return boolean
	 */
	public function isAlias() {
		return $this->aliasFor !== NULL;
	}

	/**
	 * @param ResourceDefinition $collectionOf
	 * @return void
	 */
	public function setCollectionOf(ResourceDefinition $collectionOf) {
		$this->collectionOf = $collectionOf;
	}

	/**
	 * @return ResourceDefinition
	 */
	public function getCollectionOf() {
		if ($this->isAlias()) {
			return $this->aliasFor->getCollectionOf();
		}
		return $this->collectionOf;
	}

	/**
	 * @return boolean
	 */
	public function isCollection() {
		if ($this->isAlias()) {
			return $this->aliasFor->isCollection();
		}
		return $this->collectionOf !== NULL;
	}

	/**
	 * @return array
	 */
	public function getOptions() {
		if ($this->isCollection()) {
			return $this->mergeOptions($this->collectionOf->getOptions());
		} elseif ($this->isAlias()) {
			return $this->mergeOptions($this->aliasFor->getOptions());
		}
		return $this->options;
	}

	/**
	 * @param array $baseOptions
	 * @return array
	 */
	protected function mergeOptions(array $baseOptions) {
		$mergedOptions = Arrays::arrayMergeRecursiveOverrule($baseOptions, $this->options);
		// the "includeProperties" should not be merged
		if (isset($this->options['includeProperties'])) {
			$mergedOptions['includeProperties'] = $this->options['includeProperties'];
		}
		return $mergedOptions;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * returns the name of this resource, prefixed with the "linkNamespace" if any
	 *
	 * @return string
	 */
	public function getLinkName() {
		$name = $this->getName();
		if (isset($this->options['linkNamespace'])) {
			$name = $this->options['linkNamespace'] . ':' . $name;
		}
		return $name;
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
	 * @param ResourcePropertyDefinition $propertyDefinition
	 * @return void
	 */
	public function addPropertyDefinition(ResourcePropertyDefinition $propertyDefinition) {
		$this->propertyDefinitions[$propertyDefinition->getName()] = $propertyDefinition;
	}

	/**
	 * @return array<ResourcePropertyDefinition>
	 */
	public function getPropertyDefinitions() {
		return $this->propertyDefinitions;
	}

	/**
	 * @param ResourceLinkDefinition $linkDefinition
	 * @return void
	 */
	public function addLinkDefinition(ResourceLinkDefinition $linkDefinition) {
		$this->linkDefinitions[$linkDefinition->getRel()] = $linkDefinition;
	}

	/**
	 * @return array
	 */
	public function getLinkDefinitions() {
		return $this->linkDefinitions;
	}

	/**
	 * @param ResourceDefinition $embeddedResourceDefinition
	 * @param string $propertyName optional property name, if NULL the property name is expected to be the same as the name of given $embeddedResourceDefinition
	 * @return void
	 */
	public function addEmbeddedResourceDefinition(ResourceDefinition $embeddedResourceDefinition, $propertyName = NULL) {
		if ($propertyName === NULL) {
			$propertyName = $embeddedResourceDefinition->getName();
		}
		$this->embeddedResourceDefinitions[$propertyName] = $embeddedResourceDefinition;
	}

	/**
	 * @return array<ResourceDefinition>
	 */
	public function getEmbeddedResourceDefinitions() {
		return $this->embeddedResourceDefinitions;
	}

	/**
	 * @return string
	 */
	public function __toString() {
		$name = $this->getName();
		if ($this->isAlias()) {
			$name = sprintf('%s (alias for "%")', $name, $this->getAliasFor());
		}
		return $name;
	}
}
?>