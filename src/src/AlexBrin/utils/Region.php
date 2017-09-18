<?php

namespace AlexBrin\utils;

use AlexBrin\events\RegionCreateEvent;
use AlexBrin\events\RegionDeleteEvent;
use AlexBrin\RegionProtect;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;

/*
 *
 * rgName:
 *     owner: nickname
 *     owners: [nickname1, nickname2]
 *     members: [nickname1, nickname2]
 *     flags:
 *         build: allow/deny
 *         entry: allow/deny
 *         pvp: allow/deny
 *         use: allow/deny
 *         frame: allow/deny
 *         door: allow/deny
 *         chest: allow/deny
 *         cmd: allow/deny
 *         dropItem: allow/deny
 *     coord:
 *         level: levelName
 *         x1: 0
 *         x2: 0
 *         y1: 0
 *         y2: 0
 *         z1: 0
 *         z2: 0
 *
 */
class Region {
    public static $defaultFlags = [
        'pvp' => 'deny',
        'build' => 'deny',
        'entry' => 'allow',
        'use' => 'deny',
        'flame' => 'deny',
        'door' => 'allow',
        'chest' => 'deny',
        'bucket' => 'deny',
        'burn' => 'deny',
        'explode' => 'deny',
        'cmd' => 'allow',
        'dropItem' => 'allow',
    ];

    /* @var string $rgName */
    private $rgName;

    /* @var string $owner */
    private $owner;

    /* @var array $owners */
    private $owners;

    /* @var array $members */
    private $members;

    /* @var array $flags */
    private $flags;

    /* @var Level $level */
    private $level;

    /* @var Vector3 $vector1 */
    private $vector1;

    /* @var Vector3 $vector2 */
    private $vector2;

    public function __construct(string $rgName = null, array $rgInfo = null) {
        if(!$rgName)
            return;

        if(!is_array($rgInfo)) {
            $rgInfo = static::findRegionByRgName($rgName, true);
            if(!$rgInfo)
                return;
        }

        $this->setName($rgInfo['name']);
        $this->setOwner($rgInfo['owner']);
        $this->setOwners($rgInfo['owners'] ?? []);
        $this->setMembers($rgInfo['members'] ?? []);
        $this->setFlags($rgInfo['flags']);
        $this->setLevel($rgInfo['coord']['level']);
        $this->setVectors($rgInfo['coord']);
    }

    /**
     * @param string $rgName
     */
    public function setName(string $rgName) {
        $this->rgName = $rgName;
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->rgName;
    }

    /**
     * @return string
     */
    public function getOwner(): string {
        return $this->owner;
    }

    /**
     * @param string $owner
     */
    public function setOwner($owner) {
        if($owner instanceof Player)
            $owner = $owner->getName();

        $this->owner = mb_strtolower($owner);
    }

    /**
     * @return array
     */
    public function getOwners(): array {
        return $this->owners;
    }

    /**
     * @param  string|Player $player
     * @param  bool          $main [если true - проверит только основного владельца]
     * @return bool
     */
    public function isOwner($player, $main = false) {
        if($player instanceof Player)
            $player = $player->getName();
        $player = mb_strtolower($player);

        if($player == $this->owner)
            return true;

        if(!$main)
            if(in_array($player, $this->owners))
                return true;

        return false;
    }

    /**
     * @param string|Player $owner
     */
    public function addOwner($owner) {
        if($owner instanceof Player)
            $owner = $owner->getName();
        $owner = mb_strtolower($owner);

        if(in_array($owner, $this->owners))
            return;
        $this->owners[] = $owner;
    }

    /**
     * @param string|Player $owner
     */
    public function removeOwner($owner) {
        if($owner instanceof Player)
            $owner = $owner->getName();
        $owner = mb_strtolower($owner);

        if(($key = array_search($owner, $this->owners)) === false)
            return;

        unset($this->owners[$key]);
    }

    /**
     * @param array $owners
     */
    public function setOwners(array $owners) {
        foreach($owners as $key => $value)
            $owners[$key] = mb_strtolower($value);

        $this->owners = $owners;
    }

    /**
     * @return array
     */
    public function getMembers(): array {
        return $this->members;
    }

    /**
     * @param  string|Player $player
     * @return bool
     */
    public function isMember($player) {
        if($player instanceof Player)
            $player = $player->getName();
        $player = mb_strtolower($player);

        return in_array($player, $this->members);
    }

    /**
     * @param Player|string $member
     */
    public function addMember($member) {
        if($member instanceof Player)
            $member = $member->getName();
        $member = mb_strtolower($member);

        if(in_array($member, $this->members))
            return;
        $this->members[] = $member;
    }

    /**
     * @param Player|string $member
     */
    public function removeMember($member) {
        if($member instanceof Player)
            $member = $member->getName();
        $member = mb_strtolower($member);

        if(($key = array_search($member, $this->members)) === false)
            return;

        unset($this->owners[$key]);
    }

    /**
     * @param array $members
     */
    public function setMembers(array $members) {
        $this->members = $members;
    }

    /**
     * @return array
     */
    public function getFlags(): array {
        return $this->flags;
    }

    /**
     * @param  string $flag
     * @return bool true if $flag = 'allow', else false
     */
    public function getFlag($flag): bool {
        if(!isset($this->flags[$flag]))
            return false;

        return $this->flags[$flag] == 'allow' ? true : false;
    }

    /**
     * @param array $flags
     */
    public function setFlags(array $flags) {
        $this->flags = $flags;
    }

    public function updateFlag(string $flag, bool $value) {
        if(!isset($this->flags[$flag]))
            return false;

        $this->flags[$flag] = $value ? 'allow' : 'deny';
        return true;
    }

    /**
     * @param Level|string $levelName
     */
    public function setLevel($levelName) {
        if($levelName instanceof Level)
            $level = $levelName;
        else
            $level = RegionProtect::getInstance()->getServer()->getLevelByName($levelName);
        $this->level = $level;
    }

    /**
     * @return Level
     */
    public function getLevel(): Level {
        return $this->level;
    }

    public function getLevelName(): string {
        return mb_strtolower($this->level->getName());
    }

    /**
     * @param int|array      $x1
     * @param int|null       $x2
     * @param int|null       $y1
     * @param int|null       $y2
     * @param int|null       $z1
     * @param int|null       $z2
     */
    public function setVectors($x1, $x2 = null, int $y1 = null, int $y2 = null, int $z1 = null, int $z2 = null) {
        if(is_array($x1)) {
            $y1 = $x1['y1'];
            $x2 = $x1['x2'];
            $y2 = $x1['y2'];
            $z1 = $x1['z1'];
            $z2 = $x1['z2'];
            $x1 = $x1['x1'];
        }

        $this->vector1 = new Vector3($x1, $y1, $z1);
        $this->vector2 = new Vector3($x2, $y2, $z2);
    }

    /**
     * @param int|Vector3 $x
     * @param int|null    $y
     * @param int|null    $z
     */
    public function setFirstVector($x, int $y = null, int $z = null) {
        if($x instanceof Vector3) {
            $y = $x->y;
            $z = $x->z;
            $x = $x->x;
        }

        $this->vector1 = new Vector3($x, $y, $z);
    }

    /**
     * @param int|Vector3 $x
     * @param int|null    $y
     * @param int|null    $z
     */
    public function setSecondVector($x, int $y = null, int $z = null) {
        if($x instanceof Vector3) {
            $y = $x->y;
            $z = $x->z;
            $x = $x->x;
        }

        $this->vector2 = new Vector3($x, $y, $z);
    }

    public function getFirstPos(): Vector3 {
        return $this->vector1;
    }

    public function getSecondPos(): Vector3 {
        return $this->vector2;
    }

    /**
     * @param Vector3 $pos
     * @return bool
     */
    public function isInRegion(Vector3 $pos): bool {
        $first = $this->getFirstPos(); $second = $this->getSecondPos();
        if(($pos->x >= $first->x && $pos->x <= $second->x) &&
            ($pos->y >= $first->y && $pos->y <= $second->y) &&
            ($pos->z >= $first->z && $pos->z <= $second->z))
            return true;
        return false;
    }

    /**
     * @param  array|Region  $region
     * @param  array|Vector3 $point
     * @return bool
     */
    public static function isPointInRegion($region, $point): bool {
        if($region instanceof Region)
            $region = [
                'x1' => $region->getFirstPos()->x,
                'x2' => $region->getSecondPos()->x,
                'y1' => $region->getFirstPos()->y,
                'y2' => $region->getSecondPos()->y,
                'z1' => $region->getFirstPos()->z,
                'z2' => $region->getSecondPos()->z,
            ];

        if(is_array($point))
            $point = new Vector3($point['x'], $point['y'], $point['z']);

        if(($point->x >= $region['x1'] && $point->x <= $region['x2']) &&
            ($point->y >= $region['y1'] && $point->y <= $region['y2']) &&
            ($point->z >= $region['z1'] && $point->z <= $region['z2']))
            return true;
        return false;
    }

    /**
     * @param array|Region $region1
     * @param array|Region $region2
     * @return bool
     */
    public static function isRegionIntersects($region1, $region2): bool {
        if($region1 instanceof Region)
            $region1 = [
                'x1' => $region1->getFirstPos()->x,
                'x2' => $region1->getSecondPos()->x,
                'y1' => $region1->getFirstPos()->y,
                'y2' => $region1->getSecondPos()->y,
                'z1' => $region1->getFirstPos()->z,
                'z2' => $region1->getSecondPos()->z,
            ];
        if($region2 instanceof Region)
            $region2 = [
                'x1' => $region2->getFirstPos()->x,
                'x2' => $region2->getSecondPos()->x,
                'y1' => $region2->getFirstPos()->y,
                'y2' => $region2->getSecondPos()->y,
                'z1' => $region2->getFirstPos()->z,
                'z2' => $region2->getSecondPos()->z,
            ];

        for($x = $region1['x1']; $x < $region1['x2']; $x++)
            for($y = $region1['y1']; $y < $region1['y2']; $y++)
                for($z = $region1['z1']; $z < $region1['z2']; $z++)
                    if(self::isPointInRegion($region2, new Vector3($x, $y, $z)))
                        return true;
        return false;
    }

    public function save() {
        $regions = RegionProtect::getInstance()->getRegionsObject();
        $_region = [
            'name' => $this->rgName,
            'owner' => $this->owner,
            'owners' => $this->owners,
            'members' => $this->members,
            'flags' => $this->flags,
            'coord' => [
                'level' => $this->getLevelName(),
                'x1' => $this->getFirstPos()->x,
                'x2' => $this->getSecondPos()->x,
                'y1' => $this->getFirstPos()->y,
                'y2' => $this->getSecondPos()->y,
                'z1' => $this->getFirstPos()->z,
                'z2' => $this->getSecondPos()->z,
            ],
        ];
        $regions->setNested("{$this->getLevelName()}.{$this->rgName}", $_region);
        RegionProtect::getInstance()->saveRegions();
    }

    /**
     * @param Player $owner
     */
    public function delete(Player $owner) {
        $ev = new RegionDeleteEvent($owner, $this);
        RegionProtect::getInstance()->getServer()->getPluginManager()->callEvent($ev);
        if($ev->isCancelled())
            return;

        $regions = RegionProtect::getInstance()->getRegionsObject();
        $_regions = $regions->get($this->getLevelName());
        unset($_regions[$this->getName()]);
        $regions->set($this->getLevelName(), $_regions);
        $regions->save();
    }

    /**
     * @param string $name
     * @param Player $owner
     * @param Position $pos1
     * @param Position $pos2
     * @return bool|Region
     */
    public static function create(string $name, Player $owner, Position $pos1, Position $pos2) {
        $region = new Region();
        $region->setLevel($pos1->level);
        $regions = RegionProtect::getInstance()->getRegions()[$region->getLevelName()] ?? [];
        $coord1 = [
            'x1' => $pos1->x,
            'x2' => $pos2->x,
            'y1' => $pos1->y,
            'y2' => $pos2->y,
            'z1' => $pos1->z,
            'z2' => $pos2->z,
        ];
        foreach($regions as $rgInfo) {
            $coord2 = $rgInfo['coord'];
            if(self::isRegionIntersects($coord1, $coord2)) {
                $owner->sendMessage(RegionProtect::getInstance()->getMessage('create.intersects'));
                return;
            }
        }

        $region->setName($name);
        $region->setOwner($owner->getName());
        $region->setFlags(self::$defaultFlags);
        $region->setFirstVector($pos1);
        $region->setSecondVector($pos2);

        $ev = new RegionCreateEvent($owner, $region);
        RegionProtect::getInstance()->getServer()->getPluginManager()->callEvent($ev);
        if($ev->isCancelled())
            return;

        $region->save();
        return $region;
    }

    /**
     * @param  string            $rgName
     * @param  bool              $onlyInfo
     * @return Region|array|null
     */
    public static function findRegionByRgName(string $rgName, $onlyInfo = false) {
        $regions = RegionProtect::getInstance()->getRegions();

        $region = [];
        foreach($regions as $world)
            if(isset($world[$rgName]))
                $region = $world[$rgName];

        if($region == null)
            return null;

        if($onlyInfo)
            return $region;

        return new Region($rgName, $region);
    }

    /**
     * @param Player|string $nickname
     * @return array
     */
    public static function findByOwner($nickname) {
        if($nickname instanceof Player)
            $nickname = $nickname->getName();
        $nickname = mb_strtolower($nickname);

        $regions = [];
        foreach(RegionProtect::getInstance()->getRegions() as $regions)
            foreach($regions as $rgInfo)
                if($rgInfo['owner'] == $nickname)
                    $regions[$rgInfo['name']] = $rgInfo;

        return $regions;
    }

    /**
     * @param  Position    $pos
     * @return Region|null
     */
    public static function findRegionByPos(Position $pos) {
        $lvlName = mb_strtolower($pos->level->getName());
        $regions = RegionProtect::getInstance()->getRegions()[$lvlName] ?? [];
        foreach($regions as $rgName => $rgInfo) {
            $coord = $rgInfo['coord'];
            if($pos->x >= $coord['x1'] && $pos->x <= $coord['x2'] &&
                $pos->y >= $coord['y1'] && $pos->y <= $coord['y2'] &&
                $pos->z >= $coord['z1'] && $pos->z <= $coord['z2'])
                return new Region($rgName, $rgInfo);
        }

        return null;
    }

}