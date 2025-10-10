<?php
namespace APISDK\Models;
use APISDK\Models\ModelAbstract;
use APISDK\ApiException;
use APISDK\DbAdapters\DbAdapterInterface;

class Cities extends ModelAbstract implements ModelInterface
{
	
	/**
	 * 
	 * @param \CI_DB_driver $db
	 */
	public function __construct(DbAdapterInterface $dbAdapter)
	{
		$dbAdapter->setDbTable(self::getTablePrefix()."users");
		$this->setDbAdapter($dbAdapter);
	}
	
	public function getCities(){
	    $sQuery = "SELECT * FROM cities
				";
	        
	    return $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	}
	
	public function getCitiesByCountryId(string $id){
	    $sQuery = "SELECT * FROM cities WHERE country_id={$id}
				";
	    
	    return $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	}
	
	public function getCityById(string $id)
	{
	    $sQuery = "SELECT * FROM cities WHERE id ='{$id}'
				";
	    
	    $row = $this->getDbAdapter()
	    ->query($sQuery)
	    ->fetch(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
	    }
	    return false;
	}
	
	/**
	 *
	 * @param array $data
	 * @return array
	 */
	public function update(array $data) {
		return $this->getDbAdapter()->update($data);
	}
	
}