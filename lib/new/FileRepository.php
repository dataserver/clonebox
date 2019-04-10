<?php
class FileRepository implements IFileRepository
{
    private $pdo;
    private $_errors = [];
    private $valid = true;

    public function __construct()
    {
        $this->pdo = MyPDO::instance();
    }

    /**
     * Return if method chaining was sucessful
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
     * Return errors generated during method chaining
     *
     * @return array  of errors
     */
    public function errors()
    {
        return $this->_errors;
    }

    /**
     * Insert into database current instances values
     *
     * @return self
     */
    public function add(File $file)
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("INSERT INTO files 
								   (folder_id, name, normalized, extension, size, format, thumbnail, saved_as, lastmodified_on, created_on) VALUES 
								   (:folder_id, :name, :normalized, :extension, :size, :format, :thumbnail, :saved_as, :lastmodified_on, :created_on)");
            $stmt->bindValue(":folder_id", $file->folder_id, PDO::PARAM_INT);
            $stmt->bindValue(":normalized", $file->normalized, PDO::PARAM_STR);
            $stmt->bindValue(":name", $file->name, PDO::PARAM_STR);
            $stmt->bindValue(":extension", $file->extension, PDO::PARAM_STR);
            $stmt->bindValue(":size", $file->size, PDO::PARAM_INT);
            $stmt->bindValue(":format", $thfileis->format, PDO::PARAM_STR);
            $stmt->bindValue(":thumbnail", $file->thumbnail, PDO::PARAM_STR);
            $stmt->bindValue(":saved_as", $file->saved_as, PDO::PARAM_STR);
            $stmt->bindValue(":lastmodified_on", $file->lastmodified_on, PDO::PARAM_INT);
            $stmt->bindValue(":created_on", $file->created_on, PDO::PARAM_INT);
            $stmt->execute();

            $this->id = $this->pdo->lastInsertId();

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollback();
            $this->_errors[] = $e->getMessage();
            $this->valid = false;
        }
        return $this;
    }



    /**
     * Update database current values of instance->id
     *
     * @return self
     */
    public function update(File $file)
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("UPDATE files SET 
												folder_id = :folder_id,
												name = :name,
												extension = :extension,
												size = :size,
												format = :format,
												thumbnail = :thumbnail,
												folder_id = :folder_id,
												saved_as = :saved_as,

												lastmodified_on = :lastmodified_on
									WHERE id = :id ");
            $stmt->bindValue(":id", $file->id, PDO::PARAM_INT);
            $stmt->bindValue(":folder_id", $file->folder_id, PDO::PARAM_INT);
            $stmt->bindValue(":name", $file->name, PDO::PARAM_STR);
            $stmt->bindValue(":extension", $file->extension, PDO::PARAM_STR);
            $stmt->bindValue(":size", $file->size, PDO::PARAM_INT);
            $stmt->bindValue(":format", $file->format, PDO::PARAM_STR);
            $stmt->bindValue(":thumbnail", $file->thumbnail, PDO::PARAM_STR);
            $stmt->bindValue(":saved_as", $file->saved_as, PDO::PARAM_STR);
            $stmt->bindValue(":lastmodified_on", time(), PDO::PARAM_INT);
            // $stmt->bindValue(":created_on", $file->created_on, PDO::PARAM_INT);
            $stmt->execute();
            $count = $stmt->rowCount();

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollback();
            $this->_errors[] = $e->getMessage();
            $this->valid = false;
        }
        if ($count < 1) {
            $this->valid = false;
        }

        return $this;
    }


    /**
     * Delete file with id
     *
     * @param int id  file id
     * @return self
     */
    public function remove(File $file)
    {
        if (!$this->valid) {
            return $this;
        }
       
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("DELETE FROM files WHERE id=:id");
            $stmt->bindValue(":id", $file->id, PDO::PARAM_INT);
            $stmt->execute();
            $count = $stmt->rowCount();

            $files_to_delete = $this->findByFolderId([$file->id]);
            $this->_removeServerFiles($files_to_delete);

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollback();
            $this->_errors[] = $e->getMessage();
            $this->valid = false;
        }
        if ($count < 1) {
            $this->_errors[] = 'no record deleted';
            $this->valid = false;
        }

        return $this;
    }

    /**
     * Delete files in array of ids
     *
     * @param array array of ids
     * @return self
     */
    public function removeBatch(array $ids_array)
    {
        $files_to_delete = [];
        $ids_array = $this->_filterArrayIds($ids_array);
        
        try {
            $this->pdo->beginTransaction();
            if (count($ids_array) > 0) {
                $where_in  = str_repeat('?,', count($ids_array)-1) . '?';

                $query = "SELECT * FROM files WHERE id IN (". $where_in .")";
                $stmt = $this->pdo->prepare($query);
                $stmt->execute($ids_array);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $files_to_delete = $rows;

                $this->_removeServerFiles($files_to_delete);

                $query = "DELETE FROM files WHERE id IN (". $where_in .")";
                $stmt = $this->pdo->prepare($query);
                $stmt->execute($ids_array);
                $count = $stmt->rowCount();
            } else {
                $this->_errors[] = 'no array';
                $this->valid = false;
            }
            
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollback();
            $this->_errors[] = $e->getMessage();
            $this->valid = false;
        }

        return $this;
    }


    /**
     * Get file info with $id or current instance->id
     *
     * @param int   $id file id
     * @return self
     */
    public function find(int $id = null)
    {
        if ($id) {
            $id = intval($id);
        } else {
            $this->valid = false;

            return $this;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM files WHERE id=:id LIMIT 1");
        $stmt->bindValue(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            $this->_errors[] = 'no record found';
            $this->valid = false;
        }

        return $this->createInstance($data);
    }


    /**
     * Get files in array of ids
     *
     * @param array  array of ids
     * @return array array of associated values
     */
    public function findByIds(array $ids_array): array
    {
        $rows = [];

        if (is_array($ids_array)) {
            $in_array = filter_ids_array($ids_array);
            
            if (count($in_array) > 0) {
                $where_in  = str_repeat('?,', count($in_array)-1) . '?';
                $query = "SELECT *, 'file' AS type FROM files WHERE id IN (". $where_in .")";
                $stmt = $this->pdo->prepare($query);
                $stmt->execute($in_array);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $this->_errors[] = 'not array';
            $this->valid = false;
        }

        if ($rows) {
            return $rows;
        }

        return $rows;
    }

    /**
     * Get files by name
     *
     * @param array  array of ids
     * @return array array of associated values
     */
    public function findByName(string $str): array
    {
        $rows = [];


        return $rows;
    }

    /**
     * Get all files from database
     *
     * @param array $params [
     * 	'orderby' = order of sorting, default normalized field
     * ]
     * @return array  associated values
     */
    public function findAll(array $params = []): array
    {
        $results = // Query by prepared statement
        $files = [];
        $default_options = [
            'orderby' => "normalized",
        ];
        $options = array_merge($default_options, $params);
        
        $query = " SELECT * FROM files ";
        $query .= ($options['orderby']) ? " ORDER BY ". $options['orderby'] : "";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // $files = $results;
        foreach ($results as $data) {
            $files[] = $this->createInstance($data);
        }

        return $files;
    }












    private function toArray($obj){
        $reflector = new ReflectionObject($obj);
        $nodes = $reflector->getProperties();
        $out = [];
        foreach ($nodes as  $node) {
            $nod = $reflector->getProperty($node->getName());
            $nod->setAccessible(true);
            $out[$node->getName()] = $nod->getValue($obj);
        }

        return $out;
    }






    private function createInstance($data)
    {
        return $this->map($data, new File(
            $data['name'],
            $data['extension'],
            $data['size'],
            $data['format'],
            $data['saved_as'],
            $data['thumbnail'],
            $data['created_on'],
            $data['lastmodified_on'],
            $data['folder_id'],
            $data['id']
        ));
    }
    private function map($data, File $file)
    {
        foreach ($data as $attribute => $value) {
            $method = 'set' . $this->_underscoreToCamelCase($attribute, true);
            if (method_exists($file, $method)) {
                call_user_func_array(array($file, $method), array($value));
            } else {
                throw new Exception("No setter method exists for '$attribute'");
            }
        }

        return $file;
    }


    /**
     * Delete files in the webserver
     *
     * @param array  array of associated values
     * @return void
     */
    private function _removeServerFiles(array $files_rows = [])
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
     * Convert snake_case to Camel case, every character after underscore is capitalized
     * e.g:  saved_as to saveAs, created_on -> createdOn
     * 
     * @param string   $string
     * @param bool     $capitalizeFirstCharacter
     * @return string  converted string
     */
    private function _underscoreToCamelCase($string, $capitalizeFirstCharacter = false)
    {
        $str = str_replace('_', '', ucwords($string, '_'));
        if (!$capitalizeFirstCharacter) {
            $str = lcfirst($str);
        }

        return $str;
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
    private function _filterArrayIds(array $array = [])
    {
        $array = array_filter($array, 'strlen');
        $array = array_map(function ($value) {
            return intval($value);
        }, $array);

        return $array;
    }



}
