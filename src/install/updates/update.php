<?php
use DBA\LikeFilter;
use DBA\StoredValue;
use DBA\Factory;

/*
 * This script should automatically determine the current base function and go through
 * the newer update scripts and run all actions which need to be executed.
 */

require_once(dirname(__FILE__) . "/../../inc/conf.php");
require_once(dirname(__FILE__) . "/../../dba/init.php");
require_once(dirname(__FILE__) . "/../../inc/Util.class.php");

$qF = new LikeFilter(StoredValue::STORED_VALUE_ID, "update_%");
$entries = Factory::getStoredValueFactory()->filter([Factory::FILTER => $qF]);
$PRESENT = [];
foreach($entries as $entry){
  $PRESENT[substr($entry->getId(), 7)] = true;
}

$EXECUTED = [];

// determine which update scripts it needs to consider
$storedVersion = Factory::getStoredValueFactory()->get("version");
$storedBuild = Factory::getStoredValueFactory()->get("build");
$upgradePossible = true;
if($storedVersion == null){
  // we just save the current version and assume that the upgrade was executed up to this version
  $storedVersion = new StoredValue("version", explode("+", $VERSION)[0]);
  Factory::getStoredValueFactory()->save($storedVersion);
  $upgradePossible = false;
}
if($storedBuild == null){
  // we just save the current build and assume that the upgrade was executed up to this build
  $storedBuild = new StoredValue("version", ($BUILD == 'repository')?Util::getGitCommit(true):$BUILD);
  Factory::getStoredValueFactory()->save($storedBuild);
  $upgradePossible = false;
}
if($upgradePossible){ // we can actually check if there are upgrades to be applied
  $allFiles = scandir(dirname(__FILE__));
  foreach($allFiles as $file){
    if(Util::startsWith($file, "update_v")){
      // check version
      $minor = substr($file, 8, strpos($file, "_", 7) - 10);
      if(Util::versionComparison($minor, substr($storedVersion->getVal(), 0, strpos($storedVersion->getVal(), ".", 2))) < 1){
        // script needs to be checked
        include(dirname(__FILE__) . "/" . $file);
      }
    }
  }

  $stores = [];
  foreach($EXECUTED as $key => $val){
    $stores[] = new StoredValue("update_" . $key, "1");
  }
  if(sizeof($stores) > 0){
    Factory::getStoredValueFactory()->massSave($stores);
  }

  // save the new version
  $storedVersion->setVal(explode("+", $VERSION)[0]);
  Factory::getStoredValueFactory()->update($storedVersion);
  $storedBuild->setVal(($BUILD == 'repository')?Util::getGitCommit(true):$BUILD);
  Factory::getStoredValueFactory()->update($storedBuild);
}
