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
use TYPO3\Flow\Http\Request;
use TYPO3\Flow\Mvc\ActionRequest;
use TYPO3\Flow\Mvc\Routing\UriBuilder;
use TYPO3\Flow\Reflection\ClassReflection;
use TYPO3\Flow\Utility\Arrays;
use TYPO3\Flow\Utility\TypeHandling;

/**
 * @Flow\Scope("singleton")
 */
class ResourceDefinitionFactory {

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Reflection\ReflectionService
	 */
	protected $reflectionService;

	/**
	 * @var array
	 */
	protected $apiConfiguration;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @var array
	 */
	protected $generatedResourceDefinitions = array();

	/**
	 * @param array $settings
	 * @return void
	 */
	public function injectSettings(array $settings) {
		$this->settings = $settings;
	}

	/**
	 * @return void
	 * @throws \Exception
	 */
	public function initializeObject() {
		if (!isset($this->settings['apis'][$this->getApiName()])) {
			throw new \Exception(sprintf('The API "%s" is not defined', $this->getApiName()), 1374763522);
		}
		$this->apiConfiguration = $this->settings['apis'][$this->getApiName()];
	}

	/**
	 * @return string
	 */
	protected function getApiName() {
			// TODO make configurable (via $options)
		return 'default';
	}

	/**
	 * @param string $className
	 * @return ResourceDefinition
	 * @throws \Exception
	 */
	public function createFromClassName($className) {
		if (!isset($this->apiConfiguration['resources'])) {
			throw new \Exception(sprintf('No resources defined for API "%s"', $this->getApiName()), 1374760304);
		}
		foreach ($this->apiConfiguration['resources'] as $resourceName => $resourceConfiguration) {
			if (!isset($resourceConfiguration['className']) || $resourceConfiguration['className'] !== $className) {
				continue;
			}
			return $this->createFromResourceName($resourceName);
		}
		throw new \Exception(sprintf('No resources defined for class name "%s" for API "%s"', $className, $this->getApiName()), 1374760307);
	}

	/**
	 * @param string $resourceName
	 * @return ResourceDefinition
	 * @throws \Exception
	 */
	public function createFromResourceName($resourceName) {
		if (isset($this->generatedResourceDefinitions[$resourceName])) {
			return $this->generatedResourceDefinitions[$resourceName];
		}
		if (!isset($this->apiConfiguration['resources'][$resourceName])) {
			throw new \Exception(sprintf('Resource "%s" is not defined for API "%s"', $resourceName, $this->getApiName()), 1374759094);
		}
		$commonConfiguration = isset($this->apiConfiguration['commonConfiguration']) ? $this->apiConfiguration['commonConfiguration'] : array();
		$resourceConfiguration = Arrays::arrayMergeRecursiveOverrule($commonConfiguration, $this->apiConfiguration['resources'][$resourceName]);
		$resourceDefinition = new ResourceDefinition($resourceName, $resourceConfiguration);
		$this->generatedResourceDefinitions[$resourceName] = $resourceDefinition;

		// links
		if (isset($resourceConfiguration['links'])) {
			foreach ($resourceConfiguration['links'] as $rel => $linkConfiguration) {
				$linkDefinition = new ResourceLinkDefinition($rel);
				if (isset($linkConfiguration['title'])) {
					$linkDefinition->setTitle($linkConfiguration['title']);
				}
				if (isset($linkConfiguration['name'])) {
					$linkDefinition->setName($linkConfiguration['name']);
				}
				if (isset($linkConfiguration['resource'])) {
					$linkDefinition->setResourceDefinition($this->createFromResourceName($linkConfiguration['resource']));
				}
				if (isset($linkConfiguration['routeValues'])) {
					$linkDefinition->setRouteValues($linkConfiguration['routeValues']);
				}
				if (isset($linkConfiguration['append'])) {
					$linkDefinition->setAppendQueryString($linkConfiguration['append']);
				}
				if (isset($linkConfiguration['absolute'])) {
					$linkDefinition->setAbsolute($linkConfiguration['absolute']);
				}
				if (isset($linkConfiguration['templated'])) {
					$linkDefinition->setTemplated($linkConfiguration['templated']);
				}
				$resourceDefinition->addLinkDefinition($linkDefinition);
			}
		}

		if (isset($resourceConfiguration['aliasFor'])) {
			$resourceDefinition->setAliasFor($this->createFromResourceName($resourceConfiguration['aliasFor']));
			return $resourceDefinition;
		}

		if (isset($resourceConfiguration['collectionOf'])) {
			$resourceDefinition->setCollectionOf($this->createFromResourceName($resourceConfiguration['collectionOf']));
			return $resourceDefinition;
		}

		// class reflection
		if (isset($resourceConfiguration['className'])) {
			$className = $resourceConfiguration['className'];
			$classReflection = new ClassReflection($className);
			$classSchema = $this->reflectionService->getClassSchema($className);
		}

		// description
		if (isset($resourceConfiguration['description'])) {
			$resourceDefinition->setDescription($resourceConfiguration['description']);
		} elseif (isset($resourceConfiguration['className'])) {
			$resourceDefinition->setDescription($classReflection->getDescription());
		}

		// properties
		$propertyNames = $this->getResourcePropertyNames($resourceConfiguration);
		foreach ($propertyNames as $propertyName) {
			$propertyDefinition = new ResourcePropertyDefinition($propertyName);
			$propertyConfiguration = isset($resourceConfiguration['properties'][$propertyName]) ? $resourceConfiguration['properties'][$propertyName] : array();

			// description
			if (isset($propertyConfiguration['description'])) {
				$propertyDefinition->setDescription($propertyConfiguration['description']);
			} elseif (isset($resourceConfiguration['className'])) {
				if ($classReflection->hasProperty($propertyName)) {
					$propertyDefinition->setDescription($classReflection->getProperty($propertyName)->getDescription());
				}
			}

			// static value
			if (isset($propertyConfiguration['staticValue'])) {
				$propertyDefinition->setStaticValue($propertyConfiguration['staticValue']);
			}

			// add property/embedded resource
			if (!isset($resourceConfiguration['className']) || !$classSchema->hasProperty($propertyName)) {
				$resourceDefinition->addPropertyDefinition($propertyDefinition);
			} else {
				$propertyMetadata = $classSchema->getProperty($propertyName);
				$propertyDefinition->setType($propertyMetadata['type']);
				if ($propertyMetadata['type'] === 'DateTime' || TypeHandling::isSimpleType($propertyMetadata['type'])) {
					$resourceDefinition->addPropertyDefinition($propertyDefinition);
				} else {
					$resourceDefinition->addEmbeddedResourceDefinition($this->createFromResourceName($propertyDefinition->getName()));
				}
			}
		}

		return $resourceDefinition;
	}

	/**
	 * @param array $resourceConfiguration
	 * @return array
	 */
	protected function getResourcePropertyNames(array $resourceConfiguration) {
		$propertyNames = array();
		if (isset($resourceConfiguration['className'])) {
			$propertyNames = $this->getGettablePropertyNames($resourceConfiguration['className']);
		}
		if (isset($resourceConfiguration['properties'])) {
			$propertyNames = array_merge($propertyNames, array_keys($resourceConfiguration['properties']));
		}
		if (isset($resourceConfiguration['includeProperties'])) {
			$propertyNames = array_intersect($propertyNames, $resourceConfiguration['includeProperties']);
		}
		if (isset($resourceConfiguration['excludeProperties'])) {
			$propertyNames = array_diff($propertyNames, $resourceConfiguration['excludeProperties']);
		}
		return $propertyNames;
	}

	/**
	 * @param string $className
	 * @return array
	 */
	protected function getGettablePropertyNames($className) {
		$propertyNames = get_class_vars($className);
		foreach (get_class_methods($className) as $methodName) {
			if (is_callable(array($className, $methodName))) {
				if (substr($methodName, 0, 3) === 'get') {
					$propertyNames[] = lcfirst(substr($methodName, 3));
				} elseif (substr($methodName, 0, 2) === 'is') {
					$propertyNames[] = lcfirst(substr($methodName, 2));
				}
			}
		}
		return $propertyNames;
	}


}
?>