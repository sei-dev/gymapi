<?php
namespace APISDK\DbAdapters;
/**
 * 
 * @author arsenleontijevic
 *
 */
interface DbAdapterInterface {
	
	
	/**
	 * Set table
	 * 
	 * @param string $dbTable
	 */
	public function setDbTable(string $dbTable);

	/**
	 * Execute sql query
	 * 
	 * @param string $sql
	 */
	public function query(string $sql);
	
	/**
	 * Insert new row
	 * 
	 * @param string $table
	 * @param array $data
	 */
	public function insert(array $data);
	
	/**
	 * Update table
	 *
	 * @param string $table
	 * @param array $data
	 */
	public function update(array $data);
};