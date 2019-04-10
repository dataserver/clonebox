<?php
class Folder
{
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

    public function __construct()
    {
        $this->pdo = MyPDO::instance();

        $this->created_on = time();
        $this->lastmodified_on = time();
    }

    public function __destruct()
    {
    }

    /**
     * Insert into database the current instance values
     *
     * @return self return current instance
     */
    public function add()
    {
        try {
            $this->pdo->beginTransaction();

            $this->normalized = strtoupper(Urlify::normalize_name($this->name, false));
            $this->path = (is_null($this->path)) ? $this->pathToFolder($this->parent_id) . $this->normalized . "/" : $this->path;

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
            $this->_node_add($this->id, $this->parent_id);
            
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollback();
            $this->valid = false;
            $this->_errors[] = $e->getMessage();
        }
        
        return $this;
    }

    /**
     * Get from database the info from given $id or from instance defined id
     *
     * @param int    $id folder_id, default is instance->id
     * @return self  return current instance
     */
    public function find(int $id = null)
    {
        $id = (!$id) ? $this->id : intval($id);
        if ($id < 1) {
            $this->valid = false;

            return $this;
        }

        $stmt = $this->pdo->prepare(" SELECT * FROM folders WHERE id=:id LIMIT 1");
        $stmt->bindValue(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
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

    /**
     * Update database the current values based instance->id
     *
     * @return self  return current instance
     */
    public function set()
    {
        try {
            $this->pdo->beginTransaction();
            $this->path = $this->pathToFolder($this->parent_id) . $this->normalized . "/";

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

    
    /**
     * Delete current node, all files will be deleted too
     * // https://stackoverflow.com/questions/6802539/hierarchical-tree-database-for-directories-in-filesystem
     *
     * @param int      $id folder_id
     * @return self    return current instance
     */
    public function delete(int $id = null)
    {
        $id = (!$id) ? $this->id : $id;
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

    /**
     * Move current instance folder to target node
     * // https://stackoverflow.com/questions/6802539/hierarchical-tree-database-for-directories-in-filesystem
     *
     * @param array    $array of folders ids
     * @return self    return current instance
     */
    public function deleteBatch(array $ids_array)
    {
        if (!$this->valid) {
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

    /**
     * Move current instance folder to target node
     *
     * @param int     $target_folder  folder id
     * @return self   return current instance
     */
    public function move(int $target_folder)
    {
        if (!$this->valid) {
            return $this;
        }
        $this->_node_move($this->id, $target_folder);
        $this->parent_id = $target_folder;
        $this->set();

        $sub_folders = $this->_node_get_children($this->id, ['self' => true, 'data_type'=> 'flat']);
        $this->pathUpdate($sub_folders);

        return $this;
    }

    /**
     * Check if operations in chain was valid. You can check errors using $this->errors()
     *
     * @return bool
     */
    public function isValid()
    {
        if ($this->valid) {
            return true;
        }
        
        return false;
    }

    /**
     * Return errors in a chain of operations
     *
     * @return array of errors
     */
    public function errors()
    {
        return $this->_errors;
    }

    /**
     * Return current id
     *
     * @return int
     */
    public function id()
    {
        return (int) $this->id;
    }

    /**
     * Set current node_id
     *
     * @param int     $id
     * @return self   return current instance
     */
    public function setId(int $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set new name for the folder
     *
     * @param string  $new_name
     * @return self   return current instance
     */
    public function setName(string $new_name)
    {
        if (!$this->valid) {
            return $this;
        }
        $this->name = $new_name;
        $this->normalized = strtoupper(Urlify::normalize_name($this->name, false));
        $this->set();

        return $this;
    }

    /**
     * Set if result in chain MUST include the current node
     *
     * @param bool    $self, default is false
     * @return self   return current instance
     */
    public function setShowSelf(bool $self = false)
    {
        $this->show_self = $self;

        return $this;
    }

    /**
     * Return true or false if folder_id exists
     *
     * @param int   $id folder_id
     * @return bool
     */
    public function exists(int $id = null)
    {
        $id = (!$id) ? $this->id : (int) $id;

        $stmt = $this->pdo->prepare(" SELECT * FROM folders WHERE id=:id LIMIT 1 ");
        $stmt->bindValue(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return true;
        }

        return false;
    }

    /**
     * Get direct children of given folder id
     *
     * @param int    $id folder_id
     * @return array  associated rows
     */

    public function children(int $id = null)
    {
        $id = (!$id) ? $this->id : $id;
        if (!$this->children) {
            $this->children = $this->_get_children($id);
        }

        return $this->children;
    }

    /**
     * Return array of files belong to given folder_id
     * @param int $id folder id
     * @return array
     */
    public function files(int $id = null)
    {
        $id = (!$id) ? $this->id : $id;
        if (!$this->files) {
            $this->files = $this->_get_files($id);
        }

        return $this->files;
    }

    /**
     * Return descendents folders of a given node.
     *
     * @param int     $folder_id, default is null returning complete tree
     * @return array  @array in nested representation of descendents folders
     */
    public function nested(int $id = null)
    {
        $id = (!$id) ? $this->id : $id;
        if (!$this->nested) {
            $mixed = $this->_node_get_children($id, ['self' => $this->show_self, 'data_type'=> 'mixed']);
            $this->descendents = $mixed['flat'];
            $this->nested = $mixed['nested'];
        }

        return $this->nested;
    }

    /**
     * Return descendents folders of a given node.
     *
     * @param int     $folder_id, default is null returning complete tree
     * @return array  @array of associated values. 'flat' format
     */
    public function descendents(int $id = null)
    {
        $id = (!$id) ? $this->id : $id;
        if (!$this->descendents) {
            $mixed = $this->_node_get_children($id, ['self' => $this->show_self, 'data_type'=> 'mixed']);
            $this->descendents = $mixed['flat'];
            $this->nested = $mixed['nested'];
        }

        return $this->descendents;
    }

    /**
     * Return ancestors folders of a given node.
     *
     * @param int     $folder_id, default is null returning complete tree
     * @return array  flat array of associated values
     */
    public function ancestors(int $id = null)
    {
        $id = (!$id) ? $this->id : $id;
        if (!$this->ancestors) {
            $this->ancestors = $this->_node_get_parent($id, ['self' => $this->show_self]);
        }

        return $this->ancestors;
    }

    /**
     * Return ancestors folders of current node.
     *
     * @param int     $folder_id, default is null returning complete tree
     * @return array  flat array of associated values
     */
    public function breadcrumbs(int $folder_id = null)
    {
        $folder_id = (!$folder_id) ? $this->id : $folder_id;
        if (!$this->breadcrumbs) {
            $this->breadcrumbs = $this->_node_get_parent($folder_id, ['self' => $this->show_self]);
        }

        return $this->breadcrumbs;
    }

    /**
     * Return info from folder with a give path
     *
     * @param string  $path of folder e.g. HOME/ABC/XYZ/
     * @return mixed  array of associated values of folder with given $path, return false if no folder is found
     */
    public function pathExists(string $path)
    {
        $path = strtoupper($path);
        $stmt = $this->pdo->prepare(" SELECT * FROM folders WHERE path=:path LIMIT 1 ");
        $stmt->bindValue(":path", $path, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        return false;
    }

    /**
     * Update database field info 'path'
     *
     * @param array $folders_ids
     * @return void
     */
    public function pathUpdate(array $folders)
    {
        foreach ($folders as $row) {
            $folder = new self;
            $folder->find($row['id']);
            // $folder->path = $this->pathToFolder($row['id']);
            $folder->set();
        }
    }

    /***
     * Generate path to current folder e.g HOME/ASDF/QWERT/
     *
     * @param int $id = id of folder
     */
    public function pathToFolder(int $id = null)
    {
        $id = (!$id) ? $this->id : $id;
        if (!$this->breadcrumbs) {
            $this->breadcrumbs = $this->_node_get_parent($id, ['self' => true]);
        }
        $steps = $this->breadcrumbs;
        $path = "";
        foreach ($steps as $folder) {
            $path .= $folder['normalized'] . "/";
        }
        return $path;
    }

    /**
     * Return folder id of a path.
     * Create, if required, database for each part(folder/directory) of path
     * e.g. xxx/yyy/abc
     * if none of this path exists, 3 new database entries will be created: xxx, yyy and abc.
     * The id of last subfolder (abc) will be returned.
     *
     * @param string $path path relative to parent_id
     * @param int    $parent_id
     */
    public function getIdpathToFolder(string $path, int $parent_id)
    {
        $parent_path = $this->pathToFolder($parent_id);
        $last_parent_id = $parent_id;
        $last_relative_dir = '';
        $folder_id = null;

        $items = explode('/', $path);
        foreach ($items as $key => $value) {
            if ($value != "") {
                $folder = new self;
                $folder->name = $value;
                $folder->normalized = strtoupper(Urlify::normalize_name($value, false));
                $folder->parent_id = $last_parent_id;
                $path = $parent_path . $last_relative_dir . $folder->normalized . "/";
                $row = $this->pathExists($path);
                if ($row) {
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

    /**
     * Return all folders in the database, default order is normalized name
     *
     * @param array $params [
     * 	'orderby' => "normalized"   // database field to be used, default normalize ASC
     * ]
     */
    public function all(array $params = [])
    {
        $default_options = [
            'orderby' => "normalized",
        ];
        $options = array_merge($default_options, $params);
        
        $query = " SELECT *, 'folder' AS type FROM folders ";
        $query .= ($options['orderby']) ? " ORDER BY ". $options['orderby'] : "";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($rows) {
            return $rows;
        }

        return [];
    }

    /**
     * Return array of folders from array of ids
     *
     * @param array  $ids_array array of ids.
     * @return array $rows of associdated array from database.
     */
    public function getBatch(array $ids_array)
    {
        $rows = [];

        if (is_array($ids_array)) {
            $in_array = filter_ids_array($ids_array);
            
            if (count($in_array) > 0) {
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

    /**
     * Return direct children folders of given $folder_id
     *
     * @param int     $folder_id
     * @return mixed  $array of associated rows or false if none is found
     */
    private function _get_children(int $folder_id)
    {
        $stmt = $this->pdo->prepare(" SELECT * FROM folders WHERE parent_id=:id ");
        $stmt->bindValue(":id", $folder_id, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->children = $rows;

        if ($rows) {
            return $rows;
        }
        return false;
    }

    /**
     * Return all descendents folders of a given $folder_id
     *
     * @param int    $folder_id
     * @return mixed $array of rows or false if none is found
     */
    private function _get_descendents(int $folder_id)
    {
        $rows = $this->_node_get_children($folder_id, ['self' => false, 'data_type'=> 'mixed']);
        if ($rows) {
            return $rows;
        }

        return false;
    }

    /**
     * Return all files with folder id = $folder_id
     *
     * @param int    $folder_id
     * @return array $array of associated rows
     */
    private function _get_files(int $folder_id)
    {
        $stmt = $this->pdo->prepare(" SELECT * FROM files WHERE folder_id=:folder_id ");
        $stmt->bindValue(":folder_id", $folder_id, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows) {
            return $rows;
        }

        return [];
    }

    /**
     * Delete all files belong to specific folder_id
     *
     * @param int     @folder_id
     * @return mixed  number of deleted files or false when none has being deleted
     */
    private function _delete_folder_files(int $folder_id)
    {
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

        if ($count > 0) {
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
    /**
     *
     * Add node to a tree
     *
     * @param  int    $node_id (folder_id) of node that will be added.
     * @param  int    $target_id target location of new node
     * @return bool   result of insertion
     */
    private function _node_add(int $node_id, int $target_id = 1)
    {
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
     * @return boolean  return result of operation
     */
    private function _node_delete(int $node_id, bool $delete_reference = true)
    {
        try {
            $rows = false;
            $this->pdo->beginTransaction();

            if ($delete_reference) {
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

            if ($delete_reference) {
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
        if ($res1 and $res2) {
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
    private function _node_get_root()
    {
        $query = " SELECT r.descendant
			FROM tree_path r 
			LEFT JOIN tree_path p ON (r.descendant = p.descendant AND p.ancestor != p.descendant)
			WHERE p.descendant IS NULL
		";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows) {
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
            'depth' => null,
            'self' => false,
        ];
        $options = (object) array_merge($default_options, $params);

        $query = " SELECT t.*
			FROM folders t
			JOIN tree_path c ON (t.id = c.ancestor)
			WHERE
				c.descendant = :node_id
		";
        if (!$options->self) {
            $query .= " AND c.ancestor != :node_id_self ";
        }
        if ($options->depth) {
            $query .= " AND c.depth = :depth ";
        }
        $query .= " ORDER BY t.id ";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(":node_id", $node_id, PDO::PARAM_INT);

        if (!$options->self) {
            $stmt->bindValue(":node_id_self", $node_id, PDO::PARAM_INT);
        }
        if ($options->depth) {
            $stmt->bindValue(":depth", $options->depth, PDO::PARAM_INT);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 0) {
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

        if (!$options->self) {
            $query .= " AND c1.descendant != :node_id_self ";
        }
        if ($options->depth) {
            $query .= " AND c1.depth = :depth ";
        }
        if ($options->orderby) {
            $query .= " ORDER BY t." . $options->orderby . " ";
        }
        
        $stmt = $this->pdo->prepare($query);
        if (!$options->self) {
            $stmt->bindValue(":node_id_self", $node_id, PDO::PARAM_INT);
        }
        if ($options->depth) {
            $stmt->bindValue(":depth", $options->depth, PDO::PARAM_INT);
        }
        $stmt->bindValue(":node_id", $node_id, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
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
                if (!$options->depth) {
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

    /**
     * Delete server files ($row['saved_as'] and $row['thumbnail'])
     *
     * @param array  $array of associated entries array
     * @return void
     */
    private function _delete_server_files(array $files_rows = [])
    {
        foreach ($files_rows as $row) {
            $file_pointer = CONFIG['base_path_store'] . $row['saved_as'];
            $thumb_pointer = CONFIG['base_path_thumb'] . $row['thumbnail'];
            if (file_exists($file_pointer)) {
                unlink($file_pointer);
            }
            if (!empty($row['thumbnail']) && file_exists($thumb_pointer)) {
                unlink($thumb_pointer);
            }
        }
    }

    /**
     *
     * Filter array to make sure are integer values,
     * remove empty values and convert strings to int
     *
     * @param array   $array array of ids array("1", "", 2, " 3 ", 4, 5)
     *
     * @return array  $array of int ids array(1, 2, 3, 4, 5)
     */
    private function _filter_ids_array(array $array = [])
    {
        $array = array_filter($array, 'strlen');
        $array = array_map(function ($value) {
            return intval($value);
        }, $array);

        return $array;
    }

    /**
     * Generated a nested array from flat array with id && parent_id props
     *
     * @param array   $flatArray
     * @param int     $root_id, from wich node should the nested array be returned. by default is 0,i.e., the ROOT
     * @return array  nested array
     */
    private function _generate_nested_array(array $flatArray, int $root_id = 0)
    {
    
        // initiate result array
        $tree = array();
        // iterate $flatArray
        foreach ($flatArray as $item) {
            // for convenience, initiate these vars
            $id = $item[ 'id' ];
            $parentId = $item[ 'parent_id' ];
    
            // initiate this item's children array;
            $item[ 'children' ] = [];
            // if parent doesn't exist yet, initiate it along with an empty 'children' array
            if (!isset($tree[ $parentId ])) {
                $tree[ $parentId ] = [
                    'children' => []
                ];
            }
            // if this item is initiated already (as being a parent) merge it with the current item
            $tree[ $id ] = isset($tree[ $id ]) ? $tree[ $id ] + $item : $item;
            // add this item to the parents children collection by reference (for efficiency)
            // $tree[ $parentId ][ 'children' ][ $id ] = &$tree[ $id ];
    
            // nah. without fixed index
            $tree[ $parentId ][ 'children' ][] = &$tree[ $id ];
        }

        return $tree[$root_id];
    }
}
