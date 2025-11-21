<?php
namespace APISDK\DbAdapters;

use APISDK\ApiException;
use CodeIgniter\Database\BaseConnection;

class DBAdapter extends DbAdapterAbstract implements DbAdapterInterface
{
	
	/**
	 * DB Driver
	 * 
	 * @var unknown
	 */
	public $db = null;
	
	/**
	 *
	 * @var string
	 */
	private $dbTable = null;
	
	/**
	 *
	 * @param \CI_DB_driver $db
	 */
	public function __construct(\Phlib\Db\Adapter $db)
	{
		$this->db = $db;
	}
	
	/**
	 * Set Db Table
	 *
	 * @param DbAdapterInterface $db
	 */
	public function setDbTable(string $dbTable)
	{
		$this->dbTable = $dbTable;
		return $this;
	}
	
	/**
	 * Set Db Table
	 *
	 * @param DbAdapterInterface $db
	 */
	public function getDbTable()
	{
	    return $this->dbTable;
	}
	
	/**
	 * Query
	 *
	 * @param string $sql
	 * @return array
	 */
	public function escape(string $sql)
	{
		return $this->db->escape($sql);
	}

	/**
	 * Quote
	 *
	 * @param $value
	 * @return mixed
	 */
	public function quote($value)
	{
	    return $this->db->getConnection()->quote($value);
	}
	
	/**
	 * Prepare an SQL statement for execution.
	 *
	 * @param string $statement
	 * @return \PDOStatement
	 */
	public function prepare($statement)
	{
	    // the prepare method is emulated by PDO, so no point in detected disconnection
	    return $this->db->prepare($statement);
	}
		
	/**
	* Query
	*
	* @param string $sql
	* @return array
	*/
	public function query(string $sql, array $bind = [])
	{
	    return $this->db->query($sql, $bind);
	}
	
	/**
	 * 
	 * @param string $table
	 * @param array $data
	 * @see \APISDK\DbAdapterInterface::insert()
	 */
	public function insert(array $data)
	{
		$res = $this->db->insert($this->dbTable, $data);
		if($res)
		{
		    return $this->db->lastInsertId();
		}else{
			return false;
		}
	}
		
	/**
	*
	* @param string $table
	* @param array $data
	* @see \APISDK\DbAdapterInterface::insert()
	*/
	public function insertIntoTable($table, array $data)
	{
	    $res = $this->db->insert($table, $data);
	    if($res)
	    {
	        return $this->db->lastInsertId($this->dbTable);
	    }else{
	        return false;
	    }
	}
	
	/**
	 *
	 * @param string $table
	 * @param array $data
	 * @see \APISDK\DbAdapterInterface::insert()
	 */
	public function update(array $data)
	{
		if(!isset($data['id']) || intval($data['id']) < 1)
		{
			throw new ApiException("Update function requires id key in provided data array");
		}
		
		$this->db->where("id", data["id"]);
		unset($data['id']);
		$this->db->update($this->dbTable, $data);
		return $this->db->affected_rows();  
	}
	
	/**
	 * Insert (on duplicate key update) data in table.
	 *
	 * @param string $table
	 * @param array $data
	 * @param array $updateFields
	 * @return int Number of affected rows
	 */
	public function upsert($table, array $data, array $updateFields)
	{
	    if(isset($updateFields["id"])) unset($updateFields["id"]);
	    //Remove id if its 0
	    if(isset($data["id"]) && ($data["id"] === "0" || empty($data["id"]))) unset($data["id"]);
	    
	    $updateFields = array_keys($updateFields);
	    
	    return $this->db->upsert($table, $data, $updateFields);
	}
	
	
	
	public function getLastInsertId($table = null) {
	    $table = is_null($table) ? $this->dbTable : $table;
	    return $this->db->lastInsertId($table);
	}
}