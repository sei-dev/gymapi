<?php
namespace APISDK\Models;
use APISDK\Models\ModelAbstract;
use APISDK\ApiException;
use APISDK\DbAdapters\DbAdapterInterface;

class PaymentCallbacks extends ModelAbstract implements ModelInterface
{
	
	/**
	 * 
	 * @param \CI_DB_driver $db
	 */
	public function __construct(DbAdapterInterface $dbAdapter)
	{
		$dbAdapter->setDbTable(self::getTablePrefix()."payment_callback");
		$this->setDbAdapter($dbAdapter);
	}
	
	public function getItemByMerchantTransactionId(string $merchant_transaction_id) {
	    // Escape the value properly
	    $escapedId = $this->getDbAdapter()->quote($merchant_transaction_id);
	    
	    $sQuery = "SELECT json_result FROM payment_callback WHERE merchant_transaction_id = {$escapedId} LIMIT 1";
	    
	    $result = $this->getDbAdapter()->query($sQuery)->fetchAll(\PDO::FETCH_ASSOC);
	    return !empty($result) ? $result[0]['json_result'] : null;
	}
	
	public function insertItem(string $merchant_transaction_id, string $json){
	    $sQuery = "INSERT INTO payment_callback (merchant_transaction_id, json_result)
	    VALUES ('$merchant_transaction_id', '$json')";
	    
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