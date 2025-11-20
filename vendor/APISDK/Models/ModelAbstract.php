<?php
namespace APISDK\Models;

use APISDK\DbAdapters\DbAdapterAbstract;
use APISDK\DbAdapters\DbAdapterInterface;

/**
 * Model abstract class with some base methods
 * @author arsenleontijevic
 * @since 29.09.2019
 *
 */
abstract class ModelAbstract {


	/**
	 * 
	 * @var DbAdapterInterface
	 */
	private $dbAdapter = null;


/**
 * Set Db Adapter
 * 
 * @param DbAdapterInterface $db
 */
protected function setDbAdapter(DbAdapterInterface $dbAdapter)
{
	$this->dbAdapter = $dbAdapter;
	return $this;
}

/**
 * Get Db Adapter
 * 
 * @param DbAdapterInterface $dbAdapter
 * @return \APISDK\DbAdapterInterface
 */
protected function getDbAdapter()
{
	return $this->dbAdapter;
}

protected function replaceParam(&$request, $key)
{
    $val = $request[$key];
    unset($request[$key]);
    return $val;
}
	


/**
 * 
 * @return string
 */
public static function getTablePrefix()
{
	return "";
}
	
}