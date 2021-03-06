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
use TYPO3\Flow\Http\Response;
use TYPO3\Flow\Mvc\Exception\NoMatchingRouteException;
use TYPO3\Flow\Mvc\View\AbstractView;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Reflection\ObjectAccess;
use Wwwision\Hal\Domain\Dto\ResourceDefinition;
use Wwwision\Hal\Domain\Dto\ResourceDefinitionFactory;
use Wwwision\Hal\Domain\Dto\ResourceLinkDefinition;
use Wwwision\Hal\Domain\Dto\ResourcePropertyDefinition;
use Wwwision\Hal\Exception;

/**
 * A HAL view
 */
class HalView extends AbstractView {

	/**
	 * @Flow\Inject
	 * @var ResourceDefinitionFactory
	 */
	protected $resourceDefinitionFactory;

	/**
	 * @Flow\Inject
	 * @var PersistenceManagerInterface
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
		$resource = $this->getResource();
		if ($resource === NULL) {
			// TODO throw exception / return empty result?
			return NULL;
		}
		$resourceName = $this->getResourceName();
		$resourceDefinition = $this->resourceDefinitionFactory->createFromResourceName($resourceName);
		$cacheLifetime = $resourceDefinition->getCacheLifetime();
		if ($cacheLifetime !== NULL) {
			$response = $this->controllerContext->getResponse();
			if ($response instanceof Response) {
				$response->getHeaders()->setCacheControlDirective('max-age', $cacheLifetime);
			}
		}
		return (string)$this->buildHalResource($resourceDefinition);
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
		return '';
	}

	/**
	 * @param ResourceDefinition $resourceDefinition
	 * @return Resource
	 * @throws Exception
	 */
	protected function buildHalResource(ResourceDefinition $resourceDefinition) {
		$resource = $this->getResource();
		$halResource = new Resource($this->getResourceUri($resourceDefinition, $resource));

		$data = array();
		if (is_object($resource)) {
			$resourceId = $this->persistenceManager->getIdentifierByObject($resource);
			if ($resourceId !== NULL) {
				$data['id'] = $resourceId;
			}
		}
		/** @var $propertyDefinition ResourcePropertyDefinition */
		foreach ($resourceDefinition->getPropertyDefinitions() as $propertyDefinition) {
			if ($propertyDefinition->hasStaticValue()) {
				$propertyValue = $propertyDefinition->getStaticValue();
			} else {
				$propertyValue = ObjectAccess::getProperty($resource, $propertyDefinition->getName());
			}
			$propertyValue = $this->convertPropertyValue($propertyValue, $propertyDefinition);
			$data[$propertyDefinition->getResourceName()] = $propertyValue;
		}
		$halResource->setData($data);

		if ($resourceDefinition->isCollection()) {
			foreach ($resource as $embeddedResource) {
				$halResource->setEmbedded($resourceDefinition->getLinkName(), $this->createCollectionResource($resourceDefinition, $embeddedResource));
			}
		} else {
			/** @var $embeddedResourceDefinition ResourceDefinition */
			foreach ($resourceDefinition->getEmbeddedResourceDefinitions() as $propertyName => $embeddedResourceDefinition) {
				if ($embeddedResourceDefinition->isCollection()) {
					$embeddedResources = ObjectAccess::getPropertyPath($resource, $propertyName);
					foreach ($embeddedResources as $embeddedResource) {
						$halResource->setEmbedded($embeddedResourceDefinition->getLinkName(), $this->createCollectionResource($embeddedResourceDefinition, $embeddedResource));
					}
				} else {
					$embeddedResource = ObjectAccess::getPropertyPath($resource, $propertyName);
					if ($embeddedResource !== NULL) {
						$halResource->setEmbedded($embeddedResourceDefinition->getLinkName(), $this->createEmbeddedResource($embeddedResourceDefinition, $embeddedResource, $embeddedResourceDefinition->getOptions()), TRUE);
					}
				}
			}
		}

		/** @var $linkDefinition ResourceLinkDefinition */
		foreach ($resourceDefinition->getLinkDefinitions() as $linkDefinition) {
			if ($linkDefinition->hasRouteValues()) {
				try {
					$href = $this->buildUriFromRouteValues($linkDefinition->getRouteValues(), $linkDefinition->isAbsolute());
				} catch (NoMatchingRouteException $exception) {
					throw new Exception(sprintf('Could not create URI for link definition "%s" (resource "%s")', $linkDefinition, $resourceDefinition), 1383496970, $exception);
				}
			} else {
				$href = $this->getResourceUri($linkDefinition->getResourceDefinition(), NULL);
			}
			if ($linkDefinition->hasAppendQueryString()) {
				$href .= $linkDefinition->getAppendQueryString();
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
		return $this->createEmbeddedResource($collectionResourceDefinition, $resource, $resourceDefinition->getOptions());
	}

	/**
	 * @param ResourceDefinition $resourceDefinition
	 * @param object $resource
	 * @param array $options
	 * @return Resource
	 */
	protected function createEmbeddedResource(ResourceDefinition $resourceDefinition, $resource, array $options) {
		$halResource = new Resource($this->getResourceUri($resourceDefinition, $resource));

		$data = array();
		$data['id'] = $this->persistenceManager->getIdentifierByObject($resource);

		$includeProperties = isset($options['includeProperties']) ? $options['includeProperties'] : array();

		/** @var $propertyDefinition ResourcePropertyDefinition */
		foreach ($resourceDefinition->getPropertyDefinitions() as $propertyDefinition) {
			if (!in_array($propertyDefinition->getName(), $includeProperties)) {
				continue;
			}
			if ($propertyDefinition->hasStaticValue()) {
				$data[$propertyDefinition->getResourceName()] = $propertyDefinition->getStaticValue();
			} else {
				$propertyValue = ObjectAccess::getPropertyPath($resource, $propertyDefinition->getName());
				$propertyValue = $this->convertPropertyValue($propertyValue, $propertyDefinition);
				$data[$propertyDefinition->getResourceName()] = $propertyValue;
			}
		}
		$halResource->setData($data);

		return $halResource;
	}

	/**
	 * @param ResourceDefinition $resourceDefinition
	 * @param $resource
	 * @return string
	 * @throws Exception
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

		try {
			return $this->buildUriFromRouteValues($routeValues);
		} catch (NoMatchingRouteException $exception) {
			throw new Exception(sprintf('Could not create URI for Resource "%s"', $resourceDefinition), 1383496975, $exception);
		}
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

		$routeValues = array_map(array($this, 'replacePlaceholders'), $routeValues);

		$uri = $this->controllerContext->getUriBuilder()
			->reset()
			->setCreateAbsoluteUri($absolute)->uriFor($actionName, $routeValues, $controllerName, $packageKey, $subPackageKey);
		return $uri;
	}

	/**
	 * replaces "{placeholder}" by $this->variables['placeholder'] in the given $string
	 *
	 * @param string $string
	 * @return mixed
	 */
	protected function replacePlaceholders($string) {
		if (is_string($string) && strpos($string, '{') === 0) {
			$variableName = substr($string, 1, -1);
			return ObjectAccess::getPropertyPath($this->variables, $variableName);
		}
		return $string;
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

	/**
	 * Converts the given $propertyValue based upon its "type" attribute
	 *
	 * @param mixed $propertyValue
	 * @param ResourcePropertyDefinition $propertyDefinition
	 * @return mixed
	 */
	protected function convertPropertyValue($propertyValue, ResourcePropertyDefinition $propertyDefinition) {
		if (!$propertyDefinition->hasType()) {
			return $propertyValue;
		}
		$propertyType = $propertyDefinition->getType();

		// TODO: support more type conversions
		switch ($propertyType) {
			case 'string':
				if (is_object($propertyValue) && !method_exists($propertyValue, '__toString')) {
					return (string)$this->persistenceManager->getIdentifierByObject($propertyValue);
				}
				return (string)$propertyValue;
		}
		return $propertyValue;
	}

}

?>