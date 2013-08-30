<?php
namespace Wwwision\Hal\View;

/*                                                                        *
 * This script belongs to the Flow package "Wwwision.Hal".                *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Hal\Link;
use Hal\Resource;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Mvc\ActionRequest;
use TYPO3\Flow\Mvc\View\AbstractView;
use TYPO3\Flow\Reflection\ObjectAccess;
use Wwwision\Hal\Domain\Dto\ResourceDefinition;
use Wwwision\Hal\Domain\Dto\ResourceLinkDefinition;
use Wwwision\Hal\Domain\Dto\ResourcePropertyDefinition;

/**
 * A HAL view
 */
class HalView extends AbstractView {

	/**
	 * @Flow\Inject
	 * @var \Wwwision\Hal\Domain\Dto\ResourceDefinitionFactory
	 */
	protected $resourceDefinitionFactory;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Persistence\PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @param array $settings
	 * @return void
	 */
	public function injectSettings(array $settings) {
		$this->settings = $settings;
	}

	/**
	 * Renders the view
	 *
	 * @return string The rendered view
	 */
	public function render() {
		return (string)$this->buildHalResource();
	}

	/**
	 * @return mixed
	 */
	protected function getResource() {
		$resourceName = $this->getResourceName();
		return isset($this->variables[$resourceName]) ? $this->variables[$resourceName] : NULL;
	}

	/**
	 * @return string
	 */
	protected function getResourceName() {
		$variables = $this->variables;
		foreach ($variables as $variableName => $variableValue) {
			if ($variableName === 'settings' || substr($variableName, 0, 1) === '_') {
				continue;
			}
			return $variableName;
		}
	}

	/**
	 * @return Resource
	 */
	protected function buildHalResource() {
		$resource = $this->getResource();
		$resourceName = $this->getResourceName();
		$resourceDefinition = $this->resourceDefinitionFactory->createFromResourceName($resourceName);
		$halResource = new Resource($this->getResourceUri($resourceDefinition, $resource));

		$data = array();
		/** @var $propertyDefinition ResourcePropertyDefinition */
		foreach ($resourceDefinition->getPropertyDefinitions() as $propertyDefinition) {
			if ($propertyDefinition->hasStaticValue()) {
				$data[$propertyDefinition->getName()] = $propertyDefinition->getStaticValue();
			} else {
				$data[$propertyDefinition->getName()] = ObjectAccess::getProperty($resource, $propertyDefinition->getName());
			}
		}
		$halResource->setData($data);

		if ($resourceDefinition->isCollection()) {
			foreach ($resource as $embeddedResource) {
				$embeddedResourceDefinition = $resourceDefinition->getCollectionOf();
				$halResource->setEmbedded($resourceDefinition->getLinkName(), $this->createCollectionResource($resourceDefinition, $embeddedResource));
			}
		} else {
			/** @var $embeddedResourceDefinition ResourceDefinition */
			foreach ($resourceDefinition->getEmbeddedResourceDefinitions() as $embeddedResourceDefinition) {
				if ($embeddedResourceDefinition->isCollection()) {
					$embeddedResources = ObjectAccess::getProperty($resource, $embeddedResourceDefinition->getName());
					foreach ($embeddedResources as $embeddedResource) {
						$halResource->setEmbedded($embeddedResourceDefinition->getLinkName(), $this->createCollectionResource($embeddedResourceDefinition, $embeddedResource));
					}
				} else {
					$embeddedResource = ObjectAccess::getProperty($resource, $embeddedResourceDefinition->getName());
					if ($embeddedResource !== NULL) {
						$halResource->setEmbedded($embeddedResourceDefinition->getLinkName(), $this->createEmbeddedResource($embeddedResourceDefinition, $embeddedResource));
					}
				}
			}
		}

		/** @var $linkDefinition ResourceLinkDefinition */
		foreach ($resourceDefinition->getLinkDefinitions() as $linkDefinition) {
			if ($linkDefinition->hasRouteValues()) {
				$href = $this->buildUriFromRouteValues($linkDefinition->getRouteValues(), $linkDefinition->isAbsolute());
			} else {
				$href = $this->getResourceUri($linkDefinition->getResourceDefinition(), NULL);
			}
			if ($linkDefinition->hasAppendQueryString()) {
				$href .= strpos($href, '?') !== FALSE ? '&' . $linkDefinition->getAppendQueryString() : '?' . $linkDefinition->getAppendQueryString();
			}
			$halResource->setLink(new Link($href, $linkDefinition->getRel(), $linkDefinition->getTitle(), $linkDefinition->getName(), NULL, $linkDefinition->isTemplated()));
		}

		return $halResource;
	}

	/**
	 * @param ResourceDefinition $resourceDefinition
	 * @param $resource
	 * @return Resource
	 */
	protected function createCollectionResource(ResourceDefinition $resourceDefinition, $resource) {
		$collectionResourceDefinition = $resourceDefinition->getCollectionOf();
		return $this->createEmbeddedResource($collectionResourceDefinition, $resource);
	}

	/**
	 * @param ResourceDefinition $resourceDefinition
	 * @param $resource
	 * @return Resource
	 */
	protected function createEmbeddedResource(ResourceDefinition $resourceDefinition, $resource) {
		$resourceOptions = $resourceDefinition->getOptions();
		$halResource = new Resource($this->getResourceUri($resourceDefinition, $resource));

		$data = array();
		$data['id'] = $this->persistenceManager->getIdentifierByObject($resource);

		$includeProperties = isset($resourceOptions['includeProperties']) ? $resourceOptions['includeProperties'] : array();

		/** @var $propertyDefinition ResourcePropertyDefinition */
		foreach ($resourceDefinition->getPropertyDefinitions() as $propertyDefinition) {
			if (!in_array($propertyDefinition->getName(), $includeProperties)) {
				continue;
			}
			if ($propertyDefinition->hasStaticValue()) {
				$data[$propertyDefinition->getName()] = $propertyDefinition->getStaticValue();
			} else {
				$data[$propertyDefinition->getName()] = ObjectAccess::getProperty($resource, $propertyDefinition->getName());
			}
		}
		$halResource->setData($data);

		return $halResource;
	}

	/**
	 * @param ResourceDefinition $resourceDefinition
	 * @param $resource
	 * @return string
	 */
	protected function getResourceUri(ResourceDefinition $resourceDefinition, $resource) {
		$resourceConfiguration = $resourceDefinition->getOptions();
		if (!isset($resourceConfiguration['routeValues'])) {
			return NULL;
		}
		$routeValues = $resourceConfiguration['routeValues'];

		// FIXME is_array
		if (!$resourceDefinition->isCollection() && !is_array($resource)) {
			$resourceName = $resourceDefinition->isAlias() ? $resourceDefinition->getAliasFor()->getName() : $resourceDefinition->getName();
			$routeValues[$resourceName] = $resource;
		}

		return $this->buildUriFromRouteValues($routeValues);
	}

	/**
	 * @param array $routeValues
	 * @param boolean $absolute whether or not the URI should be absolute
	 * @return string
	 */
	protected function buildUriFromRouteValues(array $routeValues, $absolute = FALSE) {
		$actionName = $this->extractRouteValue($routeValues, '@action');
		$controllerName = $this->extractRouteValue($routeValues, '@controller');
		$packageKey = $this->extractRouteValue($routeValues, '@package');
		$subPackageKey = $this->extractRouteValue($routeValues, '@subpackage');

		$uri = '';
		if (!$absolute) {
			// FIXME this is currently needed for the HAL browser..
			$uri .= '/';
		}
		$uri .= $this->controllerContext->getUriBuilder()
			->reset()
			->setCreateAbsoluteUri($absolute)->uriFor($actionName, $routeValues, $controllerName, $packageKey, $subPackageKey);
		return $uri;
	}

	/**
	 * Returns the entry $key from the array $routeValues removing the original array item.
	 * If $key does not exist, NULL is returned.
	 *
	 * @param array $routeValues
	 * @param string $key
	 * @return mixed the specified route value or NULL if it is not set
	 */
	protected function extractRouteValue(array &$routeValues, $key) {
		if (!isset($routeValues[$key])) {
			return NULL;
		}
		$routeValue = $routeValues[$key];
		unset($routeValues[$key]);
		return $routeValue;
	}

}

?>