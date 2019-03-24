<?php

/**
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace SkyWars\arena;

use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\scheduler\Task;
use pocketmine\tile\Sign;
use skywars\math\Time;
use skywars\math\Vector3;

/**
 * Class ArenaScheduler
 * @package skywars\arena
 */
class ArenaScheduler extends Task {

    /** @var Arena $plugin */
    protected $plugin;

    /** @var int $startTime */
    public $startTime = 40;

    /** @var float|int $gameTime */
    public $gameTime = 20 * 60;

    /** @var int $restartTime */
    public $restartTime = 10;

    /** @var array $restartData */
    public $restartData = [];

    /**
     * ArenaScheduler constructor.
     * @param Arena $plugin
     */
    public function __construct(Arena $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick) {
        $this->reloadSign();

        if($this->plugin->setup) return;

        switch ($this->plugin->phase) {
            case Arena::PHASE_LOBBY:
                if(count($this->plugin->players) >= 2) {
                    $this->plugin->broadcastMessage("§a> Start " . Time::calculateTime($this->startTime) . " seconds left.", Arena::MSG_TIP);
                    $this->startTime--;
                    if($this->startTime == 0) {
                        $this->plugin->startGame();
                    }
                }
                else {
                    $this->plugin->broadcastMessage("§c> Requires Player To Start Game!", Arena::MSG_TIP);
                    $this->startTime = 40;
                }
                break;
            case Arena::PHASE_GAME:
                $this->plugin->broadcastMessage("§4> Active Players " . count($this->plugin->players) . ", End of Game: " . Time::calculateTime($this->gameTime) . "", Arena::MSG_TIP);
                switch ($this->gameTime) {
                    case 15 * 60:
                        $this->plugin->broadcastMessage("§4> 5min for the Renewal of All Crates");
                        break;
                    case 11 * 60:
                        $this->plugin->broadcastMessage("§4> 1min for the Renewal of All Crates.");
                        break;
                    case 10 * 60:
                        $this->plugin->broadcastMessage("§4> All Chests Are Renewed.");
                        break;
                }
                if($this->plugin->checkEnd()) $this->plugin->startRestart();
                $this->gameTime--;
                break;
            case Arena::PHASE_RESTART:
                $this->plugin->broadcastMessage("§4> Renewal {$this->restartTime} Seconds.", Arena::MSG_TIP);
                $this->restartTime--;

                switch ($this->restartTime) {
                    case 0:

                        foreach ($this->plugin->players as $player) {
                            $player->teleport($this->plugin->plugin->getServer()->getDefaultLevel()->getSpawnLocation());

                            $player->getInventory()->clearAll();
                            $player->getArmorInventory()->clearAll();
                            $player->getCursorInventory()->clearAll();

                            $player->setFood(20);
                            $player->setHealth(40);

                            $player->setGamemode($this->plugin->plugin->getServer()->getDefaultGamemode());
                        }
                        $this->plugin->loadArena(true);
                        $this->reloadTimer();
                        break;
                }
                break;
        }
    }

    public function reloadSign() {
        if(!is_array($this->plugin->data["joinsign"]) || empty($this->plugin->data["joinsign"])) return;

        $signPos = Position::fromObject(Vector3::fromString($this->plugin->data["joinsign"][0]), $this->plugin->plugin->getServer()->getLevelByName($this->plugin->data["joinsign"][1]));

        if(!$signPos->getLevel() instanceof Level) return;

        $signText = [
            "§eSkyWars",
            "§6[ §6? / ? §6]",
            "§4test.",
            "§4A little Beke..."
        ];

        if($signPos->getLevel()->getTile($signPos) === null) return;

        if($this->plugin->setup) {
            /** @var Sign $sign */
            $sign = $signPos->getLevel()->getTile($signPos);
            $sign->setText($signText[0], $signText[1], $signText[2], $signText[3]);
            return;
        }

        $signText[1] = "§9[ §b" . count($this->plugin->players) . " / " . $this->plugin->data["slots"] . " §9]";

        switch ($this->plugin->phase) {
            case Arena::PHASE_LOBBY:
                if(count($this->plugin->players) >= $this->plugin->data["slots"]) {
                    $signText[2] = "§4Full";
                    $signText[3] = "§8Harita: §4{$this->plugin->level->getFolderName()}";
                }
                else {
                    $signText[2] = "§eKiller";
                    $signText[3] = "§4Map: §4{$this->plugin->level->getFolderName()}";
                }
                break;
            case Arena::PHASE_GAME:
                $signText[2] = "§4Oyunda";
                $signText[3] = "§8Harita: §7{$this->plugin->level->getFolderName()}";
                break;
            case Arena::PHASE_RESTART:
                $signText[2] = "§4refreshing...";
                $signText[3] = "§8Harita: §7{$this->plugin->level->getFolderName()}";
                break;
        }

        /** @var Sign $sign */
        $sign = $signPos->getLevel()->getTile($signPos);
        $sign->setText($signText[0], $signText[1], $signText[2], $signText[3]);
    }

    public function reloadTimer() {
        $this->startTime = 30;
        $this->gameTime = 20 * 60;
        $this->restartTime = 10;
    }
}
