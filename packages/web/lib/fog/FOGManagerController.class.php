<?php
/** \class FOGManagerController
	Used by the Manager Class files as the base
	for these files.
*/
abstract class FOGManagerController extends FOGBase
{
	// Table
	/** Sets the table the manager class needs to look for
		it's elements.
	*/
	public $databaseTable = '';
	// Search query
	/** Sets the search query to pull information.
		Alternate is find().
	*/
	public $searchQuery = '';
	// Child class name variables
	/** For the child class */
	protected $childClass;
	/** The variables of the class */
	protected $classVariables;
	/** The database fields. */
	protected $databaseFields;
	/** The database to class relationships extra data, but not needed **/
	protected $databaseFieldClassRelationships;
	/** Needed external table items **/
	protected $databaseNeededFieldClassRelationships;
	/** Searchable class elements **/
	protected $databaseSearchFieldClassRelationships;
	/** Search Query Template **/
	private $searchQueryTemplate = "SELECT `%s` FROM %s %s %s";
	// Construct
	/** __construct()
		Different constructor from FOG Base
	*/
	public function __construct()
	{
		// FOGBase contstructor
		parent::__construct();
		// Set child classes name
		$this->childClass = preg_replace('#_?Manager$#', '', get_class($this));
		// Get child class variables
		$this->classVariables = get_class_vars($this->childClass);
		// Set required child variable data
		$this->databaseTable = $this->classVariables['databaseTable'];
		$this->databaseFields = $this->classVariables['databaseFields'];
		$this->databaseFieldsFlipped = array_flip($this->databaseFields);
		$this->databaseFieldClassRelationships = $this->classVariables['databaseFieldClassRelationships'];
		$this->databaseNeededFieldClassRelationships = $this->classVariables['databaseNeededFieldClassRelationships'];
		$this->databaseSearchFieldClassRelationships = $this->classVariables['databaseSearchFieldClassRelationships'];
	}
	public function __destruct()
	{
		parent::__destruct();
	}
	// Search
	public function search($keyword = null)
	{
		try
		{
			if (empty($keyword))
				$keyword = $_REQUEST['crit'];
			$keyword = preg_replace('#%+#', '%', '%'.preg_replace('#[[:space:]]#', '%', $keyword).'%');
			$_SESSION['caller'] = __FUNCTION__;
			if (empty($keyword))
				throw new Exception('No keyword passed');
			foreach((array)$this->databaseFields AS $common => $dbField)
			{
				if ($common != 'createdBy')
					$findWhere[$common] = $keyword;
			}
			if ($this->classClass == 'User')
				return $this->getClass('UserManager')->find($findWhere,'OR');
			$HostIDs = ($this->childClass == 'Host' ? $this->getClass('HostManager')->find($findWhere,'OR','','','','','','id') : $this->getClass('HostManager')->find(array('name' => $keyword,'description' => $keyword,'ip' => $keyword),'OR','','','','','','id'));
			// Get all the hosts host search is different than other searches
			$MACHosts = $this->getClass('MACAddressAssociationManager')->find(array('mac' => $keyword,'description' => $keyword),'OR','','','','','','hostID');
			$InventoryHosts = $this->getClass('InventoryManager')->find(array('sysserial' => $keyword,'caseserial' => $keyword,'mbserial' => $keyword,'primaryUser' => $keyword,'other1' => $keyword,'other2' => $keyword,'sysman' => $keyword,'sysproduct' => $keyword),'OR','','','','','','hostID');
			$HostIDs = array_unique(array_merge((array)$HostIDs,(array)$MACHosts,(array)$InventoryHosts));
			// Get the IDs of the objects we are trying to "scan" for
			if ($this->childClass == 'Host')
			{
				$ImageIDs = $this->getClass('ImageManager')->find(array('name' => $keyword,'description' => $keyword),'OR','','','','','','id');
				$GroupIDs = $this->getClass('GroupManager')->find(array('name' => $keyword,'description' => $keyword),'OR','','','','','','id');
				$SnapinIDs = $this->getClass('SnapinManager')->find(array('name' => $keyword,'description' => $keyword,'file' => $keyword),'OR','','','','','','id');
				$PrinterIDs = $this->getClass('PrinterManager')->find(array('name' => $keyword),'OR','','','','','','id');
				//$ImageHostIDs = $this->getClass('HostManager')->find(array('imageID' => $ImageIDs),'','','','','','','id');
				$GroupHostIDs = $this->getClass('GroupAssociationManager')->find(array('groupID' => $GroupIDs),'','','','','','','hostID');
				$SnapinHostIDs = $this->getClass('SnapinAssociationManager')->find(array('snapinID' => $SnapinIDs),'','','','','','','hostID');
				$PrinterHostIDs = $this->getClass('PrinterAssociationManager')->find(array('printerID' => $PrinterIDs),'','','','','','','hostID');
				$HostIDs = array_unique(array_merge((array)$HostIDs,(array)$GroupHostIDs,(array)$ImageHostIDs,(array)$SnapinHostIDs,(array)$PrinterHostIDs));
				$findWhere = array('id' => $HostIDs);
				unset($GroupIDs,$ImageIDs,$SnapinIDs,$PrinterIDs,$GroupHostIDs,$ImageHostIDs,$SnapinHostIDs,$PrinterHostIDs,$HostIDs);
			}
			else if ($this->childClass == 'Group')
			{
				$GroupIDs = $this->getClass('GroupManager')->find($findWhere,'OR','','','','','','id');
				$GroupHostIDs = $this->getClass('GroupAssociationManager')->find(array('hostID' => $HostIDs),'','','','','','','groupID');
				$GroupIDs = array_unique(array_merge((array)$GroupIDs,(array)$GroupHostIDs));
				$findWhere = array('id' => $GroupIDs);
				unset($GroupIDs,$GroupHostIDs,$HostIDs);
			}
			else if ($this->childClass == 'Image')
			{
				$ImageIDs = $this->getClass('ImageManager')->find($findWhere,'OR','','','','','','id');
				$ImageHostIDs = $this->getClass('HostManager')->find(array('id' => $HostIDs),'','','','','','','imageID');
				$ImageIDs = array_unique(array_merge((array)$ImageIDs,(array)$ImageHostIDs));
				$findWhere = array('id' => $ImageIDs);
				unset($ImageIDs,$ImageHostIDs,$HostIDs);
			}
			else if ($this->childClass == 'Snapin')
			{
				$SnapinIDs = $this->getClass('SnapinManager')->find($findWhere,'OR','','','','','','id');
				$SnapinHostIDs = $this->getClass('SnapinAssociationManager')->find(array('hostID' => $HostIDs),'','','','','','','snapinID');
				$SnapinIDs = array_unique(array_merge((array)$SnapinIDs,(array)$SnapinHostIDs));
				$findWhere = array('id' => $SnapinIDs);
				unset($SnapinIDs,$SnapinHostIDs,$HostIDs);
			}
			else if ($this->childClass == 'Printer')
			{
				$PrinterIDs = $this->getClass('PrinterManager')->find($findWhere,'OR','','','','','','id');
				$PrinterHostIDs = $this->getClass('PrinterAssociationManager')->find(array('hostID' => $HostIDs),'','','','','','','printerID');
				$PrinterIDs = array_unique(array_merge((array)$PrinterIDs,(array)$PrinterHostIDs));
				$findWhere = array('id' => $PrinterIDs);
				unset($PrinterIDs,$PrinterHostIDs,$HostIDs);
			}
			else if ($this->childClass == 'Task')
			{
				$TaskIDs = $this->getClass('TaskManager')->find($findWhere,'OR','','','','','','id');
				$TaskStateIDs = $this->getClass('TaskStateManager')->find(array('name' => $keyword),'','','','','','','id');
				$TaskTypeIDs = $this->getClass('TaskTypeManager')->find(array('name' => $keyword),'','','','','','','id');
				$GroupIDs = $this->getClass('GroupManager')->find(array('name' => $keyword,'description' => $keyword),'OR','','','','','','id');
				$ImageIDs = $this->getClass('ImageManager')->find(array('name' => $keyword,'description' => $keyword),'OR','','','','','','id');
				$SnapinIDs = $this->getClass('SnapinManager')->find(array('name' => $keyword,'description' => $keyword,'file' => $keyword),'OR','','','','','','id');
				$PrinterIDs = $this->getClass('PrinterManager')->find(array('name' => $keyword),'OR','','','','','','id');
				$GroupHostIDs = $this->getClass('GroupAssociationManager')->find(array('groupID' => $GroupIDs),'','','','','','','hostID');
				//$ImageHostIDs = $this->getClass('HostManager')->find(array('imageID' => $ImageIDs),'','','','','','','id');
				$SnapinHostIDs = $this->getClass('SnapinAssociationManager')->find(array('snapinID' => $SnapinIDs),'','','','','','','hostID');
				$PrinterHostIDs = $this->getClass('PrinterAssociationManager')->find(array('printerID' => $PrinterIDs),'','','','','','','hostID');
				$HostIDs = array_unique(array_merge((array)$HostIDs,(array)$GroupHostIDs,(array)$ImageHostIDs,(array)$SnapinHostIDs,(array)$PrinterHostIDs));
				$findWhere = array('id' => $TaskIDs,'typeID' => $TaskTypeIDs,'stateID' => $TaskStateIDs,'hostID' => $HostIDs);
				unset($TaskIDs,$TaskTypeIDs,$GroupIDs,$ImageIDs,$SnapinIDs,$PrinterIDs,$GroupHostIDs,$ImageHostIDs,$SnapinHostIDs,$PrinterHostIDs,$HostIDs);
			}
			unset($_SESSION['caller']);
			return array_unique($this->getClass($this->childClass)->getManager()->find($findWhere,'OR'));
		}
		catch (Exception $e)
		{
			$this->debug('Search failed! Error: %s', array($e->getMessage()));
		}
		return false;
	}
	/** find($where = array(),$whereOperator = 'AND',$orderBy = 'name',$sort = 'ASC')
		Pulls the information from the database into the resepective class file.
	*/
	public function find($where = '', $whereOperator = '', $orderBy = '', $sort = '',$compare = '',$groupBy = false,$not = false,$idField = false)
	{
		try
		{
			if (empty($compare))
				$compare = '=';
			// Fail safe defaults
			if (empty($where))
				$where = array();
			if (empty($whereOperator))
				$whereOperator = 'AND';
			if (empty($orderBy))
			{
				if (array_key_exists('name',$this->databaseFields))
					$orderBy = 'name';
				else
					$orderBy = 'id';
			}
			else if (!array_key_exists($orderBy,$this->databaseFields))
				$orderBy = 'id';
			$not = ($not ? ' NOT ' : '');
			list($getFields,$join) = $this->getClass($this->childClass,array('id' => 0))->buildQuery();
			if ($idField || (!$where && !$whereOperator && !$orderBy && !$sort && !$compare && !$groupBy && !$not && !$idField))
			{
				if (strtolower($_SESSION['caller']) == 'search')
				{
					$getFields = $this->databaseFields[$idField ? $idField : 'id'];
					$idField = $idField ? $idField : 'id';
				}
				else
				{
					if ($idField)
						$getFields = $this->databaseFields[$idField ? $idField : 'id'];
				}
			}
			// Error checking
			if (empty($this->databaseTable))
				throw new Exception('No database table defined');
			// Create Where Array
			if (count($where))
			{
				foreach((array)$where AS $field => $value)
				{
					if (is_array($value))
						$whereArray[] = sprintf("`%s` %s IN ('%s')", $this->key($field), $not,implode("', '", $value));
					else if (!is_array($value))
						$whereArray[] = sprintf("`%s` %s '%s'", $this->key($field), (preg_match('#%#', $value) ? 'LIKE' : ($not ? '!' : '').$compare), $value);
				}
			}
			foreach((array)$orderBy AS $item)
			{
				if ($this->databaseFields[$item])
					$orderArray[] = sprintf("`%s`",$this->databaseFields[$item]);
			}
			foreach((array)$groupBy AS $item)
			{
				if ($this->databaseFields[$item])
					$groupArray[] = sprintf("`%s`",$this->databaseFields[$item]);
			}
			$groupImplode = implode((array)$groupArray,','.(count($join) ? $this->childclass.'`.`' : ''));
			$orderImplode = implode((array)$orderArray,','.(count($join) ? $this->childclass.'`.`' : ''));
			$groupByField = 'GROUP BY '.(count($join) ? '`'.$this->childClass.'`.' : '').$groupImplode;
			$orderByField = 'ORDER BY '.(count($join) ? '`'.$this->childClass.'`.' : '').$orderImplode;
			if ($groupBy)
			{
				$sql = "SELECT %s`%s` FROM (SELECT %s`%s` FROM `%s` %s %s %s %s %s) `%s` %s %s %s %s";
				$fieldValues = array(
					(!count($whereArray) ? 'DISTINCT ' : ''),
					$getFields,
					(!count($whereArray) ? 'DISTINCT ' : ''),
					$getFields,
					$this->databaseTable,
					count($join) ? $this->childClass : '',
					count($join) ? implode($join) : '',
					(count($whereArray) ? 'WHERE '.implode(' '.$whereOperator.' ',$whereArray) : ''),
					$orderByField,
					$sort,
					count($join) ? $this->childClass : $this->databaseTable,
					count($join) ? implode($join) : '',
					$groupByField,
					$orderByField,
					$sort
				);
			}
			else
			{
				$sql = "SELECT %s`%s` FROM `%s` %s %s %s %s %s";
				$fieldValues = array(
					(!count($whereArray) ? 'DISTINCT ' : ''),
					$getFields,
					$this->databaseTable,
					count($join) ? $this->childClass : '',
					count($join) ? implode($join) : '',
					(count($whereArray) ? 'WHERE '.implode(' '.$whereOperator.' ',$whereArray) : ''),
					$orderByField,
					$sort
				);
			}
			$data = array();
			// Select all
			$this->DB->query($sql,$fieldValues);
			if ($idField)
			{
				while($id = $this->DB->fetch(MYSQLI_NUM)->get($idField))
					$ids[] = $id[0];
				$data = array_unique((array)$ids);
			}
			else
			{
				while($queryData = $this->DB->fetch()->get())
				{
					$Main = new $this->childClass(array('id' => 0));
					$Main->getQuery($queryData);
					array_push($data,$Main);
				}
			}
			unset($id,$ids,$row);
			$data = array_unique($data);
			// Return
			return (array)$data;
		}
		catch (Exception $e)
		{
			$this->debug('Find all failed! Error: %s', array($e->getMessage()));
		}
		return false;
	}
	/** count($where = array(),$whereOperator = 'AND')
		Returns the count of the database.
	*/
	public function count($where = array(), $whereOperator = 'AND', $compare = '=')
	{
		try
		{
			// Fail safe defaults
			if (empty($where))
				$where = array();
			if (empty($whereOperator))
				$whereOperator = 'AND';
			// Error checking
			if (empty($this->databaseTable))
				throw new Exception('No database table defined');
			// Create Where Array
			if (count($where))
			{
				foreach((array)$where AS $field => $value)
				{
					if (is_array($value))
						$whereArray[] = sprintf("`%s` IN ('%s')", $this->key($field), implode("', '", $value));
					else
						$whereArray[] = sprintf("`%s` %s '%s'", $this->key($field), (preg_match('#%#', $value) ? 'LIKE' : $compare), $value);
				}
			}
			// Count result rows
			$this->DB->query("SELECT COUNT(%s) AS total FROM `%s`%s LIMIT 1", array(
				$this->databaseFields['id'],
				$this->databaseTable,
				(count($whereArray) ? ' WHERE ' . implode(' ' . $whereOperator . ' ', $whereArray) : '')
			));
			// Return
			return (int)$this->DB->fetch()->get('total');
		}
		catch (Exception $e)
		{
			$this->debug('Find all failed! Error: %s', array($e->getMessage()));
		}
		return false;
	}
	// Blackout - 12:09 PM 26/04/2012
	// NOTE: VERY! powerful... use with care
	/** destroy($where = array(),$whereOperator = 'AND',$orderBy = 'name',$sort = 'ASC')
		Removes the relevant fields from the database.
	*/
	public function destroy($where = array(), $whereOperator = 'AND', $orderBy = 'name', $sort = 'ASC')
	{
		foreach ((array)$this->find($where, $whereOperator, $orderBy, $sort) AS $object)
			$object->destroy();
		return true;
	}
	// Blackout - 11:28 AM 22/11/2011
	/** buildSelectBox($matchID = '',$elementName = '',$orderBy = 'name')
		Builds a select box for the class values found.
	*/
	public function buildSelectBox($matchID = '', $elementName = '', $orderBy = 'name', $filter = '')
	{
		$matchID = ($_REQUEST['node'] == 'image' ? ($matchID === 0 ? 1 : $matchID) : $matchID);
		if (empty($elementName))
			$elementName = strtolower($this->childClass);
		foreach($this->find($filter ? array('id' => $filter) : '','',$orderBy,'','','',($filter ? true : false)) AS $Object)
			$listArray[] = '<option value="'.$Object->get('id').'"'.($matchID == $Object->get('id') ? ' selected' : '').'>'.$Object->get('name').' - ('.$Object->get('id').')</option>';
		return (isset($listArray) ? sprintf('<select name="%s" autocomplete="off"><option value="">%s</option>%s</select>',$elementName,'- '.$this->foglang['PleaseSelect'].' -',implode("\n",$listArray)) : false);
	}
	// TODO: Read DB fields from child class
	/** exists($name, $id = 0)
		Finds if the item already exists in the database.
	*/
	public function exists($name, $id = 0, $idfield = 'id')
	{
		if (empty($idfield))
			$idfield = 'id';
		$this->DB->query("SELECT COUNT(%s) AS total FROM `%s` WHERE `%s` = '%s' AND `%s` <> '%s'", 
			array(	
				$this->databaseFields[$idfield],
				$this->databaseTable,
				$this->databaseFields['name'],
				$name,
				$this->databaseFields[$idfield],
				$id
			)
		);
		return ($this->DB->fetch()->get('total') ? true : false);
	}
	// Key
	/** key($key)
		Returns the key's of the database fields.
	*/
	public function key($key)
	{
		if (array_key_exists($key, $this->databaseFields))
			return $this->databaseFields[$key];
		// Cannot be used until all references to acual field names are converted to common names
		if (array_key_exists($key, $this->databaseFieldsFlipped))
			return $this->databaseFieldsFlipped[$key];
		return $key;
	}
}
