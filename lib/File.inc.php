<?php
Class File{
	protected $pdo;
	private $valid = true;
	private $_errors = [];

	public $id = null;
	public $folder_id = null;
	public $name = null;
	public $normalized = null;
	public $extension = null;
	public $size = null;
	public $type = 'file';
	public $thumbnail = null;
	public $saved_as = null;
	public $lastmodified_on = null;
	public $created_on = null;

	public $filenameBadChars = [
		'../',
		'<!--',
		'-->',
		'<',
		'>',
		"'",
		'"',
		'&',
		'$',
		'#',
		'{',
		'}',
		'[',
		']',
		'=',
		';',
		'?',
		'%20',
		'%22',
		'%3c', // <
		'%253c', // <
		'%3e', // >
		'%0e', // >
		'%28', // (
		'%29', // )
		'%2528', // (
		'%26', // &
		'%24', // $
		'%3f', // ?
		'%3b', // ;
		'%3d',       // =
	];


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

		return $this->id;
	}

	public function setId(int $id) {
		$this->id = $id;

		return $this;
	}

	public function setName(string $new_name) {
		if ( !$this->valid ) {

			return $this;
		}
		$name = $this->_get_file_name($new_name);
		$this->name = $name . '.' . $this->extension;
		$this->normalized = strtoupper( Urlify::normalize_name($this->name, false) );
		$this->set();

		return $this;
	}
	
	public function move(int $target_folder) {
		if ( !$this->valid ) {

			return $this;
		}
		try {
			$this->pdo->beginTransaction();
			$stmt = $this->pdo->prepare("UPDATE files SET folder_id=:folder_id WHERE id=:id");
			$stmt->bindValue(":id", $this->id, PDO::PARAM_INT);
			$stmt->bindValue(":folder_id", $target_folder, PDO::PARAM_INT);
			$stmt->execute();
			$this->pdo->commit();
		} catch (PDOException $e) {
			$this->pdo->rollback();
			$this->_errors[] = $e->getMessage();
			$this->valid = false;
		}
		return $this;
	}


	public function add() {

		try {
			$this->pdo->beginTransaction();

			$this->normalized = strtoupper( Urlify::normalize_name($this->name, false) );

			$stmt = $this->pdo->prepare("INSERT INTO files 
								   (folder_id, name, normalized, extension, size, type, thumbnail, saved_as, lastmodified_on, created_on) VALUES 
								   (:folder_id, :name, :normalized, :extension, :size, :type, :thumbnail, :saved_as, :lastmodified_on, :created_on)");
			$stmt->bindValue(":folder_id", $this->folder_id, PDO::PARAM_INT);
			$stmt->bindValue(":normalized", $this->normalized, PDO::PARAM_STR);
			$stmt->bindValue(":name", $this->name, PDO::PARAM_STR);
			$stmt->bindValue(":extension", $this->extension, PDO::PARAM_STR);
			$stmt->bindValue(":size", $this->size, PDO::PARAM_INT);
			$stmt->bindValue(":type", $this->type, PDO::PARAM_STR);
			$stmt->bindValue(":thumbnail", $this->thumbnail, PDO::PARAM_STR);
			$stmt->bindValue(":saved_as", $this->saved_as, PDO::PARAM_STR);
			$stmt->bindValue(":lastmodified_on", $this->lastmodified_on, PDO::PARAM_INT);
			$stmt->bindValue(":created_on", $this->created_on, PDO::PARAM_INT);
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

	public function set($file) {

		try {
			$this->pdo->beginTransaction();

			$stmt = $this->pdo->prepare("UPDATE files SET 
												folder_id = :folder_id,
												name = :name,
												extension = :extension,
												size = :size,
												type = :type,
												thumbnail = :thumbnail,
												folder_id = :folder_id,
												saved_as = :saved_as,

												lastmodified_on = :lastmodified_on
									WHERE id = :id ");
			$stmt->bindValue(":id", $this->id, PDO::PARAM_INT);
			$stmt->bindValue(":folder_id", $this->folder_id, PDO::PARAM_INT);
			$stmt->bindValue(":name", $this->name, PDO::PARAM_STR);
			$stmt->bindValue(":extension", $this->extension, PDO::PARAM_STR);
			$stmt->bindValue(":size", $this->size, PDO::PARAM_INT);
			$stmt->bindValue(":type", $this->type, PDO::PARAM_STR);
			$stmt->bindValue(":thumbnail", $this->thumbnail, PDO::PARAM_STR);
			$stmt->bindValue(":saved_as", $this->saved_as, PDO::PARAM_STR);			
			$stmt->bindValue(":lastmodified_on", $this->lastmodified_on, PDO::PARAM_INT);
			// $stmt->bindValue(":created_on", $this->created_on, PDO::PARAM_INT);
			$stmt->execute();
			$count = $stmt->rowCount();

			$this->pdo->commit();
		} catch (PDOException $e) {
			$this->pdo->rollback();
			$this->_errors[] = $e->getMessage();
			$this->valid = false;
		}
		if ( $count < 1 ) {
			$this->valid = false;
		}

		return $this;
	}

	public function delete(int $id = null) {
		$id = ( !$id ) ? $this->id : $id;
		if ( !$this->valid ) {

			return $this;
		}

		$files_to_delete = $this->getBatch( [$id] );
		try {
			$this->pdo->beginTransaction();

			$stmt = $this->pdo->prepare("DELETE FROM files WHERE id=:id");
			$stmt->bindValue(":id", $id, PDO::PARAM_INT);
			$stmt->execute();
			$count = $stmt->rowCount();

			$this->_delete_server_files($files_to_delete);

			$this->pdo->commit();
		} catch (PDOException $e) {
			$this->pdo->rollback();
			$this->_errors[] = $e->getMessage();
			$this->valid = false;
		}
		if ( $count < 1 ) {
			$this->_errors[] = 'no record deleted';
			$this->valid = false;
		}

		return $this;
	}

	public function deleteBatch(array $ids_array) {
		$files_to_delete = [];

		try {
			$this->pdo->beginTransaction();

			$ids_array = $this->_filter_ids_array($ids_array);
			if ( count($ids_array) > 0 ) {
				$where_in  = str_repeat('?,', count($ids_array)-1) . '?';

				$query = "SELECT * FROM files WHERE id IN (". $where_in .")";
				$stmt = $this->pdo->prepare($query);
				$stmt->execute($ids_array);
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
				$files_to_delete = $rows;

				$this->_delete_server_files($files_to_delete);

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

	public function get(int $id = null) {
		$id = ( !$id ) ? $this->id : $id;
		if ( $id ) {
			$id = intval($id);
		} else {
			$this->valid = false;

			return $this;
		}
		$stmt = $this->pdo->prepare("SELECT *, 'file' AS type FROM files WHERE id=:id LIMIT 1");
		$stmt->bindValue(":id", $id, PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if ( $row ) {
			$this->id = (int) $row['id'];
			$this->folder_id = (int) $row['folder_id'];
			$this->name = $row['name'];
			$this->normalized = $row['normalized'];
			$this->extension = $row['extension'];
			$this->size = (int) $row['size'];
			$this->type = $row['type'];
			$this->thumbnail = $row['thumbnail'];
			$this->lastmodified_on = $row['lastmodified_on'];
			$this->created_on = $row['created_on'];
		} else {
			$this->_errors[] = 'no record found';
			$this->valid = false;
		}

		return $this;
	}

	public function getBatch(array $ids_array) {
		$rows = [];

		if ( is_array($ids_array) ) {
			$in_array = filter_ids_array($ids_array);
			
			if ( count($in_array) > 0 ) {
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
		
		return $rows;
	}

	public function all(array $params = []) {

		$default_options = [
			'orderby' => "normalized",
		];
		$options = array_merge($default_options, $params);
		
		$query = " SELECT * FROM files ";
		$query .= ($options['orderby']) ? " ORDER BY ". $options['orderby'] : "";
		$stmt = $this->pdo->prepare($query);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		if ( $rows ) {

			return $rows;
		}

		return [];
	}

	public function sanitizeFilename($str, $relative_path = false)
	{
		$bad = $this->filenameBadChars;

		if (! $relative_path)
		{
			$bad[] = './';
			$bad[] = '/';
		}

		$str = $this->_remove_invisible_characters($str, false);

		do
		{
			$old = $str;
			$str = str_replace($bad, '', $str);
		}
		while ($old !== $str);

		return stripslashes($str);
	}
	
	private function _remove_invisible_characters($str, $url_encoded = true)
	{
		$non_displayables = [];

		// every control character except newline (dec 10),
		// carriage return (dec 13) and horizontal tab (dec 09)
		if ($url_encoded)
		{
			$non_displayables[] = '/%0[0-8bcef]/';  // url encoded 00-08, 11, 12, 14, 15
			$non_displayables[] = '/%1[0-9a-f]/';   // url encoded 16-31
		}

		$non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';   // 00-08, 11, 12, 14-31, 127

		do
		{
			$str = preg_replace($non_displayables, '', $str, -1, $count);
		}
		while ($count);

		return $str;
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

	private function _get_file_extenstion(string $file) {
		$info = pathinfo($file);

		return $info['extension'];
	}

	private function _get_file_name(string $file) {
		$info = pathinfo($file);

		return basename($file, '.' . $info['extension']);
	}

}