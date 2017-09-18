<?php

namespace AlexBrin;

use AlexBrin\commands\rg;
use AlexBrin\utils\EconomyManager;
use AlexBrin\utils\Region;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\utils\Config;

class RegionProtect extends PluginBase implements Listener {
    /* @var RegionProtect $instance */
    public static $instance;

    public $prefix;

  /* @var Config $config */
	private $config;

	/* @var Config $regions */
	private $regions;

	/* @var EconomyManager $eco */
	private $eco;

	public function onEnable() {
		$f = $this->getDataFolder();
		if(!is_dir($f))
			@mkdir($f);

		$this->saveResource('config.yml');
		$this->config = new Config($f.'config.yml', Config::YAML);
        $this->prefix = $this->config->getNested('messages.prefix');
		$this->regions = new Config($f.'regions.json', Config::JSON);
		$this->eco = new EconomyManager($this);

		self::$instance = $this;

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);

        $this->getServer()->getCommandMap()->register('rg', new rg($this, $this->prefix, 'rg', 'Помощь по привату'));
	}

    /**
     * @param int|Position $x
     * @param int|null     $y
     * @param int|null     $z
     * @param string|Level $level
     * @return Region|null
     */
    public function findRegion($x, $y = null, $z = null, $level = null) {
        if(!$x instanceof Position) {
            if(is_string($level))
                $level = $this->getLevelByName($level);
            $pos = new Position(
                $x,
                $y,
                $z,
                $level
            );
        }
        else {
            $pos = $x;
        }

        return Region::findRegionByPos($pos);
    }

    /**
     * @return Config
     */
    public function getRegionsObject(): Config {
        return $this->regions;
    }

    /**
     * @return array
     */
    public function getRegions(): array {
        return $this->regions->getAll();
    }

    /**
     * @return bool
     */
    public function saveRegions(): bool {
        return $this->regions->save();
    }

    public function getEco(): EconomyManager {
        return $this->eco;
    }

    /**
     * @param  string $levelName
     * @return Level
     */
    public function getLevelByName(string $levelName): Level {
        return $this->getServer()->getLevelByName($levelName);
    }

    /**
     * @return RegionProtect
     */
    public static function getInstance(): RegionProtect {
        return self::$instance;
    }

    /**
     * @param  Position|Vector3|array $pos1
     * @param  Position|Vector3|null  $pos2
     * @return float
     */
    public static function calculateSize($pos1, $pos2 = null): float {
        if(is_array($pos1)) {
            $pos2 = $pos1[2];
            $pos1 = $pos1[1];
        }

        $x = sqrt(($pos2->x - $pos1->x) ** 2);
        $y = sqrt(($pos2->y - $pos1->y) ** 2);
        $z = sqrt(($pos2->z - $pos1->z) ** 2);

        return $x * $y * $z;
    }

    /**
     * @param string $node
     * @param array  $params
     * @param bool   $prefix
     * @return string
     */
    public function getMessage($node, $params = [], $prefix = true): string {
        $message = $this->config->getNested("messages.$node");

        $i = 0;
        foreach($params as $value) {
            $message = str_replace("%var$i%", $value, $message);
            $i++;
        }

        if($prefix)
            $message = $this->prefix . $message;

        return $message;
    }

    /**
     * @param  string $node
     * @param  mixed  $default
     * @return mixed
     */
    public function getParam($node, $default = null) {
        return $this->config->getNested("params.$node", $default);
    }

}

?>