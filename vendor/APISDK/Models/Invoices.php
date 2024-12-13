<?php
namespace APISDK\Models;
use APISDK\Models\ModelAbstract;
use APISDK\ApiException;
use APISDK\DbAdapters\DbAdapterInterface;

class Invoices extends ModelAbstract implements ModelInterface
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
	
	public function getInvoiceItems(){
	    $sQuery = "SELECT * FROM invoice_items;
				";
	        
	    return $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	}
	
	public function getMonthlyItem(){
	    $sQuery = "SELECT * FROM invoice_items WHERE id = '1';
				";
	    
	    $row = $this->getDbAdapter()
	    ->query($sQuery)
	    ->fetch(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
	    }
	    return false;
	}
	
	public function getYearlyItem(){
	    $sQuery = "SELECT * FROM invoice_items WHERE id = '2';
				";
	    
	    $row = $this->getDbAdapter()
	    ->query($sQuery)
	    ->fetch(\PDO::FETCH_ASSOC);
	    if (isset($row)) {
	        return $row;
	    }
	    return false;
	}
	
	
	public function addInvoiceMonthly(string $trainer_id, string $valid_until){
	    $sQuery = "INSERT INTO invoices (trainer_id, item_id, valid_until)
	    VALUES ({'$trainer_id'}, 1, '$valid_until'});
				";
	    
	    return $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	}
	
	public function addInvoiceYearly(string $trainer_id, string $valid_until){
	    $sQuery = "INSERT INTO invoices (trainer_id, item_id, valid_until)
	    VALUES ({'$trainer_id'}, 1, '$valid_until');
				";
	    
	    return $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
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