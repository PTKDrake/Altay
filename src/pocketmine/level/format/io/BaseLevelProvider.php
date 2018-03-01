<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\level\format\io;

use pocketmine\level\format\Chunk;
use pocketmine\level\generator\Generator;
use pocketmine\level\LevelException;
use pocketmine\math\Vector3;
use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;

abstract class BaseLevelProvider implements LevelProvider{
	/** @var string */
	protected $path;
	/** @var CompoundTag */
	protected $levelData;

	/** @var ChunkProviderThread */
	protected $chunkProvider;


	public function __construct(string $path){
		$this->path = $path;
		if(!file_exists($this->path)){
			mkdir($this->path, 0777, true);
		}

		$this->loadLevelData();
		$this->fixLevelData();

		$this->chunkProvider = $this->createChunkProvider();
		$this->chunkProvider->start();
	}


	protected function loadLevelData() : void{
		$nbt = new BigEndianNBTStream();
		$levelData = $nbt->readCompressed(file_get_contents($this->getPath() . "level.dat"));

		if(!($levelData instanceof CompoundTag) or !$levelData->hasTag("Data", CompoundTag::class)){
			throw new LevelException("Invalid level.dat");
		}

		$this->levelData = $levelData->getCompoundTag("Data");
	}

	protected function fixLevelData() : void{
		if(!$this->levelData->hasTag("generatorName", StringTag::class)){
			$this->levelData->setString("generatorName", (string) Generator::getGenerator("DEFAULT"), true);
		}

		if(!$this->levelData->hasTag("generatorOptions", StringTag::class)){
			$this->levelData->setString("generatorOptions", "");
		}
	}

	abstract protected function createChunkProvider() : ChunkProviderThread;

	public function getPath() : string{
		return $this->path;
	}

	public function getName() : string{
		return $this->levelData->getString("LevelName");
	}

	public function getTime() : int{
		return $this->levelData->getLong("Time", 0, true);
	}

	public function setTime(int $value){
		$this->levelData->setLong("Time", $value, true); //some older PM worlds had this in the wrong format
	}

	public function getSeed() : int{
		return $this->levelData->getLong("RandomSeed");
	}

	public function setSeed(int $value){
		$this->levelData->setLong("RandomSeed", $value);
	}

	public function getSpawn() : Vector3{
		return new Vector3($this->levelData->getInt("SpawnX"), $this->levelData->getInt("SpawnY"), $this->levelData->getInt("SpawnZ"));
	}

	public function setSpawn(Vector3 $pos){
		$this->levelData->setInt("SpawnX", $pos->getFloorX());
		$this->levelData->setInt("SpawnY", $pos->getFloorY());
		$this->levelData->setInt("SpawnZ", $pos->getFloorZ());
	}

	public function doGarbageCollection(){

	}

	/**
	 * @return CompoundTag
	 */
	public function getLevelData() : CompoundTag{
		return $this->levelData;
	}

	public function saveLevelData(){
		$nbt = new BigEndianNBTStream();
		$buffer = $nbt->writeCompressed(new CompoundTag("", [
			$this->levelData
		]));
		file_put_contents($this->getPath() . "level.dat", $buffer);
	}

	public function requestChunkLoad(int $chunkX, int $chunkZ) : void{
		$this->chunkProvider->requestChunkLoad($chunkX, $chunkZ);
	}

	public function getBufferedChunk() : ?Chunk{
		return $this->chunkProvider->readChunkFromBuffer();
	}

	public function hasBufferedChunks() : bool{
		return $this->chunkProvider->hasChunksInBuffer();
	}

	public function requestChunkSave(Chunk $chunk) : void{
		$this->chunkProvider->requestChunkSave($chunk);
	}

	public function close(){
		$this->chunkProvider->quit();
	}
}
