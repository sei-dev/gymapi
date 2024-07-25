<?php
namespace APISDK\DbAdapters;

use APISDK\ApiException;
use CodeIgniter\Database\BaseConnection;

class CIAdapter extends DbAdapterAbstract implements DbAdapterInterface
{
	
	/**
	 * DB Driver
	 * 
	 * @var unknown
	 */
	private $db = null;
	
	/**
	 *
	 * @var string
	 */
	private $dbTable = null;
	
	/**
	 *
	 * @param \CI_DB_driver $db
	 */
	public function __construct(BaseConnection $db)
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
	* Query
	*
	* @param string $sql
	* @return array
	*/
	public function query(string $sql)
	{
		return $this->db->query($sql)->result();
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
			return $this->db->insert_id();
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
		$this->db->where('id', $data['id']);
		unset($data['id']);
		$this->db->update($this->dbTable, $data);
		return $this->db->affected_rows();  
	}
}