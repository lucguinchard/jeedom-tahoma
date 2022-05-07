<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once __DIR__ . '/../../3rdparty/tahoma.inc.php';
require_once 'tahoma.class.php';

class tahomaCmd extends cmd {
	/*     * *************************Attributs****************************** */

	/*     * ***********************Methode static*************************** */

	/*     * *********************Methode d'instance************************* */

	public function preSave() {

	}

	public function execute($_options = null) {
		//log::add(__CLASS__, 'debug', "execute()");

		$userId = config::byKey('userId', __CLASS__);
		$userPassword = config::byKey('userPassword', __CLASS__);

		$deviceURL = $this->getConfiguration('deviceURL');
		$commandName = $this->getConfiguration('commandName');
		$parameters = $this->getConfiguration('parameters');

		if ($commandName == "execAction") {
			$oid = $deviceURL;

			tahomaExecAction($userId, $userPassword, $oid);

			return;
		}

		if ($this->type == 'action') {
			switch ($this->subType) {
			case 'slider':
				$type = $this->getConfiguration('request');
				$parameters = str_replace('#slider#', $_options['slider'], $parameters);

				$newEventValue = $parameters;

				switch ($type) {
				case 'orientation':
					if ($commandName == "setOrientation") {
						$parameters = array_map('intval', explode(",", $parameters));
						tahomaSendCommand($userId, $userPassword, $deviceURL, $commandName, $parameters, $this->getName());

						$eqLogics = eqLogic::byType(__CLASS__);
						foreach ($eqLogics as $eqLogic) {
							if ($eqLogic->getConfiguration('deviceURL') == $deviceURL) {
								foreach ($eqLogic->getCmd() as $command) {
									if ($command->getType() == 'info') {
										if ($command->getName() == "core:SlateOrientationState") {
											$command->setCollectDate('');
											$command->event($newEventValue);
										}
									}
								}
							}
						}

						return;
					}
				case 'closure':
					if ($commandName == "setClosure") {
						$parameters = 100 - $parameters;

						$parameters = array_map('intval', explode(",", $parameters));
						tahomaSendCommand($userId, $userPassword, $deviceURL, $commandName, $parameters, $this->getName());

						$eqLogics = eqLogic::byType(__CLASS__);
						foreach ($eqLogics as $eqLogic) {
							if ($eqLogic->getConfiguration('deviceURL') == $deviceURL) {
								foreach ($eqLogic->getCmd() as $command) {
									if ($command->getType() == 'info') {
										if ($command->getName() == "core:ClosureState") {
											$command->setCollectDate('');
											$command->event($newEventValue);
										}
									}
								}
							}
						}

						return;
					}
					break;
				}
				case 'select':
					if ($commandName == 'setLockedUnlocked'){
						$parameters = str_replace('#select#', $_options['select'], $parameters);
					}
				break;
			}

			if ($this->getConfiguration('nparams') == 0) {
				$parameters = "";
			} else if ($commandName == "setClosure") {
				$parameters = array_map('intval', explode(",", $parameters));
			} else {
				$parameters = explode(",", $parameters);
			}

			if ($commandName == "cancelExecutions") {
				$execId = $parameters[0];

				log::add(__CLASS__, 'debug', "will cancelExecutions: (" . $execId . ") from tahoma.class");

				tahomaCancelExecutions($userId, $userPassword, $execId);
			} else {
				$execId = tahomaSendCommand($userId, $userPassword, $deviceURL, $commandName, $parameters, $this->getName());

				log::add(__CLASS__, 'debug', "return cancelExecutions: (" . $execId . ")");

				$eqLogics = eqLogic::byType(__CLASS__);
				foreach ($eqLogics as $eqLogic) {
					if ($eqLogic->getConfiguration('deviceURL') == $deviceURL) {
						foreach ($eqLogic->getCmd() as $command) {
							if ($command->getConfiguration('commandName') == "cancelExecutions") {
								log::add(__CLASS__, 'debug', "set cancelExecutions: (" . $execId . ")");

								$command->setConfiguration('parameters', $execId);
								$command->save();

							}
						}
					}
				}
			}
			// Rafraichissement des valeurs après actions
			if ($commandName == 'setSecuredPositionTemperature'
				|| $commandName == 'setEcoTemperature'
				|| $commandName == 'setComfortTemperature'
				|| $commandName == 'setManuAndSetPointModes'
				|| $commandName == 'setHeatingLevel'
				|| $commandName == 'setActiveMode'
				|| $commandName == 'setOnOff'
				|| $commandName == 'lock'
				|| $commandName == 'unlock'
				|| $commandName == 'setLockedUnlocked') {
				sleep(5);
				tahoma::syncEqLogicWithRazberry();
			}

			return;
		}

		if ($this->type == 'info') {

			return;
		}

	}

}