<?php
namespace Wwwision\Hal\Controller;

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
use TYPO3\Flow\Mvc\Controller\ActionController;
use Wwwision\Hal\Exception;

/**
 */
class DocumentationController extends ActionController {

	/**
	 * @Flow\Inject
	 * @var \Wwwision\Hal\Domain\Dto\ResourceDefinitionFactory
	 */
	protected $resourceDefinitionFactory;

	/**
	 * @param string $apiName
	 * @return void
	 * @throws Exception
	 */
	public function indexAction($apiName) {
		if (!isset($this->settings['apis'][$apiName]['resources'])) {
			throw new Exception(sprintf('There are no resources defined for API "%s"', $apiName));
		}
		$this->view->assign('apiName', $apiName);
		$this->view->assign('resourceDefinitions', $this->settings['apis'][$apiName]['resources']);
	}

	/**
	 * @param string $apiName
	 * @param string $resourceName
	 * @return void
	 * @throws \Exception
	 */
	public function showAction($apiName, $resourceName = NULL) {
		if ($resourceName === NULL) {
			$this->forward('index', NULL, NULL, array('apiName' => $apiName));
		}

		$resourceDefinition = $this->resourceDefinitionFactory->createFromResourceName($resourceName);
		$this->view->assign('apiName', $apiName);
		$this->view->assign('resourceDefinition', $resourceDefinition);
	}

}
?>