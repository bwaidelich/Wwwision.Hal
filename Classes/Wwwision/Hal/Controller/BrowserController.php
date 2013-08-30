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

/**
 * Displays the HAL Browser
 */
class BrowserController extends ActionController {

	/**
	 * @param string $token
	 * @param string $apiRoot
	 * @return void
	 */
	public function indexAction($token = NULL, $apiRoot = NULL) {
		$requestHeaders = isset($this->settings['browser']['defaultRequestHeaders']) ? $this->settings['browser']['defaultRequestHeaders'] : array();
		if ($token !== NULL) {
			$requestHeaders['X-Api-Token'] = $token;
		}

		$this->view->assign('requestHeaders', $requestHeaders);
		$this->view->assign('apiRoot', $apiRoot);
	}

}
?>