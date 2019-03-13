<?php
Class Folder{
	protected $pdo;
	private $valid = true;
	private $_errors = [];
	private $show_self = false;  // include folder (as root) in the results?

	public $id = null;
	public $parent_id = null;
	public $name = null;
	public $normalized = null;
	public $path = null;
	public $type = 'folder';
	public $lastmodified_on = null;
	public $created_on = null;

	public $files = null;
	public $children = null;     // direct children (1st degree)
	public $descendents = null;  // descendents in flat array
	public $nested = null;       // descendents in nested array
	public $breadcrumbs = false;

	public function __construct() {
		$this->pdo = MyPDO::instance();

		$this->created_on = time();
		$this->lastmodified_on = time();
	}

	public function __destruct() {

	}

	public function isValid() {
		if ( $this->valid ) {

			return true;
		}
		
		return false;
	}

	public function errors() {

		return $this->_errors;
	}

	public function id() {

		return (int) $this->id;
	}
	public function setId(int $id) {
		$this->id = $id;

		return $this;
	}

	public function setName(string $new_name) {
		if ( !$this->valid ) {

			return $this;
		}
		$this->name = $new_name;
		$this->normalized = strtoupper( Urlify::normalize_name($this->name, false) );
		$this->set();

		return $this;
	}
	public function setShowSelf(bool $self = false) {
		$this->show_self = $self;

		return $this;
	}

	public function move(int $target_folder) {
		if ( !$this->valid ) {

			return $this;
		}
		$this->_node_move($this->id, $target_folder);
		$this->parent_id = $target_folder;
		$this->set();

		$sub_folders = $this->_node_get_children($this->id, ['self' => true, 'data_type'=> 'flat']);
		$this->updatePaths($sub_folders);

		return $this;
	}

	public function exists(int $id = null) {
		$id = ( !$id ) ? $this->id : (int) $id;

		$stmt = $this->pdo->prepare(" SELECT * FROM folders WHERE id=:id LIMIT 1 ");
		$stmt->bindValue(":id", $id, PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ( $row ) {

			return true;
		}

		return false;
	}
	public function pathExists(string $path) {

		$stmt = $this->pdo->prepare(" SELECT * FROM folders WHERE path=:path LIMIT 1 ");
		$stmt->bindValue(":path", $path, PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ( $row ) {
			return $row;
		}

		return false;
	}

	public function updatePaths(array $folders) {
		foreach ($folders as $row) {
			$folder = new self;
			$folder->get($row['id']);
			// $folder->path = $this->getPathToFolder($row['id']);
			$folder->set();
		}
	}

	public function add() {

		try {
			$this->pdo->beginTransaction();

			$this->normalized = strtoupper( Urlify::normalize_name($this->name, false) );
			$this->path = ( is_null($this->path) ) ? $this->getPathToFolder($this->parent_id) . $this->normalized . "/" : $this->path;

			$stmt = $this->pdo->prepare("INSERT INTO folders 
								   (name, normalized, parent_id, path, lastmodified_on, created_on) VALUES 
								   (:name, :normalized, :parent_id, :path, :lastmodified_on, :created_on)");
			$stmt->bindValue(":parent_id", $this->parent_id, PDO::PARAM_INT);
			$stmt->bindValue(":name", $this->name, PDO::PARAM_STR);
			$stmt->bindValue(":normalized", $this->normalized, PDO::PARAM_STR);
			$stmt->bindValue(":path", $this->path, PDO::PARAM_STR);
			$stmt->bindValue(":lastmodified_on", $this->lastmodified_on, PDO::PARAM_INT);
			$stmt->bindValue(":created_on", $this->created_on, PDO::PARAM_INT);
			$stmt->execute();
			$this->id = (int) $this->pdo->lastInsertId();
			$this->_node_add($this->id , $this->parent_id);
			
			$this->pdo->commit();
		} catch (PDOException $e) {
			$this->pdo->rollback();
			$this->valid = false;
			$this->_errors[] = $e->getMessage();
		}
		
		return $this;
	}

	public function set() {

		try {
			$this->pdo->beginTransaction();
			$this->path = $this->getPathToFolder($this->parent_id) . $this->normalized . "/";

			$stmt = $this->pdo->prepare("UPDATE folders SET 
												parent_id=:parent_id,
												name=:name,
												normalized=:normalized,
												path=:path,

												lastmodified_on=:lastmodified_on
									WHERE id=:id");
			$stmt->bindValue(":id", $this->id, PDO::PARAM_INT);

			$stmt->bindValue(":parent_id", $this->parent_id, PDO::PARAM_INT);
			$stmt->bindValue(":name", $this->name, PDO::PARAM_STR);
			$stmt->bindValue(":normalized", $this->normalized, PDO::PARAM_STR);
			$stmt->bindValue(":path", $this->path, PDO::PARAM_STR);
			$stmt->bindValue(":lastmodified_on", $this->lastmodified_on, PDO::PARAM_INT);
			// $stmt->bindValue(":created_on", $this->created_on , PDO::PARAM_INT);

			$stmt->execute();
			$count = $stmt->rowCount();
			$this->pdo->commit();
		} catch (PDOException $e) {
			$this->pdo->rollback();
			$this->valid = false;
			$this->_errors[] = $e->getMessage();
		}
		if ($count < 1) {
			$this->valid = false;
			$this->_errors[] = 'no record updated';
		}

		return $this;
}

	// https://stackoverflow.com/questions/6802539/hierarchical-tree-database-for-directories-in-filesystem
	public function delete(int $id = null) {
		$id = ( !$id ) ? $this->id : $id;
		if (!$this->valid) {

			return $this;
		}

		try {
			$this->pdo->beginTransaction();

			$sub_folders = $this->_node_get_children($id, ['self' => true, 'data_type'=> 'flat']);
			$descendents_folders_id = array_column($sub_folders, 'id');  // ids of folder && descendents folders
			foreach ($descendents_folders_id as $folder_id) {                   // delete files from folder && descendents bfolders
				$this->_delete_folder_files($folder_id);
			}
			$this->_node_delete($id);                                           // delete folder node (include descendents)

			$this->pdo->commit();
		} catch (PDOException $e) {
			$this->pdo->rollback();
			$this->valid = false;
			$this->_errors[] = $e->getMessage();
		}

		return $this;
	}

	// https://stackoverflow.com/questions/6802539/hierarchical-tree-database-for-directories-in-filesystem
	public function deleteBatch(array $ids_array) {
		if ( !$this->valid ) {

			return $this;
		}
		try {
			$this->pdo->beginTransaction();

			$folders_ids = filter_ids_array($ids_array);
			foreach ($folders_ids as $folder_id) {
				$this->delete($folder_id);
			}

			$this->pdo->commit();
 		} catch (PDOException $e) {
			$this->pdo->rollback();
			$this->valid = false;
			$this->_errors[] = $e->getMessage();
		}

		return $this;
	}

	public function get(int $id = null) {
		$id = ( !$id ) ? $this->id : intval($id);
		if ( $id < 1 ) {
			$this->valid = false;

			return $this;
		}

		$stmt = $this->pdo->prepare(" SELECT * FROM folders WHERE id=:id LIMIT 1");
		$stmt->bindValue(":id", $id, PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ( $row ) {
			$this->id = $row['id'];
			$this->parent_id = $row['parent_id'];
			$this->name = $row['name'];
			$this->normalized = $row['normalized'];
			$this->path = $row['path'];
			$this->lastmodified_on = $row['lastmodified_on'];
			$this->created_on = $row['created_on'];
		} else {
			$this->valid = false;
			$this->_errors[] = 'no record found';
		}

		return $this;
	}

	public function children(int $id = null) {
		$id = ( !$id ) ? $this->id : $id;
		if ( !$this->children ){
			$this->children = $this->_get_children($id);
		}

		return $this->children;
	}

	public function files(int $id = null) {
		$id = ( !$id ) ? $this->id : $id;
		if ( !$this->files ){
			$this->files = $this->_get_files($id);
		}

		return $this->files;
	}

	public function nested(int $id = null) {
		$id = ( !$id ) ? $this->id : $id;
		if ( !$this->nested ) {
			$mixed = $this->_node_get_children($id, ['self' => $this->show_self, 'data_type'=> 'mixed']);
			$this->descendents = $mixed['flat'];
			$this->nested = $mixed['nested'];
		}

		return $this->nested;
	}

	public function descendents(int $id = null) {
		$id = ( !$id ) ? $this->id : $id;
		if ( !$this->descendents ) {
			$mixed = $this->_node_get_children($id, ['self' => $this->show_self, 'data_type'=> 'mixed']);
			$this->descendents = $mixed['flat'];
			$this->nested = $mixed['nested'];
		}

		return $this->descendents;
	}

	public function ancestors(int $id = null) {
		$id = ( !$id ) ? $this->id : $id;
		if ( !$this->ancestors ){
			$this->ancestors = $this->_node_get_parent($id, ['self' => $this->show_self]);
		}

		return $this->ancestors;
	}

	public function breadcrumbs(int $id = null) {
		$id = (!$id) ? $this->id : $id;
		if ( !$this->breadcrumbs ) {
			$this->breadcrumbs = $this->_node_get_parent($id, ['self' => $this->show_self]);
		}

		return $this->breadcrumbs;
	}

	/***
	 * Generate path to current folder e.g HOME/ASDF/QWERT/
	 * 
	 * @param int $id = id of folder
	 */
	public function getPathToFolder(int $id = null) {
		$id = (!$id) ? $this->id : $id;
		if ( !$this->breadcrumbs ) {
			$this->breadcrumbs = $this->_node_get_parent($id, ['self' => true]);
		}
		$steps = $this->breadcrumbs;
		$path = "";
		foreach( $steps as $folder) {
			$path .= $folder['normalized'] . "/";
		}
		return $path;
	}
	// Home/Xyz/Abc
	// first check if exists. then create.
	public function getIdToFolderPath(string $path, int $parent_id) {
		$parent_path = $this->getPathToFolder($parent_id);
		$last_parent_id = $parent_id;
		$last_relative_dir = '';
		$folder_id = null;

		$items = explode('/', $path);
		foreach ($items as $key => $value) {
			if ( $value != "" ) {
				$folder = new self;
				$folder->name = $value;
				$folder->normalized = strtoupper( Urlify::normalize_name($value, false) );
				$folder->parent_id = $last_parent_id;
				$path = $parent_path . $last_relative_dir . $folder->normalized . "/";
				$row = $this->pathExists($path);
				if ( $row ) {
					$folder = (object) $row;
					$folder_id = $folder->id;
				} else {
					$folder_id = $folder->add()->id();
				}
				$last_parent_id = $folder_id;
				$last_relative_dir .= $folder->normalized . '/';
			}
		}
		
		return $folder_id;
	}

	public function all(array $params = []) {

		$default_options = [
			'orderby' => "normalized",
		];
		$options = array_merge($default_options, $params);
		
		$query = " SELECT *, 'folder' AS type FROM folders ";
		$query .= ($options['orderby']) ? " ORDER BY ". $options['orderby'] : "";

		$stmt = $this->pdo->prepare($query);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		if ( $rows ) {

			return $rows;
		}

		return [];
	}

	public function getBatch(array $ids_array) {
		$rows = [];

		if ( is_array($ids_array) ) {
			$in_array = filter_ids_array($ids_array);
			
			if ( count($in_array) > 0 ) {
				$where_in  = str_repeat('?,', count($in_array)-1) . '?';
				$query = "SELECT * FROM folders WHERE id IN (". $where_in .")";
				$stmt = $this->pdo->prepare($query);
				$stmt->execute($in_array);
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			}
		} else {
			$this->valid = false;
			$this->_errors[] = 'no array';
		}
		
		return $rows;
	}


/***
 *                     d8b                   888            
 *                     Y8P                   888            
 *                                           888            
 *    88888b.  888d888 888 888  888  8888b.  888888 .d88b.  
 *    888 "88b 888P"   888 888  888     "88b 888   d8P  Y8b 
 *    888  888 888     888 Y88  88P .d888888 888   88888888 
 *    888 d88P 888     888  Y8bd8P  888  888 Y88b. Y8b.     
 *    88888P"  888     888   Y88P   "Y888888  "Y888 "Y8888  
 *    888                                                   
 *    888                                                   
 *    888                                                   
 */


	private function _get_children(int $folder_id) {

		$stmt = $this->pdo->prepare(" SELECT * FROM folders WHERE parent_id=:id ");
		$stmt->bindValue(":id", $folder_id, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetch(PDO::FETCH_ASSOC);

		$this->children = $rows;

		if ( $rows ) {

			return $rows;
		}
		return false;
	}
	
	private function _get_descendents(int $folder_id) {

		$rows = $this->_node_get_children($folder_id, ['self' => false, 'data_type'=> 'mixed']);
		if ( $rows ) {
			return $rows;
		}

		return false;
	}

	private function _get_files(int $folder_id) {

		$stmt = $this->pdo->prepare(" SELECT * FROM files WHERE folder_id=:folder_id ");
		$stmt->bindValue(":folder_id", $folder_id, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if ( $rows ) {

			return $rows;
		}

		return [];
	}

	private function _delete_folder_files(int $folder_id) {

		try {
			$this->pdo->beginTransaction();

			$stmt = $this->pdo->prepare(" SELECT * FROM files WHERE folder_id=:folder_id ");
			$stmt->bindValue(":folder_id", $folder_id, PDO::PARAM_INT);
			$stmt->execute();
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$files_to_delete = $rows;

			$this->_delete_server_files($files_to_delete);

			$stmt = $this->pdo->prepare(" DELETE FROM files WHERE folder_id=:folder_id ");
			$stmt->bindValue(":folder_id", $folder_id, PDO::PARAM_INT);
			$stmt->execute();
			$count = $stmt->rowCount();

			$this->pdo->commit();
		} catch (PDOException $e) {
			$this->pdo->rollback();
			$this->_errors[] = $e->getMessage();
			$this->valid = false;
		}

		if ( $count > 0) {

			return $count;
		}

		return false;
	}

	
	/***
	 *             888                                                   888             888      888          
	 *             888                                                   888             888      888          
	 *             888                                                   888             888      888          
	 *     .d8888b 888  .d88b.  .d8888b  888  888 888d888  .d88b.        888888  8888b.  88888b.  888  .d88b.  
	 *    d88P"    888 d88""88b 88K      888  888 888P"   d8P  Y8b       888        "88b 888 "88b 888 d8P  Y8b 
	 *    888      888 888  888 "Y8888b. 888  888 888     88888888       888    .d888888 888  888 888 88888888 
	 *    Y88b.    888 Y88..88P      X88 Y88b 888 888     Y8b.           Y88b.  888  888 888 d88P 888 Y8b.     
	 *     "Y8888P 888  "Y88P"   88888P'  "Y88888 888      "Y8888         "Y888 "Y888888 88888P"  888  "Y8888  
	 *                                                                                                         
	 *                                                                                                         
	 *                                                                                                         
	 */
	// https://gist.github.com/dazld/2174233

	private function _node_add(int $node_id, int $target_id = 1) {

		$query = " INSERT INTO tree_path (ancestor, descendant, depth)
			SELECT * FROM (
				SELECT t.ancestor, :node_id, t.depth + 1
					FROM tree_path AS t
					WHERE 
						t.descendant = :target_id
					UNION ALL
						SELECT :node_id2, :node_id3, 0
			) as tmp
		";
		$stmt = $this->pdo->prepare($query);
		// binding parameters, apparently you can't use a placeholder twice
		// http://php.net/manual/en/pdostatement.bindvalue.php
		$stmt->bindValue(":target_id", $target_id, PDO::PARAM_INT);
		$stmt->bindValue(":node_id", $node_id, PDO::PARAM_INT);
		$stmt->bindValue(":node_id2", $node_id, PDO::PARAM_INT);
		$stmt->bindValue(":node_id3", $node_id, PDO::PARAM_INT);
		$result = $stmt->execute();

		return $result;
	}

	/**
	 * 
	 * Delete node and all children from the tree.
	 * 
	 * @param  int      node id
	 * @param  boolean  if TRUE, it will also delete from reference table
	 * @return mixed
	 */
	private function _node_delete(int $node_id, bool $delete_reference = true)
	{
		try {
			$rows = false;
			$this->pdo->beginTransaction();

			if ( $delete_reference ) {
				$rows = $this->_node_get_children($node_id, ['self' => true, 'data_type' => 'flat']);
				$children_nodes_ids = array_column($rows, 'id');
			}

			$query = " DELETE FROM tree_path
					WHERE descendant IN (SELECT descendant 
											FROM tree_path 
											WHERE ancestor = :node_id
										);
			";
			$stmt = $this->pdo->prepare($query);
			$stmt->bindValue(":node_id", $node_id, PDO::PARAM_INT);
			$stmt->execute();

			if ( $delete_reference ) {
				$in  = str_repeat('?,', count($children_nodes_ids)-1) . '?';
				$query = "DELETE FROM folders WHERE id IN (". $in .") ";
				$stmt = $this->pdo->prepare($query);
				$stmt->execute($children_nodes_ids);
				// = $stmt->rowCount();
			}
			$this->pdo->commit();

			return true;
		} catch (PDOException $e) {
			$this->pdo->rollback();
			$this->_errors[] = $e->getMessage();

			return false;
		}

		return false;
	}

	/**
	 * Move node with its children to another node.
	 * 
	 * @link  http://www.mysqlperformanceblog.com/2011/02/14/moving-subtrees-in-closure-table/
	 * 
	 * @param  int  node to be moved
	 * @param  int  target node
	 * @return void
	 */
	// https://gist.github.com/kentoj/872cbefc68f68a2a97b6189da9cd6e23
	private function _node_move(int $node_id, int $target_id)
	{
		try {
			$this->pdo->beginTransaction();
			// greee. Doesnt work on SQLITE
			$query1 = " DELETE a FROM tree_path AS a 
				JOIN tree_path AS d ON a.descendant = d.descendant
				LEFT JOIN tree_path AS x
					ON (x.ancestor = d.ancestor AND x.descendant = a.ancestor)
				WHERE 
					d.ancestor = :node_id 
					AND 
					x.ancestor IS NULL
			";
			
			$query1 = " DELETE FROM tree_path
					WHERE descendant IN (SELECT descendant FROM tree_path WHERE ancestor = :node_id)
					AND ancestor NOT IN (SELECT descendant FROM tree_path WHERE ancestor = :node_id2)
			";
			$stmt = $this->pdo->prepare($query1);
			$stmt->bindValue(":node_id", $node_id, PDO::PARAM_INT);
			$stmt->bindValue(":node_id2", $node_id, PDO::PARAM_INT);
			$res1 = $stmt->execute();

			//http://www.sqlitetutorial.net/sqlite-cross-join/
			$query2 = " INSERT INTO tree_path (ancestor, descendant, depth)
				SELECT  supertree.ancestor, subtree.descendant, supertree.depth + subtree.depth + 1 AS depth
					FROM tree_path AS supertree
					CROSS JOIN tree_path AS subtree
					WHERE 
						subtree.ancestor = :node_id
					AND 
						supertree.descendant = :target_id
			";
			$stmt2 = $this->pdo->prepare($query2);
			$stmt2->bindValue(":node_id", $node_id, PDO::PARAM_INT);
			$stmt2->bindValue(":target_id", $target_id, PDO::PARAM_INT);
			$res2 = $stmt2->execute();
			$this->pdo->commit();
		} catch (PDOException $e) {
			$this->pdo->rollback();
			$this->_errors[] = $e->getMessage();

			return false;
		}
		if ( $res1 AND $res2 ) {

			return true;
		}

		$this->pdo->rollback();
		return false;
	}


	/**
	 * Check if current node has children.
	 * 
	 * @param   int       node id
	 * @return  boolean
	 */
	private function _node_has_children(int $node_id)
	{
		$query = "SELECT descendant FROM tree_path WHERE ancestor=:note_id";

		$stmt = $this->pdo->prepare($query);
		$stmt->bindValue(":node_id", $node_id, PDO::PARAM_INT);
		$descendants = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($descendants as $k => $v) {
			$descendants[$k] = $v['descendant'];
		}
		
		$query = " SELECT COUNT(*) as total 
			FROM tree_path 
			WHERE 
				ancestor IN (:descendants)
				AND
				descendant != :node_id
				";
		$stmt = $this->pdo->prepare($query);
		$stmt->bindValue(":descendant", implode(',', $descendants), PDO::PARAM_STR);
		$stmt->bindValue(":node_id", $node_id, PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return (bool) $row['total'];
	}

	/**
	 * Get (all) root nodes.
	 */
	private function _node_get_root() {
		$query = " SELECT r.descendant
			FROM tree_path r 
			LEFT JOIN tree_path p ON (r.descendant = p.descendant AND p.ancestor != p.descendant)
			WHERE p.descendant IS NULL
		";
		$stmt = $this->pdo->prepare($query);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if ( $rows ) {

			return array_column($rows, 'descendant');
		} else {

			return false;
		}
	}

	/**
	 * Get parent(s) of current node.
	 * 
	 * @param  int    $node_id current node id
	 * @param  array  $params = [
	 *             'depth' 		=> (mixed)   depth up to. Default: false.
	 *             'self' 		=> (boolean) include the current node into array result. Default: false
	 * ]
	 * @return mixed array if succed
	 */
	private function _node_get_parent(int $node_id, array $params = [])
	{
		$default_options = [
			'depth' => NULL,
			'self' => false,
		];
		$options = (object) array_merge($default_options, $params);

		$query = " SELECT t.*
			FROM folders t
			JOIN tree_path c ON (t.id = c.ancestor)
			WHERE
				c.descendant = :node_id
		";
		if ( !$options->self ) {
			$query .= " AND c.ancestor != :node_id_self ";
		}
		if ( $options->depth ) {
			$query .= " AND c.depth = :depth ";
		}
		$query .= " ORDER BY t.id ";

		$stmt = $this->pdo->prepare($query);
		$stmt->bindValue(":node_id", $node_id, PDO::PARAM_INT);

		if ( !$options->self ) {
			$stmt->bindValue(":node_id_self", $node_id, PDO::PARAM_INT);
		}
		if ( $options->depth ) {
			$stmt->bindValue(":depth", $options->depth, PDO::PARAM_INT);
		}

		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if ( count($rows) > 0 ) {

			return $rows;
		}

		return false;
	}

	/**
	 * Fetch children(s) of the node.
	 * 
	 * If level/depth specified then self will be ignore.
	 *
	 * @param  int    $node_id current node id
	 * @param  array  $params = [
	 *             'depth' 		=> (mixed)   depth up to. Default: null.
	 *             'self' 		=> (boolean) include the current node into array result. Default: false
	 *             'nested' 	=> (boolean) nestify the result. Default: false
	 *             'orderby' 	=> (string)  DB column name to sort and order. Default: id
	 * ]
	 * @return mixed array if succed
	 */
	private function _node_get_children(int $node_id = 1, array $params = [])
	{
		$default_options = [
			'depth' => null,
			'self' => false,
			'orderby' => 'id ASC',
			'data_type' => 'mixed',
		];
		$options = (object) array_merge($default_options, $params);
		
		$query2 = " SELECT f.*
				FROM folders AS f
					JOIN tree_path AS tree ON f.parent_id = tp.descendant
					WHERE tp.ancestor = :node_id
		";
		$query = " SELECT t.*,  'folder' AS type, '0' AS size, c2.ancestor as parent, c1.depth as depth
				FROM tree_path c1
					JOIN folders t ON (t.id= c1.descendant)
					LEFT JOIN tree_path c2 ON (c2.depth=1 AND c2.descendant=c1.descendant)
					WHERE c1.ancestor = :node_id
		";

		if ( !$options->self ) {
			$query .= " AND c1.descendant != :node_id_self ";
		}
		if ( $options->depth ) {
			$query .= " AND c1.depth = :depth ";
		}
		if ( $options->orderby ) {
			$query .= " ORDER BY t." . $options->orderby . " ";
		}
		
		$stmt = $this->pdo->prepare($query);
		if ( !$options->self ) {
			$stmt->bindValue(":node_id_self", $node_id, PDO::PARAM_INT);
		}
		if ( $options->depth ) {
			$stmt->bindValue(":depth", $options->depth, PDO::PARAM_INT);	
		}
		$stmt->bindValue(":node_id", $node_id, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if ( !$rows ) {

			return [];
		}
		$with_fixed_ints = [];
		foreach ($rows as $key => $row) {
			$with_fixed_ints[$key] = $row;
			$with_fixed_ints[$key]['id'] = (int) $row['id'];
			$with_fixed_ints[$key]['parent_id'] = (int) $row['parent_id'];
			$with_fixed_ints[$key]['parent'] = (int) $row['parent'];
			$with_fixed_ints[$key]['depth'] = (int) $row['depth'];
		}

		switch ($options->data_type) {
			case 'nested':

				return $this->_generate_nested_array($with_fixed_ints, $node_id);
				break;
			case 'flat':

				return $with_fixed_ints;
				break;
			case 'mixed':
			default:
				if ( !$options->depth ) {
					$nested = $this->_generate_nested_array($with_fixed_ints, $node_id);
				} else {
					$nested = null;
				}

				return [
						'params' => $options,
						'flat' => $with_fixed_ints,
						'nested' => $nested,
				];
				break;
		}
	}

	private function _delete_server_files(array $files_rows = []) {

		foreach ($files_rows as $row) {
			$file_pointer = CONFIG['base_path_store'] . $row['saved_as'];
			$thumb_pointer = CONFIG['base_path_thumb'] . $row['thumbnail'];
			if ( file_exists($file_pointer) ) {
				unlink( $file_pointer );
			}
			if ( !empty($row['thumbnail']) && file_exists($thumb_pointer) ) {
				unlink( $thumb_pointer );
			}
		}
	}

	private function _filter_ids_array2(array $array = []) {
		$array = array_filter( $array, 'strlen' );
		$array = array_map(function($value) {

			return intval($value);
		}, $array);

		return $array;
	}

	private function _filter_ids_array(array $array = []) {
		$array = filter_var($array, FILTER_VALIDATE_INT, [
													  'flags'   => FILTER_REQUIRE_ARRAY,
													  'options' => ['min_range' => 1]
													]
						);
		$filtered = array_filter($array, 'is_int');

		return $filtered;
	}

	private function _generate_nested_array( array $flatArray, int $root_id = 0 )
	{
	
		// initiate result array
		$tree = array();
		// iterate $flatArray
		foreach( $flatArray as $item )
		{
			// for convenience, initiate these vars
			$id = $item[ 'id' ];
			$parentId = $item[ 'parent_id' ];
	
			// initiate this item's children array;
			$item[ 'children' ] = [];
			// if parent doesn't exist yet, initiate it along with an empty 'children' array
			if( !isset( $tree[ $parentId ] ) ) {
				$tree[ $parentId ] = [
					'children' => []
				];
			}
			// if this item is initiated already (as being a parent) merge it with the current item
			$tree[ $id ] = isset( $tree[ $id ] ) ? $tree[ $id ] + $item : $item;
			// add this item to the parents children collection by reference (for efficiency)
			// $tree[ $parentId ][ 'children' ][ $id ] = &$tree[ $id ];
	
			// nah. without fixed index
			$tree[ $parentId ][ 'children' ][] = &$tree[ $id ];
		}

		return $tree[$root_id];
	}
	

}